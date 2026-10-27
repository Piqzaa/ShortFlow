<?php

namespace App\Tests\Unit;

use App\Service\LocalStoryWriter;
use App\Service\PublicationException;
use App\Service\PublicationTexts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Captured failures are deliberately red until the corresponding R2 corrections. */
final class WriterQualityRegressionTest extends TestCase
{
    private const LANGUAGE_WARNING = '/prompt[^.]*\b(?:français|anglais)\b|\b(?:français|anglais)\b[^.]*prompt/iu';
    private const NETWORKS = ['youtube', 'instagram', 'tiktok'];

    /** @return array<string,mixed> */
    private static function corpus(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/fixtures/redacteur-audit-20261027.json'), true, 32, JSON_THROW_ON_ERROR);
    }

    /** @return array<string,mixed> */
    private static function captured(string $id): array
    {
        foreach (self::corpus()['cases'] as $case) {
            if ($id === $case['input']['id']) {
                return $case;
            }
        }
        throw new \LogicException('Missing versioned audit case: '.$id);
    }

    private function offlineWriter(): LocalStoryWriter
    {
        return new LocalStoryWriter(new MockHttpClient(static function (): never { throw new \LogicException('Offline review must not call any model.'); }), false, 'http://localhost:11434', 'unused');
    }

    /** @param array<string,mixed> $envelope */
    private function replay(array $envelope, MockHttpClient &$http): LocalStoryWriter
    {
        $http = new MockHttpClient([new MockResponse(json_encode($envelope, JSON_THROW_ON_ERROR))]);

        return new LocalStoryWriter($http, true, 'http://localhost:11434', 'synthetic-replay');
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function envelope(array $data): array
    {
        return ['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($data, JSON_THROW_ON_ERROR)]];
    }

    /** @return iterable<string,array{string}> */
    public static function storyCases(): iterable
    {
        foreach (['milo', 'pedagogie', 'recette', 'produit', 'dialogue', 'danse'] as $id) {
            yield $id => [$id];
        }
    }

    #[DataProvider('storyCases')]
    public function testOfflineReviewKeepsCapturedScenesIntactWithoutCertifyingTheirMeaning(string $id): void
    {
        $case = self::captured($id);
        $before = $case['result'];
        self::assertSame($case['input']['durations'], array_column($before['shots'], 'seconds'));
        foreach ($before['shots'] as $shot) {
            self::assertNotSame('', trim($shot['prompt']));
            self::assertNotSame('', trim($shot['description']));
        }
        $warnings = $this->offlineWriter()->review($case['input']['idea'], $case['input']['context'], $case['result']);
        if ('dialogue' !== $id) {
            self::assertDoesNotMatchRegularExpression('/répliqu|dialogue|parole/iu', implode(' ', $warnings), 'A missing-dialogue warning must not be added to every story.');
        }
        self::assertSame($before, $case['result'], 'Review must not rewrite the captured human-readable scenes or technical prompts.');
        // Valid fields and an empty warning list cannot establish narrative truth.
    }

    public function testCapturedFrenchVideoDirectionsRequireALanguageWarning(): void
    {
        $case = self::captured('dialogue');
        $warnings = $this->offlineWriter()->review($case['input']['idea'], $case['input']['context'], $case['result']);
        self::assertMatchesRegularExpression(self::LANGUAGE_WARNING, implode(' ', $warnings), 'The captured French directions must not be presented without a language warning.');
    }

    public function testCapturedDialogueLostFromVideoPromptsRequiresItsOwnWarning(): void
    {
        $case = self::captured('dialogue');
        $lines = ['Tu as trouvé mon chat ?', 'Oui, il dort sur ta chaise !'];
        foreach ($lines as $line) {
            self::assertStringContainsString($line, $case['input']['idea']);
            self::assertStringContainsString($line, implode(' ', array_column($case['result']['shots'], 'description')));
            self::assertStringNotContainsString($line, implode(' ', array_column($case['result']['shots'], 'prompt')));
        }
        $warnings = $this->offlineWriter()->review($case['input']['idea'], $case['input']['context'], $case['result']);
        self::assertMatchesRegularExpression('/répliqu|dialogue|parole/iu', implode(' ', $warnings), 'A language warning alone cannot report the missing spoken lines.');
    }

    /** @return iterable<string,array{string}> */
    public static function quotedLines(): iterable
    {
        yield 'short quote from the historical case' => ['Tu as trouvé mon chat ?'];
        yield 'synthetic quote with several French markers' => ['Le chat est dans une boîte sur les coussins.'];
    }

    #[DataProvider('quotedLines')]
    public function testEnglishDirectionsMayContainAnIntentionalExactFrenchQuote(string $line): void
    {
        // Synthetic positive controls, not newly captured model responses.
        $idea = 'Léa dit exactement en français « '.$line.' ».';
        $proposal = ['summary' => 'Léa parle face à la caméra.', 'shots' => [['seconds' => 5, 'description' => $idea, 'prompt' => 'Medium shot of Lea speaking to camera. She says in French: « '.$line.' ». Static camera, natural daylight.']]];
        $warnings = $this->offlineWriter()->review($idea, [], $proposal);
        self::assertDoesNotMatchRegularExpression(self::LANGUAGE_WARNING, implode(' ', $warnings), 'Quoted French speech is not French technical direction. Other review warnings are permitted.');
    }

    public function testActualRawHashtagDiagnosticStillFailsNormalization(): void
    {
        $corpus = self::corpus();
        $response = $corpus['diagnosticResponse'];
        self::assertTrue($response['done']);
        self::assertSame('stop', $response['done_reason']);
        $raw = json_decode($response['message']['content'], true, 32, JSON_THROW_ON_ERROR);
        self::assertSame(self::NETWORKS, array_keys($raw));
        self::assertCount(5, $raw['youtube']['hashtags']);
        self::assertStringContainsString('#', $raw['youtube']['description']);
        $this->expectException(PublicationException::class);
        $this->expectExceptionMessage('Les hashtags doivent rester séparés');
        (new PublicationTexts())->normalize($raw, self::NETWORKS, $corpus['diagnostic']['profile']);
    }

    public function testActualRawHashtagDiagnosticIsRejectedByTheWriterWithoutRetry(): void
    {
        $corpus = self::corpus();
        $http = new MockHttpClient();
        $writer = $this->replay($corpus['diagnosticResponse'], $http);
        try {
            $writer->publication($corpus['diagnostic']['source'], $corpus['diagnostic']['profile'], self::NETWORKS);
            self::fail('A complete JSON envelope must not bypass the hashtag validator.');
        } catch (PublicationException $error) {
            self::assertStringContainsString('Les hashtags doivent rester séparés', $error->getMessage());
        }
        self::assertSame(1, $http->getRequestsCount());
    }

    /** Derive a schema-valid fixture from normalized output, never call it raw capture.
     * @return array<string,array<string,mixed>>
     */
    private function reconstructedMinimalResponse(): array
    {
        $output = self::captured('text-import-minimal')['result'];
        foreach ($output as &$network) {
            $field = isset($network['description']) ? 'description' : 'caption';
            [$body, $tags] = explode("\n\n", $network[$field], 2);
            preg_match_all('/#([\p{L}\p{N}_]+)/u', $tags, $matches);
            $network[$field] = $body;
            $network['hashtags'] = $matches[1];
        }
        unset($network);

        return $output;
    }

    public function testInventedMinimalSourcePackIsStructurallyValidButNotFactuallyValidated(): void
    {
        $captured = self::captured('text-import-minimal');
        self::assertSame(['title' => 'Vacances 2026'], $captured['input']['source']);
        self::assertSame($captured['result'], (new PublicationTexts())->normalize($this->reconstructedMinimalResponse(), self::NETWORKS, ['language' => 'fr']));
        self::assertStringContainsString('pas encore de plan final', $captured['result']['instagram']['caption']);
    }

    /** @param array<string,array<string,mixed>> $response */
    private function assertMinimalSourceIsRefusedOrStrictlyNeutral(array $response): void
    {
        $source = self::captured('text-import-minimal')['input']['source'];
        $http = new MockHttpClient();
        $writer = $this->replay($this->envelope($response), $http);
        try {
            $result = $writer->publication($source, ['language' => 'fr'], self::NETWORKS);
        } catch (PublicationException $error) {
            self::assertMatchesRegularExpression('/contexte|précis|décri|information|source|détails/iu', $error->getMessage(), 'Insufficient context needs an actionable refusal, not an unrelated transport/format failure.');
            self::assertLessThanOrEqual(1, $http->getRequestsCount());

            return;
        }
        self::assertSame(self::NETWORKS, array_keys($result));
        foreach ($result as $network => $row) {
            self::assertEqualsCanonicalizing('youtube' === $network ? ['title', 'description'] : ['caption'], array_keys($row), 'A neutral pack still requires every requested text field.');
            foreach ($row as $text) {
                // A finite safe oracle for THIS title-only fixture, not a semantic classifier.
                $body = trim(preg_replace('/#[\p{L}\p{N}_]+/u', '', $text));
                self::assertContains($body, ['Vacances 2026', 'Vidéo : Vacances 2026', 'À découvrir : Vacances 2026'], 'A title alone does not establish preparation, destinations, plans or expectations. Refuse for context, or return a literal neutral title-only variant.');
                preg_match_all('/#([\p{L}\p{N}_]+)/u', $text, $tags);
                foreach ($tags[1] as $tag) {
                    self::assertContains(mb_strtolower($tag), ['vacances2026', 'vacances', '2026'], 'The neutral fixture must not reintroduce unsupported facts through hashtags.');
                }
            }
        }
        self::assertLessThanOrEqual(1, $http->getRequestsCount());
    }

    public function testCapturedInventionsFromATitleOnlyMustBeRefusedOrReplacedByNeutralText(): void
    {
        $this->assertMinimalSourceIsRefusedOrStrictlyNeutral($this->reconstructedMinimalResponse());
    }

    public function testLiteralNeutralTitleOnlyControlMayBeReturnedOrRequestMoreContext(): void
    {
        $this->assertMinimalSourceIsRefusedOrStrictlyNeutral([
            'youtube' => ['title' => 'Vacances 2026', 'description' => 'Vacances 2026', 'hashtags' => ['vacances2026']],
            'instagram' => ['caption' => 'Vidéo : Vacances 2026', 'hashtags' => ['vacances2026']],
            'tiktok' => ['caption' => 'À découvrir : Vacances 2026', 'hashtags' => []],
        ]);
    }
}
