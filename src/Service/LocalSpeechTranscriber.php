<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final class LocalSpeechTranscriber implements SpeechTranscriber
{
    public function __construct(#[Autowire('%kernel.project_dir%/scripts/transcribe.py')] private string $script)
    {
    }

    public function transcribe(string $audio, string $language = 'fr'): array
    {
        if (!is_executable('/opt/vydflow-speech/bin/python')) {
            throw new VideoLibraryException('Le moteur local de sous-titres est indisponible. Vérifie le worker de montage.');
        }
        $process = new Process(['/opt/vydflow-speech/bin/python', $this->script, $audio, $language]);
        $process->setTimeout(240);
        try {
            $process->mustRun();
            $data = json_decode($process->getOutput(), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new VideoLibraryException('La transcription locale n’a pas abouti. Réessaie sur une vidéo plus courte avec une voix audible.');
        }
        if (!is_array($data)) {
            throw new VideoLibraryException('La transcription est illisible.');
        }

        return $data;
    }
}
