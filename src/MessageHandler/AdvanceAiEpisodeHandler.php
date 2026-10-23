<?php

namespace App\MessageHandler;

use App\Message\AdvanceAiEpisode;
use App\Service\AiEpisodeFlow;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AdvanceAiEpisodeHandler
{
    public function __construct(private AiEpisodeFlow $flow)
    {
    }

    public function __invoke(AdvanceAiEpisode $message): void
    {
        $this->flow->advance($message->id);
    }
}
