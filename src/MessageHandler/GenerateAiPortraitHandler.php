<?php

namespace App\MessageHandler;

use App\Message\GenerateAiPortrait;
use App\Service\AiPortraits;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateAiPortraitHandler
{
    public function __construct(private AiPortraits $portraits)
    {
    }

    public function __invoke(GenerateAiPortrait $message): void
    {
        $this->portraits->process($message->id);
    }
}
