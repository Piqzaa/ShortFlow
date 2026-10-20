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

final class AiImageInputsTest extends TestCase
{
    private string $directory;
    private User $owner;
    private AiReferenceStorage $storage;
    private const FIRST = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.jpg';
    private const LAST = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.jpg';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/vydflow-frame-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory.'/42', 0750, true);
        foreach ([self::FIRST, self::LAST] as $name) {
            copy(dirname(__DIR__).'/Fixtures/reference.jpg', $this->directory.'/42/'.$name);
        }
        $this->owner = new User('synthetic@example.test');
        (new \ReflectionProperty(User::class, 'id'))->setValue($this->owner, 42);
        $this->storage = new AiReferenceStorage($this->directory);
    }

    protected function tearDown(): void
    {
        foreach ([self::FIRST, self::LAST] as $name) {
            unlink($this->directory.'/42/'.$name);
        }
        rmdir($this->directory.'/42');
        rmdir($this->directory);
    }

    public static function configurations(): iterable
    {
        foreach (AiCatalog::MODELS as $id => $model) {
            if (!($model['image'] ?? false)) {
                continue;
            }
            foreach (array_unique([$model['seconds'][0], $model['seconds'][array_key_last($model['seconds'])]]) as $seconds) {
                if (!($model['requiresEnd'] ?? false)) {
                    yield $id.'-'.$seconds.'-start' => [$id, $seconds, false];
                }
                if (isset($model['endImageField'])) {
                    yield $id.'-'.$seconds.'-end' => [$id, $seconds, true];
                }
            }
        }
    }

    #[DataProvider('configurations')]
    public function testEveryImageConfigurationMatchesItsProviderContract(string $id, int $seconds, bool $withEnd): void
    {
        $catalog = new AiCatalog();
        $model = $catalog->model($id);
        $ratio = $model['ratios'][0];
        $job = new AiGeneration($this->owner, 'Image', 'A slow camera move', $id, $seconds, $ratio, $catalog->estimate($id, $seconds));
        $job->attachReference(self::FIRST);
        if ($withEnd) {
            $job->attachEndReference(self::LAST);
        }
        $calls = 0;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($model, $seconds, $withEnd, &$calls): MockResponse {
            ++$calls;
            self::assertSame('POST', $method);
            self::assertSame(0, $options['max_redirects']);
            $body = json_decode($options['body'], true, 64, JSON_THROW_ON_ERROR);
            if ('fal' === $model['provider']) {
                self::assertSame('https://queue.fal.run/'.$model['endpoint'], $url);
                $schemas = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/fal-image-contracts.json'), true, 64, JSON_THROW_ON_ERROR);
                $schema = $schemas[$model['endpoint']];
                foreach ($schema['required'] as $required) {
                    self::assertArrayHasKey($required, $body);
                }
                foreach ($body as $key => $value) {
                    self::assertArrayHasKey($key, $schema['properties'], 'Undocumented field '.$key.' in '.$model['endpoint']);
                    $rule = $schema['properties'][$key];
                    if (isset($rule['enum'])) {
                        self::assertContains($value, $rule['enum']);
                    }
                    if (isset($rule['const'])) {
                        self::assertSame($rule['const'], $value);
                    }
                    if (isset($rule['type'])) {
                        self::assertSame(match ($rule['type']) {
                            'integer' => 'int', 'boolean' => 'bool', default => $rule['type']
                        }, get_debug_type($value));
                    }
                }
                if ('image_urls' === ($model['imageField'] ?? '')) {
                    self::assertCount(2, $body['image_urls']);
                    self::assertSame([['duration' => $seconds, 'prompt' => 'A slow camera move']], $body['transitions']);
                    $first = $body['image_urls'][0];
                    $last = $body['image_urls'][1];
                } else {
                    $first = $body[$model['imageField'] ?? 'image_url'];
                    $last = $withEnd ? $body[$model['endImageField']] : null;
                }
            } elseif ('ltx' === $model['provider']) {
                self::assertSame('https://api.ltx.io/v2/image-to-video', $url);
                self::assertSame($model['endpoint'], $body['model']);
                self::assertSame($seconds, $body['duration']);
                self::assertArrayNotHasKey('aspect_ratio', $body);
                $first = $body['image_uri'];
                $last = $withEnd ? $body['last_frame_uri'] : null;
                if (!$withEnd) {
                    self::assertArrayNotHasKey('last_frame_uri', $body);
                }
            } else {
                self::assertSame('https://api.replicate.com/v1/models/'.$model['endpoint'].'/predictions', $url);
                self::assertSame($seconds, $body['input']['duration']);
                self::assertSame($model['quality'], $body['input']['resolution']);
                $first = $body['input']['image'];
                $last = $withEnd ? $body['input']['last_frame'] : null;
                self::assertArrayNotHasKey('enable_safety_checker', $body['input']);
            }
            foreach (array_filter([$first, $last]) as $uri) {
                self::assertStringStartsWith('data:image/jpeg;base64,', $uri);
                $bytes = base64_decode(substr($uri, strlen('data:image/jpeg;base64,')), true);
                self::assertNotFalse($bytes);
                self::assertSame('image/jpeg', getimagesizefromstring($bytes)['mime']);
            }
            if ('fal' === $model['provider']) {
                $base = 'https://queue.fal.run/'.implode('/', array_slice(explode('/', $model['endpoint']), 0, 2)).'/requests/image-contract';

                return new MockResponse(json_encode(['status_url' => $base.'/status', 'response_url' => $base], JSON_THROW_ON_ERROR));
            }

            return new MockResponse('{"id":"image-contract"}');
        });
        $api = match ($model['provider']) {
            'fal' => new FalVideoApi($http, $catalog, $this->storage),
            'ltx' => new LtxVideoApi($http, $catalog, $this->storage),
            'replicate' => new ReplicateVideoApi($http, $catalog, $this->storage),
        };
        $api->submit($job, 'synthetic-not-a-real-key');
        self::assertSame(1, $calls);
    }

    public function testCropAndContainKeepTheChosenFrameWithoutChangingTheOriginal(): void
    {
        $original = file_get_contents($this->storage->path($this->owner, self::FIRST));
        $crop = $this->storage->bytes($this->owner, self::FIRST, '16:9', 'crop');
        $contain = $this->storage->bytes($this->owner, self::FIRST, '16:9', 'contain');
        self::assertSame([1920, 1080], array_slice(getimagesizefromstring($crop), 0, 2));
        self::assertSame([1920, 1080], array_slice(getimagesizefromstring($contain), 0, 2));
        self::assertNotSame($crop, $contain);
        self::assertSame($original, file_get_contents($this->storage->path($this->owner, self::FIRST)));
        self::assertSame($original, $this->storage->bytes($this->owner, self::FIRST));
    }

    public function testConditionalPricesIncludeTheImageFeeAndNeverUseBaseRatesBlindly(): void
    {
        $catalog = new AiCatalog();
        self::assertSame(84, $catalog->estimate('kling-3-image', 5));
        self::assertSame(45, $catalog->estimate('pika-22-image-1080p', 5));
        self::assertSame(150, $catalog->estimate('pixverse-56-frames-1080p', 8));
        self::assertSame(30, $catalog->estimate('pika-frames-1080p', 5));
        self::assertSame(36, $catalog->estimate('grok-image-720', 5));
        self::assertSame(126, $catalog->estimate('grok-15-image-1080p', 5));
        self::assertSame(180, $catalog->estimate('ltx-25-fast-4k-image', 6));
    }
}
