<?php

declare(strict_types=1);

namespace App\Message;

final readonly class RefreshPublicationStatistics
{
    public function __construct(public int $id, public string $requestKey)
    {
    }
}
