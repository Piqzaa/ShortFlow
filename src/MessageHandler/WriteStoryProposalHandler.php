<?php

namespace App\MessageHandler;

use App\Message\WriteStoryProposal;
use App\Service\StoryProposals;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WriteStoryProposalHandler
{
    public function __construct(private StoryProposals $proposals)
    {
    }

    public function __invoke(WriteStoryProposal $message): void
    {
        $this->proposals->process($message->id);
    }
}
