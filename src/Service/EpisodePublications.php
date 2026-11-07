<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\PublicationDraft;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class EpisodePublications
{
    public function __construct(private EntityManagerInterface $em, private PublicationDrafts $drafts, private PublicationCalendar $calendar, private PublicationLock $lock, private \Symfony\Component\Clock\ClockInterface $clock, private PublicationEditorial $editorial, private SeriesPublicationSettings $publicationSettings)
    {
    }

    public function prepare(User $owner, AiEpisode $episode, int $revision, ?int $videoId = null): PublicationDraft
    {
        $prepared = $this->lock->run((int) $owner->getId(), fn (): PublicationDraft => $this->em->getConnection()->transactional(function () use ($owner, $episode, $revision, $videoId): PublicationDraft {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
            if ($episode->getOwner()->getId() !== $owner->getId() || 'ready' !== $episode->getFlowState()) {
                throw new PublicationException('Valide d’abord une version disponible de cet épisode.');
            }
            if ($episode->getRevision() !== $revision) {
                throw new PublicationException('La version de cet épisode a changé. Recharge la page avant de préparer sa publication.');
            }
            if (null !== $videoId) {
                $displayed = $episode->getApprovedVideo() ?? $episode->getAssembly()?->getOutput();
                if (!$displayed || $displayed->getId() !== $videoId || $displayed->getOwner()->getId() !== $owner->getId()) {
                    throw new PublicationException('La version affichée a changé. Recharge la vidéo avant de préparer sa publication.');
                }
                if (!$episode->getApprovedVideo()) {
                    $episode->approve($displayed);
                }
            }
            if (!$episode->getApprovedVideo()) {
                throw new PublicationException('Valide d’abord une version disponible de cet épisode.');
            }
            $video = $episode->getApprovedVideo();
            $previous = $this->em->getRepository(PublicationDraft::class)->findBy(['episode' => $episode, 'owner' => $owner], ['id' => 'DESC']);
            foreach ($previous as $draft) {
                if ($draft->videoIds() === [$video->getId()]) {
                    return $draft;
                }
                if ('scheduled' === $draft->getStatus()) {
                    throw new PublicationException('Une ancienne version est encore programmée. Annule sa programmation avant de préparer la nouvelle version.');
                }
            }
            $title = ($episode->isStandalone() ? '' : $episode->getSeries()->getName().' · ').$episode->getTitle();
            $occurrence = $this->em->getRepository(\App\Entity\SeriesOccurrence::class)->findOneBy(['episode' => $episode]);
            $settings = $episode->isStandalone() ? [] : $episode->getSeries()->getPublicationSettings();
            $networks = $occurrence ? $occurrence->getNetworks() : ($settings['networks'] ?? []);
            $content = [];
            $accounts = [];
            $snapshot = $occurrence ? $occurrence->getAccounts() : ($settings['accounts'] ?? []);
            foreach ($networks as $network) {
                // A legacy occurrence without a snapshot requires a human choice, never a current-account fallback.
                $accounts[$network] = $snapshot[$network] ?? null;
                $content[$network]['videos'][$video->getId()] = match ($network) {
                    'youtube' => ['title' => mb_substr($episode->getTitle(), 0, 100), 'description' => $occurrence?->getSummary() ?? '', 'privacy' => 'public', 'audience' => 'yes', 'synthetic' => true],
                    'instagram' => ['caption' => $occurrence?->getSummary() ?: $episode->getTitle(), 'shareToFeed' => true, 'synthetic' => true],
                    'tiktok' => ['caption' => $occurrence?->getSummary() ?: $episode->getTitle(), 'privacy' => null, 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => true],
                    default => throw new \LogicException('Unknown stored network.'),
                };
            }
            $draft = $this->drafts->save($owner, [(int) $video->getId()], $networks, $content, $accounts, mb_substr($title, 0, 120));
            if ($occurrence) {
                $at = $occurrence->getScheduledAt();
                $zone = $occurrence->getTimezone();
                // This is only a proposed date. Normal confirmation still rejects expired slots.
                $draft->plan($at, $zone, $at->setTimezone(new \DateTimeZone($zone))->format('Y-m-d\TH:i'), $this->clock->now());
            }
            $draft->attachEpisode($episode);
            $this->em->flush();

            return $draft;
        }));
        if ('draft' === $prepared->getStatus()) {
            $this->editorial->suggestMissing($owner, $prepared);
        }

        return $prepared;
    }

    public function prepareVideo(User $owner, \App\Entity\VideoSeriesEpisode $episode, int $revision): PublicationDraft
    {
        return $this->lock->run((int) $owner->getId(), fn (): PublicationDraft => $this->em->getConnection()->transactional(function () use ($owner, $episode, $revision): PublicationDraft {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
            $series = $episode->getSeries();
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            $video = $episode->getVideo();
            if ($series->getOwner()->getId() !== $owner->getId() || $video->getOwner()->getId() !== $owner->getId() || !$episode->isActive()) {
                throw new PublicationException('Cet épisode est indisponible.');
            }
            if ($series->getRevision() !== $revision) {
                throw new PublicationException('La série a changé. Recharge ses épisodes avant de préparer la publication.');
            }
            $previous = $this->em->getRepository(PublicationDraft::class)->findOneBy(['videoEpisode' => $episode, 'owner' => $owner], ['id' => 'DESC']);
            if ($previous) {
                return $previous;
            }
            $settings = $series->getPublicationSettings();
            $networks = $settings['networks'] ?? [];
            $accounts = [];
            $content = [];
            foreach ($networks as $network) {
                $accounts[$network] = $settings['accounts'][$network] ?? null;
                $content[$network]['videos'][$video->getId()] = match ($network) {
                    'youtube' => ['title' => mb_substr($video->getTitle(), 0, 100), 'description' => '', 'privacy' => 'public', 'audience' => 'yes', 'synthetic' => false],
                    'instagram' => ['caption' => '', 'shareToFeed' => true, 'synthetic' => false],
                    'tiktok' => ['caption' => '', 'privacy' => null, 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => false],
                    default => throw new \LogicException('Unknown stored network.'),
                };
            }
            $draft = $this->drafts->save($owner, [(int) $video->getId()], $networks, $content, $accounts, mb_substr($series->getName().' · '.$video->getTitle(), 0, 120));
            $draft->attachVideoEpisode($episode);
            $this->em->flush();

            return $draft;
        }));
    }

    /** @param list<AiEpisode> $episodes
     * @return array<int,list<array<string,mixed>>>
     */
    public function summaries(array $episodes): array
    {
        if (!$episodes) {
            return [];
        }
        $drafts = $this->em->createQueryBuilder()->select('d', 'items')->from(PublicationDraft::class, 'd')
            ->leftJoin('d.items', 'items')->where('d.owner = :owner AND d.episode IN (:episodes)')
            ->setParameter('owner', $episodes[0]->getOwner())->setParameter('episodes', $episodes)
            ->orderBy('d.id', 'DESC')->getQuery()->getResult();

        return $this->describe($drafts);
    }

    /** @param list<\App\Entity\VideoSeriesEpisode> $episodes @return array<int,list<array<string,mixed>>> */
    public function videoSummaries(array $episodes): array
    {
        if (!$episodes) {
            return [];
        }
        $drafts = $this->em->createQueryBuilder()->select('d', 'items')->from(PublicationDraft::class, 'd')->leftJoin('d.items', 'items')->where('d.owner = :owner AND d.videoEpisode IN (:episodes)')->setParameter('owner', $episodes[0]->getSeries()->getOwner())->setParameter('episodes', $episodes)->orderBy('d.id', 'DESC')->getQuery()->getResult();

        return $this->describe($drafts, true);
    }

    /** @param list<PublicationDraft> $drafts @return array<int,list<array<string,mixed>>> */
    private function describe(array $drafts, bool $videos = false): array
    {
        $snapshot = $this->calendar->snapshot($drafts);
        $accountNames = $drafts ? array_map('array_flip', $this->publicationSettings->choices($drafts[0]->getOwner())) : [];
        $states = $snapshot['states'];
        $result = [];
        $labels = ['queued' => 'En attente', 'uploading' => 'Envoi en cours', 'processing' => 'Traitement en cours', 'published' => 'Publiée', 'failed' => 'Échec', 'paused' => 'En pause', 'review' => 'À vérifier', 'cancelled' => 'Annulée'];
        foreach ($drafts as $draft) {
            $episode = $videos ? $draft->getVideoEpisode() : $draft->getEpisode();
            if (!$episode) {
                continue;
            }
            $destinations = [];
            foreach ($draft->getNetworks() as $network) {
                $jobStatus = $snapshot['destinations'][$draft->getSubmissionKey()][$network] ?? null;
                $status = $jobStatus ? ($labels[$jobStatus] ?? 'En cours') : PublicationCalendar::LABELS[$states[$draft->getId()]];
                $identity = $draft->getAccounts()[$network] ?? null;
                $destinations[] = ['name' => MultiNetworkPublishing::LABELS[$network], 'status' => $status, 'account' => $identity ? ($accountNames[$network][$identity] ?? 'Compte enregistré indisponible') : ('draft' === $draft->getStatus() ? 'Compte à choisir' : 'Compte non renseigné dans la préparation')];
            }
            $source = $episode instanceof AiEpisode ? $episode->getApprovedVideo() : $episode->getVideo();
            $result[$episode->getId()][] = ['draft' => $draft, 'label' => PublicationCalendar::LABELS[$states[$draft->getId()]], 'current' => $draft->videoIds() === [$source?->getId()], 'destinations' => $destinations];
        }

        return $result;
    }
}
