<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Message\PublishYouTube;
use App\Repository\VideoAssetRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class YouTubePublishing
{
    public function __construct(private EntityManagerInterface $em, private VideoAssetRepository $videos, private MessageBusInterface $bus, private PublicationLock $lock, private YouTubeApi $api, private PublicationVideoCheck $videoCheck)
    {
    }

    public function connection(User $owner): ?YouTubeConnection
    {
        return $this->em->getRepository(YouTubeConnection::class)->findOneBy(['owner' => $owner]);
    }

    public function validateVideo(VideoAsset $video): void
    {
        $this->videoCheck->validate($video, 'youtube');
    }

    /** @param list<int> $ids @param array<string|int, array<string, mixed>> $rows */
    public function enqueue(User $owner, array $ids, array $rows, string $key, string $expectedChannel): void
    {
        if (!$this->api->isReady()) {
            throw new PublicationException('La connexion YouTube est indisponible.');
        }
        $this->lock->run((int) $owner->getId(), function () use ($owner, $ids, $rows, $key, $expectedChannel): void {
            $this->em->wrapInTransaction(function () use ($owner, $ids, $rows, $key, $expectedChannel): void {
                $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
                if ($this->em->getRepository(YouTubePublication::class)->count(['owner' => $owner, 'submissionKey' => $key]) > 0) {
                    return;
                }
                $connection = $this->connection($owner) ?? throw new PublicationException('Connecte ta chaîne YouTube avant de publier.');
                if ($connection->getChannelId() !== $expectedChannel) {
                    throw new PublicationException('La chaîne a changé. Recharge la préparation avant de confirmer.');
                }
                if ($this->em->getRepository(YouTubePublication::class)->count(['owner' => $owner, 'status' => YouTubePublication::ACTIVE]) + count($ids) > 100) {
                    throw new PublicationException('Termine ou annule les envois en attente avant de continuer.');
                }
                foreach ($ids as $id) {
                    $source = $this->videos->owned($id, $owner) ?? throw new PublicationException('Une vidéo sélectionnée est indisponible.');
                    $this->em->refresh($source, LockMode::PESSIMISTIC_WRITE);
                    $this->validateVideo($source);
                    $row = $rows[$id];
                    $job = new YouTubePublication($owner, $source, $connection, $key, $row['title'], $row['description'] ?? '', $row['privacy'], 'yes' === $row['audience'], (bool) $row['synthetic']);
                    $this->em->persist($job);
                    $this->em->flush();
                    $this->bus->dispatch(new PublishYouTube((int) $job->getId()));
                }
            });
        });
    }

    public function resume(YouTubePublication $job): void
    {
        $this->lock->run((int) $job->getOwner()->getId(), function () use ($job): void {
            $this->em->wrapInTransaction(function () use ($job): void {
                $this->em->refresh($job, LockMode::PESSIMISTIC_WRITE);
                if ('paused' !== $job->getStatus() && !('review' === $job->getStatus() && $job->getRemoteId())) {
                    return;
                }
                $connection = $this->connection($job->getOwner());
                if (!$this->api->isReady() || !$connection || $connection->getChannelId() !== $job->getChannelId()) {
                    throw new PublicationException('Reconnecte la chaîne destinataire avant de reprendre.');
                }
                $job->resume();
                $this->em->flush();
                $this->bus->dispatch(new PublishYouTube((int) $job->getId()));
            });
        });
    }
}
