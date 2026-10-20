<?php

namespace App\Tests\Unit;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Service\AiCatalog;
use App\Service\AiReferenceStorage;
use App\Service\LtxVideoApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LtxVideoApiTest extends TestCase
{
    public static function resolutions(): iterable
    {
        yield ['ltx-25-fast-720', '9:16', '720x1280', 54];
        yield ['ltx-25-fast-1080', '16:9', '1920x1080', 78];
        yield ['ltx-25-fast-4k', '9:16', '2160x3840', 180];
        yield ['ltx-23-fast-4k', '16:9', '3840x2160', 144];
    }

    #[DataProvider('resolutions')]
    public function testResolutionAndPriceStayConsistentThroughAsyncGeneration(string $id, string $ratio, string $resolution, int $cents): void
    {
        $catalog = new AiCatalog();
        self::assertSame($cents, $catalog->estimate($id, 6));
        $job = new AiGeneration(new User('synthetic@example.test'), 'Test', 'A forest', $id, 6, $ratio, $cents);
        $calls = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($resolution, &$calls): MockResponse {
            ++$calls;
            self::assertContains('Authorization: Bearer synthetic-ltx-key', $options['headers']);
            self::assertSame(0, $options['max_redirects']);
            if ('POST' === $method) {
                self::assertSame('https://api.ltx.io/v2/text-to-video', $url);
                $input = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
                self::assertSame($resolution, $input['resolution']);
                self::assertSame(6, $input['duration']);
                self::assertSame(25, $input['fps']);
                self::assertTrue($input['generate_audio']);

                return new MockResponse('{"id":"synthetic-job"}', ['http_code' => 202]);
            }
            self::assertSame('https://api.ltx.io/v2/text-to-video/synthetic-job', $url);

            return new MockResponse('{"status":"completed","result":{"video_url":"https://storage.googleapis.com/ltx-test/result.mp4"}}');
        });
        $api = new LtxVideoApi($http, $catalog, new AiReferenceStorage(sys_get_temp_dir().'/vydflow-ai-unit'));
        $job->progress('processing', remote: $api->submit($job, 'synthetic-ltx-key'));
        self::assertSame(['ready' => true, 'url' => 'https://storage.googleapis.com/ltx-test/result.mp4'], $api->poll($job, 'synthetic-ltx-key'));
        self::assertSame(2, $calls);
    }
}
