<?php

namespace App\Tests\Unit;

use App\Service\AiCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AiPriceFreshnessTest extends TestCase
{
    public function testFourteenDayBoundaryStillExpiresImmediatelyAfterIt(): void
    {
        $clock = new MockClock('2026-11-02T00:00:00Z');
        $catalog = new AiCatalog(priceClock: $clock);
        self::assertFalse($catalog->priceExpired('2026-10-19'));
        $clock->sleep(1);
        self::assertTrue($catalog->priceExpired('2026-10-19'));
        self::assertFalse($catalog->priceExpired('2026-10-20'));
        self::assertTrue($catalog->priceExpired('2000-01-01'));
    }

    public function testPriceFixtureClockCannotBringRetiredModelsBack(): void
    {
        $catalog = new AiCatalog(new MockClock('2026-11-02T12:00:00Z'), new MockClock('2026-10-30T12:00:00Z'));
        self::assertFalse($catalog->priceExpired(AiCatalog::CHECKED));
        $this->expectException(\RuntimeException::class);
        $catalog->assertAvailable('pika-22');
    }

    public function testDateOnlyPriceUsesUtcEvenWhenPhpUsesParis(): void
    {
        $previous = date_default_timezone_get();
        try {
            date_default_timezone_set('Europe/Paris');
            $clock = new MockClock('2026-11-02T00:00:00Z');
            $catalog = new AiCatalog($clock);
            self::assertFalse($catalog->priceExpired('2026-10-19'));
            $clock->sleep(1);
            self::assertTrue($catalog->priceExpired('2026-10-19'));
        } finally {
            date_default_timezone_set($previous);
        }
    }
}
