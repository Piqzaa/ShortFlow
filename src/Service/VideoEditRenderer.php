<?php

namespace App\Service;

use App\Entity\VideoEdit;
use App\Repository\VideoAssetRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class VideoEditRenderer
{
    public function __construct(private VideoAssetRepository $videos, private PrivateVideoStorage $storage, private VideoProbe $probe, private EditCaptions $captions, private EditAudioClips $audioClips, #[Autowire('%kernel.project_dir%/var/storage/edits/%kernel.environment%')] private string $directory, #[Autowire('%kernel.project_dir%/public/fonts/editor')] private string $fontsDirectory)
    {
    }

    public function render(VideoEdit $project): string
    {
        if (is_link($this->directory) || (!is_dir($this->directory) && !@mkdir($this->directory, 0750, true) && !is_dir($this->directory))) {
            throw new VideoLibraryException('Le stockage de l’export est indisponible.');
        }
        // Recover only our own abandoned temporary work, never library sources/results.
        foreach (glob($this->directory.'/*', GLOB_ONLYDIR) ?: [] as $old) {
            if (!is_link($old) && preg_match('/^[a-f0-9]{48}$/D', basename($old)) && filemtime($old) < time() - 86400) {
                $this->discard($old.'/result.mp4');
            }
        }
        $work = $this->directory.'/'.bin2hex(random_bytes(24));
        if (!mkdir($work, 0750)) {
            throw new VideoLibraryException('Le stockage de l’export est indisponible.');
        }
        $result = $work.'/result.mp4';
        $deadline = microtime(true) + 240;
        [$width, $height] = VideoEdit::FORMATS[$project->getFormat()];
        try {
            $list = '';
            foreach (EditTimeline::segments($project->getClips()) as $index => $clip) {
                if (isset($clip['gap'])) {
                    $this->run(['ffmpeg', '-v', 'error', '-nostdin', '-y', '-f', 'lavfi', '-i', 'color=c='.str_replace('#', '0x', $project->getSettings()['background'] ?? '#000000').':s='.$width.'x'.$height.':r=30', '-f', 'lavfi', '-i', 'anullsrc=r=48000:cl=stereo', '-t', sprintf('%.6F', $clip['gap']), '-c:v', 'libx264', '-threads', '2', '-preset', 'veryfast', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-ar', '48000', '-ac', '2', 'clip-'.$index.'.mp4'], $work, $deadline);
                    $list .= 'file clip-'.$index.".mp4\n";
                    continue;
                }
                $video = $this->videos->owned($clip['id'], $project->getOwner());
                if (!$video) {
                    throw new VideoLibraryException('Une vidéo source est indisponible.');
                }
                $input = $this->storage->path($video->getStorageName());
                $audioProbe = new Process(['ffprobe', '-v', 'error', '-protocol_whitelist', 'file', '-select_streams', 'a:0', '-show_entries', 'stream=index', '-of', 'csv=p=0', $input]);
                $audioProbe->setTimeout(10);
                $audioProbe->mustRun();
                $hasAudio = '' !== trim($audioProbe->getOutput());
                $command = ['ffmpeg', '-hide_banner', '-loglevel', 'error', '-nostdin', '-y', '-max_alloc', '67108864', '-threads', '2', '-filter_threads', '1', '-protocol_whitelist', 'file', '-ss', sprintf('%.6F', $clip['start']), '-i', $input];
                if (!$hasAudio) {
                    array_push($command, '-f', 'lavfi', '-i', 'anullsrc=r=48000:cl=stereo');
                }
                $fit = $project->getSettings()['fit'];
                $frame = EditClipStyle::filters($clip, $width, $height, $fit, $project->getSettings()['background'] ?? '#000000');
                array_push($command, '-map', '0:v:0', '-map', $hasAudio ? '0:a:0' : '1:a:0', '-t', sprintf('%.6F', ($clip['end'] - $clip['start']) / $clip['rate']), '-vf', 'setpts=(PTS-STARTPTS)/'.sprintf('%.4F', $clip['rate']).','.$frame.',setsar=1,fps=30', '-af', EditTimeline::tempo($clip['rate']).EditClipStyle::audioFades($clip).',aresample=48000,apad', '-c:v', 'libx264', '-threads', '2', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-ar', '48000', '-ac', '2', '-b:a', '128k', '-map_metadata', '-1', '-map_chapters', '-1', '-fs', (string) (VideoLibrary::MAX_BYTES + 1), 'clip-'.$index.'.mp4');
                $this->run($command, $work, $deadline);
                $list .= 'file clip-'.$index.".mp4\n";
            }
            file_put_contents($work.'/clips.txt', $list);
            $settings = $project->getSettings();
            $effects = $project->getTexts() || $settings['intro'] || ('off' !== $settings['captionStyle'] && $project->getCaptions()) || $project->getAudioClips() || 100 != $settings['sourceVolume'];
            $this->run(['ffmpeg', '-hide_banner', '-loglevel', 'error', '-nostdin', '-y', '-protocol_whitelist', 'file', '-f', 'concat', '-safe', '1', '-i', 'clips.txt', '-c', 'copy', '-map_metadata', '-1', '-movflags', '+faststart', '-fs', (string) (VideoLibrary::MAX_BYTES + 1), $effects ? 'assembled.mp4' : 'result.mp4'], $work, $deadline);
            if ($effects) {
                $filters = [];
                if ($project->getTexts() || ('off' !== $settings['captionStyle'] && $project->getCaptions())) {
                    $overlays = array_merge('off' === $settings['captionStyle'] ? [] : $project->getCaptions(), $project->getTexts());
                    file_put_contents($work.'/captions.ass', $this->captions->ass($overlays, $width, $height, $settings['captionStyle'], ['x' => $settings['captionX'], 'y' => $settings['captionY'], 'size' => $settings['captionSize'], 'typography' => $settings['typography'] ?? []]));
                    $filters[] = "subtitles=captions.ass:fontsdir='".str_replace(['\\', ':', "'"], ['\\\\', '\\:', "\\'"], $this->fontsDirectory)."'";
                }
                if ($settings['intro']) {
                    file_put_contents($work.'/title.txt', $this->openingTitle($project->getTitle(), $width));
                    $filters[] = "drawtext=fontfile=/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf:textfile=title.txt:expansion=none:fontsize=48:fontcolor=white:box=1:boxcolor=black@0.65:boxborderw=16:x=(w-tw)/2:y=h*0.10:enable='lt(t,2.5)'";
                }
                $command = ['ffmpeg', '-v', 'error', '-nostdin', '-y', '-threads', '2', '-filter_threads', '1', '-filter_complex_threads', '1', '-protocol_whitelist', 'file', '-i', 'assembled.mp4'];
                $this->audioClips->appendMix($project, $command);
                if ($filters) {
                    array_push($command, '-vf', implode(',', $filters));
                }
                array_push($command, '-t', sprintf('%.6F', $project->getDuration()), '-c:v', $filters ? 'libx264' : 'copy');
                if ($filters) {
                    array_push($command, '-threads', '2', '-preset', 'veryfast', '-crf', '23', '-pix_fmt', 'yuv420p');
                }
                array_push($command, '-c:a', 'aac', '-b:a', '128k', '-map_metadata', '-1', '-map_chapters', '-1', '-movflags', '+faststart', '-fs', (string) (VideoLibrary::MAX_BYTES + 1), 'result.mp4');
                $this->run($command, $work, $deadline);
            }
            clearstatcache(true, $result);
            $info = $this->probe->inspect(new UploadedFile($result, 'montage.mp4', null, null, true));
            if (filesize($result) > VideoLibrary::MAX_BYTES || abs($info['duration'] - $project->getDuration()) > 0.5 || $info['width'] !== $width || $info['height'] !== $height) {
                throw new VideoLibraryException('Le résultat dépasse 100 Mo ou ne correspond pas aux coupes. Raccourcis le montage avant de réessayer.');
            }
            @chmod($result, 0640);
        } catch (\Throwable $error) {
            $this->discard($result);
            throw $error;
        } finally {
            foreach (glob($work.'/clip-*') ?: [] as $file) {
                @unlink($file);
            }
            @unlink($work.'/clips.txt');
        }

        return $result;
    }

    private function openingTitle(string $title, int $width): string
    {
        $limit = max(12, (int) floor($width / 34));
        $words = preg_split('/\\s+/u', mb_substr($title, 0, 60)) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            foreach (mb_str_split($word, $limit) as $part) {
                if (mb_strlen($line.' '.$part) > $limit && '' !== $line) {
                    $lines[] = $line;
                    $line = '';
                }
                $line = trim($line.' '.$part);
            }
        }
        if ('' !== $line) {
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /** @param list<string> $command */
    private function run(array $command, string $work, float $deadline): void
    {
        if ($deadline <= microtime(true)) {
            throw new VideoLibraryException('L’export a pris trop de temps. Raccourcis ton montage puis réessaie.');
        }
        $process = new Process($command, $work);
        $process->setTimeout(max(1, $deadline - microtime(true)));
        $process->disableOutput();
        $process->mustRun();
    }

    public function discard(string $path): void
    {
        if (!str_starts_with($path, $this->directory.'/') || !preg_match('~/[a-f0-9]{48}/result\.mp4$~D', $path)) {
            return;
        }
        $work = dirname($path);
        foreach (glob($work.'/*') ?: [] as $file) {
            if (is_file($file) && !is_link($file)) {
                @unlink($file);
            }
        }
        @rmdir($work);
    }
}
