<?php

namespace App\Tests\Unit;

use App\Service\SeriesMontage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeriesMontageTest extends TestCase
{
    public function testDefaultsPreserveFullClipsAndAudioWithoutAddingText(): void
    {
        self::assertSame(['sourceVolume' => 100, 'intro' => false, 'fit' => 'contain'], SeriesMontage::normalize([]));
        self::assertSame(['sourceVolume' => 0, 'intro' => true, 'fit' => 'cover'], SeriesMontage::normalize(['sourceVolume' => 0, 'intro' => true, 'fit' => 'cover']));
    }

    public static function invalidSettings(): iterable
    {
        yield 'unknown operation' => [['musicUrl' => 'https://example.test/music.mp3']];
        yield 'negative volume' => [['sourceVolume' => -1]];
        yield 'excessive volume' => [['sourceVolume' => 101]];
        yield 'numeric string' => [['sourceVolume' => '40']];
        yield 'fractional volume' => [['sourceVolume' => 1.5]];
        yield 'boolean volume' => [['sourceVolume' => true]];
        yield 'string boolean' => [['intro' => 'true']];
        yield 'unknown framing' => [['fit' => 'stretch']];
        yield 'not an object' => ['cover'];
    }

    #[DataProvider('invalidSettings')]
    public function testUnsupportedSettingsAreRefused(mixed $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SeriesMontage::normalize($input);
    }
}
