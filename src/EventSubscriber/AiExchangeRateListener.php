<?php

namespace App\EventSubscriber;

use App\Service\AiExchangeRateUpdater;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

#[AsEventListener(event: WorkerRunningEvent::class)]
final class AiExchangeRateListener
{
    private int $nextCheck = 0;

    public function __construct(private AiExchangeRateUpdater $updater, private LoggerInterface $logger)
    {
    }

    public function __invoke(WorkerRunningEvent $event): void
    {
        if (!$event->isWorkerIdle() || !in_array('background', $event->getWorker()->getMetadata()->getTransportNames(), true) || time() < $this->nextCheck) {
            return;
        }
        $this->nextCheck = time() + 300;
        try {
            $this->updater->refresh();
        } catch (\Throwable) {
            $this->logger->warning('Le taux indicatif BCE n’a pas pu être actualisé. Le dernier taux reste conservé.');
        }
    }
}
