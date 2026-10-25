<?php

namespace App\Tests\Unit;

use App\Service\AiCatalog;
use App\Service\SeriesAuthorization;
use PHPUnit\Framework\TestCase;

final class SeriesAuthorizationTest extends TestCase
{
    public function testContractAndIdentityAreBoundToTheVersionedAuthorization(): void
    {
        $authorization = new SeriesAuthorization(new AiCatalog());
        $recipe = ['model' => 'kling-25', 'ratio' => '9:16'];
        $accepted = $authorization->fingerprint($recipe, 'A white cat.', [5, 5], 70, 'old-key');
        self::assertSame($accepted, $authorization->fingerprint($recipe, 'A white cat.', [5, 5], 70, 'old-key'));
        self::assertNotSame($accepted, $authorization->fingerprint($recipe, 'A white cat.', [5, 5], 70, 'new-key'));
        $recipe['model'] = 'kling-25-image';
        self::assertNotSame($accepted, $authorization->fingerprint($recipe, 'A white cat.', [5, 5], 70, 'old-key'));
        self::assertNotSame(hash('sha256', serialize([$recipe, 'A white cat.', [5, 5], 70, '2026-10-20', 'old-key'])), $accepted);
    }
}
