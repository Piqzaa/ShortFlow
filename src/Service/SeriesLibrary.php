<?php

namespace App\Service;

use App\Entity\AiSeries;
use App\Entity\User;
use App\Entity\VideoSeries;
use Doctrine\ORM\EntityManagerInterface;

final class SeriesLibrary
{
    public function __construct(private EntityManagerInterface $em, private EpisodeCovers $covers, private VideoSeriesStudio $videos)
    {
    }

    /** @return array{entries:list<array{series:AiSeries|VideoSeries,kind:string,videoId:?int,episodes:int}>,page:int,pages:int} */
    public function page(User $owner, int $page): array
    {
        $db = $this->em->getConnection();
        $count = (int) $db->fetchOne('SELECT (SELECT COUNT(*) FROM ai_series WHERE owner_id = :owner) + (SELECT COUNT(*) FROM video_series WHERE owner_id = :owner)', ['owner' => $owner->getId()]);
        $pages = max(1, (int) ceil($count / 12));
        $page = min(max(1, $page), $pages);
        $rows = $db->fetchAllAssociative("SELECT id, kind FROM (SELECT id, created_at, 'ai' AS kind FROM ai_series WHERE owner_id = :owner UNION ALL SELECT id, created_at, 'videos' AS kind FROM video_series WHERE owner_id = :owner) series ORDER BY created_at DESC, id DESC, kind ASC LIMIT 12 OFFSET ".(($page - 1) * 12), ['owner' => $owner->getId()]);
        $entries = [];
        foreach ($rows as $row) {
            if ('ai' === $row['kind']) {
                $series = $this->em->getRepository(AiSeries::class)->findOneBy(['id' => (int) $row['id'], 'owner' => $owner]);
                if ($series) {
                    $entries[] = ['series' => $series, 'kind' => 'ai', 'videoId' => $this->covers->series($owner, [$series])[(int) $series->getId()] ?? null, 'episodes' => 0];
                }
            } else {
                $series = $this->videos->owned((int) $row['id'], $owner);
                if ($series) {
                    $episodes = $this->videos->episodes($series);
                    $entries[] = ['series' => $series, 'kind' => 'videos', 'videoId' => $episodes ? $episodes[0]->getVideo()->getId() : null, 'episodes' => count($episodes)];
                }
            }
        }

        return ['entries' => $entries, 'page' => $page, 'pages' => $pages];
    }
}
