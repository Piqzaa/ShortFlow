<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\VideoSeries;
use App\Entity\VideoSeriesEpisode;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class VideoSeriesStudio
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function owned(int $id, User $owner): ?VideoSeries
    {
        return $this->em->getRepository(VideoSeries::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /** @return list<VideoSeriesEpisode> */
    public function episodes(VideoSeries $series): array
    {
        return $this->em->createQueryBuilder()->select('e', 'v')->from(VideoSeriesEpisode::class, 'e')->join('e.video', 'v')->where('e.series = :series')->andWhere('v.owner = :owner')->setParameter('series', $series)->setParameter('owner', $series->getOwner())->orderBy('e.position', 'ASC')->getQuery()->getResult();
    }

    /** @param list<string> $selected */
    public function save(User $owner, string $name, array $selected, string $order, int $revision, ?VideoSeries $series = null): VideoSeries
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

        return $this->em->wrapInTransaction(function () use ($owner, $name, $ordered, $revision, $series): VideoSeries {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            if ($series) {
                $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
                if ($series->getOwner()->getId() !== $owner->getId() || $revision !== $series->getRevision()) {
                    throw new \RuntimeException('Cette série a changé. Recharge-la avant d’enregistrer.');
                }
            }
            $videos = [];
            foreach ($ordered as $id) {
                $video = $this->em->getRepository(VideoAsset::class)->findOneBy(['id' => (int) $id, 'owner' => $owner]);
                if (!$video) {
                    throw new \RuntimeException('Une vidéo n’est plus disponible. Vérifie ta sélection.');
                }
                $videos[] = $video;
            }
            if ($series) {
                foreach ($this->episodes($series) as $episode) {
                    $this->em->remove($episode);
                }
                $this->em->flush();
                $series->revise($name);
            } else {
                $series = new VideoSeries($owner, $name);
                $this->em->persist($series);
            }
            foreach ($videos as $index => $video) {
                $this->em->persist(new VideoSeriesEpisode($series, $video, $index + 1));
            }

            return $series;
        });
    }
}
