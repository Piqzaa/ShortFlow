<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\PrivateVideoStorage;
use App\Service\PublicationException;
use App\Service\PublicationVideoCheck;
use App\Service\VideoProbe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class PublicationVideoCheckTest extends TestCase
{
    /** @param array<string, mixed> $metadata */
    private function video(array $metadata = [], int $width = 1080, int $height = 1920, float $duration = 5): VideoAsset
    {
        $video = new VideoAsset(new User('probe@example.test'), 'Clip', str_repeat('a', 48).'.mp4', 'video/mp4', 1000, $duration, $width, $height, 'h264');
        $video->recordTechnicalMetadata($metadata + ['fps' => 30, 'videoBitrate' => 3000000, 'pixelRatio' => 1, 'rotation' => 0, 'audioCodec' => null]);

        return $video;
    }

    private function check(): PublicationVideoCheck
    {
        return new PublicationVideoCheck(new VideoProbe(), new PrivateVideoStorage(sys_get_temp_dir().'/vydflow-probe-unit'));
    }

    public function testPortraitSquareRotationAndNonSquarePixelsUseDisplayedDimensions(): void
    {
        $check = $this->check();
        foreach ([$this->video(), $this->video([], 1080, 1080), $this->video(['rotation' => -90], 1920, 1080), $this->video(['pixelRatio' => 0.5], 1920, 1080)] as $video) {
            $check->validate($video, 'youtube');
        }
        $this->expectException(PublicationException::class);
        $this->expectExceptionMessage('horizontale');
        $check->validate($this->video(['rotation' => 90]), 'youtube');
    }

    /** @return iterable<string, array{string, array<string, mixed>, string}> */
    public static function incompatible(): iterable
    {
        yield 'slow Instagram' => ['instagram', ['fps' => 22.99], '23 à 60'];
        yield 'fast TikTok' => ['tiktok', ['fps' => 60.01], '23 à 60'];
        yield 'missing cadence' => ['tiktok', ['fps' => null], 'illisible'];
        yield 'excessive bitrate' => ['instagram', ['videoBitrate' => 25000001], '25 Mbit/s'];
        yield 'unknown bitrate' => ['instagram', ['videoBitrate' => null], 'débit vidéo'];
        yield 'interlaced' => ['instagram', ['interlaced' => true], 'progressive'];
        yield 'surround sound' => ['instagram', ['audioCodec' => 'aac', 'audioSampleRate' => 48000, 'audioChannels' => 6], 'mono ou stéréo'];
        yield 'high sample rate' => ['instagram', ['audioCodec' => 'aac', 'audioSampleRate' => 96000, 'audioChannels' => 2], '48 kHz'];
        yield 'unexpected audio' => ['instagram', ['audioCodec' => 'opus', 'audioSampleRate' => 48000, 'audioChannels' => 2], 'AAC'];
        yield 'oblique display' => ['youtube', ['rotation' => 45], 'Rotation'];
    }

    /** @param array<string, mixed> $metadata */
    #[DataProvider('incompatible')]
    public function testIncompatibleVideoHasAnActionableMessage(string $network, array $metadata, string $message): void
    {
        $this->expectException(PublicationException::class);
        $this->expectExceptionMessage($message);
        $this->check()->validate($this->video($metadata), $network);
    }

    public function testExactLimitsSilentVideoAndCreatorSpecificDuration(): void
    {
        $this->check()->validate($this->video(['fps' => 23, 'videoBitrate' => 25000000], 1920, 1920, 180), 'instagram');
        $this->check()->validate($this->video(['fps' => 60], 360, 640, 3), 'tiktok', 3);
        $this->expectException(PublicationException::class);
        $this->expectExceptionMessage('3 à 4 secondes');
        $this->check()->validate($this->video(), 'tiktok', 4);
    }

    public function testRealProbeExtractsCadenceAndOldVideoRemainsReadableWithoutDatabaseMutation(): void
    {
        $probe = new VideoProbe();
        $path = dirname(__DIR__).'/Fixtures/reels-valid.mp4';
        $info = $probe->inspect(new UploadedFile($path, 'source.mp4', null, null, true));
        self::assertSame(25.0, $info['technical']['fps']);
        self::assertGreaterThan(0, $info['technical']['videoBitrate']);
        self::assertNull($info['technical']['audioCodec']);
        $directory = sys_get_temp_dir().'/vydflow-legacy-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $name = str_repeat('b', 48).'.mp4';
        copy($path, $directory.'/'.$name);
        try {
            $video = new VideoAsset(new User('legacy@example.test'), 'Ancienne vidéo', $name, 'video/mp4', (int) filesize($path), 3, 96, 160, 'h264');
            (new PublicationVideoCheck($probe, new PrivateVideoStorage($directory)))->validate($video, 'instagram');
            self::assertNull($video->getTechnicalMetadata());
        } finally {
            unlink($directory.'/'.$name);
            rmdir($directory);
        }
    }

    public function testMissingOldVideoCannotBeSilentlyApproved(): void
    {
        $video = new VideoAsset(new User('missing@example.test'), 'Absente', str_repeat('f', 48).'.mp4', 'video/mp4', 1000, 5, 1080, 1920, 'h264');
        $this->expectException(PublicationException::class);
        $this->expectExceptionMessage('ne peut pas être vérifiée');
        $this->check()->validate($video, 'youtube');
    }

    public function testRealMp4RotationIsReadFromItsDisplayMatrix(): void
    {
        $path = sys_get_temp_dir().'/vydflow-rotation-'.bin2hex(random_bytes(8)).'.mp4';
        try {
            $process = new Process(['ffmpeg', '-v', 'error', '-i', dirname(__DIR__).'/Fixtures/reels-valid.mp4', '-c', 'copy', '-metadata:s:v:0', 'rotate=90', $path]);
            $process->setTimeout(10)->mustRun();
            $info = (new VideoProbe())->inspect(new UploadedFile($path, 'rotated.mp4', null, null, true));
            self::assertSame(90.0, abs($info['technical']['rotation']));
            $this->expectException(PublicationException::class);
            $this->expectExceptionMessage('horizontale');
            $this->check()->validate($this->video($info['technical'], $info['width'], $info['height'], $info['duration']), 'youtube');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
