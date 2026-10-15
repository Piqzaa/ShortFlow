<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Service\PublicationException;
use App\Service\TokenVault;
use App\Service\YouTubeApi;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class YouTubeApiTest extends TestCase
{
    private function vault(): TokenVault
    {
        return new TokenVault(base64_encode(str_repeat('k', 32)));
    }

    private function api(MockHttpClient $http): YouTubeApi
    {
        return new YouTubeApi($http, $this->vault(), $this->createMock(EntityManagerInterface::class), 'test-id', 'test-secret', 'http://localhost:8000/studio/connexions/youtube/retour');
    }

    public function testVaultRandomizedAuthenticatedEncryptionAndWrongKey(): void
    {
        $vault = $this->vault();
        $a = $vault->encrypt('private-token');
        $b = $vault->encrypt('private-token');
        self::assertNotSame($a, $b);
        self::assertStringNotContainsString('private-token', $a);
        self::assertSame('private-token', $vault->decrypt($a));
        $this->expectException(PublicationException::class);
        (new TokenVault(base64_encode(str_repeat('z', 32))))->decrypt($a);
    }

    public function testOAuthUsesStatePkceOfflineAndMinimalChannelScopes(): void
    {
        $api = $this->api(new MockHttpClient());
        parse_str((string) parse_url($api->authorizationUrl('state', 'verifier'), PHP_URL_QUERY), $query);
        self::assertSame('state', $query['state']);
        self::assertSame('S256', $query['code_challenge_method']);
        self::assertSame('offline', $query['access_type']);
        self::assertSame(YouTubeApi::SCOPES, $query['scope']);
        self::assertSame(rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '='), $query['code_challenge']);
        $this->expectException(PublicationException::class);
        $this->api(new MockHttpClient(new MockResponse(json_encode(['access_token' => 'a', 'refresh_token' => 'b', 'scope' => 'https://www.googleapis.com/auth/youtube.upload']))))->exchange('code', 'verifier');
    }

    public function testResumableProbeReconcilesOffsetsAndCompletionWithoutNewUpload(): void
    {
        $responses = [new MockResponse('', ['http_code' => 308, 'response_headers' => ['Range: bytes=0-255']]), new MockResponse('{"id":"abcdefghijk"}', ['http_code' => 200]), new MockResponse('', ['http_code' => 404])];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$responses): MockResponse {
            self::assertSame('PUT', $method);
            self::assertStringStartsWith('https://www.googleapis.com/upload/youtube/v3/videos', $url);
            self::assertContains('Content-Range: bytes */1000', $options['headers']);

            return array_shift($responses);
        });
        $api = $this->api($http);
        $session = 'https://www.googleapis.com/upload/youtube/v3/videos?upload_id=test';
        self::assertSame(['offset' => 256, 'id' => null, 'expired' => false], $api->probe($session, 'access', 1000));
        self::assertSame(['offset' => 1000, 'id' => 'abcdefghijk', 'expired' => false], $api->probe($session, 'access', 1000));
        self::assertTrue($api->probe($session, 'access', 1000)['expired']);
    }

    public function testProviderCannotRedirectUploadToAnotherHost(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 200, 'response_headers' => ['Location: https://attacker.test/steal']]));
        $owner = new User('unit@example.test');
        $source = new VideoAsset($owner, 'Clip', str_repeat('a', 48).'.mp4', 'video/mp4', 1000, 1, 96, 160, 'h264');
        $vault = $this->vault();
        $connection = new YouTubeConnection($owner, 'UC'.str_repeat('a', 22), 'Chaîne', $vault->encrypt('a'), $vault->encrypt('r'), 3600);
        $job = new YouTubePublication($owner, $source, $connection, str_repeat('a', 64), 'Clip', '', 'private', false, false);
        $this->expectException(PublicationException::class);
        $this->api($http)->start($job, 'access');
    }

    public function testNetworkErrorNeverExposesCredentialOrSession(): void
    {
        $api = $this->api(new MockHttpClient(function (): never { throw new \RuntimeException('SECRET-access-upload-url'); }));
        try {
            $api->probe('https://www.googleapis.com/upload/youtube/v3/videos?upload_id=SECRET', 'SECRET', 1000);
            self::fail('Expected safe exception');
        } catch (PublicationException $e) {
            self::assertStringNotContainsString('SECRET', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }

    public function testDescriptionValidationUsesUtf8BytesAndRefusesChevrons(): void
    {
        $factory = \Symfony\Component\Form\Forms::createFormFactoryBuilder()->addExtension(new \Symfony\Component\Form\Extension\Validator\ValidatorExtension(\Symfony\Component\Validator\Validation::createValidator()))->getFormFactory();
        foreach ([[str_repeat('é', 2500), true], [str_repeat('é', 2501), false], ['<tag>', false], ['', true]] as [$description,$expected]) {
            $form = $factory->create(\App\Form\YouTubeVideoType::class);
            $form->submit(['title' => 'Un titre', 'description' => $description, 'privacy' => 'private', 'audience' => 'no', 'synthetic' => false]);
            self::assertSame($expected, $form->isValid());
        }
    }
}
