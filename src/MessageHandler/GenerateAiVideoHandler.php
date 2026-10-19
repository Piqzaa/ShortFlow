<?php

namespace App\MessageHandler;

use App\Message\GenerateAiVideo;
use App\Service\AiGenerations;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateAiVideoHandler
{
    public function __construct(private AiGenerations $generations)
    {
    }

    public function __invoke(GenerateAiVideo $message): void
    {
        $this->generations->process($message->id);
    }
}
