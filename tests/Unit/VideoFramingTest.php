<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Entity\VideoAsset;
use PHPUnit\Framework\TestCase;

final class VideoFramingTest extends TestCase
{
    public function testHorizontalOutputCannotPassForVerticalEvenWhenTheFinalCanvasIsVertical(): void
    {
        $video = new VideoAsset(new User('framing@example.test'), 'Clip', str_repeat('a', 48).'.mp4', 'video/mp4', 100, 5, 1280, 720, 'h264');
        self::assertTrue($video->matchesRatio('16:9'));
        self::assertFalse($video->matchesRatio('9:16'));
        self::assertFalse($video->matchesRatio('1:1'));
        self::assertTrue($video->matchesRatio('auto'));
        $rounded = new VideoAsset(new User('rounded@example.test'), 'Clip', str_repeat('b', 48).'.mp4', 'video/mp4', 100, 5, 544, 960, 'h264');
        self::assertTrue($rounded->matchesRatio('9:16'));
        self::assertFalse($rounded->matchesRatio('16:9'));
    }
}
