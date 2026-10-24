<?php

namespace App\Message;

final readonly class PrepareSeriesEpisode
{
    public function __construct(public int $id, public int $revision, public string $scheduledAt)
    {
    }
}
