<?php

namespace App\MessageHandler;

use App\Message\RenderVideoEdit;
use App\Service\VideoEdits;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RenderVideoEditHandler
{
    public function __construct(private VideoEdits $edits)
    {
    }

    public function __invoke(RenderVideoEdit $message): void
    {
        $this->edits->process($message->id);
    }
}
