<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\InstagramPublication;
use App\Entity\PublicationDraft;
use App\Entity\TikTokPublication;
use App\Entity\User;
use App\Entity\YouTubePublication;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class EpisodePublications
{
    public function __construct(private EntityManagerInterface $em, private PublicationDrafts $drafts, private PublicationCalendar $calendar, private PublicationLock $lock, private MultiNetworkPublishing $publishing, private \Symfony\Component\Clock\ClockInterface $clock)
    {
    }

    public function prepare(User $owner, AiEpisode $episode, int $revision): PublicationDraft
    {
        return $this->lock->run((int) $owner->getId(), fn (): PublicationDraft => $this->em->getConnection()->transactional(function () use ($owner, $episode, $revision): PublicationDraft {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
            if ($episode->getOwner()->getId() !== $owner->getId() || 'ready' !== $episode->getFlowState() || !$episode->getApprovedVideo()) {
                throw new PublicationException('Valide d’abord une version disponible de cet épisode.');
            }
            if ($episode->getRevision() !== $revision) {
                throw new PublicationException('La version de cet épisode a changé. Recharge la page avant de préparer sa publication.');
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
            $networks = $occurrence?->getNetworks() ?? [];
            $content = [];
            $accounts = [];
            $destinations = $networks ? $this->publishing->destinations($owner) : [];
            foreach ($networks as $network) {
                $accounts[$network] = $destinations[$network]['account'];
                $content[$network]['videos'][$video->getId()] = match ($network) {
                    'youtube' => ['title' => mb_substr($episode->getTitle(), 0, 100), 'description' => $occurrence->getSummary() ?? '', 'privacy' => 'public', 'audience' => 'yes', 'synthetic' => true],
                    'instagram' => ['caption' => $occurrence->getSummary() ?: $episode->getTitle(), 'shareToFeed' => true, 'synthetic' => true],
                    'tiktok' => ['caption' => $occurrence->getSummary() ?: $episode->getTitle(), 'privacy' => null, 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => true],
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
    }

    /** @param list<AiEpisode> $episodes
     * @return array<int,list<array<string,mixed>>>
     */
    public function summaries(array $episodes): array
    {
        if (!$episodes) {
            return [];
        }
        $drafts = $this->em->getRepository(PublicationDraft::class)->findBy(['owner' => $episodes[0]->getOwner(), 'episode' => $episodes], ['id' => 'DESC']);
        $states = $this->calendar->states($drafts);
        $result = [];
        $labels = ['queued' => 'En attente', 'uploading' => 'Envoi en cours', 'processing' => 'Traitement en cours', 'published' => 'Publiée', 'failed' => 'Échec', 'paused' => 'En pause', 'review' => 'À vérifier', 'cancelled' => 'Annulée'];
        foreach ($drafts as $draft) {
            $episode = $draft->getEpisode();
            $destinations = [];
            foreach ($draft->getNetworks() as $network) {
                $entity = ['youtube' => YouTubePublication::class, 'instagram' => InstagramPublication::class, 'tiktok' => TikTokPublication::class][$network];
                $jobs = $draft->getSubmissionKey() ? $this->em->getRepository($entity)->findBy(['owner' => $draft->getOwner(), 'submissionKey' => $draft->getSubmissionKey()]) : [];
                $status = $jobs ? ($labels[$jobs[0]->getStatus()] ?? 'En cours') : PublicationCalendar::LABELS[$states[$draft->getId()]];
                $destinations[] = ['name' => MultiNetworkPublishing::LABELS[$network], 'status' => $status];
            }
            $result[$episode->getId()][] = ['draft' => $draft, 'label' => PublicationCalendar::LABELS[$states[$draft->getId()]], 'current' => $draft->videoIds() === [$episode->getApprovedVideo()?->getId()], 'destinations' => $destinations];
        }

        return $result;
    }
}
