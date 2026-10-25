<?php

namespace App\Tests\Unit;

use App\Service\NativeVideoPreview;
use PHPUnit\Framework\TestCase;

final class NativeVideoPreviewTest extends TestCase
{
    private function video(): string
    {
        return 'data:video/mp4;base64,'.base64_encode("\x00\x00\x00\x18ftypisomsynthetic-contract");
    }

    public function testNativeMultiShotIsOneCallWithExplicitCutsAndNoConflictingPrompt(): void
    {
        $preview = (new NativeVideoPreview())->kling([['prompt' => 'Milo opens the door.', 'seconds' => 5], ['prompt' => 'Milo looks outside.', 'seconds' => 5]], '9:16');
        self::assertSame(1, $preview['calls']);
        self::assertFalse($preview['continuous']);
        self::assertSame(168, $preview['cents']);
        self::assertArrayNotHasKey('prompt', $preview['input']);
        self::assertSame('10', $preview['input']['duration']);
        self::assertCount(2, $preview['input']['multi_prompt']);
    }

    public function testExtensionReceivesVideoAndBillsContextPlusNewDuration(): void
    {
        $preview = (new NativeVideoPreview())->extension($this->video(), 10, 24, 1080, 1920, 5, 3, 'Continue walking.');
        self::assertSame(80, $preview['cents']);
        self::assertSame(8.0, $preview['billedSeconds']);
        self::assertSame('ltx-2-3-pro', $preview['input']['model']);
        self::assertSame($this->video(), $preview['input']['video_uri']);
        self::assertArrayNotHasKey('image_uri', $preview['input']);
        self::assertSame('9:16', $preview['outputRatio']);
    }

    public function testHighFpsCannotSilentlyReduceThePaidContext(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new NativeVideoPreview())->extension($this->video(), 10, 50, 1920, 1080, 6, 5, 'Continue.');
    }

    public function testLastFrameCannotMasqueradeAsNativeVideoExtension(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new NativeVideoPreview())->extension('data:image/jpeg;base64,'.base64_encode('synthetic'), 10, 24, 1920, 1080, 5, 3, 'Continue.');
    }

    public function testSquareVideoIsRejectedInsteadOfSilentlyCropped(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new NativeVideoPreview())->extension($this->video(), 10, 24, 1080, 1080, 5, 3, 'Continue.');
    }
}
