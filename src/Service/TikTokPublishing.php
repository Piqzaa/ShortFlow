<?php

namespace App\Service;

use App\Entity\TikTokConnection;
use App\Entity\TikTokPublication;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Message\PublishTikTok;
use App\Repository\VideoAssetRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class TikTokPublishing
{
    public function __construct(private EntityManagerInterface $em, private VideoAssetRepository $videos, private MessageBusInterface $bus, private PublicationLock $lock, private TikTokApi $api, private TikTokMediaLink $links)
    {
    }

    public function connection(User $owner): ?TikTokConnection
    {
        return $this->em->getRepository(TikTokConnection::class)->findOneBy(['owner' => $owner]);
    }

    public function ready(User $owner): bool
    {
        $connection = $this->connection($owner);

        return $this->api->isReady() && $this->links->isReady() && $connection && !$connection->isExpired();
    }

    public function validateVideo(VideoAsset $video, int $maxDuration = 600): void
    {
        if ('video/mp4' !== $video->getMimeType() || 'h264' !== $video->getCodec() || $video->getDuration() < 3 || $video->getDuration() > $maxDuration || min($video->getWidth(), $video->getHeight()) < 360 || max($video->getWidth(), $video->getHeight()) > 4096 || $video->getByteSize() > 4 * 1024 * 1024 * 1024) {
            throw new PublicationException('Pour TikTok : MP4 H.264, 3 à '.$maxDuration.' secondes, dimensions de 360 à 4096 pixels.');
        }
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $creator @return array<string, mixed> */
    public function options(array $row, array $creator): array
    {
        $caption = $row['caption'] ?? '';
        if (!is_string($caption) || strlen(mb_convert_encoding($caption, 'UTF-16LE', 'UTF-8')) / 2 > 2200 || !in_array($row['privacy'] ?? null, $creator['privacy_level_options'], true)) {
            throw new PublicationException('Choisis une visibilité disponible et une légende de 2 200 caractères maximum (les emoji peuvent compter double).');
        }
        foreach (['comment', 'duet', 'stitch', 'disclosure', 'ownBrand', 'partnerBrand', 'synthetic'] as $flag) {
            if (!is_bool($row[$flag] ?? null)) {
                throw new PublicationException('Vérifie les réglages TikTok.');
            }
        }
        foreach (['comment', 'duet', 'stitch'] as $flag) {
            if ($row[$flag] && $creator[$flag.'_disabled']) {
                throw new PublicationException('Les autorisations du compte TikTok ont changé. Recharge la préparation.');
            }
        }
        if ($row['disclosure'] && !$row['ownBrand'] && !$row['partnerBrand']) {
            throw new PublicationException('Indique la marque concernée par ce contenu commercial.');
        }
        if (!$row['disclosure'] && ($row['ownBrand'] || $row['partnerBrand'])) {
            throw new PublicationException('Active la déclaration de contenu commercial pour les marques sélectionnées.');
        }
        if ($row['partnerBrand'] && 'SELF_ONLY' === $row['privacy']) {
            throw new PublicationException('TikTok ne permet pas un partenariat commercial en visibilité privée.');
        }

        return ['title' => $caption, 'privacy_level' => $row['privacy'], 'disable_comment' => !$row['comment'], 'disable_duet' => !$row['duet'], 'disable_stitch' => !$row['stitch'], 'brand_organic_toggle' => $row['ownBrand'], 'brand_content_toggle' => $row['partnerBrand'], 'is_aigc' => $row['synthetic']];
    }

    /** @param list<int> $ids @param array<int|string, array<string, mixed>> $rows */
    public function enqueue(User $owner, array $ids, array $rows, string $key, string $expectedAccount): void
    {
        $this->lock->run((int) $owner->getId(), function () use ($owner, $ids, $rows, $key, $expectedAccount): void {
            $this->em->wrapInTransaction(function () use ($owner, $ids, $rows, $key, $expectedAccount): void {
                $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
                if ($this->em->getRepository(TikTokPublication::class)->count(['owner' => $owner, 'submissionKey' => $key])) {
                    return;
                }
                $connection = $this->connection($owner);
                if ($connection) {
                    $this->em->refresh($connection);
                }
                if (!$this->ready($owner) || !$connection || $connection->getOpenId() !== $expectedAccount) {
                    throw new PublicationException('Reconnecte TikTok et recharge la préparation.');
                }
                if (!$ids || count($ids) > 5 || count(array_unique($ids)) !== count($ids) || $this->em->getRepository(TikTokPublication::class)->count(['owner' => $owner, 'status' => TikTokPublication::ACTIVE]) + count($ids) > 100) {
                    throw new PublicationException('Sélectionne jusqu’à 5 vidéos et termine les envois en attente.');
                }
                $creator = $this->api->creator($connection);
                foreach ($ids as $index => $id) {
                    $video = $this->videos->owned($id, $owner) ?? throw new PublicationException('Une vidéo est indisponible.');
                    $this->em->refresh($video, LockMode::PESSIMISTIC_WRITE);
                    $this->validateVideo($video, $creator['max_video_post_duration_sec']);
                    $job = new TikTokPublication($owner, $video, $connection, $key, $creator['creator_nickname'], $this->options($rows[$id] ?? [], $creator));
                    $this->em->persist($job);
                    $this->em->flush();
                    $this->bus->dispatch(new PublishTikTok((int) $job->getId()), [new DelayStamp($index * 15000)]);
                }
            });
        });
    }

    public function resume(TikTokPublication $job): void
    {
        $this->lock->run((int) $job->getOwner()->getId(), function () use ($job): void {
            $this->em->wrapInTransaction(function () use ($job): void {
                $this->em->refresh($job, LockMode::PESSIMISTIC_WRITE);
                if ('paused' !== $job->getStatus()) {
                    return;
                }
                if (!$this->ready($job->getOwner()) || $this->connection($job->getOwner())?->getOpenId() !== $job->getOpenId()) {
                    throw new PublicationException('Reconnecte le compte TikTok destinataire.');
                }
                if ($job->isInitAttempted() && !$job->getPublishId()) {
                    throw new PublicationException('Vérifie ton profil TikTok : le résultat de cet envoi est incertain.');
                }
                $job->resume();
                if (!$job->getPublishId()) {
                    $job->change('queued');
                }
                $this->em->flush();
                $this->bus->dispatch(new PublishTikTok((int) $job->getId()));
            });
        });
    }
}
