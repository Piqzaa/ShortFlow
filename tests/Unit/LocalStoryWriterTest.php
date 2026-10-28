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
                new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode(['identity' => 'White cat Milo', 'shots' => [$row, $row]], JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR)),
            ]);
            try {
                (new LocalStoryWriter($http, true, 'http://localhost:11434', 'test'))->propose('Milo', [10, 10], 'independent', 'auto');
                self::fail('Bad scenes must be refused.');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('Tes scènes sont conservées', $error->getMessage());
            }
        }
    }

    /** @param array<string,mixed> $story
     * @param array<string,mixed> $directions
     */
    private function writer(array $story, array $directions): LocalStoryWriter
    {
        $responses = array_map(static fn (array $data): MockResponse => new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($data, JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR)), [$story, $directions]);

        return new LocalStoryWriter(new MockHttpClient($responses), true, 'http://localhost:11434', 'synthetic');
    }

    public function testSharedVisualIdentityIsKeptInEveryPromptWithinTheVideoLimit(): void
    {
        $writer = $this->writer(
            ['title' => 'Milo', 'summary' => 'Milo suit une balle puis se couche.', 'identity' => 'Milo est un chat blanc.', 'beats' => ['Milo suit la balle.', 'Milo se couche.']],
            ['identity' => 'Milo, a white cat.', 'shots' => [['seconds' => 5, 'prompt' => str_repeat('A slow camera tracks the cat. ', 25)], ['seconds' => 10, 'prompt' => 'Close framing: the cat curls up next to the red ball.']]],
        );
        $result = $writer->propose('Milo mon chat blanc', [5, 10], 'independent', 'calme');
        self::assertSame([], $result['warnings']);
        self::assertTrue($result['translationReady']);
        self::assertSame(\App\Service\StoryTextChecks::translationSource('Milo mon chat blanc'), $result['shots'][0]['translationSource']);
        self::assertSame([5, 10], array_column($result['shots'], 'seconds'));
        foreach ($result['shots'] as $shot) {
            self::assertStringStartsWith('Milo, a white cat. ', $shot['prompt']);
            self::assertLessThanOrEqual(1100, mb_strlen($shot['prompt']));
        }
    }

    public function testSuspiciousLanguageAccessoriesAndRepeatedSummaryAreReportedWithoutLosingScenes(): void
    {
        $writer = $this->writer(
            ['title' => 'Milo', 'summary' => 'Milo suit la balle.', 'identity' => 'Un chat blanc.', 'beats' => ['Milo suit la balle.']],
            ['identity' => 'White cat Milo with a leather collar', 'shots' => [['seconds' => 10, 'prompt' => 'Milo marche dans le salon avec son collier, puis suit la balle sur le tapis.']]],
        );
        $result = $writer->propose('Milo mon chat blanc sans collier', [10], 'independent', 'auto', ['previous' => [['summary' => 'MILO suit la balle !']]]);
        self::assertCount(3, $result['warnings']);
        self::assertStringContainsString('français', $result['warnings'][0]);
        self::assertStringContainsString('accessoire', $result['warnings'][1]);
        self::assertStringContainsString('précédent', $result['warnings'][2]);
        self::assertCount(1, $result['shots']);
        self::assertFalse($result['translationReady']);
    }

    public function testFrenchIdentityCannotBeHiddenByEnglishSceneAndDraftIsPreservedWithoutRetry(): void
    {
        $result = $this->writer(
            ['title' => 'Banane', 'summary' => 'La banane ouvre une porte.', 'identity' => 'Une banane jaune.', 'beats' => ['La banane ouvre une porte.']],
            ['identity' => 'Une banane jaune.', 'shots' => [['seconds' => 5, 'prompt' => 'A static camera frames the banana opening the door.']]],
        )->propose('Une banane jaune ouvre une porte.', [5], 'independent', 'auto');
        self::assertFalse($result['translationReady']);
        self::assertSame('La banane ouvre une porte.', $result['shots'][0]['description']);
        self::assertSame('Une banane jaune.', $result['shots'][0]['translatedIdentity']);
        self::assertStringContainsString('Traduction à corriger', implode(' ', $result['warnings']));
    }

    public function testRequestedClothingDoesNotTriggerAnAccessoryWarning(): void
    {
        $writer = $this->writer(
            ['title' => 'Une exploration', 'summary' => 'Une exploratrice en veste verte avance vers la clairière.', 'identity' => 'Une exploratrice en veste verte.', 'beats' => ['Elle atteint la clairière.']],
            ['identity' => 'An explorer in a green jacket', 'shots' => [['seconds' => 10, 'prompt' => 'A wide camera follows the explorer toward the sunlit clearing.']]],
        );
        self::assertSame([], $writer->propose('Une exploratrice en veste verte', [10], 'independent', 'aventure')['warnings']);
    }

    public function testEnglishNarrationIsFlaggedForFrenchReview(): void
    {
        $writer = $this->writer(
            ['title' => 'Milo', 'summary' => 'The cat moves into the room with his ball.', 'identity' => 'Un chat blanc.', 'beats' => ['Milo entre dans la pièce.']],
            ['identity' => 'White cat Milo', 'shots' => [['seconds' => 10, 'prompt' => 'The white cat walks slowly toward the ball in a wide shot.']]],
        );
        $result = $writer->propose('Milo mon chat blanc', [10], 'independent', 'auto');
        self::assertCount(1, $result['warnings']);
        self::assertStringContainsString('texte français', $result['warnings'][0]);
    }

    public function testMissingIdentityTranslationIsNotSilentlyAccepted(): void
    {
        $writer = $this->writer(
            ['title' => 'Milo', 'summary' => 'Milo suit la balle.', 'identity' => 'Un chat blanc.', 'beats' => ['Milo suit la balle.']],
            ['shots' => [['seconds' => 10, 'prompt' => 'A wide shot of the cat following the ball on the floor.']]],
        );
        $this->expectException(StoryWritingException::class);
        $writer->propose('Milo', [10], 'independent', 'auto');
    }

    public function testNegatedAccessoriesDoNotHidePositiveContradictionsOrCreateFalseAlerts(): void
    {
        foreach (['White cat Milo. No collar or accessories.', 'White cat Milo. No clothing or collar.'] as $identity) {
            foreach (['A wide shot of the cat walking toward the ball.' => 0, 'A wide shot of the cat wearing a collar and walking toward the ball.' => 1] as $prompt => $expected) {
                $writer = $this->writer(
                    ['title' => 'Milo', 'summary' => 'Milo suit la balle.', 'identity' => 'Un chat blanc.', 'beats' => ['Milo suit la balle.']],
                    ['identity' => $identity, 'shots' => [['seconds' => 10, 'prompt' => $prompt]]],
                );
                self::assertCount($expected, $writer->propose('Milo mon chat blanc', [10], 'independent', 'auto')['warnings']);
            }
        }
    }

    public function testEyeColorsAreComparedToTheProvidedCharacterAcrossLanguages(): void
    {
        foreach (['Milo chat blanc' => 1, 'Milo chat blanc aux yeux verts' => 0, 'Milo chat blanc aux yeux bleus' => 1] as $idea => $expected) {
            $writer = $this->writer(
                ['title' => 'Milo', 'summary' => 'Milo suit la balle.', 'identity' => 'Un chat blanc.', 'beats' => ['Milo suit la balle.']],
                ['identity' => 'White cat Milo with green eyes', 'shots' => [['seconds' => 10, 'prompt' => 'The cat follows the ball across the room in a wide shot.']]],
            );
            self::assertCount($expected, $writer->propose($idea, [10], 'independent', 'auto')['warnings']);
        }
    }

    public function testSavedProposalsCanBeReviewedWithoutAnEnabledEngineOrAnyApiRequest(): void
    {
        $http = new MockHttpClient(static function (): never { throw new \LogicException('Offline review must not request an API.'); });
        $writer = new LocalStoryWriter($http, false, 'http://localhost:11434', 'unused');
        $proposal = ['summary' => 'Milo suit la balle.', 'shots' => [['description' => 'Milo suit la balle.', 'prompt' => 'White cat Milo with green eyes follows the ball.', 'seconds' => 10]]];
        self::assertCount(1, $writer->review('Milo chat blanc', [], $proposal));
        $proposal['shots'][0]['prompt'] = 'White cat Milo follows the ball.';
        $proposal['shots'][0]['description'] = str_repeat('Une action ', 29).'sans sa fin';
        self::assertCount(1, $writer->review('Milo chat blanc', [], $proposal));
        $proposal['shots'][0]['description'] .= '.';
        self::assertSame([], $writer->review('Milo chat blanc', [], $proposal));
    }
}
