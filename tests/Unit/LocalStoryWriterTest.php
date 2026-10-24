<?php

namespace App\Tests\Unit;

use App\Service\LocalStoryWriter;
use App\Service\StoryWritingException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LocalStoryWriterTest extends TestCase
{
    public function testFailuresHaveSafeActionableReasons(): void
    {
        $cases = [
            'model' => new MockResponse('{"error":"model missing"}', ['http_code' => 404]),
            'incomplete' => new MockResponse('{"done":true,"done_reason":"length","message":{"content":"partial"}}'),
            'invalid' => new MockResponse('{"done":true,"done_reason":"stop","message":{"content":"not json"}}'),
            'timeout' => static function (): never { throw new TransportException('Max duration was reached for local request'); },
            'unavailable' => static function (): never { throw new TransportException('Connection refused'); },
        ];
        foreach ($cases as $reason => $response) {
            try {
                (new LocalStoryWriter(new MockHttpClient($response), true, 'http://localhost:11434', 'test'))->propose('Milo', [10], 'independent', 'auto');
                self::fail('Expected a safe writing failure.');
            } catch (StoryWritingException $error) {
                self::assertSame($reason, $error->reason);
                self::assertStringContainsString('Tes scènes sont conservées', $error->getMessage());
                self::assertStringNotContainsString('local request', $error->getMessage());
            }
        }
    }

    public function testDisabledWriterNeverCallsAnApi(): void
    {
        $http = new MockHttpClient(static function (): never { throw new \LogicException('No call allowed'); });
        $this->expectException(\RuntimeException::class);
        (new LocalStoryWriter($http, false, 'http://localhost:11434', 'test'))->propose('Milo', [10], 'independent', 'auto');
    }

    public function testRemoteTextServiceIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        (new LocalStoryWriter(new MockHttpClient(), true, 'https://example.com', 'test'))->propose('Milo', [10], 'independent', 'auto');
    }

    public function testBadDurationAndDuplicateScenesAreRefused(): void
    {
        foreach ([['seconds' => 15, 'prompt' => str_repeat('scene ', 10)], ['seconds' => 10, 'prompt' => str_repeat('scene ', 10)]] as $row) {
            $http = new MockHttpClient([
                new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => '{"title":"Milo","summary":"A resolved story","identity":"white cat","beats":["Start","End"]}']], JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode(['shots' => [$row, $row]], JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR)),
            ]);
            try {
                (new LocalStoryWriter($http, true, 'http://localhost:11434', 'test'))->propose('Milo', [10, 10], 'independent', 'auto');
                self::fail('Bad scenes must be refused.');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('Tes scènes sont conservées', $error->getMessage());
            }
        }
    }
}
