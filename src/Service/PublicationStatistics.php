<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InstagramConnection;
use App\Entity\InstagramPublication;
use App\Entity\PublicationStatistic;
use App\Entity\TikTokConnection;
use App\Entity\TikTokPublication;
use App\Entity\User;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Message\RefreshPublicationStatistics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class PublicationStatistics
{
    private const JOBS = ['youtube' => YouTubePublication::class, 'instagram' => InstagramPublication::class, 'tiktok' => TikTokPublication::class];

    public function __construct(private EntityManagerInterface $em, private MessageBusInterface $bus, private PublicationLock $lock, private YouTubeApi $youtube, private InstagramApi $instagram, private TikTokApi $tiktok, private PublicationStatisticsAccess $access)
    {
    }

    public function owned(User $owner, string $network, int $id): YouTubePublication|InstagramPublication|TikTokPublication|null
    {
        if (!isset(self::JOBS[$network])) {
            return null;
        }

        return $this->em->getRepository(self::JOBS[$network])->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    public function queue(User $owner, string $network, int $id): bool
    {
        return $this->lock->run((int) $owner->getId(), function () use ($owner, $network, $id): bool {
            $job = $this->owned($owner, $network, $id);
            if (!$this->access->enabled($network) || !$job || 'published' !== $job->getStatus() || !$job->getRemoteId()) {
                throw new PublicationException('Les statistiques nécessitent une publication confirmée, un identifiant vidéo et les autorisations de lecture du réseau.');
            }

            return $this->em->wrapInTransaction(function () use ($owner, $network, $id): bool {
                $snapshot = $this->em->getRepository(PublicationStatistic::class)->findOneBy(['owner' => $owner, 'network' => $network, 'publicationId' => $id]);
                if ($snapshot && !$snapshot->canRequest()) {
                    return false;
                }
                if ($snapshot) {
                    $snapshot->request();
                } else {
                    $snapshot = new PublicationStatistic($owner, $network, $id);
                    $this->em->persist($snapshot);
                }
                $this->em->flush();
                $this->bus->dispatch(new RefreshPublicationStatistics((int) $snapshot->getId(), $snapshot->getRequestKey()));

                return true;
            });
        });
    }

    public function refresh(int $id, string $requestKey): void
    {
        $snapshot = $this->em->find(PublicationStatistic::class, $id);
        if (!$snapshot) {
            return;
        }
        $this->lock->run((int) $snapshot->getOwner()->getId(), function () use ($snapshot, $requestKey): void {
            $this->em->refresh($snapshot);
            if ('queued' !== $snapshot->getStatus() || !hash_equals($snapshot->getRequestKey(), $requestKey)) {
                return;
            }
            try {
                $job = $this->owned($snapshot->getOwner(), $snapshot->getNetwork(), $snapshot->getPublicationId());
                if (!$this->access->enabled($snapshot->getNetwork()) || !$job || 'published' !== $job->getStatus() || !$job->getRemoteId()) {
                    throw new \RuntimeException();
                }
                $remoteId = $job->getRemoteId();
                if ($job instanceof YouTubePublication) {
                    $connection = $this->em->getRepository(YouTubeConnection::class)->findOneBy(['owner' => $job->getOwner()]);
                    if (!$connection || $connection->getChannelId() !== $job->getChannelId()) {
                        throw new \RuntimeException();
                    }
                    $values = $this->youtube->statistics($connection, $remoteId);
                } elseif ($job instanceof InstagramPublication) {
                    $connection = $this->em->getRepository(InstagramConnection::class)->findOneBy(['owner' => $job->getOwner()]);
                    if (!$connection || $connection->getInstagramId() !== $job->getInstagramId()) {
                        throw new \RuntimeException();
                    }
                    $values = $this->instagram->statistics($connection, $remoteId);
                } else {
                    $connection = $this->em->getRepository(TikTokConnection::class)->findOneBy(['owner' => $job->getOwner()]);
                    if (!$connection || $connection->getOpenId() !== $job->getOpenId()) {
                        throw new \RuntimeException();
                    }
                    $values = $this->tiktok->statistics($connection, $remoteId);
                }
                $snapshot->record(['views' => $this->counter($values['views'] ?? null), 'likes' => $this->counter($values['likes'] ?? null), 'comments' => $this->counter($values['comments'] ?? null)]);
            } catch (\Throwable) {
                // Provider errors can contain tokens or identifiers: keep only a neutral notice.
                $snapshot->unavailable();
            }
            $this->em->flush();
        });
    }

    private function counter(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string) $value)) {
            throw new \RuntimeException();
        }
        $digits = (string) $value;
        $max = (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            throw new \RuntimeException();
        }

        return (int) $value;
    }
}
