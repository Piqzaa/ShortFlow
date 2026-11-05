<?php

namespace App\Tests\Unit;

use App\Service\AiPortraitApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AiPortraitApiTest extends TestCase
{
    public function testDisabledClientCannotSubmitPollOrDownload(): void
    {
        $http = new MockHttpClient(fn () => throw new \LogicException('HTTP forbidden.'));
        $api = new AiPortraitApi($http, false);
        foreach (['submit', 'download'] as $action) {
            try {
                'submit' === $action ? $api->submit(['input' => []], 'synthetic') : $api->download('https://fal.media/image.jpg');
                self::fail('Disabled API must reject calls.');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('désactivée', $error->getMessage());
            }
        }
        self::assertSame(0, $http->getRequestsCount());
    }

    public function testUntrustedMediaAndReceiptsCannotReachNetwork(): void
    {
        $http = new MockHttpClient(fn () => throw new \LogicException('HTTP forbidden.'));
        $api = new AiPortraitApi($http, true);
        foreach (['http://fal.media/a', 'https://fal.media.evil.test/a', 'https://a.b.fal.media/a', 'https://fal.media:443/a', 'https://key@fal.media/a', 'https://storage.googleapis.com/private/a', 'https://127.0.0.1/a'] as $url) {
            try {
                $api->download($url);
                self::fail('Untrusted media must be rejected.');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('refusé', $error->getMessage());
            }
        }
        try {
            $api->poll(['request_id' => 'id', 'status_url' => 'https://evil.test/status', 'response_url' => 'https://queue.fal.run/fal-ai/flux-2-pro/requests/id'], 'synthetic');
            self::fail('Untrusted receipt must be rejected.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('invalide', $error->getMessage());
        }
        self::assertSame(0, $http->getRequestsCount());
    }

    public function testBoundedJsonAndOversizedDownloadAreRejectedWithoutTemporaryLeak(): void
    {
        $api = new AiPortraitApi(new MockHttpClient(new MockResponse(str_repeat('x', 65537))), true);
        try {
            $api->submit(['input' => []], 'synthetic');
            self::fail('Oversized JSON must be rejected.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('volumineuse', $error->getMessage());
        }
        $api = new AiPortraitApi(new MockHttpClient(new MockResponse((function (): \Generator {
            for ($i = 0; $i < 11; ++$i) {
                yield str_repeat('x', 1048576);
            }
        })())), true);
        $before = glob(sys_get_temp_dir().'/portrait-*') ?: [];
        try {
            $api->download('https://fal.media/image.jpg');
            self::fail('Oversized media must be rejected.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('volumineux', $error->getMessage());
        }
        self::assertSame($before, glob(sys_get_temp_dir().'/portrait-*') ?: []);
    }

    public function testRedirectIsNotFollowedAndDoesNotLeakTemporaryFile(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 302, 'response_headers' => ['location: https://evil.test/private']]));
        $api = new AiPortraitApi($http, true);
        $before = glob(sys_get_temp_dir().'/portrait-*') ?: [];
        try {
            $api->download('https://fal.media/image.jpg');
            self::fail('Redirect must be rejected.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('indisponible', $error->getMessage());
        }
        self::assertSame(1, $http->getRequestsCount());
        self::assertSame($before, glob(sys_get_temp_dir().'/portrait-*') ?: []);
    }
}
