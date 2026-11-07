<?php

namespace App\Service;

use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Entity\VideoSeries;
use App\Entity\VideoSeriesEpisode;
use App\Repository\PublicationDraftRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Grouped scheduling for a series of imported videos. Every confirmed episode
 * keeps its own slot; the whole confirmation happens in one owner-locked
 * transaction so a replay can never program a single episode twice.
 */
final class VideoSeriesSchedule
{
    public const LIMIT = 20;

    public function __construct(private EntityManagerInterface $em, private PublicationLock $lock, private PublicationDraftRepository $drafts, private PublicationDrafts $service)
    {
    }

    /** @return list<array{episode:VideoSeriesEpisode,minute:int,draft:?PublicationDraft,kind:string}> */
    public function rows(VideoSeries $series): array
    {
        $episodes = $this->order($series);
        $stored = [];
        if ($episodes) {
            $loaded = $this->em->createQueryBuilder()->select('d', 'items')->from(PublicationDraft::class, 'd')->leftJoin('d.items', 'items')->where('d.owner = :owner AND d.videoEpisode IN (:episodes)')->setParameter('owner', $series->getOwner())->setParameter('episodes', $episodes)->orderBy('d.id', 'DESC')->getQuery()->getResult();
            foreach ($loaded as $draft) {
                /** @var PublicationDraft $draft */
                $episode = $draft->getVideoEpisode();
                if ($episode) {
                    $stored[$episode->getId()] ??= $draft;
                }
            }
        }
        $rows = [];
        foreach ($episodes as $i => $episode) {
            $draft = $stored[$episode->getId()] ?? null;
            $rows[] = ['episode' => $episode, 'minute' => $i + 1, 'draft' => $draft, 'kind' => $this->kind($draft)];
        }

        return $rows;
    }

    /**
     * Verify the reviewed selection against the current state. Structural problems abort the whole preview;
     * a bad slot or an incomplete preparation only excludes its episode from the confirmable subset.
     *
     * @param array<int,array{draft:int,revision:int,local:?string,zone:string}> $picked
     *
     * @return array<string,mixed>
     */
    public function preview(VideoSeries $series, int $seriesRevision, array $picked): array
    {
        $owner = $series->getOwner();
        if (!$picked) {
            throw new PublicationException('Coche au moins un épisode programmable.');
        }
        if (count($picked) > self::LIMIT) {
            throw new PublicationException('Choisis de 1 à '.self::LIMIT.' épisodes par préparation groupée.');
        }
        $byId = [];
        foreach ($this->order($series) as $i => $episode) {
            $byId[$episode->getId()] = $i + 1;
        }
        $ready = [];
        $issues = [];
        foreach ($picked as $episodeId => $choice) {
            if (!isset($byId[$episodeId])) {
                throw new PublicationException('Un épisode choisi n’appartient plus à cette série. Recharge ses épisodes.');
            }
            $episode = $this->em->find(VideoSeriesEpisode::class, $episodeId) ?? throw new PublicationException('Un épisode choisi n’est plus disponible.');
            $draft = $this->drafts->owned($choice['draft'], $owner) ?? throw new PublicationException('Cet épisode n’a plus de brouillon. Recharge ses épisodes.');
            if ($draft->getVideoEpisode()?->getId() !== $episodeId || $draft->getRevision() !== $choice['revision'] || 'draft' !== $draft->getStatus()) {
                throw new PublicationException('Un épisode a changé depuis l’aperçu. Refais l’aperçu avant de confirmer.');
            }
            $slot = ['episode' => $episode, 'minute' => $byId[$episodeId], 'draft' => $draft, 'local' => $choice['local'], 'zone' => $choice['zone'], 'videos' => array_map(static fn (\App\Entity\PublicationDraftItem $item): array => ['id' => $item->getVideoId(), 'title' => $item->getTitle(), 'source' => $item->getSource()], $draft->getItems()->toArray())];
            try {
                $slot['at'] = $this->service->resolved($choice['local'], $choice['zone']);
            } catch (PublicationException $error) {
                $slot['issue'] = $error->getMessage();
            }
            $review = $this->service->review($draft);
            $slot['review'] = $review;
            if ($slot['issue'] ?? false) {
                $issues[] = $slot;
                continue;
            }
            if ($review['ready']) {
                $ready[] = $slot;
            } else {
                $issues[] = $slot + ['issue' => 'La préparation reste incomplète : vérifie les comptes, les textes et les réglages.'];
            }
        }

        return ['ready' => $ready, 'issues' => $issues];
    }

    /**
     * Arm every reviewed and dated preparation of the subset in one owner-locked transaction.
     * An invalid or changed preparation refuses the complete reviewed subset.
     *
     * @param array<int,array{draft:int,revision:int,local:?string,zone:string}> $picked
     *
     * @return non-empty-list<int>
     */
    public function confirm(User $owner, VideoSeries $series, int $seriesRevision, array $picked): array
    {
        if (!$picked) {
            throw new PublicationException('Coche au moins un épisode programmable.');
        }
        if (count($picked) > self::LIMIT) {
            throw new PublicationException('Choisis de 1 à '.self::LIMIT.' épisodes par préparation groupée.');
        }

        return $this->lock->run((int) $owner->getId(), fn (): array => $this->em->getConnection()->transactional(function () use ($owner, $series, $seriesRevision, $picked): array {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            if ($series->getOwner()->getId() !== $owner->getId() || $series->getRevision() !== $seriesRevision) {
                throw new PublicationException('La série a changé. Refais l’aperçu avant de confirmer.');
            }
            $slots = [];
            foreach ($picked as $episodeId => $choice) {
                $episode = $this->em->find(VideoSeriesEpisode::class, $episodeId) ?? throw new PublicationException('Un épisode confirmé n’est plus disponible.');
                $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
                if (!$episode->isActive() || $episode->getSeries()->getId() !== $series->getId() || $episode->getVideo()->getOwner()->getId() !== $series->getOwner()->getId()) {
                    throw new PublicationException('Un épisode confirmé est indisponible. Refais l’aperçu.');
                }
                $draft = $this->drafts->owned($choice['draft'], $owner) ?? throw new PublicationException('Cet épisode n’a plus de brouillon. Refais l’aperçu.');
                $this->em->refresh($draft, LockMode::PESSIMISTIC_WRITE);
                if ($draft->getVideoEpisode()?->getId() !== $episodeId || $draft->videoIds() !== [$episode->getVideo()->getId()]) {
                    throw new PublicationException('Un brouillon confirmé n’est plus celui de son épisode. Refais l’aperçu.');
                }
                if ('scheduled' === $draft->getStatus()) {
                    throw new PublicationException('Un épisode confirmé est déjà programmé. Le rejeu n’a rien modifié.');
                }
                if ('draft' !== $draft->getStatus() || $draft->getRevision() !== $choice['revision']) {
                    throw new PublicationException('Un épisode a changé depuis l’aperçu. Refais l’aperçu avant de confirmer.');
                }
                $this->service->checkComplete($draft);
                $slots[] = ['draft' => $draft, 'local' => $choice['local'], 'zone' => $choice['zone']];
            }
            $dates = [];
            foreach ($slots as $index => $slot) {
                $dates[$index] = $this->service->resolved($slot['local'], $slot['zone']);
            }
            foreach ($slots as $index => $slot) {
                $this->service->schedule($slot['draft'], $dates[$index], (string) $slot['local'], $slot['zone']);
            }
            $this->em->flush();
            foreach ($slots as $slot) {
                $this->service->wake($slot['draft']);
            }

            return array_keys($picked);
        }));
    }

    private function kind(?PublicationDraft $draft): string
    {
        if (!$draft) {
            return 'to_prepare';
        }

        return match ($draft->getStatus()) {
            'draft' => 'plan',
            'scheduled' => 'scheduled',
            'dispatched' => 'sent',
            'blocked', 'missed' => 'attention',
            default => 'history',
        };
    }

    /** @return list<VideoSeriesEpisode> */
    private function order(VideoSeries $series): array
    {
        return $this->em->createQueryBuilder()->select('e', 'v')->from(VideoSeriesEpisode::class, 'e')->join('e.video', 'v')->where('e.series = :series')->andWhere('e.active = true')->setParameter('series', $series)->orderBy('e.position', 'ASC')->getQuery()->getResult();
    }
}
