<?php

namespace App\Tests\Unit;

use App\Service\PublicationException;
use App\Service\PublicationTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class PublicationTimeTest extends TestCase
{
    public function testLocalTimesResolveToUtcWithoutServerTimezoneAssumptions(): void
    {
        $time = new PublicationTime(new MockClock('2026-10-18T08:00:00+00:00'));
        self::assertSame('2026-10-18T09:00:00+00:00', $time->resolve('2026-10-18T11:00', 'Europe/Paris')->format('c'));
        self::assertSame('2026-10-18T15:00:00+00:00', $time->resolve('2026-10-18T11:00', 'America/New_York')->format('c'));
        self::assertSame('2026-10-18T11:00:00+00:00', $time->resolve('2026-10-18T11:00', 'UTC')->format('c'));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidTimes(): iterable
    {
        yield 'spring gap' => ['2026-03-29T02:30', 'Europe/Paris'];
        yield 'autumn ambiguity' => ['2026-10-25T02:30', 'Europe/Paris'];
        yield 'invalid calendar date' => ['2026-02-30T11:00', 'Europe/Paris'];
        yield 'invalid zone' => ['2026-10-18T11:00', 'Mars/Olympus'];
        yield 'offset without IANA zone' => ['2026-10-18T11:00', '+02:00'];
    }

    #[DataProvider('invalidTimes')]
    public function testInvalidOrAmbiguousTimesAreRejected(string $local, string $zone): void
    {
        $this->expectException(PublicationException::class);
        (new PublicationTime(new MockClock()))->resolve($local, $zone);
    }

    public function testSchedulingAndLateStartBoundaries(): void
    {
        $clock = new MockClock('2026-10-18T08:00:00+00:00');
        $time = new PublicationTime($clock);
        $time->validateFuture($clock->now()->modify('+120 seconds'));
        $time->validateFuture($clock->now()->modify('+90 days'));
        foreach (['+119 seconds', '+90 days +1 second', '-1 second'] as $offset) {
            try {
                $time->validateFuture($clock->now()->modify($offset));
                self::fail('The schedule boundary must be enforced.');
            } catch (PublicationException $error) {
                self::assertStringContainsString('90 jours', $error->getMessage());
            }
        }
        $due = $clock->now();
        $clock->sleep(120);
        $time->assertOnTime($due);
        $clock->sleep(1);
        $this->expectException(PublicationException::class);
        $time->assertOnTime($due);
    }
}
