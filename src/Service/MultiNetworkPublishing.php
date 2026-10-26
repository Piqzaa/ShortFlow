<?php

namespace App\Service;

use App\Entity\InstagramPublication;
use App\Entity\TikTokPublication;
use App\Entity\User;
use App\Entity\YouTubePublication;
use App\Repository\VideoAssetRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MultiNetworkPublishing
{
    public const LABELS = ['youtube' => 'YouTube', 'instagram' => 'Instagram Reels', 'tiktok' => 'TikTok'];
    private const ENTITIES = ['youtube' => YouTubePublication::class, 'instagram' => InstagramPublication::class, 'tiktok' => TikTokPublication::class];

    public function __construct(private EntityManagerInterface $em, private VideoAssetRepository $videos, private PublicationLock $lock, private YouTubePublishing $youtube, private YouTubeApi $youtubeApi, private InstagramPublishing $instagram, private TikTokPublishing $tiktok, private TikTokApi $tiktokApi)
    {
    }

    /** @return array<string, array<string, mixed>> */
    public function destinations(User $owner, array $accounts = []): array
    {
        $youtube = $this->youtube->connection($owner, $accounts['youtube'] ?? null);
        $instagram = $this->instagram->connection($owner, $accounts['instagram'] ?? null);
        $tiktok = $this->tiktok->connection($owner, $accounts['tiktok'] ?? null);
        foreach ([$youtube, $instagram, $tiktok] as $connection) {
            if ($connection) {
                // Recheck identities after taking the owner lock, including cached ORM objects.
                $this->em->refresh($connection);
            }
        }

        return [
            'youtube' => ['label' => self::LABELS['youtube'], 'account' => $youtube?->getChannelId(), 'name' => $youtube?->getChannelTitle(), 'ready' => $this->youtubeApi->isReady() && null !== $youtube],
            'instagram' => ['label' => self::LABELS['instagram'], 'account' => $instagram?->getInstagramId(), 'name' => $instagram ? '@'.$instagram->getUsername() : null, 'ready' => $this->instagram->ready($owner, $accounts['instagram'] ?? null)],
            'tiktok' => ['label' => self::LABELS['tiktok'], 'account' => $tiktok?->getOpenId(), 'name' => $tiktok?->getDisplayName(), 'ready' => $this->tiktok->ready($owner, $accounts['tiktok'] ?? null)],
        ];
    }

    /** @param list<int> $ids @param list<string> $networks @param array<string, ?string> $accounts @param array<string, array<string, mixed>> $content @return array<string, mixed> */
    public function context(User $owner, array $ids, array $networks, array $accounts, array $content = []): array
    {
        $destinations = $this->destinations($owner, $accounts);
        $selected = [];
        $issues = [];
        $creator = [];
        foreach ($networks as $network) {
            if (!$destinations[$network]['ready']) {
                $issues[$network][] = 'tiktok' === $network ? $this->tiktok->readinessIssue($owner, $accounts['tiktok'] ?? null) : 'Choisis un compte connecté et disponible dans les destinations avant de publier.';
            }
            if (($accounts[$network] ?? null) !== $destinations[$network]['account']) {
                $issues[$network][] = 'Le compte destinataire a changé. Reviens au choix des réseaux pour le vérifier.';
            }
        }
        if (in_array('tiktok', $networks, true) && $destinations['tiktok']['ready'] && !isset($issues['tiktok'])) {
            try {
                $connection = $this->tiktok->connection($owner, $accounts['tiktok'] ?? null);
                if ($connection) {
                    $creator = $this->tiktokApi->creator($connection);
                    $destinations['tiktok']['name'] = $creator['creator_nickname'];
                }
            } catch (PublicationException $error) {
                $issues['tiktok'][] = $error->getMessage();
            }
        }
        if (in_array('tiktok', $networks, true) && count($ids) > 5) {
            $issues['tiktok'][] = 'TikTok accepte jusqu’à 5 vidéos par préparation. Réduis la sélection ou retire TikTok.';
        }
        if ($creator) {
            foreach ($content['tiktok']['videos'] ?? [] as $row) {
                try {
                    $this->tiktok->options($row, $creator);
                } catch (PublicationException $error) {
                    $issues['tiktok'][] = $error->getMessage().' Reviens aux textes et réglages avant de confirmer.';
                }
            }
        }
        foreach ($ids as $id) {
            $video = $this->videos->owned($id, $owner) ?? throw new PublicationException('Une vidéo sélectionnée est indisponible.');
            $selected[] = $video;
            foreach ($networks as $network) {
                try {
                    if ('youtube' === $network) {
                        $this->youtube->validateVideo($video);
                    } elseif ('instagram' === $network) {
                        $this->instagram->validateVideo($video);
                    } elseif ('tiktok' === $network) {
                        $this->tiktok->validateVideo($video, $creator['max_video_post_duration_sec'] ?? 600);
                    }
                } catch (PublicationException $error) {
                    $issues[$network][] = $video->getTitle().' : '.$error->getMessage();
                }
            }
        }

        return ['videos' => $selected, 'destinations' => $destinations, 'issues' => $issues, 'creator' => $creator, 'ready' => [] === $issues && [] !== $networks];
    }

    /** @param list<int> $ids @param list<string> $networks @param array<string, array<string, mixed>> $content @param array<string, ?string> $accounts */
    public function enqueue(User $owner, array $ids, array $networks, array $content, string $key, array $accounts, ?callable $beforeCommit = null): void
    {
        if (!$ids || count($ids) > 20 || count(array_unique($ids)) !== count($ids) || !$networks || count(array_unique($networks)) !== count($networks) || array_diff($networks, array_keys(self::LABELS))) {
            throw new PublicationException('Vérifie les vidéos et les réseaux sélectionnés.');
        }
        $this->lock->run((int) $owner->getId(), function () use ($owner, $ids, $networks, $content, $key, $accounts, $beforeCommit): void {
            $existing = [];
            foreach ($networks as $network) {
                $existing[$network] = $this->em->getRepository(self::ENTITIES[$network])->count(['owner' => $owner, 'submissionKey' => $key]);
            }
            if (array_sum($existing) > 0) {
                if (count(array_filter($existing, static fn (int $count): bool => $count === count($ids))) === count($networks)) {
                    return;
                }
                throw new PublicationException('Ce lot a déjà été engagé. Consulte Publications avant toute nouvelle tentative.');
            }
            $context = $this->context($owner, $ids, $networks, $accounts, $content);
            if (!$context['ready']) {
                throw new PublicationException('Le lot reste en préparation : vérifie les comptes et la compatibilité de chaque destination.');
            }
            // Nested network transactions use savepoints on the same connection as Messenger.
            // A failure on any destination rolls back every job and every queued message.
            $this->em->getConnection()->transactional(function () use ($owner, $ids, $networks, $content, $key, $accounts, $beforeCommit): void {
                foreach ($networks as $network) {
                    $publisher = match ($network) {
                        'youtube' => $this->youtube,
                        'instagram' => $this->instagram,
                        'tiktok' => $this->tiktok,
                        default => throw new PublicationException('Réseau indisponible.'),
                    };
                    $publisher->enqueue($owner, $ids, $content[$network]['videos'], $key, (string) $accounts[$network]);
                }
                if ($beforeCommit) {
                    $beforeCommit();
                }
            });
        });
    }
}
