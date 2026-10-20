<?php

namespace App\Tests\Unit;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Service\AiCatalog;
use App\Service\AiReferenceStorage;
use App\Service\FalVideoApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FalVideoApiTest extends TestCase
{
    public static function models(): iterable
    {
        foreach (AiCatalog::MODELS as $id => $model) {
            if ('fal' !== $model['provider'] || ($model['image'] ?? false)) {
                continue;
            }
            foreach (array_unique([$model['seconds'][0], $model['seconds'][array_key_last($model['seconds'])]]) as $seconds) {
                yield $id.'-'.$seconds => [$id, $seconds];
            }
        }
    }

    #[DataProvider('models')]
    public function testNativeInputsMatchPublishedProviderContracts(string $id, int $seconds): void
    {
        $model = AiCatalog::MODELS[$id];
        $contracts = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/fal-video-contracts.json'), true, 64, JSON_THROW_ON_ERROR);
        $contract = $contracts[$model['endpoint']];
        $owner = new User('synthetic@example.test');
        (new \ReflectionProperty(User::class, 'id'))->setValue($owner, 42);
        $job = new AiGeneration($owner, 'Test', 'A forest at dawn', $id, $seconds, $model['ratios'][0], (new AiCatalog())->estimate($id, $seconds));
        $application = implode('/', array_slice(explode('/', $model['endpoint']), 0, 2));
        $base = 'https://queue.fal.run/'.$application.'/requests/synthetic';
        $calls = 0;
        $api = new FalVideoApi(new MockHttpClient(function (string $method, string $url, array $options) use ($model, $contract, $base, &$calls): MockResponse {
            ++$calls;
            self::assertSame('POST', $method);
            self::assertSame('https://queue.fal.run/'.$model['endpoint'], $url);
            self::assertSame(0, $options['max_redirects']);
            $input = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
            foreach ($contract['required'] ?? [] as $required) {
                self::assertArrayHasKey($required, $input);
            }
            foreach ($input as $field => $value) {
                self::assertArrayHasKey($field, $contract['properties'], 'Undocumented field '.$field.' in '.$model['endpoint']);
                $rules = $contract['properties'][$field];
                if (isset($rules['type'])) {
                    self::assertSame(match ($rules['type']) {
                        'integer' => 'int',
                        'boolean' => 'bool',
                        default => $rules['type'],
                    }, get_debug_type($value), $field);
                }
                if (isset($rules['enum'])) {
                    self::assertContains($value, $rules['enum'], $field);
                }
                if (isset($rules['minimum'])) {
                    self::assertGreaterThanOrEqual($rules['minimum'], $value);
                }
                if (isset($rules['maximum'])) {
                    self::assertLessThanOrEqual($rules['maximum'], $value);
                }
            }
            if ('bytedance/seedance-2.5/text-to-video' === $model['endpoint']) {
                self::assertSame('H264', $input['codec']);
                self::assertFalse($input['draft']);
                self::assertTrue($input['generate_audio']);
                self::assertSame(hash('sha256', 'vydflow-user:42'), $input['end_user_id']);
            }

            return new MockResponse(json_encode(['status_url' => $base.'/status', 'response_url' => $base], JSON_THROW_ON_ERROR));
        }), new AiCatalog(), new AiReferenceStorage(sys_get_temp_dir().'/vydflow-ai-unit'));
        self::assertSame(['status_url' => $base.'/status', 'response_url' => $base], $api->submit($job, 'synthetic-key'));
        self::assertSame(1, $calls);
    }

    public static function badQueueUrls(): iterable
    {
        foreach (['https://queue.fal.run/fal-ai/pika/requests/other/status', 'https://queue.fal.run.evil.test/fal-ai/kling-video/requests/x/status', 'https://queue.fal.run/fal-ai/kling-video/../pika/requests/x/status', 'https://queue.fal.run/fal-ai/kling-video/requests/x/status?redirect=other', 'http://127.0.0.1/private'] as $url) {
            yield [$url];
        }
    }

    #[DataProvider('badQueueUrls')]
    public function testPollingRejectsAnotherApplicationOrUnsafeUrlBeforeSendingTheKey(string $url): void
    {
        $job = new AiGeneration(new User('synthetic@example.test'), 'Test', 'A forest', 'kling-25', 5, '9:16', 35);
        $job->progress('processing', remote: ['status_url' => $url, 'response_url' => $url]);
        $http = new MockHttpClient(static function (): never { throw new \LogicException('No authenticated request should be made.'); });
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Adresse fournisseur invalide.');
        (new FalVideoApi($http, new AiCatalog(), new AiReferenceStorage(sys_get_temp_dir().'/vydflow-ai-unit')))->poll($job, 'synthetic-key');
    }

    public function testTieredPricingNeverExtrapolatesTheFiveSecondRate(): void
    {
        $catalog = new AiCatalog();
        self::assertSame(45, $catalog->estimate('pixverse-56', 5));
        self::assertSame(90, $catalog->estimate('pixverse-56', 8));
        self::assertSame(99, $catalog->estimate('pixverse-56', 10));
        self::assertSame(1419, $catalog->estimate('seedance-25', 30));
        $this->expectException(\InvalidArgumentException::class);
        $catalog->validate('hailuo-02', 6, '9:16');
    }
}
