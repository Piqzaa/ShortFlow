<?php

namespace App\Tests\Unit;

use App\Service\AiCatalog;
use PHPUnit\Framework\TestCase;

final class AiCatalogGroupsTest extends TestCase
{
    public function testEveryDurableExecutionChoiceRemainsInOneProviderGroup(): void
    {
        $groups = (new AiCatalog())->groups();
        $ids = [];
        foreach ($groups as $group) {
            $providers = $endpoints = [];
            foreach ($group['variants'] as $id) {
                $ids[] = $id;
                $providers[] = AiCatalog::MODELS[$id]['provider'];
                $endpoints[] = AiCatalog::MODELS[$id]['endpoint'];
            }
            self::assertCount(1, array_unique($providers));
            self::assertCount(1, array_unique($endpoints));
        }
        self::assertCount(count(AiCatalog::MODELS), array_unique($ids));
        self::assertContains('pika-22-1080', $groups['pika-22']['variants']);
        self::assertContains('ltx-23-fast-4k', $groups['ltx-23-fast-720']['variants']);
        self::assertContains('ltx-25-fast-4k', $groups['ltx-25-fast-720']['variants']);
    }
}
