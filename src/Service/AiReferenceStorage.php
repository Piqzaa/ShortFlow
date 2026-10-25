<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class AiReferenceStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/storage/ai-references/%kernel.environment%')] private string $directory)
    {
    }

    private function directory(User $owner): string
    {
        if (null === $owner->getId() || is_link($this->directory)) {
            throw new \RuntimeException('Image indisponible.');
        }
        $path = $this->directory.'/'.$owner->getId();
        if (is_link($path) || (!is_dir($path) && !@mkdir($path, 0750, true) && !is_dir($path))) {
            throw new \RuntimeException('Le stockage des images est indisponible.');
        }

        return $path;
    }

    public function path(User $owner, string $name): string
    {
        if (!preg_match('/^[a-f0-9]{48}\.jpg$/D', $name)) {
            throw new \RuntimeException('Image indisponible.');
        }
        $path = $this->directory($owner).'/'.$name;
        if (is_link($path) || !is_file($path)) {
            throw new \RuntimeException('Image indisponible. Importe à nouveau ta référence.');
        }

        return $path;
    }

    public function store(User $owner, UploadedFile $file): string
    {
        $size = $file->isValid() ? @getimagesize($file->getPathname()) : false;
        if ($file->getSize() > 10485760 || !is_array($size) || min($size[0], $size[1]) < 300 || max($size[0], $size[1]) > 4096 || $size[0] / $size[1] < 0.4 || $size[0] / $size[1] > 2.5 || !in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('Choisis un JPG, PNG ou WebP de 10 Mo maximum, de 300 à 4 096 pixels par côté, dans un format entre 2:5 et 5:2.');
        }
        $directory = $this->directory($owner);
        $used = 0;
        foreach (glob($directory.'/*.jpg') ?: [] as $existing) {
            $used += (int) filesize($existing);
        }
        if ($used + 2097152 > 104857600) {
            throw new \RuntimeException('La limite de 100 Mo d’images de référence est atteinte pour ce compte.');
        }
        $name = bin2hex(random_bytes(24)).'.jpg';
        $path = $directory.'/'.$name;
        try {
            $process = new Process(['ffmpeg', '-nostdin', '-v', 'error', '-n', '-max_alloc', '67108864', '-protocol_whitelist', 'file,pipe', '-threads', '1', '-i', $file->getPathname(), '-frames:v', '1', '-vf', "scale=w='min(2048,iw)':h='min(2048,ih)':force_original_aspect_ratio=decrease", '-map_metadata', '-1', '-c:v', 'mjpeg', '-q:v', '3', '-threads', '1', '-update', '1', $path]);
            $process->setTimeout(15);
            $process->mustRun();
            if (!is_file($path) || filesize($path) > 2097152) {
                throw new \RuntimeException();
            }
            @chmod($path, 0640);

            return $name;
        } catch (\Throwable) {
            @unlink($path);
            throw new \RuntimeException('Impossible de préparer cette image. Essaie une image plus légère.');
        }
    }

    public function bytes(User $owner, string $name, string $ratio = 'auto', string $fit = 'crop', ?string $match = null): string
    {
        $path = $this->path($owner, $name);
        if (!in_array($ratio, ['auto', '9:16', '16:9', '1:1'], true) || !in_array($fit, ['crop', 'contain'], true)) {
            throw new \RuntimeException('Cadrage indisponible.');
        }
        if ('auto' === $ratio && null === $match) {
            $bytes = file_get_contents($path);
        } else {
            [$width, $height] = match ($ratio) {
                '9:16' => [1080, 1920], '16:9' => [1920, 1080], '1:1' => [1080, 1080],
                default => array_slice(getimagesize($this->path($owner, $match)) ?: throw new \RuntimeException('Image de début indisponible.'), 0, 2),
            };
            $filter = 'crop' === $fit
                ? 'scale='.$width.':'.$height.':force_original_aspect_ratio=increase,crop='.$width.':'.$height
                : 'scale='.$width.':'.$height.':force_original_aspect_ratio=decrease,pad='.$width.':'.$height.':(ow-iw)/2:(oh-ih)/2:black';
            $process = new Process(['ffmpeg', '-nostdin', '-v', 'error', '-max_alloc', '67108864', '-protocol_whitelist', 'file,pipe', '-threads', '1', '-i', $path, '-frames:v', '1', '-vf', $filter, '-map_metadata', '-1', '-c:v', 'mjpeg', '-q:v', '3', '-threads', '1', '-f', 'image2pipe', 'pipe:1']);
            $process->setTimeout(15);
            try {
                $process->mustRun();
                $bytes = $process->getOutput();
            } catch (\Throwable) {
                throw new \RuntimeException('Impossible de préparer ce cadrage. Essaie une image plus légère ou un autre format.');
            }
        }
        if (false === $bytes || strlen($bytes) > 2097152) {
            throw new \RuntimeException('Image trop volumineuse après cadrage. Essaie une image plus légère.');
        }

        return $bytes;
    }

    public function dataUri(User $owner, string $name, string $ratio = 'auto', string $fit = 'crop', ?string $match = null): string
    {
        return 'data:image/jpeg;base64,'.base64_encode($this->bytes($owner, $name, $ratio, $fit, $match));
    }

    /** @param list<string> $keep
     * @return array{files:int,bytes:int}
     */
    public function sweep(User $owner, array $keep, \DateTimeImmutable $before, bool $apply = false): array
    {
        if ($before > new \DateTimeImmutable('-30 days')) {
            throw new \InvalidArgumentException('Le délai de conservation minimal est de 30 jours.');
        }
        $directory = $this->directory($owner);
        $retained = array_fill_keys($keep, true);
        $result = ['files' => 0, 'bytes' => 0];
        foreach (glob($directory.'/*.jpg') ?: [] as $path) {
            $name = basename($path);
            if (!preg_match('/^[a-f0-9]{48}\.jpg$/D', $name) || isset($retained[$name]) || is_link($path) || !is_file($path)) {
                continue;
            }
            clearstatcache(true, $path);
            $mtime = filemtime($path);
            $size = filesize($path);
            if (false === $mtime || false === $size || $mtime >= $before->getTimestamp()) {
                continue;
            }
            if ($apply && !unlink($path)) {
                throw new \RuntimeException('Une image inutilisée n’a pas pu être retirée.');
            }
            ++$result['files'];
            $result['bytes'] += $size;
        }

        return $result;
    }
}
