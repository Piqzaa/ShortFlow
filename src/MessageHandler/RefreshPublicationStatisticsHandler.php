<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RefreshPublicationStatistics;
use App\Service\PublicationStatistics;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RefreshPublicationStatisticsHandler
{
    public function __construct(private PublicationStatistics $statistics)
    {
    }

    public function __invoke(RefreshPublicationStatistics $message): void
    {
        $this->statistics->refresh($message->id, $message->requestKey);
    }
}
