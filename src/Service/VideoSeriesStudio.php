<?php

namespace App\Service;

use App\Entity\InstagramPublication;
use App\Entity\PublicationDraft;
use App\Entity\TikTokPublication;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\VideoSeries;
use App\Entity\VideoSeriesEpisode;
use App\Entity\YouTubePublication;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class VideoSeriesStudio
{
    public function __construct(private EntityManagerInterface $em, private SeriesPublicationSettings $publicationSettings, private PublicationCalendar $calendar, private PublicationLock $lock)
    {
    }

    public function owned(int $id, User $owner): ?VideoSeries
    {
        return $this->em->getRepository(VideoSeries::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /** @return list<VideoSeriesEpisode> */
    public function episodes(VideoSeries $series): array
    {
        return $this->em->createQueryBuilder()->select('e', 'v')->from(VideoSeriesEpisode::class, 'e')->join('e.video', 'v')->where('e.series = :series')->andWhere('e.active = true')->andWhere('v.owner = :owner')->setParameter('series', $series)->setParameter('owner', $series->getOwner())->orderBy('e.position', 'ASC')->getQuery()->getResult();
    }

    public function sourceEpisode(int $id, User $owner, VideoAsset $video): ?VideoSeriesEpisode
    {
        $episode = $this->em->getRepository(VideoSeriesEpisode::class)->findOneBy(['id' => $id, 'video' => $video, 'active' => true]);

        return $episode?->getSeries()->getOwner()->getId() === $owner->getId() ? $episode : null;
    }

    /** @return list<PublicationDraft> */
    public function deletionBlockers(VideoSeries $series): array
    {
        $drafts = $this->em->createQueryBuilder()->select('d')->from(PublicationDraft::class, 'd')->join('d.videoEpisode', 'e')
            ->where('e.series = :series AND d.owner = :owner')->setParameter('series', $series)->setParameter('owner', $series->getOwner())
            ->orderBy('d.id', 'ASC')->getQuery()->getResult();
        $snapshot = $this->calendar->snapshot($drafts);
        $activeStates = ['youtube' => YouTubePublication::ACTIVE, 'instagram' => InstagramPublication::ACTIVE, 'tiktok' => TikTokPublication::ACTIVE];
        $blockers = [];
        foreach ($drafts as $draft) {
            if ('scheduled' === $draft->getStatus() || 'active' === $snapshot['states'][$draft->getId()]) {
                $blockers[] = $draft;
                continue;
            }
            foreach ($snapshot['destinations'][$draft->getSubmissionKey()] ?? [] as $network => $status) {
                if (in_array($status, $activeStates[$network], true)) {
                    $blockers[] = $draft;
                    break;
                }
            }
        }

        return $blockers;
    }

    public function delete(User $owner, int $id, int $revision): void
    {
        $this->lock->run((int) $owner->getId(), fn () => $this->em->getConnection()->transactional(function () use ($owner, $id, $revision): void {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $series = $this->owned($id, $owner) ?? throw new PublicationException('Cette série est indisponible.');
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            if ($series->getRevision() !== $revision) {
                throw new PublicationException('La série a changé. Vérifie son contenu avant de la supprimer.');
            }
            if ($this->deletionBlockers($series)) {
                throw new PublicationException('Annule les programmations ou termine les envois en cours avant de supprimer cette série.');
            }
            // The database removes only episode associations and clears draft links.
            $this->em->remove($series);
            $this->em->flush();
        }));
    }

    public function append(User $owner, VideoSeries $series, int $videoId): VideoSeriesEpisode
    {
        return $this->em->getConnection()->transactional(function () use ($owner, $series, $videoId): VideoSeriesEpisode {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $series = $this->owned((int) $series->getId(), $owner) ?? throw new VideoLibraryException('Cette série est indisponible.');
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            $video = $this->em->getRepository(VideoAsset::class)->findOneBy(['id' => $videoId, 'owner' => $owner]);
            if ($series->getOwner()->getId() !== $owner->getId() || !$video) {
                throw new VideoLibraryException('Cette vidéo ou cette série est indisponible.');
            }

            $episode = $this->em->getRepository(VideoSeriesEpisode::class)->findOneBy(['series' => $series, 'video' => $video]);
            if ($episode?->isActive()) {
                return $episode;
            }
            $active = $this->episodes($series);
            if (count($active) >= 100) {
                throw new VideoLibraryException('Cette série contient déjà 100 épisodes.');
            }
            $position = $active ? $active[array_key_last($active)]->getPosition() + 1 : 1;
            $episode ??= new VideoSeriesEpisode($series, $video, $position);
            $episode->place($position);
            $this->em->persist($episode);
            $series->revise($series->getName());
            $this->em->flush();

            return $episode;
        });
    }

    /** @param list<string> $selected */
    public function save(User $owner, string $name, array $selected, string $order, int $revision, ?VideoSeries $series = null, ?array $publication = null): VideoSeries
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 120 || count($selected) < 1 || count($selected) > 100) {
            throw new \RuntimeException('Nomme ta série et choisis entre 1 et 100 vidéos.');
        }
        $ids = array_map('strval', $selected);
        $ordered = '' === trim($order) ? $ids : explode(',', $order);
        $expected = $ids;
        $actual = $ordered;
        sort($expected);
        sort($actual);
        if ($actual !== $expected || count(array_unique($ids)) !== count($ids)) {
            throw new \RuntimeException('Vérifie la sélection et l’ordre des épisodes.');
        }
        foreach ($ids as $value) {
            if (!preg_match('/^[1-9][0-9]{0,9}$/D', $value)) {
                throw new \RuntimeException('Vidéo indisponible.');
            }
        }

        return $this->em->getConnection()->transactional(function () use ($owner, $name, $ordered, $revision, $series, $publication): VideoSeries {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            if ($series) {
                $series = $this->owned((int) $series->getId(), $owner) ?? throw new \RuntimeException('Cette série est indisponible.');
                $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
                if ($series->getOwner()->getId() !== $owner->getId() || $revision !== $series->getRevision()) {
                    throw new \RuntimeException('Cette série a changé. Recharge-la avant d’enregistrer.');
                }
            }
            $normalizedPublication = null === $publication ? null : $this->publicationSettings->normalize($owner, $publication, $series?->getPublicationSettings() ?? []);
            $videos = [];
            foreach ($ordered as $id) {
                $video = $this->em->getRepository(VideoAsset::class)->findOneBy(['id' => (int) $id, 'owner' => $owner]);
                if (!$video) {
                    throw new \RuntimeException('Une vidéo n’est plus disponible. Vérifie ta sélection.');
                }
                $videos[] = $video;
            }
            $existing = [];
            if ($series) {
                $all = $this->em->getRepository(VideoSeriesEpisode::class)->findBy(['series' => $series]);
                foreach ($all as $episode) {
                    $existing[(int) $episode->getVideo()->getId()] = $episode;
                    if ($episode->isActive() && !in_array((string) $episode->getVideo()->getId(), $ordered, true)) {
                        $drafts = $this->em->getRepository(PublicationDraft::class)->findBy(['videoEpisode' => $episode, 'owner' => $owner]);
                        if (array_diff($this->calendar->states($drafts), ['draft', 'cancelled', 'published'])) {
                            throw new \RuntimeException('Cet épisode a une publication programmée ou à vérifier. Annule ou termine sa publication avant de le retirer de la série.');
                        }
                    }
                }
                foreach ($all as $episode) {
                    $episode->place(-(int) $episode->getId(), false);
                }
                $this->em->flush();
                $series->revise($name);
            } else {
                $series = new VideoSeries($owner, $name);
                $this->em->persist($series);
            }
            foreach ($videos as $index => $video) {
                if (isset($existing[$video->getId()])) {
                    $existing[$video->getId()]->place($index + 1);
                } else {
                    $this->em->persist(new VideoSeriesEpisode($series, $video, $index + 1));
                }
            }
            if (null !== $normalizedPublication) {
                $series->setPublicationSettings($normalizedPublication);
            }
            $this->em->flush();

            return $series;
        });
    }
}
