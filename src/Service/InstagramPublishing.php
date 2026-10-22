<?php

namespace App\Service;

use App\Entity\InstagramConnection;
use App\Entity\InstagramPublication;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Message\PublishInstagram;
use App\Repository\VideoAssetRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class InstagramPublishing
{
    public function __construct(private EntityManagerInterface $em, private VideoAssetRepository $videos, private MessageBusInterface $bus, private PublicationLock $lock, private InstagramApi $api, private InstagramMediaLink $links, private PublicationVideoCheck $videoCheck)
    {
    }

    public function connection(User $owner): ?InstagramConnection
    {
        return $this->em->getRepository(InstagramConnection::class)->findOneBy(['owner' => $owner]);
    }

    public function ready(User $owner): bool
    {
        $connection = $this->connection($owner);

        return $this->api->isReady() && $this->links->isReady() && $connection && !$connection->isExpired();
    }

    public function validateVideo(VideoAsset $video): void
    {
        $this->videoCheck->validate($video, 'instagram');
    }

    /** @param list<int> $ids @param array<int|string, array<string, mixed>> $rows */
    public function enqueue(User $owner, array $ids, array $rows, string $key, string $expectedAccount): void
    {
        $this->lock->run((int) $owner->getId(), function () use ($owner, $ids, $rows, $key, $expectedAccount): void {
            $this->em->wrapInTransaction(function () use ($owner, $ids, $rows, $key, $expectedAccount): void {
                $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
                if ($this->em->getRepository(InstagramPublication::class)->count(['owner' => $owner, 'submissionKey' => $key])) {
                    return;
                }
                $connection = $this->connection($owner);
                if ($connection) {
                    $this->em->refresh($connection);
                }
                if (!$this->ready($owner) || !$connection || $expectedAccount !== $connection->getInstagramId()) {
                    throw new PublicationException('Reconnecte le compte destinataire et recharge la préparation.');
                }
                if (!$ids || count($ids) > 20 || $this->em->getRepository(InstagramPublication::class)->count(['owner' => $owner, 'status' => InstagramPublication::ACTIVE]) + count($ids) > 100) {
                    throw new PublicationException('Termine les envois en attente avant de continuer.');
                }
                foreach ($ids as $id) {
                    $video = $this->videos->owned($id, $owner) ?? throw new PublicationException('Une vidéo sélectionnée est indisponible.');
                    $this->em->refresh($video, LockMode::PESSIMISTIC_WRITE);
                    $this->validateVideo($video);
                    $row = $rows[$id];
                    $job = new InstagramPublication($owner, $video, $connection, $key, $row['caption'] ?? '', (bool) $row['shareToFeed'], (bool) $row['synthetic']);
                    $this->em->persist($job);
                    $this->em->flush();
                    $this->bus->dispatch(new PublishInstagram((int) $job->getId()));
                }
            });
        });
    }

    public function resume(InstagramPublication $job): void
    {
        $this->lock->run((int) $job->getOwner()->getId(), function () use ($job): void {
            $this->em->wrapInTransaction(function () use ($job): void {
                $this->em->refresh($job, LockMode::PESSIMISTIC_WRITE);
                if ('paused' !== $job->getStatus() && !('review' === $job->getStatus() && $job->getContainerId() && $job->isPublishAttempted())) {
                    return;
                }
                if (!$this->ready($job->getOwner()) || $this->connection($job->getOwner())?->getInstagramId() !== $job->getInstagramId()) {
                    throw new PublicationException('Reconnecte le compte destinataire avant de reprendre.');
                }
                $job->resume();
                $this->em->flush();
                $this->bus->dispatch(new PublishInstagram((int) $job->getId()));
            });
        });
    }
}
