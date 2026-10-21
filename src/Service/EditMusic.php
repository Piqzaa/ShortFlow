<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

/** Private, normalized music files. Never accepts a remote URL. */
final class EditMusic
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/storage/edit-music/%kernel.environment%')] private string $directory)
    {
    }

    public function import(UploadedFile $file): string
    {
        if (!$file->isValid() || $file->getSize() > 20 * 1024 * 1024 || !in_array(strtolower($file->getClientOriginalExtension()), ['mp3', 'wav', 'm4a', 'ogg'], true)) {
            throw new VideoLibraryException('Choisis un fichier MP3, WAV, M4A ou OGG de 20 Mo maximum.');
        }
        $probe = new Process(['ffprobe', '-v', 'error', '-protocol_whitelist', 'file', '-show_entries', 'stream=codec_type:format=duration', '-of', 'json', $file->getPathname()]);
        $probe->setTimeout(10);
        $probe->run();
        $info = json_decode($probe->getOutput(), true);
        $duration = (float) ($info['format']['duration'] ?? 0);
        if (!$probe->isSuccessful() || !is_finite($duration) || $duration <= 0 || $duration > 600 || ['audio'] !== array_column($info['streams'] ?? [], 'codec_type')) {
            throw new VideoLibraryException('Ce fichier doit contenir uniquement du son, pour une durée de 10 minutes maximum.');
        }
        if (is_link($this->directory) || (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory))) {
            throw new VideoLibraryException('Le stockage musical est indisponible.');
        }
        $name = bin2hex(random_bytes(24)).'.m4a';
        $path = $this->path($name);
        try {
            $convert = new Process(['ffmpeg', '-v', 'error', '-nostdin', '-y', '-threads', '2', '-protocol_whitelist', 'file', '-i', $file->getPathname(), '-map', '0:a:0', '-t', '600', '-c:a', 'aac', '-b:a', '128k', '-ar', '48000', '-ac', '2', '-map_metadata', '-1', '-map_chapters', '-1', '-movflags', '+faststart', $path]);
            $convert->setTimeout(45);
            $convert->disableOutput();
            $convert->mustRun();
            chmod($path, 0640);
        } catch (\Throwable) {
            $this->discard($name);
            throw new VideoLibraryException('Cette piste n’a pas pu être lue. Essaie un autre fichier audio.');
        }

        return $name;
    }

    public function path(string $name): string
    {
        if (!preg_match('/^[a-f0-9]{48}\.m4a$/D', $name)) {
            throw new VideoLibraryException('Piste indisponible.');
        }

        return $this->directory.'/'.$name;
    }

    public function duplicate(string $name): string
    {
        $copy = bin2hex(random_bytes(24)).'.m4a';
        if (!is_file($this->path($name)) || !copy($this->path($name), $this->path($copy))) {
            throw new VideoLibraryException('La piste musicale est indisponible.');
        }
        chmod($this->path($copy), 0640);

        return $copy;
    }

    public function duration(string $name): float
    {
        $probe = new Process(['ffprobe', '-v', 'error', '-protocol_whitelist', 'file', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $this->path($name)]);
        $probe->setTimeout(10);
        $probe->run();
        if (!$probe->isSuccessful() || (float) $probe->getOutput() <= 0) {
            throw new VideoLibraryException('La piste musicale est indisponible. Importe-la de nouveau.');
        }

        return (float) $probe->getOutput();
    }

    public function discard(?string $name): void
    {
        if ($name && preg_match('/^[a-f0-9]{48}\.m4a$/D', $name)) {
            @unlink($this->path($name));
        }
    }
}
