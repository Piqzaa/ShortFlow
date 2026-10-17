<?php

namespace App\Tests\Unit;

use App\Service\InstagramApi;
use App\Service\PublicationException;
use App\Service\TokenVault;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class InstagramApiTest extends TestCase
{
    private function api(MockHttpClient $http, string $secret = 'test-secret', string $redirect = 'http://localhost:8000/studio/connexions/instagram/retour'): InstagramApi
    {
        return new InstagramApi($http, new TokenVault(base64_encode(str_repeat('i', 32))), '1234567890', $secret, $redirect, 'v26.0');
    }

    public function testAuthorizationIsDirectInstagramWithOnlyRequiredScopes(): void
    {
        $api = $this->api(new MockHttpClient());
        self::assertTrue($api->isReady());
        $url = $api->authorizationUrl('state-test');
        self::assertStringStartsWith('https://www.instagram.com/oauth/authorize?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('state-test', $query['state']);
        self::assertSame('instagram_business_basic,instagram_business_content_publish,instagram_business_manage_insights', $query['scope']);
        self::assertSame('false', $query['enable_fb_login']);
        self::assertSame('true', $query['force_reauth']);
        self::assertArrayNotHasKey('client_secret', $query);
        self::assertFalse($this->api(new MockHttpClient(), '')->isReady());
        self::assertFalse($this->api(new MockHttpClient(), redirect: 'http://example.com/retour')->isReady());
        self::assertFalse($this->api(new MockHttpClient(), redirect: 'https://user@example.com/retour')->isReady());
    }

    /** @return iterable<string, array{bool}> */
    public static function payloadShapes(): iterable
    {
        yield 'documented data wrapper' => [true];
        yield 'flat response' => [false];
    }

    #[DataProvider('payloadShapes')]
    public function testExchangesCodeThenUsesLongLivedTokenToCheckProfessionalProfile(bool $wrapped): void
    {
        $short = ['access_token' => 'short-private', 'user_id' => '123456', 'permissions' => implode(',', InstagramApi::SCOPES)];
        $profile = ['id' => '123456', 'user_id' => '17841400000000001', 'username' => 'vydflow.test', 'account_type' => 'MEDIA_CREATOR'];
        $payloads = [$wrapped ? ['data' => [$short]] : $short, ['access_token' => 'long-private', 'expires_in' => 5183944], $wrapped ? ['data' => [$profile]] : $profile];
        $calls = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$payloads, &$calls): MockResponse {
            $calls[] = [$method, strtok($url, '?')];
            self::assertSame(0, $options['max_redirects']);

            return new MockResponse(json_encode(array_shift($payloads), JSON_THROW_ON_ERROR));
        });
        $result = $this->api($http)->connect('one-use-code');
        self::assertSame('17841400000000001', $result['id']);
        self::assertSame('vydflow.test', $result['username']);
        self::assertSame('long-private', $result['access']);
        self::assertSame(5183944, $result['expires']);
        self::assertSame([
            ['POST', 'https://api.instagram.com/oauth/access_token'],
            ['GET', 'https://graph.instagram.com/access_token'],
            ['GET', 'https://graph.instagram.com/v26.0/me'],
        ], $calls);
    }

    public function testPartialPermissionsAreRejectedBeforeFurtherRequests(): void
    {
        $http = new MockHttpClient(new MockResponse('{"access_token":"short","user_id":"123456","permissions":"instagram_business_basic"}'));
        try {
            $this->api($http)->connect('code');
            self::fail('Missing publication permission must reject connection');
        } catch (PublicationException) {
            self::assertSame(1, $http->getRequestsCount());
        }
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidProfiles(): iterable
    {
        yield 'personal' => [['account_type' => 'PERSONAL']];
        yield 'wrong token identity' => [['id' => '654321']];
        yield 'unsafe username' => [['username' => '<script>']];
        yield 'missing business id' => [['user_id' => null]];
    }

    #[DataProvider('invalidProfiles')]
    public function testRejectsNonProfessionalOrInconsistentProfile(array $override): void
    {
        $profile = array_replace(['id' => '123456', 'user_id' => '17841400000000001', 'username' => 'vydflow', 'account_type' => 'BUSINESS'], $override);
        $http = new MockHttpClient([
            new MockResponse(json_encode(['access_token' => 'short', 'user_id' => '123456', 'permissions' => implode(',', InstagramApi::SCOPES)], JSON_THROW_ON_ERROR)),
            new MockResponse('{"access_token":"long","expires_in":5183944}'),
            new MockResponse(json_encode($profile, JSON_THROW_ON_ERROR)),
        ]);
        $this->expectException(PublicationException::class);
        $this->api($http)->connect('code');
    }

    public function testNetworkFailureNeverExposesProviderExceptionOrSecret(): void
    {
        $api = $this->api(new MockHttpClient(static function (): never {
            throw new \RuntimeException('provider-secret=must-not-leak');
        }));
        try {
            $api->connect('code');
            self::fail('Network error must reject connection');
        } catch (PublicationException $error) {
            self::assertStringNotContainsString('must-not-leak', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
    }
}
