<?php

namespace App\Tests\Unit;

use App\Service\CaptionTiming;
use App\Service\EditTimeline;
use App\Service\EditTypography;
use App\Service\VideoLibraryException;
use PHPUnit\Framework\TestCase;

final class EditTimelineTest extends TestCase
{
    public function testLegacyRecipesRemainContinuousAndFreePositionsIncludeGaps(): void
    {
        $legacy = [['id' => 1, 'start' => 0, 'end' => 2], ['id' => 2, 'start' => 1, 'end' => 4]];
        self::assertEquals(5, EditTimeline::duration($legacy));
        $free = [['id' => 1, 'start' => 0, 'end' => 2, 'at' => 1, 'rate' => 2], ['id' => 2, 'start' => 1, 'end' => 4, 'at' => 4, 'rate' => 1]];
        self::assertEquals(7, EditTimeline::duration($free));
        $segments = EditTimeline::segments($free);
        self::assertCount(4, $segments);
        self::assertEquals(1, $segments[0]['gap']);
        self::assertEquals(2, $segments[2]['gap']);
        $captions = (new CaptionTiming())->remap([['start' => 0.5, 'end' => 1.5, 'text' => 'Paroles']], [$legacy[0]], [$free[0]]);
        self::assertEquals(1.25, $captions[0]['start']);
        self::assertEquals(1.75, $captions[0]['end']);
    }

    public function testAdjacentCutsCoalesceOnlyWhenTheirPlaybackIsIdentical(): void
    {
        $clips = [['id' => 1, 'start' => 0, 'end' => 0.4, 'at' => 0], ['id' => 1, 'start' => 0.4, 'end' => 1, 'at' => 0.4]];
        $segments = EditTimeline::segments($clips);
        self::assertCount(1, $segments);
        self::assertEquals(1, $segments[0]['end']);
        $clips[1]['look'] = ['filter' => 'bw'];
        self::assertCount(2, EditTimeline::segments($clips));
        $clips[1]['look'] = ['fadeIn' => 0.2];
        self::assertCount(2, EditTimeline::segments($clips));
        $clips[1]['look'] = [];
        $clips[1]['at'] = 0.5;
        self::assertCount(3, EditTimeline::segments($clips));
    }

    public function testTypographyAcceptsCustomColorButRejectsUntrustedFontInstructions(): void
    {
        $style = EditTypography::validate(['font' => 'serif', 'color' => '#12AB34', 'box' => '1', 'align' => 'left']);
        self::assertSame('#12ab34', $style['color']);
        self::assertTrue($style['box']);
        $this->expectException(VideoLibraryException::class);
        EditTypography::validate(['font' => 'sans}\\pos(0,0)']);
    }
}
