<?php

namespace App\Service;

use App\Repository\VideoAssetRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PrivateVideoStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/storage/videos/%kernel.environment%')] private string $directory)
    {
    }

    public function directory(): string
    {
        if ((!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory)) || is_link($this->directory)) {
            throw new VideoLibraryException('Le stockage est indisponible. Réessaie plus tard.');
        }

        return $this->directory;
    }

    public function path(string $name): string
    {
        if (!preg_match('/^[a-f0-9]{48}\.(mp4|webm)$/D', $name)) {
            throw new VideoLibraryException('Vidéo indisponible.');
        }
        $path = $this->directory().'/'.$name;
        if (is_link($path)) {
            throw new VideoLibraryException('Vidéo indisponible.');
        }

        return $path;
    }

    public function store(UploadedFile $file, string $name): void
    {
        $path = $this->path($name);
        try {
            $file->move($this->directory(), $name);
            @chmod($path, 0640);
            clearstatcache(true, $path);
        } catch (\Throwable) {
            throw new VideoLibraryException('L’import n’a pas pu être enregistré. Réessaie plus tard.');
        }
    }

    public function discard(string $name): void
    {
        $path = $this->path($name);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function stageDeletion(string $name): ?string
    {
        $path = $this->path($name);
        if (!is_file($path)) {
            return null;
        }
        $trash = $path.'.deleted-'.bin2hex(random_bytes(8));
        if (!@rename($path, $trash)) {
            throw new VideoLibraryException('La suppression est indisponible. Réessaie plus tard.');
        }

        @touch($trash);

        return $trash;
    }

    public function restore(string $trash, string $name): void
    {
        @rename($trash, $this->path($name));
    }

    public function purge(string $trash): void
    {
        @unlink($trash);
    }

    public function cleanupTrash(VideoAssetRepository $videos): int
    {
        $count = 0;
        foreach (glob($this->directory().'/*.deleted-*') ?: [] as $path) {
            $name = explode('.deleted-', basename($path))[0];
            if (!is_link($path) && preg_match('/^[a-f0-9]{48}\.(mp4|webm)\.deleted-[a-f0-9]{16}$/D', basename($path)) && filemtime($path) < time() - 86400 && null === $videos->findOneBy(['storageName' => $name]) && @unlink($path)) {
                ++$count;
            }
        }

        return $count;
    }
}
