<?php

namespace App\Service;

use App\Entity\InstagramPublication;
use App\Entity\TikTokPublication;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubePublication;
use Doctrine\ORM\EntityManagerInterface;

final class PublicationRetry
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return array{video: VideoAsset, content: array<string, mixed>} */
    public function preparation(User $owner, string $network, int $id): array
    {
        $entity = match ($network) {
            'youtube' => YouTubePublication::class,
            'instagram' => InstagramPublication::class,
            'tiktok' => TikTokPublication::class,
            default => throw new PublicationException('Destination indisponible.'),
        };
        $job = $this->em->getRepository($entity)->findOneBy(['id' => $id, 'owner' => $owner]);
        if ($job) {
            $this->em->refresh($job);
        }
        $video = $job?->getSource();
        if (!$job || 'failed' !== $job->getStatus() || !$video || $video->getOwner()->getId() !== $owner->getId()) {
            throw new PublicationException('Seul un envoi en échec confirmé avec sa vidéo source peut être préparé à nouveau. Consulte le suivi avant de continuer.');
        }
        if ($job instanceof YouTubePublication) {
            $row = ['title' => $job->getTitle(), 'description' => $job->getDescription(), 'privacy' => $job->getPrivacy(), 'audience' => $job->isMadeForKids() ? 'yes' : 'no', 'synthetic' => $job->isSynthetic()];
        } elseif ($job instanceof InstagramPublication) {
            $row = ['caption' => $job->getCaption(), 'shareToFeed' => $job->isShareToFeed(), 'synthetic' => $job->isSynthetic()];
        } else {
            $options = $job->getOptions();
            // Visibility must always be selected again from the latest creator capabilities.
            $row = ['caption' => $options['title'], 'privacy' => null, 'comment' => !$options['disable_comment'], 'duet' => !$options['disable_duet'], 'stitch' => !$options['disable_stitch'], 'disclosure' => $options['brand_organic_toggle'] || $options['brand_content_toggle'], 'ownBrand' => $options['brand_organic_toggle'], 'partnerBrand' => $options['brand_content_toggle'], 'synthetic' => $options['is_aigc']];
        }

        return ['video' => $video, 'content' => [$network => ['videos' => [$video->getId() => $row]]]];
    }
}
