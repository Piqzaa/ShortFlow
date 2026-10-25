<?php

namespace App\Tests\Unit;

use App\Service\AiCatalog;
use App\Service\AiShotPrompts;
use PHPUnit\Framework\TestCase;

final class AiShotPromptsTest extends TestCase
{
    public function testSubjectIdentityContextFitsWithoutTruncatingTheScene(): void
    {
        $scene = str_repeat('Milo avance. ', 65);
        $prompts = new AiShotPrompts(new AiCatalog());
        $prompt = $prompts->build('vidu-q1-subject', 5, $scene, str_repeat('Identité orange. ', 400), 'independent');
        self::assertLessThanOrEqual(1500, mb_strlen($prompt));
        self::assertStringContainsString(trim($scene), $prompt);
        self::assertStringContainsString('identity only', $prompt);
        self::assertFalse($prompts->writingContext('vidu-q1-subject')['visualInput']);
    }

    public function testByteLimitedPromptsKeepUnicodeIntact(): void
    {
        $prompt = (new AiShotPrompts(new AiCatalog()))->build('pixverse-6', 5, str_repeat('é', 600), str_repeat('🐱', 600), 'single');
        self::assertLessThanOrEqual(2048, strlen($prompt));
        self::assertTrue(mb_check_encoding($prompt, 'UTF-8'));
        self::assertStringContainsString('No cuts', $prompt);
    }

    public function testLinkedImagePromptsNameSubjectsWithoutPromisingNativeVideoContinuity(): void
    {
        $prompt = (new AiShotPrompts(new AiCatalog()))->build('kling-3-image', 5, 'Milo marche.', '', 'linked', 2);
        self::assertStringContainsString('supplied first frame', $prompt);
        self::assertStringContainsString('@Element1 and @Element2', $prompt);
        self::assertStringNotContainsString('source video', $prompt);
    }

    public function testOversizedSceneIsRejectedInsteadOfSilentlyCut(): void
    {
        $this->expectException(\RuntimeException::class);
        (new AiShotPrompts(new AiCatalog()))->build('vidu-q1-subject', 5, str_repeat('a', 1500), '', 'independent');
    }
}
