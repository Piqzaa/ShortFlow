<?php

namespace App\Service;

use App\Entity\VideoEdit;
use App\Repository\VideoAssetRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final class EditAudio
{
    public function __construct(private VideoAssetRepository $videos, private PrivateVideoStorage $storage, #[Autowire('%kernel.project_dir%/var/storage/edit-audio/%kernel.environment%')] private string $directory)
    {
    }

    public function extract(VideoEdit $project): string
    {
        if (is_link($this->directory) || (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory))) {
            throw new VideoLibraryException('Le stockage audio est indisponible.');
        }
        foreach (glob($this->directory.'/*', GLOB_ONLYDIR) ?: [] as $old) {
            if (!is_link($old) && preg_match('/^[a-f0-9]{48}$/D', basename($old)) && filemtime($old) < time() - 86400) {
                $this->discard($old.'/audio.wav');
            }
        }
        $work = $this->directory.'/'.bin2hex(random_bytes(24));
        if (!mkdir($work, 0750)) {
            throw new VideoLibraryException('Le stockage audio est indisponible.');
        }
        $audio = $work.'/audio.wav';
        try {
            $list = '';
            foreach ($project->getClips() as $i => $clip) {
                $source = $this->videos->owned($clip['id'], $project->getOwner()) ?? throw new VideoLibraryException('Une source est indisponible.');
                $path = $this->storage->path($source->getStorageName());
                $probe = new Process(['ffprobe', '-v', 'error', '-protocol_whitelist', 'file', '-select_streams', 'a:0', '-show_entries', 'stream=index', '-of', 'csv=p=0', $path]);
                $probe->setTimeout(10);
                $probe->mustRun();
                $command = ['ffmpeg', '-v', 'error', '-nostdin', '-y', '-threads', '1'];
                if ('' !== trim($probe->getOutput())) {
                    array_push($command, '-protocol_whitelist', 'file', '-ss', sprintf('%.6F', $clip['start']), '-i', $path);
                } else {
                    array_push($command, '-f', 'lavfi', '-i', 'anullsrc=r=16000:cl=mono');
                }
                array_push($command, '-t', sprintf('%.6F', $clip['end'] - $clip['start']), '-vn', '-ac', '1', '-ar', '16000', '-af', 'apad', '-c:a', 'pcm_s16le', 'clip-'.$i.'.wav');
                $process = new Process($command, $work);
                $process->setTimeout(30);
                $process->disableOutput();
                $process->mustRun();
                $list .= 'file clip-'.$i.".wav\n";
            }
            file_put_contents($work.'/list.txt', $list);
            $process = new Process(['ffmpeg', '-v', 'error', '-nostdin', '-y', '-protocol_whitelist', 'file', '-f', 'concat', '-safe', '1', '-i', 'list.txt', '-c', 'copy', 'audio.wav'], $work);
            $process->setTimeout(20);
            $process->disableOutput();
            $process->mustRun();

            return $audio;
        } catch (\Throwable $error) {
            $this->discard($audio);
            throw $error;
        }
    }

    public function discard(string $path): void
    {
        if (!str_starts_with($path, $this->directory.'/') || !preg_match('~/[a-f0-9]{48}/audio\.wav$~D', $path)) {
            return;
        }
        foreach (glob(dirname($path).'/*') ?: [] as $file) {
            if (is_file($file) && !is_link($file)) {
                @unlink($file);
            }
        }
        @rmdir(dirname($path));
    }
}
