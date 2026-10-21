<?php

namespace App\Service;

interface SpeechTranscriber
{
    /** @return list<array{start:float,end:float,text:string}> */
    public function transcribe(string $audio, string $language = 'fr'): array;
}
