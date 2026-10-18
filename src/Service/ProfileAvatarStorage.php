<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class ProfileAvatarStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/storage/avatars/%kernel.environment%')] private string $directory)
    {
    }

    public function path(string $name): string
    {
        if (!preg_match('/^[a-f0-9]{48}\.jpg$/D', $name) || is_link($this->directory)) {
            throw new \RuntimeException('Photo indisponible.');
        }
        $path = $this->directory.'/'.$name;
        if (is_link($path)) {
            throw new \RuntimeException('Photo indisponible.');
        }

        return $path;
    }

    public function store(UploadedFile $file): string
    {
        $size = @getimagesize($file->getPathname());
        if ($file->getSize() > 2097152 || !is_array($size) || $size[0] < 1 || $size[1] < 1 || $size[0] > 4096 || $size[1] > 4096 || !in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('Choisis une image JPG, PNG ou WebP valide, de 2 Mo et 4 096 pixels maximum par côté.');
        }
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('La photo ne peut pas être enregistrée pour le moment.');
        }
        $name = bin2hex(random_bytes(24)).'.jpg';
        $path = $this->path($name);
        try {
            $process = new Process(['ffmpeg', '-nostdin', '-v', 'error', '-n', '-max_alloc', '67108864', '-protocol_whitelist', 'file,pipe', '-threads', '1', '-i', $file->getPathname(), '-frames:v', '1', '-vf', 'scale=256:256:force_original_aspect_ratio=increase,crop=256:256', '-map_metadata', '-1', '-c:v', 'mjpeg', '-q:v', '3', '-threads', '1', '-update', '1', $path]);
            $process->setTimeout(15);
            $process->mustRun();
            if (!is_file($path)) {
                throw new \RuntimeException();
            }
            @chmod($path, 0640);

            return $name;
        } catch (\Throwable) {
            $this->remove($name);
            throw new \RuntimeException('Impossible de lire cette image. Essaie un autre JPG, PNG ou WebP.');
        }
    }

    public function remove(?string $name): void
    {
        if (null !== $name && is_file($this->path($name))) {
            @unlink($this->path($name));
        }
    }
}
