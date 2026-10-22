<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\VideoAsset;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class EditStillImages
{
    public function __construct(private VideoLibrary $library)
    {
    }

    public function solid(User $owner, string $color, float $seconds): VideoAsset
    {
        if (!is_finite($seconds) || $seconds < 1 || $seconds > 30 || !preg_match('/^#[a-fA-F0-9]{6}$/D', $color)) {
            throw new VideoLibraryException('Choisis une couleur de fond valide.');
        }
        $base = tempnam(sys_get_temp_dir(), 'vydflow-card-');
        $png = $base.'.png';
        try {
            $process = new Process(['ffmpeg', '-v', 'error', '-nostdin', '-y', '-f', 'lavfi', '-i', 'color=c='.str_replace('#', '0x', $color).':s=1080x1920', '-frames:v', '1', '-threads', '1', $png]);
            $process->setTimeout(10);
            $process->mustRun();

            return $this->import($owner, new UploadedFile($png, 'Fond uni.png', null, null, true), $seconds);
        } catch (VideoLibraryException $error) {
            throw $error;
        } catch (\Throwable) {
            throw new VideoLibraryException('Ce fond n’a pas pu être préparé. Réessaie.');
        } finally {
            @unlink($png);
            @unlink($base);
        }
    }

    public function import(User $owner, UploadedFile $file, float $seconds): VideoAsset
    {
        $mime = $file->getMimeType();
        $size = @getimagesize($file->getPathname());
        if (!$file->isValid() || $file->getSize() > 10 * 1024 * 1024 || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !$size || $size[0] < 16 || $size[1] < 16 || $size[0] * $size[1] > 40000000 || !is_finite($seconds) || $seconds < 1 || $seconds > 30) {
            throw new VideoLibraryException('Choisis une image JPEG, PNG ou WebP de 10 Mo et 40 mégapixels maximum, affichée entre 1 et 30 secondes.');
        }
        $base = tempnam(sys_get_temp_dir(), 'vydflow-still-');
        $output = $base.'.mp4';
        try {
            $codec = ['image/jpeg' => 'mjpeg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
            $process = new Process(['ffmpeg', '-v', 'error', '-nostdin', '-y', '-f', 'image2', '-loop', '1', '-vcodec', $codec, '-protocol_whitelist', 'file', '-i', $file->getPathname(), '-t', sprintf('%.3F', $seconds), '-vf', 'scale=1920:1920:force_original_aspect_ratio=decrease:force_divisible_by=2', '-an', '-c:v', 'libx264', '-threads', '2', '-preset', 'veryfast', '-crf', '20', '-r', '30', '-pix_fmt', 'yuv420p', '-map_metadata', '-1', '-movflags', '+faststart', $output]);
            $process->setTimeout(45);
            $process->disableOutput();
            $process->mustRun();
            $title = mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 100).' · image';

            return $this->library->import($owner, new UploadedFile($output, 'image.mp4', null, null, true), $title);
        } catch (VideoLibraryException $error) {
            throw $error;
        } catch (\Throwable) {
            throw new VideoLibraryException('Cette image n’a pas pu être préparée. Essaie un autre fichier.');
        } finally {
            @unlink($output);
            @unlink($base);
        }
    }
}
