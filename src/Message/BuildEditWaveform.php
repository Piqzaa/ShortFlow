<?php

namespace App\Message;

final readonly class BuildEditWaveform
{
    public function __construct(public string $path)
    {
    }
}
