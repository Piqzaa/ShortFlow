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
            $process = new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-protocol_whitelist', 'file,pipe', '-threads', '1', '-ss', sprintf('%.3F', max(0, $video->getDuration() - 0.15)), '-i', $this->videos->path($video->getStorageName()), '-frames:v', '1', '-map_metadata', '-1', '-vf', 'scale=1080:1920:force_original_aspect_ratio=decrease', '-c:v', 'mjpeg', '-threads', '1', '-f', 'image2', $file]);
            $process->setTimeout(20);
            $process->mustRun();

            return $this->references->store($video->getOwner(), new UploadedFile($file, 'raccord.jpg', 'image/jpeg', null, true));
        } catch (\Throwable) {
            throw new \RuntimeException('Le raccord visuel n’a pas pu être préparé. Vérifie le clip et la place disponible pour les images ; aucun plan suivant n’a été demandé.');
        } finally {
            @unlink($file);
        }
    }
}
