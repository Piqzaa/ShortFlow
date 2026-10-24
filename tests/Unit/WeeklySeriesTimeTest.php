<?php

namespace App\Tests\Unit;

use App\Service\PublicationTime;
use App\Service\WeeklySeriesTime;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class WeeklySeriesTimeTest extends TestCase
{
    public function testWeeklyWallTimeSurvivesClockChangeAndSkipsAmbiguousOrMissingSlots(): void
    {
        $time = new WeeklySeriesTime(new PublicationTime(new MockClock()));
        self::assertSame('2026-10-27T17:00:00+00:00', $time->next(2, '18:00', 'Europe/Paris', new \DateTimeImmutable('2026-10-24T08:00:00Z'))->format(DATE_ATOM));
        self::assertSame('2026-11-01T01:30:00+00:00', $time->next(7, '02:30', 'Europe/Paris', new \DateTimeImmutable('2026-10-24T08:00:00Z'))->format(DATE_ATOM));
        self::assertSame('2026-04-05T00:30:00+00:00', $time->next(7, '02:30', 'Europe/Paris', new \DateTimeImmutable('2026-03-28T08:00:00Z'))->format(DATE_ATOM));
        self::assertSame('2026-11-03T17:00:00+00:00', $time->next(2, '18:00', 'Europe/Paris', new \DateTimeImmutable('2026-10-27T17:00:00Z'))->format(DATE_ATOM));
    }
}
