<?php

namespace App\Tests\Unit;

use App\Service\EditCaptions;
use App\Service\VideoLibraryException;
use PHPUnit\Framework\TestCase;

final class EditCaptionsTest extends TestCase
{
    public function testSubtitleFilesHaveRealTimingAndNoAssControlInjection(): void
    {
        $captions = new EditCaptions();
        $rows = $captions->validate([['start' => 0.25, 'end' => 1.5, 'text' => 'Bonjour {\\pos(0,0)} à tous']], 2);
        self::assertStringContainsString('00:00:00,250 --> 00:00:01,500', $captions->srt($rows));
        $ass = $captions->ass($rows, 1080, 1920, 'social');
        self::assertStringContainsString('PlayResX: 1080', $ass);
        self::assertStringNotContainsString('\\pos(0,0)', $ass);
        self::assertStringContainsString('\\pos(540,1632)', $ass);
        self::assertStringContainsString('Bonjour （／pos(0,0)）', $ass);
        self::assertStringContainsString('à tous', $ass);
    }

    public function testBundledHeadlineFontsAndCustomColoursAreWrittenToAss(): void
    {
        $captions = new EditCaptions();
        foreach (['anton' => 'Anton', 'bebas' => 'Bebas Neue', 'oswald' => 'Oswald'] as $font => $family) {
            $ass = $captions->ass([['start' => 0, 'end' => 1, 'text' => 'Bonjour', 'font' => $font, 'color' => '#123456']], 1080, 1920, 'social');
            self::assertStringContainsString($family, $ass);
            self::assertStringContainsString('563412', $ass);
        }
    }

    public function testOverlappingOrOutOfVideoCaptionsAreRejected(): void
    {
        $captions = new EditCaptions();
        foreach ([[['start' => 0, 'end' => 1.5, 'text' => 'A'], ['start' => 1, 'end' => 2, 'text' => 'B']], [['start' => 0, 'end' => 3, 'text' => 'A']], [['start' => 0, 'end' => 1, 'text' => str_repeat('a', 161)]]] as $rows) {
            try {
                $captions->validate($rows, 2);
                self::fail('Invalid captions must be rejected.');
            } catch (VideoLibraryException) {
                self::assertTrue(true);
            }
        }
    }
}
