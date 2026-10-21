<?php

namespace App\Tests\Unit;

use App\Service\EditCaptions;
use App\Service\EditTextLayers;
use App\Service\VideoLibraryException;
use PHPUnit\Framework\TestCase;

final class EditTextLayersTest extends TestCase
{
    private function text(): array
    {
        return ['id' => 'title-1', 'start' => 0, 'end' => 2, 'text' => 'Une accroche {\\pos(0,0)}', 'x' => 0.5, 'y' => 0.15, 'size' => 4, 'color' => '#f9a8d4'];
    }

    public function testOverlappingTextAndCaptionPositionsAreRenderedSafely(): void
    {
        $rows = (new EditTextLayers())->validate([$this->text(), array_replace($this->text(), ['id' => 'title-2', 'y' => 0.85])], 3);
        self::assertCount(2, $rows);
        $ass = (new EditCaptions())->ass($rows, 1080, 1920, 'clean');
        self::assertStringContainsString('\\pos(540,288)', $ass);
        self::assertStringContainsString('\\pos(540,1632)', $ass);
        self::assertStringContainsString('D4A8F9', $ass);
        self::assertStringNotContainsString('{\\pos(0,0)}', $ass);
    }

    public function testMalformedGeometryAndUnboundedTextAreRejected(): void
    {
        foreach ([['x' => -1], ['y' => INF], ['end' => 6], ['size' => 200], ['color' => 'url(x)'], ['text' => str_repeat('x', 161)], ['id' => '<bad>']] as $invalid) {
            try {
                (new EditTextLayers())->validate([array_replace($this->text(), $invalid)], 3);
                self::fail('Invalid text must be rejected.');
            } catch (VideoLibraryException) {
                self::assertTrue(true);
            }
        }
    }
}
