<?php

namespace App\Tests\Unit;

use App\Service\LocalStoryWriter;
use App\Service\PublicationException;
use App\Service\PublicationTexts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PublicationPackDiagnosisTest extends TestCase
{
    /** @return iterable<string,array{array<string,mixed>,string}> */
    public static function invalidPacks(): iterable
    {
        yield 'missing network' => [[], 'networks'];
        yield 'extra network' => [['youtube' => [], 'tiktok' => []], 'networks'];
        yield 'missing field' => [['youtube' => ['title' => 'Mug']], 'structure'];
        yield 'injected settings' => [['youtube' => ['title' => 'Mug', 'description' => 'Mug bleu.', 'hashtags' => [], 'privacy' => 'public']], 'structure'];
        yield 'too many tags' => [['youtube' => ['title' => 'Mug', 'description' => 'Mug bleu.', 'hashtags' => range(1, 6)]], 'structure'];
        yield 'embedded tags' => [['youtube' => ['title' => 'Mug', 'description' => 'Mug bleu. #mug', 'hashtags' => []]], 'embedded_hashtags'];
        yield 'empty text' => [['youtube' => ['title' => ' ', 'description' => 'Mug bleu.', 'hashtags' => []]], 'text'];
        yield 'markup' => [['youtube' => ['title' => '<Mug>', 'description' => 'Mug bleu.', 'hashtags' => []]], 'text'];
        yield 'oversized text' => [['youtube' => ['title' => str_repeat('é', 101), 'description' => 'Mug bleu.', 'hashtags' => []]], 'length'];
        yield 'invalid tag' => [['youtube' => ['title' => 'Mug', 'description' => 'Mug bleu.', 'hashtags' => ['mug bleu']]], 'hashtag'];
        yield 'excluded tag' => [['youtube' => ['title' => 'Mug', 'description' => 'Mug bleu.', 'hashtags' => ['promotion']]], 'hashtag_profile'];
    }

    #[DataProvider('invalidPacks')]
    public function testInvalidPacksKeepStrictValidationAndSafeDiagnosis(array $pack, string $reason): void
    {
        $before = $pack;
        try {
            (new PublicationTexts())->normalize($pack, ['youtube'], ['excludedHashtags' => 'promotion']);
            self::fail('Invalid packs must never be repaired or partially returned.');
        } catch (PublicationException $error) {
            self::assertSame($reason, $error->reason);
            self::assertMatchesRegularExpression('/Demande|demande|Vérifie/', $error->getMessage());
        }
        self::assertSame($before, $pack);
    }

    /** @return iterable<string,array{string,int,string,string}> */
    public static function engineFailures(): iterable
    {
        yield 'model absent' => ['PRIVATE_PROVIDER_BODY', 404, 'model', 'installation'];
        yield 'engine absent' => ['PRIVATE_PROVIDER_BODY', 503, 'unavailable', 'Ollama'];
        yield 'broken envelope' => ['PRIVATE_PROVIDER_BODY', 200, 'json', 'format'];
        yield 'incomplete generation' => [json_encode(['done' => true, 'done_reason' => 'length', 'message' => ['content' => 'PRIVATE_PROVIDER_BODY']], JSON_THROW_ON_ERROR), 200, 'incomplete', 'avant la fin'];
        yield 'broken model JSON' => [json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => 'PRIVATE_PROVIDER_BODY']], JSON_THROW_ON_ERROR), 200, 'json', 'format'];
    }

    #[DataProvider('engineFailures')]
    public function testEngineFailuresAreDistinctSafeAndNeverRetried(string $body, int $status, string $reason, string $action): void
    {
        $http = new MockHttpClient(new MockResponse($body, ['http_code' => $status]));
        $logger = new class extends AbstractLogger {
            public array $events = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->events[] = [$message, $context];
            }
        };
        try {
            (new LocalStoryWriter($http, true, 'http://localhost:11434', 'test', $logger))->publication(['description' => 'PRIVATE_SOURCE : un mug bleu sur la table.'], [], ['youtube']);
            self::fail('A failed response must not be accepted.');
        } catch (PublicationException $error) {
            self::assertSame($reason, $error->reason);
            self::assertStringContainsString($action, $error->getMessage());
            self::assertStringContainsString('Tes textes restent conservés', $error->getMessage());
            self::assertStringNotContainsString('PRIVATE', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
        self::assertSame(1, $http->getRequestsCount());
        self::assertSame([['Local editorial writing failed.', ['reason' => $reason]]], $logger->events);
    }

    public function testTransportDeadlineHasEditorialDiagnosis(): void
    {
        $http = new MockHttpClient(static function (): never { throw new TransportException('Max duration reached PRIVATE_SOURCE'); });
        try {
            (new LocalStoryWriter($http, true, 'http://localhost:11434', 'test'))->publication(['description' => 'Un mug bleu sur la table.'], [], ['youtube']);
            self::fail('The deadline must be reported.');
        } catch (PublicationException $error) {
            self::assertSame('timeout', $error->reason);
            self::assertStringContainsString('délai', $error->getMessage());
            self::assertStringNotContainsString('PRIVATE', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
    }

    public function testCompleteThreeNetworkPackIsRequestedAndNormalizedInOneCall(): void
    {
        $pack = ['youtube' => ['title' => 'Le mug bleu', 'description' => 'Un mug bleu sur la table.', 'hashtags' => ['Mug', '#mug']], 'instagram' => ['caption' => 'Le mug bleu sur la table.', 'hashtags' => []], 'tiktok' => ['caption' => 'Un mug bleu.', 'hashtags' => ['Mug']]];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use ($pack): MockResponse {
            $request = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
            $input = json_decode($request['messages'][1]['content'], true, 32, JSON_THROW_ON_ERROR);
            self::assertSame(array_keys($pack), $input['networks']);
            self::assertSame(array_keys($pack), $request['format']['required']);
            self::assertFalse($request['format']['additionalProperties']);
            self::assertStringContainsString('jamais #, < ou >', $request['messages'][0]['content']);
            foreach ($pack as $network => $fields) {
                self::assertSame(array_keys($fields), $request['format']['properties'][$network]['required']);
            }

            return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($pack, JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR));
        });
        $result = (new LocalStoryWriter($http, true, 'http://localhost:11434', 'test'))->publication(['description' => 'Un mug bleu sur la table.'], [], array_keys($pack));
        self::assertSame(1, $http->getRequestsCount());
        self::assertSame(['youtube' => ['title' => 'Le mug bleu', 'description' => "Un mug bleu sur la table.\n\n#mug"], 'instagram' => ['caption' => 'Le mug bleu sur la table.'], 'tiktok' => ['caption' => "Un mug bleu.\n\n#Mug"]], $result);
    }

    public function testBusyWriterReportsEditorialActionWithoutRequestOrUnlockingOtherWork(): void
    {
        $held = fopen(sys_get_temp_dir().'/vydflow-story-writer.lock', 'c');
        self::assertIsResource($held);
        self::assertTrue(flock($held, LOCK_EX | LOCK_NB));
        $probe = fopen(sys_get_temp_dir().'/vydflow-story-writer.lock', 'c');
        $http = new MockHttpClient();
        try {
            try {
                (new LocalStoryWriter($http, true, 'http://localhost:11434', 'test'))->publication(['description' => 'Un mug bleu sur la table.'], [], ['youtube']);
                self::fail('Other work must keep the lock.');
            } catch (PublicationException $error) {
                self::assertSame('busy', $error->reason);
                self::assertStringContainsString('Réessaie dans un instant', $error->getMessage());
                self::assertStringContainsString('Tes textes restent conservés', $error->getMessage());
            }
            self::assertSame(0, $http->getRequestsCount());
            self::assertFalse(flock($probe, LOCK_EX | LOCK_NB));
        } finally {
            fclose($probe);
            flock($held, LOCK_UN);
            fclose($held);
        }
    }
}
