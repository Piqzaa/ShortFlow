<?php

namespace App\Service;

use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Entity\VideoSeries;
use App\Entity\VideoSeriesEpisode;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class VideoSeriesSchedule
{
    public const LIMIT = 20;

    public function __construct(private EntityManagerInterface $em, private PublicationLock $lock, private PublicationDrafts $service, private PublicationTime $time, private ClockInterface $clock)
    {
    }

    /** @param list<VideoSeriesEpisode> $episodes
     * @param array<int,list<array<string,mixed>>> $summaries
     *
     * @return array<int,PublicationDraft>
     */
    public function candidates(array $episodes, array $summaries): array
    {
        $candidates = [];
        foreach ($episodes as $episode) {
            $episodeId = $episode->getId();
            foreach ($summaries[$episodeId] ?? [] as $publication) {
                if (!$publication['current']) {
                    continue;
                }
                $draft = $publication['draft'];
                if ('draft' === $draft->getStatus() && $draft->getScheduledAt() && $draft->getNetworks()
                    && !array_diff($draft->getNetworks(), array_keys(array_filter($draft->getAccounts())))) {
                    try {
                        $this->validateDate($draft);
                        $candidates[$episodeId] = $draft;
                    } catch (PublicationException) {
                        // The composer is still available to update an expired or invalid slot.
                    }
                }
                break;
            }
        }

        return $candidates;
    }

    /** @param array<int,PublicationDraft> $candidates
     * @param array<mixed> $selected
     *
     * @return array{ready:array<int,array<string,mixed>>,issues:list<array{draft:PublicationDraft,messages:list<string>}>}
     */
    public function preview(array $candidates, array $selected): array
    {
        if (count($selected) < 2 || count($selected) > self::LIMIT) {
            throw new PublicationException('Sélectionne de 2 à '.self::LIMIT.' épisodes datés.');
        }
        $ids = [];
        foreach ($selected as $id) {
            if (!is_string($id) || !ctype_digit($id) || !isset($candidates[(int) $id]) || isset($ids[(int) $id])) {
                throw new PublicationException('La sélection a changé. Vérifie les brouillons et leurs créneaux.');
            }
            $ids[(int) $id] = true;
        }
        $ready = $issues = [];
        foreach (array_intersect_key($candidates, $ids) as $episodeId => $draft) {
            $review = $this->service->review($draft);
            if (!$review['ready']) {
                $issues[] = ['draft' => $draft, 'messages' => array_merge([], ...array_values($review['issues'])) ?: ['Vérifie les comptes, les textes et les réglages.']];
                continue;
            }
            $ready[$episodeId] = ['draft' => $draft, 'destinations' => $review['destinations'], 'videos' => array_map(static fn ($item): array => ['id' => $item->getVideoId(), 'title' => $item->getTitle(), 'source' => $item->getSource()], $draft->getItems()->toArray())];
        }

        return ['ready' => $ready, 'issues' => $issues];
    }

    /** @param array<int,array{draft:int,revision:int}> $reviewed
     *
     * @return non-empty-list<int>
     */
    public function confirm(User $owner, VideoSeries $series, int $seriesRevision, array $reviewed): array
    {
        if (!$reviewed || count($reviewed) > self::LIMIT) {
            throw new PublicationException('Vérifie un nouvel aperçu avant de confirmer.');
        }

        return $this->lock->run((int) $owner->getId(), fn (): array => $this->em->getConnection()->transactional(function () use ($owner, $series, $seriesRevision, $reviewed): array {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $series = $this->em->getRepository(VideoSeries::class)->findOneBy(['id' => $series->getId(), 'owner' => $owner]) ?? throw new PublicationException('Cette série est indisponible.');
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            if ($series->getOwner()->getId() !== $owner->getId() || $series->getRevision() !== $seriesRevision) {
                throw new PublicationException('La série a changé. Refais l’aperçu avant de confirmer.');
            }
            $drafts = [];
            foreach ($reviewed as $episodeId => $choice) {
                $episode = $this->em->find(VideoSeriesEpisode::class, $episodeId) ?? throw new PublicationException('Un épisode confirmé n’est plus disponible.');
                $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
                if (!$episode->isActive() || $episode->getSeries()->getId() !== $series->getId() || $episode->getVideo()->getOwner()->getId() !== $owner->getId()) {
                    throw new PublicationException('Un épisode confirmé est indisponible. Refais l’aperçu.');
                }
                $draft = $this->service->editable($owner, $choice['draft'], $choice['revision']);
                if ($draft->getVideoEpisode()?->getId() !== $episodeId || $draft->videoIds() !== [$episode->getVideo()->getId()]) {
                    throw new PublicationException('Un brouillon confirmé n’est plus celui de son épisode. Refais l’aperçu.');
                }
                $this->service->checkComplete($draft);
                $drafts[] = $draft;
            }
            foreach ($drafts as $draft) {
                $this->validateDate($draft);
            }
            foreach ($drafts as $draft) {
                $draft->arm($this->clock->now());
            }
            $this->em->flush();
            foreach ($drafts as $draft) {
                $this->service->wake($draft);
            }

            return array_keys($reviewed);
        }));
    }

    private function validateDate(PublicationDraft $draft): void
    {
        $date = $draft->getScheduledAt() ?? throw new PublicationException('Choisis un créneau dans le brouillon.');
        $this->time->validateFuture($date);
    }
}
