<?php

namespace App\Tests\Unit;

use App\Entity\TikTokConnection;
use App\Entity\User;
use App\Service\PublicationException;
use App\Service\TikTokApi;
use App\Service\TikTokPublishing;
use App\Service\TokenVault;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TikTokApiTest extends TestCase
{
    private function api(MockHttpClient $http, bool $audited = false, string $redirect = 'https://vydflow.example.test/retour'): TikTokApi
    {
        return new TikTokApi($http, new TokenVault(base64_encode(str_repeat('t', 32))), $this->createMock(EntityManagerInterface::class), 'key', 'secret', $redirect, $audited);
    }

    private function connection(): TikTokConnection
    {
        $vault = new TokenVault(base64_encode(str_repeat('t', 32)));

        return new TikTokConnection(new User('qa@example.test'), 'creator', 'QA', $vault->encrypt('access'), $vault->encrypt('refresh'), 86400, 31536000);
    }

    public function testHttpsCallbackAndNoCredentialsInAuthorizationUrl(): void
    {
        $api = $this->api(new MockHttpClient());
        self::assertTrue($api->isReady());
        $url = $api->authorizationUrl('one-use-state');
        self::assertStringNotContainsString('secret', $url);
        self::assertStringContainsString('state=one-use-state', $url);
        foreach (['http://localhost/retour', 'https://user@example.test/retour', 'https://example.test/retour?x=1', 'https://example.test/retour#x'] as $redirect) {
            self::assertFalse($this->api(new MockHttpClient(), redirect: $redirect)->isReady());
        }
    }

    public function testMissingPublishScopeRejectedBeforeUserInfo(): void
    {
        $http = new MockHttpClient(new MockResponse('{"open_id":"creator","access_token":"access","refresh_token":"refresh","expires_in":86400,"refresh_expires_in":31536000,"scope":"user.info.basic","token_type":"Bearer"}'));
        try {
            $this->api($http)->connect('code');
            self::fail('Publishing scope required');
        } catch (PublicationException) {
            self::assertSame(1, $http->getRequestsCount());
        }
    }

    public function testUnauditedCreatorOnlyOffersPrivateAndRejectsPublicAccount(): void
    {
        $data = ['creator_nickname' => 'QA', 'privacy_level_options' => ['FOLLOWER_OF_CREATOR', 'SELF_ONLY'], 'max_video_post_duration_sec' => 60, 'comment_disabled' => false, 'duet_disabled' => true, 'stitch_disabled' => true];
        $http = new MockHttpClient(new MockResponse(json_encode(['data' => $data, 'error' => ['code' => 'ok']], JSON_THROW_ON_ERROR)));
        self::assertSame(['SELF_ONLY'], $this->api($http)->creator($this->connection())['privacy_level_options']);
        $data['privacy_level_options'] = ['PUBLIC_TO_EVERYONE', 'SELF_ONLY'];
        $this->expectException(PublicationException::class);
        $this->api(new MockHttpClient(new MockResponse(json_encode(['data' => $data], JSON_THROW_ON_ERROR))))->creator($this->connection());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidOptions(): iterable
    {
        yield 'no privacy' => [['privacy' => null]];
        yield 'unavailable privacy' => [['privacy' => 'PUBLIC_TO_EVERYONE']];
        yield 'emoji UTF16 length' => [['caption' => str_repeat('🙂', 1101)]];
        yield 'disabled comments' => [['comment' => true]];
        yield 'disclosure without brand' => [['disclosure' => true]];
        yield 'brand without disclosure' => [['ownBrand' => true]];
        yield 'private partnership' => [['disclosure' => true, 'partnerBrand' => true]];
    }

    #[DataProvider('invalidOptions')]
    public function testInvalidPrivacyInteractionCommercialAndCaptionSettingsRejected(array $override): void
    {
        $publishing = (new \ReflectionClass(TikTokPublishing::class))->newInstanceWithoutConstructor();
        $row = array_replace(['caption' => '', 'privacy' => 'SELF_ONLY', 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => false], $override);
        $this->expectException(PublicationException::class);
        $publishing->options($row, ['privacy_level_options' => ['SELF_ONLY'], 'comment_disabled' => true, 'duet_disabled' => true, 'stitch_disabled' => true]);
    }

    public function testProviderErrorNeverLeaksSecrets(): void
    {
        try {
            $this->api(new MockHttpClient(static function (): never { throw new \RuntimeException('private-secret'); }))->connect('code');
            self::fail('Network failure expected');
        } catch (PublicationException $error) {
            self::assertStringNotContainsString('private-secret', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
    }
}
