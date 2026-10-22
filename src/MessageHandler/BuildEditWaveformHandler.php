<?php

namespace App\MessageHandler;

use App\Message\BuildEditWaveform;
use App\Service\EditWaveforms;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class BuildEditWaveformHandler
{
    public function __construct(private EditWaveforms $waveforms)
    {
    }

    public function __invoke(BuildEditWaveform $message): void
    {
        $this->waveforms->build($message->path);
    }
}
