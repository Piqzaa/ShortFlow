<?php

namespace App\MessageHandler;

use App\Message\TranscribeVideoEdit;
use App\Service\VideoTranscriptions;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TranscribeVideoEditHandler
{
    public function __construct(private VideoTranscriptions $transcriptions)
    {
    }

    public function __invoke(TranscribeVideoEdit $message): void
    {
        $this->transcriptions->process($message->id);
    }
}
