<?php

namespace App\MessageHandler;

use App\Message\PrepareSeriesEpisode;
use App\Service\SeriesRecurrences;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PrepareSeriesEpisodeHandler
{
    public function __construct(private SeriesRecurrences $recurrences)
    {
    }

    public function __invoke(PrepareSeriesEpisode $message): void
    {
        $this->recurrences->produce($message);
    }
}
