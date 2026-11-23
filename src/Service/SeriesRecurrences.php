<?php

namespace App\Service;

use App\Entity\AiSeries;
use App\Entity\SeriesOccurrence;
use App\Entity\SeriesRecurrence;
use App\Message\InspectSeriesWriting;
use App\Message\PrepareSeriesEpisode;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class SeriesRecurrences
{
    private const WRITING_TIMEOUT_SECONDS = 600;

    public function __construct(private EntityManagerInterface $em, private AiEpisodeFlow $flow, private ClockInterface $clock, private MessageBusInterface $bus)
    {
    }

    public function find(AiSeries $series): ?SeriesRecurrence
    {
        return $this->em->getRepository(SeriesRecurrence::class)->findOneBy(['series' => $series]);
    }

    /** @return array{items:list<SeriesOccurrence>,total:int,page:int,pages:int} */
    public function historyPage(SeriesRecurrence $recurrence, int $page = 1): array
    {
        $total = $this->em->getRepository(SeriesOccurrence::class)->count(['recurrence' => $recurrence]);
        $pages = max(1, (int) ceil($total / 12));
        $page = max(1, min($page, $pages));
        $items = $this->em->createQueryBuilder()->select('o', 'e')->from(SeriesOccurrence::class, 'o')->leftJoin('o.episode', 'e')->where('o.recurrence = :recurrence')->setParameter('recurrence', $recurrence)->orderBy('o.scheduledAt', 'DESC')->addOrderBy('o.id', 'DESC')->setFirstResult(($page - 1) * 12)->setMaxResults(12)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function pause(SeriesRecurrence $recurrence, int $revision): void
    {
        $this->em->getConnection()->transactional(function () use ($recurrence, $revision): void {
            $this->em->lock($recurrence->getSeries()->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
            if ($recurrence->getRevision() !== $revision) {
                throw new \RuntimeException('La récurrence a changé. Recharge la page.');
            }
            $recurrence->pause('Récurrence en pause. Une génération déjà transmise peut encore se terminer et être facturée. Les publications déjà confirmées restent dans le calendrier.');
            $this->pauseEpisodes($recurrence);
            $this->em->flush();
        });
    }

    public function retire(SeriesRecurrence $recurrence): bool
    {
        return $this->em->getConnection()->transactional(function () use ($recurrence): bool {
            $this->em->lock($recurrence->getSeries()->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
            if ('active' !== $recurrence->getStatus()) {
                return false;
            }
            $recurrence->pause('La préparation automatique de scénarios a été retirée. Les épisodes déjà préparés restent accessibles.');
            $this->em->flush();

            return true;
        });
    }

    public function inspectWriting(int $id): bool
    {
        $occurrence = $this->em->find(SeriesOccurrence::class, $id);
        if (!$occurrence) {
            return false;
        }

        return $this->em->getConnection()->transactional(function () use ($occurrence): bool {
            $recurrence = $occurrence->getRecurrence();
            $this->em->lock($recurrence->getSeries()->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($occurrence, LockMode::PESSIMISTIC_WRITE);
            if ('writing' !== $occurrence->getStatus()) {
                return false;
            }
            if (!$occurrence->isAutomatic()) {
                $occurrence->manageManually();
                $this->em->flush();

                return true;
            }
            $expiresAt = $occurrence->getCreatedAt()->modify('+'.self::WRITING_TIMEOUT_SECONDS.' seconds');
            if ($expiresAt > $this->clock->now()) {
                $delay = max(1000, ($expiresAt->getTimestamp() - $this->clock->now()->getTimestamp()) * 1000);
                $this->bus->dispatch(new InspectSeriesWriting((int) $occurrence->getId()), [new DelayStamp($delay)]);

                return false;
            }
            $issue = 'La rédaction a été interrompue ou a dépassé dix minutes. Ouvre l’épisode pour le préparer manuellement ; aucun appel vidéo n’a été relancé.';
            $occurrence->fail($issue);
            $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
            if ($recurrence->getRevision() === $occurrence->getAuthorizationRevision()) {
                $recurrence->pause($issue);
                $this->pauseEpisodes($recurrence);
            }
            $this->em->flush();

            return true;
        });
    }

    public function recoverInterrupted(): int
    {
        $rows = $this->em->createQueryBuilder()->select('o.id')->from(SeriesOccurrence::class, 'o')->where('o.status = :status AND o.createdAt <= :before')->setParameter('status', 'writing')->setParameter('before', $this->clock->now()->modify('-'.self::WRITING_TIMEOUT_SECONDS.' seconds'))->getQuery()->getScalarResult();
        $recovered = 0;
        foreach ($rows as $row) {
            if ($this->inspectWriting((int) $row['id'])) {
                ++$recovered;
            }
        }

        return $recovered;
    }

    public function produce(PrepareSeriesEpisode $message): void
    {
        $recurrence = $this->em->find(SeriesRecurrence::class, $message->id);
        if ($recurrence) {
            $this->retire($recurrence);
        }
    }

    private function pauseEpisodes(SeriesRecurrence $recurrence): void
    {
        if (!$recurrence->getId()) {
            return;
        }
        foreach ($this->em->getRepository(SeriesOccurrence::class)->findBy(['recurrence' => $recurrence]) as $occurrence) {
            if (!$occurrence->isAutomatic()) {
                continue;
            }
            if ($episode = $occurrence->getEpisode()) {
                $this->flow->pause($episode);
            }
        }
    }
}
