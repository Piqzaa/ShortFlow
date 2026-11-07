<?php

namespace App\Tests\Unit;

use App\Service\PublicationException;
use App\Service\PublicationTime;
use App\Service\SeriesCalendar;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class SeriesCalendarTest extends TestCase
{
    /** @param array<string,mixed> $overrides @return list<string> */
    private function dates(array $overrides = [], string $now = '2026-11-07T08:00:00Z'): array
    {
        $clock = new MockClock($now);
        $calendar = new SeriesCalendar($this->createMock(EntityManagerInterface::class), new PublicationTime($clock), $clock);

        return $calendar->dates(array_replace(['start' => '2026-11-09', 'end' => null, 'weekdays' => [1, 3], 'hour' => '18:00', 'timezone' => 'Europe/Paris', 'count' => 3], $overrides));
    }

    public function testDatesFollowWeekdaysAndInclusivePeriod(): void
    {
        self::assertSame(['2026-11-09T17:00:00+00:00', '2026-11-11T17:00:00+00:00', '2026-11-16T17:00:00+00:00'], $this->dates(['end' => '2026-11-16']));
        self::assertSame(['2026-11-09T17:00:00+00:00'], $this->dates(['end' => '2026-11-09', 'count' => 1]));
        self::assertSame($this->dates(), $this->dates(['weekdays' => [0 => 1, 2 => 3]]));
    }

    public function testWallHourSurvivesSpringChange(): void
    {
        self::assertSame(['2027-03-27T17:00:00+00:00', '2027-03-28T16:00:00+00:00'], $this->dates(['start' => '2027-03-27', 'weekdays' => [6, 7], 'count' => 2], '2027-03-26T08:00:00Z'));
        self::assertSame(['2027-03-29T00:30:00+00:00'], $this->dates(['start' => '2027-03-28', 'weekdays' => [1], 'hour' => '02:30', 'count' => 1], '2027-03-26T08:00:00Z'));
    }

    public function testMissingAndAmbiguousHoursAreRejectedRatherThanShifted(): void
    {
        foreach ([['2027-03-27', '2027-03-26T08:00:00Z'], ['2027-10-30', '2027-10-29T08:00:00Z']] as [$start, $now]) {
            try {
                $this->dates(['start' => $start, 'weekdays' => [7], 'hour' => '02:30', 'count' => 1], $now);
                self::fail('Never guess an offset on clock changes.');
            } catch (PublicationException $error) {
                self::assertStringContainsString('inexistante ou ambiguë', $error->getMessage());
            }
        }
    }

    public function testInvalidPeriodsAndRulesCannotProducePartialCalendar(): void
    {
        foreach ([['end' => '2026-11-10'], ['end' => '2026-11-08'], ['start' => '2026-02-30'], ['start' => '2026-11-06'], ['end' => '2027-12-01'], ['weekdays' => []], ['weekdays' => [8]], ['weekdays' => [1, 1]], ['hour' => '25:00'], ['timezone' => 'Invalid/Zone'], ['count' => 91], ['count' => 0]] as $input) {
            try {
                $this->dates($input);
                self::fail('Invalid input must not create a partial calendar.');
            } catch (PublicationException $error) {
                self::assertNotSame('', $error->getMessage());
            }
        }
    }
}
