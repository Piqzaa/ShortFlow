<?php

namespace App\Service;

use App\Entity\VideoEdit;
use App\Repository\VideoAssetRepository;
use Symfony\Component\Process\Process;

final class EditAudioClips
{
    public function __construct(private VideoAssetRepository $videos, private EditMusic $music, private PrivateVideoStorage $storage)
    {
    }

    /** @param array<mixed> $rows @return list<array<string, mixed>> */
    public function validate(VideoEdit $project, array $rows, float $duration): array
    {
        if (count($rows) > 32) {
            throw new VideoLibraryException('32 fragments audio maximum par montage.');
        }
        $result = [];
        $ids = [];
        $musicDuration = null;
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['id'] ?? null) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $row['id']) || isset($ids[$row['id']]) || !in_array($row['kind'] ?? '', ['music', 'video'], true)) {
                throw new VideoLibraryException('Vérifie les fragments audio du montage.');
            }
            $ids[$row['id']] = true;
            $values = [];
            foreach (['start' => $duration, 'end' => $duration + 0.001, 'offset' => 600, 'volume' => 100] as $key => $maximum) {
                if (!is_scalar($row[$key] ?? null) || !is_numeric($row[$key]) || !is_finite((float) $row[$key]) || $row[$key] < 0 || $row[$key] > $maximum) {
                    throw new VideoLibraryException('Vérifie les durées et le volume des fragments audio.');
                }
                $values[$key] = (float) $row[$key];
            }
            if ($values['end'] - $values['start'] < 0.1 || !in_array($row['loop'] ?? null, [true, false, '0', '1', 0, 1], true)) {
                throw new VideoLibraryException('Chaque fragment audio doit durer au moins 0,1 seconde.');
            }
            $source = 0;
            if ('video' === $row['kind']) {
                if (!is_scalar($row['source'] ?? null) || !ctype_digit((string) $row['source']) || !($video = $this->videos->owned((int) $row['source'], $project->getOwner()))) {
                    throw new VideoLibraryException('Une source audio a été retirée ou est indisponible.');
                }
                $source = (int) $video->getId();
                if ((bool) $row['loop'] || $values['offset'] + $values['end'] - $values['start'] > $video->getDuration() + 0.05) {
                    throw new VideoLibraryException('Le fragment sonore dépasse la vidéo source.');
                }
            } else {
                if (!$project->getMusicName()) {
                    throw new VideoLibraryException('La musique est indisponible.');
                }
                $musicDuration ??= $this->music->duration($project->getMusicName());
                if ($values['offset'] >= $musicDuration) {
                    throw new VideoLibraryException('Le début du fragment dépasse la piste musicale.');
                }
            }
            $result[] = ['id' => $row['id'], 'kind' => $row['kind'], 'source' => $source, 'loop' => (bool) $row['loop']] + $values;
        }

        return $result;
    }

    /** @param list<string> $command */
    public function appendMix(VideoEdit $project, array &$command, bool $video = true, bool $includeMusic = true): void
    {
        $rows = $this->validate($project, $project->getAudioClips(), $project->getDuration());
        $voice = 'volume='.sprintf('%.4F', $project->getSettings()['sourceVolume'] / 100);
        $filters = ['[0:a]'.$voice.'[voice]'];
        $inputs = '[voice]';
        $index = 0;
        foreach ($rows as $row) {
            if (!$includeMusic && 'music' === $row['kind']) {
                continue;
            }
            if ('music' === $row['kind']) {
                $path = $this->music->path($project->getMusicName());
            } else {
                $sourceVideo = $this->videos->owned($row['source'], $project->getOwner());
                if (!$sourceVideo) {
                    throw new VideoLibraryException('Une source audio est indisponible.');
                }
                $path = $this->storage->path($sourceVideo->getStorageName());
                $probe = new Process(['ffprobe', '-v', 'error', '-protocol_whitelist', 'file', '-select_streams', 'a:0', '-show_entries', 'stream=index', '-of', 'csv=p=0', $path]);
                $probe->setTimeout(10);
                $probe->mustRun();
                if ('' === trim($probe->getOutput())) {
                    continue;
                }
            }
            ++$index;
            if ($row['loop']) {
                array_push($command, '-stream_loop', '-1');
            }
            array_push($command, '-ss', sprintf('%.6F', $row['offset']), '-threads', '2', '-protocol_whitelist', 'file', '-i', $path);
            $filters[] = '['.$index.':a]atrim=duration='.sprintf('%.6F', $row['end'] - $row['start']).',asetpts=PTS-STARTPTS,aresample=48000,volume='.sprintf('%.4F', $row['volume'] / 100).',adelay='.((int) round($row['start'] * 1000)).':all=1[segment'.$index.']';
            $inputs .= '[segment'.$index.']';
        }
        if ($video) {
            array_push($command, '-map', '0:v:0');
        }
        if ($index) {
            $filters[] = $inputs.'amix=inputs='.($index + 1).':duration=first:normalize=0,alimiter=limit=0.95:latency=1[mix]';
            array_push($command, '-filter_complex', implode(';', $filters), '-map', '[mix]');
        } else {
            array_push($command, '-map', '0:a:0', '-af', $voice);
        }
    }
}
