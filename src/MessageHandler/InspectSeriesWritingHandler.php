<?php

namespace App\MessageHandler;

use App\Message\InspectSeriesWriting;
use App\Service\SeriesRecurrences;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class InspectSeriesWritingHandler
{
    public function __construct(private SeriesRecurrences $recurrences)
    {
    }

    public function __invoke(InspectSeriesWriting $message): void
    {
        $this->recurrences->inspectWriting($message->id);
    }
}
