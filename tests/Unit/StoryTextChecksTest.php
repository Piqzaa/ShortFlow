<?php

namespace App\Tests\Unit;

use App\Service\LocalStoryWriter;
use App\Service\StoryTextChecks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class StoryTextChecksTest extends TestCase
{
    /** @param list<string> $prompts @param array<string,mixed> $context
     * @return array<string,string>
     */
    private function check(string $idea, array $prompts, array $context = []): array
    {
        $proposal = ['summary' => 'Deux amis se rencontrent.', 'shots' => array_map(static fn (string $prompt): array => ['prompt' => $prompt, 'description' => 'Une rencontre.', 'seconds' => 5], $prompts)];
        $before = $proposal;
        $warnings = (new StoryTextChecks())->review($idea, $context, $proposal);
        self::assertSame($before, $proposal);

        return $warnings;
    }

    /** @return iterable<string,array{string,string,bool}> */
    public static function languageCases(): iterable
    {
        $line = 'Le chat est dans une boîte sur les coussins.';
        yield 'exact French guillemets' => ['Léa dit « '.$line.' ».', 'A static camera frames Lea. She says in French: « '.$line.' ».', false];
        yield 'exact straight quotes' => ['Léa dit "'.$line.'".', 'A static camera frames Lea. She says in French: "'.$line.'".', false];
        yield 'exact curly quotes' => ['Léa dit “'.$line.'”.', 'A static camera frames Lea. She says in French: “'.$line.'”.', false];
        yield 'unknown quote is not exempt' => ['Léa salue Hugo.', 'A static camera frames Lea. She says: « '.$line.' ».', true];
        yield 'changed quote is not exempt' => ['Léa dit « '.$line.' ».', 'A static camera frames Lea. She says: « Le chat est dans une boîte sur les fauteuils. ».', true];
        yield 'unclosed quote cannot hide directions' => ['Léa dit « '.$line.' ».', 'A static camera frames Lea. She says: « '.$line, true];
        yield 'French directions outside a valid quote remain visible' => ['Léa dit « Bonjour ! ».', 'Léa regarde Hugo dans le salon puis ouvre une porte. Elle dit « Bonjour ! ».', true];
        yield 'a French prompt wrapped in quotes is still French' => ['Une rencontre.', '« Léa regarde Hugo dans le salon puis ouvre une porte. »', true];
        yield 'French title supplied explicitly' => ['Un livre intitulé « '.$line.' » est posé.', 'A static camera frames the book titled « '.$line.' ».', false];
    }

    #[DataProvider('languageCases')]
    public function testOnlyExplicitExactClosedQuotesAreExemptFromLanguageChecks(string $idea, string $prompt, bool $warn): void
    {
        $warnings = $this->check($idea, [$prompt]);
        self::assertSame($warn, isset($warnings['prompts']));
    }

    public function testUnknownDirectionLanguageProducesAnUncertaintyNotice(): void
    {
        $warnings = $this->check('Une rencontre.', ['Zwei Freunde begegnen einander langsam vor einem ruhigen kleinen Haus.']);
        self::assertArrayHasKey('promptLanguageUnknown', $warnings);
        self::assertStringContainsString('ne confirme pas', $warnings['promptLanguageUnknown']);
        $quoteOnly = $this->check('Léa dit « Le chat est dans une boîte sur les coussins. ».', ['« Le chat est dans une boîte sur les coussins. »']);
        self::assertArrayHasKey('promptLanguageUnknown', $quoteOnly, 'An exempt quotation alone must not certify English technical directions.');
    }

    public function testHistoricalDialogueHasIndependentLanguageAndMissingSpeechFindings(): void
    {
        $corpus = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/fixtures/redacteur-audit-20261027.json'), true, 32, JSON_THROW_ON_ERROR);
        $case = array_values(array_filter($corpus['cases'], static fn (array $row): bool => 'dialogue' === $row['input']['id']))[0];
        $warnings = (new StoryTextChecks())->review($case['input']['idea'], $case['input']['context'], $case['result']);
        self::assertArrayHasKey('prompts', $warnings);
        self::assertArrayHasKey('missingSpeech', $warnings);
        self::assertDoesNotMatchRegularExpression('/répliqu|dialogue|parole/iu', $warnings['prompts'], 'A language notice must not substitute for the separate missing-speech check.');
    }

    public function testSpeechLinesStayExactAndRepeatedRequestsAreNotDeduplicated(): void
    {
        $idea = 'Léa dit « Non, je n’ai rien oublié ! » puis Hugo répond « Non, je n’ai rien oublié ! ».';
        self::assertSame(['Non, je n’ai rien oublié !', 'Non, je n’ai rien oublié !'], (new StoryTextChecks())->speechLines($idea));
        $warnings = $this->check($idea, ['A static shot of Lea. She says: « Non, je n’ai rien oublié ! ».']);
        self::assertArrayHasKey('missingSpeech', $warnings);
    }

    public function testSpeechCannotBeReassembledFromFragmentsAcrossPlans(): void
    {
        $warnings = $this->check('Léa dit « Bonjour mon ami ! ».', ['A static shot of Lea. She says: « Bonjour mon ».', 'A static shot of Lea. She says: « ami ! ».']);
        self::assertArrayHasKey('missingSpeech', $warnings);
    }

    public function testExtraRepetitionsAreFlaggedButExplicitRepeatsAreAllowed(): void
    {
        $prompt = 'A static shot of Lea. She says: « Bonjour ! ».';
        self::assertArrayHasKey('repeatedSpeech', $this->check('Léa dit « Bonjour ! ».', [$prompt, $prompt, $prompt]));
        $requestedTwice = $this->check('Léa dit « Bonjour ! » puis Hugo répond « Bonjour ! ».', [$prompt, $prompt]);
        self::assertArrayNotHasKey('repeatedSpeech', $requestedTwice);
        self::assertArrayNotHasKey('missingSpeech', $requestedTwice);
    }

    public function testReversedLinesAreReportedAndPresenceNeverCertifiesTheSpeaker(): void
    {
        $idea = 'Léa dit « Bonjour Hugo ! » puis Hugo répond « Bonjour Léa ! ».';
        $warnings = $this->check($idea, ['A static shot. Hugo says: « Bonjour Léa ! ».', 'A static shot. Lea says: « Bonjour Hugo ! ».']);
        self::assertArrayHasKey('speechOrder', $warnings);
        self::assertArrayNotHasKey('missingSpeech', $warnings);
        $swapped = $this->check($idea, ['A static shot. Hugo says: « Bonjour Hugo ! ».', 'A static shot. Lea says: « Bonjour Léa ! ».']);
        self::assertArrayHasKey('speechReview', $swapped);
        self::assertStringContainsString('ne valide ni l’attribution', $swapped['speechReview']);
    }

    public function testNoSpeechAndUnquotedSpeechHaveDifferentOutcomes(): void
    {
        $prompt = 'A static shot of a cat sitting quietly by a window.';
        foreach (['Un chat sans dialogue.', 'Un chat, aucune parole.', 'Ne pas utiliser de texte ni de dialogue.'] as $idea) {
            self::assertArrayNotHasKey('speechReview', $this->check($idea, [$prompt]));
        }
        $warnings = $this->check('Léa parle avec Hugo en français.', [$prompt]);
        self::assertArrayHasKey('unparsedSpeech', $warnings);
    }

    public function testInventedSpeechIsReportedEvenIfItsTechnicalDirectionsAreEnglish(): void
    {
        $warnings = $this->check('Léa salue Hugo sans dialogue.', ['A static shot. Lea says: "Hello Hugo!".']);
        self::assertArrayHasKey('inventedSpeech', $warnings);
    }

    public function testRequestedEnglishSpeechDoesNotMakeFrenchNarrationEnglish(): void
    {
        $idea = 'Léa dit « The cat moves into the room with his ball. » en anglais.';
        $proposal = ['summary' => $idea, 'shots' => [['description' => $idea, 'prompt' => 'A static camera frames Lea. She says: « The cat moves into the room with his ball. ».', 'seconds' => 5]]];
        self::assertArrayNotHasKey('story', (new StoryTextChecks())->review($idea, [], $proposal));
    }

    /** @return iterable<string,array{string,string,bool}> */
    public static function actionCases(): iterable
    {
        yield 'simultaneous incompatible states' => ['Milo marche.', 'The cat remains completely motionless while walking across the room.', true];
        yield 'a sequence is not contradictory' => ['Milo marche puis s’arrête.', 'The cat walks across the room and then remains completely motionless.', false];
        yield 'two actors are not the same subject' => ['Milo regarde Hugo.', 'The cat remains completely motionless while Hugo walks across the room.', false];
        yield 'requested fantasy is not silently rejected' => ['Un paradoxe magique : Milo marche sans bouger.', 'The cat remains completely motionless while walking across the room.', false];
        yield 'negated fantasy does not disable the check' => ['Milo marche, sans effet magique.', 'The cat remains completely motionless while walking across the room.', true];
        yield 'speech about a dream is not a visual fantasy' => ['Léa dit « Un rêve magique ! ».', 'The cat remains completely motionless while walking across the room.', true];
        yield 'camera stillness is allowed' => ['Milo marche.', 'The camera stays completely still while the cat walks across the room.', false];
    }

    #[DataProvider('actionCases')]
    public function testOnlyTargetedApparentActionContradictionsAreFlagged(string $idea, string $prompt, bool $warn): void
    {
        self::assertSame($warn, isset($this->check($idea, [$prompt])['actions']));
    }

    public function testRequestedOutfitsAreComparedPerNamedCharacterAcrossLanguages(): void
    {
        $context = ['characters' => 'Léa porte une veste bleue, Hugo porte une chemise blanche.'];
        $correct = $this->check('Léa salue Hugo.', ['Lea in a blue jacket smiles. Hugo in a white shirt waves.'], $context);
        self::assertArrayNotHasKey('namedOutfit', $correct);
        $changed = $this->check('Léa salue Hugo.', ['Lea in a white shirt smiles. Hugo in a blue jacket waves.'], $context);
        self::assertArrayHasKey('namedOutfit', $changed);
    }

    public function testBothWritingStagesPreserveRequestedWordsAndDoNotForbidDialogue(): void
    {
        $calls = 0;
        $line = 'Non, je n’ai rien oublié !';
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls, $line): MockResponse {
            ++$calls;
            self::assertSame('http://localhost:11434/api/chat', $url);
            $request = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
            $instructions = $request['messages'][0]['content'];
            self::assertStringNotContainsString('ni dialogue', $instructions);
            self::assertStringNotContainsString('subtitles or dialogue', $instructions);
            $input = json_decode($request['messages'][1]['content'], true, 32, JSON_THROW_ON_ERROR);
            $brief = 1 === $calls ? $input : $input['brief'];
            self::assertSame([$line], $brief['requested_lines']);
            $result = 1 === $calls
                ? ['title' => 'Une rencontre', 'summary' => 'Léa répond à Hugo.', 'identity' => 'Léa.', 'beats' => ['Léa dit « '.$line.' ».']]
                : ['identity' => 'Lea', 'shots' => [['seconds' => 5, 'prompt' => 'A static camera frames Lea. She says in French: « '.$line.' ».']]];

            return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($result, JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR));
        });
        $proposal = (new LocalStoryWriter($http, true, 'http://localhost:11434', 'synthetic'))->propose('Léa dit en français « '.$line.' ».', [5], 'single', 'auto');
        self::assertSame(2, $calls);
        self::assertStringContainsString($line, $proposal['shots'][0]['description']);
        self::assertStringContainsString($line, $proposal['shots'][0]['prompt']);
        self::assertStringContainsString('locuteur', implode(' ', $proposal['warnings']));
        self::assertStringNotContainsString('consignes en français', implode(' ', $proposal['warnings']));
    }
}
