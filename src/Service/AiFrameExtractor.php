<?php

namespace App\Service;

use App\Entity\VideoAsset;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class AiFrameExtractor
{
    public function __construct(private PrivateVideoStorage $videos, private AiReferenceStorage $references)
    {
    }

    public function lastFrame(VideoAsset $video): string
    {
        $file = tempnam(sys_get_temp_dir(), 'vydflow-frame-');
        if (false === $file) {
            throw new \RuntimeException('Le raccord visuel est indisponible.');
        }
        try {
            // Decode the tail through EOF and overwrite the image for each frame:
            // the resulting file is the final decoded frame, not a time estimate.
            $deadline = microtime(true) + 20;
            foreach ([true, false] as $tail) {
                $process = new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-protocol_whitelist', 'file,pipe', '-threads', '1', ...($tail ? ['-sseof', '-1'] : []), '-i', $this->videos->path($video->getStorageName()), '-map', '0:v:0', '-an', '-map_metadata', '-1', '-vf', 'scale=1080:1920:force_original_aspect_ratio=decrease', '-c:v', 'mjpeg', '-threads', '1', '-f', 'image2', '-update', '1', $file]);
                $process->setTimeout(max(0.01, $deadline - microtime(true)));
                $process->mustRun();
                clearstatcache(true, $file);
                if (filesize($file) > 0) {
                    break;
                }
                // A longer audio track can put container EOF after all video
                // frames. Retry without seeking, within the same time budget.
            }

            return $this->references->store($video->getOwner(), new UploadedFile($file, 'raccord.jpg', 'image/jpeg', null, true));
        } catch (\Throwable) {
            throw new \RuntimeException('Le raccord visuel n’a pas pu être préparé. Vérifie le clip et la place disponible pour les images ; aucun plan suivant n’a été demandé.');
        } finally {
            @unlink($file);
        }
    }
}
