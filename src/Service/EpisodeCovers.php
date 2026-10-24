<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

final class EpisodeCovers
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @param list<AiEpisode> $episodes
     * @return array<int,int>
     */
    public function episodes(User $owner, array $episodes): array
    {
        return $this->find($owner, array_map(static fn (AiEpisode $e): int => (int) $e->getId(), $episodes), 'id');
    }

    /** @param list<AiSeries> $series
     * @return array<int,int>
     */
    public function series(User $owner, array $series): array
    {
        return $this->find($owner, array_map(static fn (AiSeries $s): int => (int) $s->getId(), $series), 'series_id');
    }

    /** @param list<int> $ids
     * @return array<int,int>
     */
    private function find(User $owner, array $ids, string $column): array
    {
        if (!$ids) {
            return [];
        }
        // Prefer the chosen final version; otherwise use the latest finished clip.
        // Both the creation and the video must belong to this owner.
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT ON (e.'.$column.') e.'.$column.' AS target, v.id AS video
            FROM ai_episode e
            LEFT JOIN ai_series s ON s.id = e.series_id
            LEFT JOIN video_edit edit ON edit.id = e.assembly_id
            LEFT JOIN ai_generation g ON g.id = e.generation_id
            LEFT JOIN ai_shot shot ON shot.episode_id = e.id
            LEFT JOIN ai_generation clip ON clip.id = shot.generation_id
            JOIN video_asset v ON v.id = COALESCE(e.approved_video_id, edit.output_id, g.video_id, clip.video_id) AND v.owner_id = :owner
            WHERE (e.owner_id = :owner OR s.owner_id = :owner) AND e.'.$column.' IN (:ids)
            ORDER BY e.'.$column.' DESC, e.id DESC, shot.position DESC',
            ['owner' => $owner->getId(), 'ids' => $ids], ['ids' => ArrayParameterType::INTEGER],
        );
        $covers = [];
        foreach ($rows as $row) {
            $covers[(int) $row['target']] = (int) $row['video'];
        }

        return $covers;
    }
}
