<?php

namespace App\Tests\Unit;

use App\Service\CaptionTiming;
use PHPUnit\Framework\TestCase;

final class CaptionTimingTest extends TestCase
{
    public function testCaptionsFollowSourceOccurrencesAcrossEditing(): void
    {
        $cases = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/caption-timing.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($cases as $case) {
            self::assertEquals($case['expected'], (new CaptionTiming())->remap($case['rows'], $case['before'], $case['after']), $case['name']);
        }
    }
}
