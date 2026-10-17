<?php

namespace App\MessageHandler;

use App\Message\DispatchScheduledPublication;
use App\Service\PublicationDrafts;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DispatchScheduledPublicationHandler
{
    public function __construct(private PublicationDrafts $drafts)
    {
    }

    public function __invoke(DispatchScheduledPublication $message): void
    {
        $this->drafts->dispatchDue($message->id, $message->revision);
    }
}
