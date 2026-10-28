<?php

namespace App\Tests\Unit;

use App\Service\LocalStoryWriter;
use App\Service\PublicationException;
use App\Service\PublicationTexts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class PublicationContextTest extends TestCase
{
    /** @return iterable<string,array{array<string,mixed>}> */
    public static function missingContexts(): iterable
    {
        yield 'empty source' => [[]];
        yield 'title alone' => [['title' => 'Vacances 2026']];
        yield 'detailed title alone' => [['title' => 'Un chat blanc ouvre une boîte rouge sur la table']];
        yield 'metadata is not content' => [['title' => 'Vacances 2026', 'videoTitle' => 'Prise finale', 'video' => 42, 'episode' => 10]];
        yield 'empty descriptions' => [['description' => '  ', 'scenes' => ['', ' ']]];
        yield 'repeated title' => [['title' => 'Vacances 2026', 'description' => ' VACANCES — 2026 ! ', 'scenes' => ['Vacances 2026']]];
        yield 'repeated video title' => [['videoTitle' => 'Version finale', 'description' => 'Version finale']];
        yield 'wrong types' => [['description' => ['Un chat marche.'], 'scenes' => [null, 42, ['prompt' => 'Un chat marche.']]]];
        yield 'punctuation' => [['description' => '!? #...', 'scenes' => ['...']]];
        yield 'numbers only' => [['description' => '2026 2027']];
        yield 'one isolated word' => [['description' => 'Voyage']];
    }

    #[DataProvider('missingContexts')]
    public function testMissingContextRequestsDetailsWithoutInferenceOrRewriting(array $source): void
    {
        $before = $source;
        $http = new MockHttpClient();
        try {
            (new LocalStoryWriter($http, true, 'http://localhost:11434', 'synthetic'))->publication($source, [], ['youtube', 'instagram', 'tiktok']);
            self::fail('A title or metadata cannot establish factual video context.');
        } catch (PublicationException $error) {
            self::assertSame('context', $error->reason);
            self::assertStringContainsString('Décris ce que montre cette version', $error->getMessage());
            self::assertStringContainsString('ne voit pas la vidéo', $error->getMessage());
            self::assertStringContainsString('Tes textes restent conservés', $error->getMessage());
            self::assertNull($error->getPrevious());
        }
        self::assertSame(0, $http->getRequestsCount());
        self::assertSame($before, $source);
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function suppliedContexts(): iterable
    {
        yield 'human facts' => [['description' => 'Un chat ouvre une boîte.']];
        yield 'short description' => [['description' => 'Mug bleu']];
        yield 'existing scenes' => [['title' => 'Le chat', 'scenes' => ['', 'A white cat opens a red box.']]];
        yield 'one useful scene among metadata' => [['title' => 'Vacances 2026', 'description' => 'Vacances 2026', 'scenes' => [42, 'La mer au coucher du soleil.']]];
        yield 'details complete a title' => [['title' => 'Mug bleu', 'description' => 'Mug bleu sur la table.']];
    }

    #[DataProvider('suppliedContexts')]
    public function testExplicitDescriptionsOrScenesMayBeUsedWithoutClaimingSemanticValidation(array $source): void
    {
        $before = $source;
        (new PublicationTexts())->requireContext($source);
        self::assertSame($before, $source);
    }
}
