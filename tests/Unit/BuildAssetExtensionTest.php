<?php

namespace App\Tests\Unit;

use App\Twig\BuildAssetExtension;
use PHPUnit\Framework\TestCase;

final class BuildAssetExtensionTest extends TestCase
{
    public function testChangingTheBuildChangesItsUrlWithoutDependingOnFileTimestamps(): void
    {
        $root = sys_get_temp_dir().'/vydflow-build-'.bin2hex(random_bytes(8));
        mkdir($root.'/public/build', 0700, true);
        $file = $root.'/public/build/app.js';
        try {
            $assets = new BuildAssetExtension($root);
            self::assertSame('/build/app.css', $assets->url('app.css'));
            file_put_contents($file, 'old build');
            $before = $assets->url('app.js');
            self::assertSame($before, $assets->url('app.js'));
            file_put_contents($file, 'new build');
            self::assertNotSame($before, $assets->url('app.js'));
            self::assertMatchesRegularExpression('~^/build/app.js\?v=[a-f0-9]{16}$~', $assets->url('app.js'));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
            rmdir($root.'/public/build');
            rmdir($root.'/public');
            rmdir($root);
        }
    }

    public function testOnlyPublicEntryAssetsCanBeRequested(): void
    {
        $assets = new BuildAssetExtension('/unused');
        $this->expectException(\InvalidArgumentException::class);
        $assets->url('../../.env.local');
    }
}
