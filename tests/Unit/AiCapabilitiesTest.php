<?php

namespace App\Tests\Unit;

use App\Service\AiCatalog;
use PHPUnit\Framework\TestCase;

final class AiCapabilitiesTest extends TestCase
{
    public function testRolesAreDistinctAndRetirementNeverRebindsAnOldEndpoint(): void
    {
        $catalog = new AiCatalog();
        self::assertSame('text', $catalog->referenceMode('vidu-q1'));
        self::assertSame('subject', $catalog->referenceMode('vidu-q1-subject'));
        self::assertSame('image', $catalog->referenceMode('vidu-q1-image'));
        $catalog->assertAvailable('pika-22', new \DateTimeImmutable('2026-10-28T23:59:00Z'));
        self::assertSame('fal-ai/pika/v2.2/text-to-video', $catalog->model('pika-22')['endpoint']);
        $this->expectException(\RuntimeException::class);
        $catalog->assertAvailable('pika-22', new \DateTimeImmutable('2026-10-29T00:00:00Z'));
    }
}
