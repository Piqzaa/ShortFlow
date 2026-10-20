<?php

namespace App\Tests\Unit;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Service\AiCatalog;
use App\Service\AiReferenceStorage;
use App\Service\FalVideoApi;
use App\Service\LtxVideoApi;
use App\Service\ReplicateVideoApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AiGenerationProgressTest extends TestCase
{
    public static function states(): iterable
    {
        yield ['fal', 'IN_QUEUE', 'queued'];
        yield ['fal', 'IN_PROGRESS', 'running'];
        yield ['ltx', 'pending', 'queued'];
        yield ['ltx', 'processing', 'running'];
        yield ['replicate', 'starting', 'queued'];
        yield ['replicate', 'processing', 'running'];
    }

    #[DataProvider('states')]
    public function testOnlyNormalizedProviderStateLeavesTheAdapter(string $provider, string $status, string $expected): void
    {
        $catalog = new AiCatalog();
        $storage = new AiReferenceStorage(sys_get_temp_dir().'/unused-ai-progress');
        $http = new MockHttpClient(function (string $method) use ($status): MockResponse {
            self::assertSame('GET', $method);

            return new MockResponse(json_encode(['status' => $status, 'logs' => 'private', 'progress' => 42], JSON_THROW_ON_ERROR));
        });
        $id = ['fal' => 'kling-25', 'ltx' => 'ltx-25-fast-720', 'replicate' => 'wan-25-replicate'][$provider];
        $job = new AiGeneration(new User('fake@example.test'), 'Test', 'A forest', $id, 6, '9:16', 1);
        $job->progress('processing', remote: ['status_url' => 'https://queue.fal.run/fal-ai/kling-video/requests/fake/status', 'ltx_id' => 'fake', 'prediction_id' => 'fake']);
        $api = match ($provider) {
            'fal' => new FalVideoApi($http, $catalog, $storage),
            'ltx' => new LtxVideoApi($http, $catalog, $storage),
            'replicate' => new ReplicateVideoApi($http, $catalog, $storage),
        };
        self::assertSame(['ready' => false, 'stage' => $expected], $api->poll($job, 'fake-key'));
        $deadline = $job->getUpdatedAt();
        $job->observeProvider($expected);
        self::assertSame($deadline, $job->getUpdatedAt());
        self::assertNotNull($job->getProviderCheckedAt());
    }
}
