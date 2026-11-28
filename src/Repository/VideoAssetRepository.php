<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubePublication;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<VideoAsset> */
final class VideoAssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoAsset::class);
    }

    public function owned(int $id, User $owner): ?VideoAsset
    {
        return $this->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /** @return list<int> */
    public function scheduledSourceIds(User $owner): array
    {
        return array_map('intval', $this->getEntityManager()->getConnection()->fetchFirstColumn(
            "SELECT DISTINCT i.source_id FROM publication_draft_item i JOIN publication_draft d ON d.id = i.draft_id WHERE d.owner_id = ? AND d.status = 'scheduled' AND i.source_id IS NOT NULL",
            [$owner->getId()],
        ));
    }

    /** @return list<int> */
    public function temporaryIdsAfter(int $after, int $limit): array
    {
        return array_map('intval', $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT id FROM video_asset WHERE temporary = true AND id > ? ORDER BY id LIMIT ?',
            [$after, $limit], [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::INTEGER],
        ));
    }

    /** @return list<int> */
    public function assembledClipIdsAfter(int $after, int $limit): array
    {
        return array_map('intval', $this->getEntityManager()->getConnection()->fetchFirstColumn(
            "SELECT v.id FROM video_asset v JOIN ai_episode e ON e.id = v.assembly_episode_id WHERE e.flow_state = 'ready' AND v.id > ? ORDER BY v.id LIMIT ?",
            [$after, $limit], [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::INTEGER],
        ));
    }

    public function assembledClipOutput(int $id): ?string
    {
        $name = $this->getEntityManager()->getConnection()->fetchOne(<<<'SQL'
            SELECT final.storage_name FROM video_asset v
            JOIN ai_episode e ON e.id = v.assembly_episode_id
            JOIN video_edit assembly ON assembly.id = e.assembly_id
            JOIN video_asset final ON final.id = assembly.output_id AND final.owner_id = v.owner_id
            WHERE v.id = :id AND e.flow_state = 'ready' AND final.id <> v.id
              AND NOT EXISTS (SELECT 1 FROM ai_episode WHERE approved_video_id = v.id)
              AND NOT EXISTS (SELECT 1 FROM video_series_episode WHERE video_id = v.id)
              AND NOT EXISTS (SELECT 1 FROM publication_draft_item WHERE source_id = v.id)
              AND NOT EXISTS (SELECT 1 FROM youtube_publication WHERE source_id = v.id)
              AND NOT EXISTS (SELECT 1 FROM instagram_publication WHERE source_id = v.id)
              AND NOT EXISTS (SELECT 1 FROM tiktok_publication WHERE source_id = v.id)
              AND NOT EXISTS (
                SELECT 1 FROM ai_generation g
                WHERE g.owner_id = v.owner_id AND g.input_settings->'extension'->>'sourceId' = CAST(v.id AS text)
              )
              AND NOT EXISTS (
                SELECT 1 FROM video_edit other
                WHERE other.owner_id = v.owner_id AND (
                    other.output_id = v.id
                    OR other.clips::jsonb @> jsonb_build_array(jsonb_build_object('id', v.id))
                    OR other.audio_clips::jsonb @> jsonb_build_array(jsonb_build_object('kind', 'video', 'source', v.id))
                )
              )
            SQL, ['id' => $id]);

        return is_string($name) ? $name : null;
    }

    public function lastSuccessfulPublicationIfUnprotected(int $id): ?\DateTimeImmutable
    {
        $at = $this->getEntityManager()->getConnection()->fetchOne(<<<'SQL'
            WITH jobs AS (
                SELECT status, updated_at FROM youtube_publication WHERE source_id = :id
                UNION ALL SELECT status, COALESCE(published_at, created_at) FROM instagram_publication WHERE source_id = :id
                UNION ALL SELECT status, COALESCE(published_at, created_at) FROM tiktok_publication WHERE source_id = :id
            )
            SELECT MAX(j.updated_at) FROM jobs j
            WHERE j.status = 'published'
              AND NOT EXISTS (SELECT 1 FROM jobs WHERE status NOT IN ('published', 'cancelled'))
              AND NOT EXISTS (
                SELECT 1 FROM publication_draft_item i JOIN publication_draft d ON d.id = i.draft_id
                WHERE i.source_id = :id AND d.status NOT IN ('dispatched', 'cancelled')
              )
              AND NOT EXISTS (SELECT 1 FROM video_series_episode WHERE video_id = :id)
              AND NOT EXISTS (SELECT 1 FROM ai_episode WHERE approved_video_id = :id)
              AND NOT EXISTS (
                SELECT 1 FROM ai_generation g JOIN video_asset v ON v.id = :id
                WHERE g.video_id = v.id OR (g.owner_id = v.owner_id AND g.input_settings->'extension'->>'sourceId' = CAST(v.id AS text))
              )
              AND NOT EXISTS (
                SELECT 1 FROM video_edit e JOIN video_asset v ON v.id = :id
                WHERE e.owner_id = v.owner_id AND (
                    e.output_id = v.id
                    OR e.clips::jsonb @> jsonb_build_array(jsonb_build_object('id', v.id))
                    OR e.audio_clips::jsonb @> jsonb_build_array(jsonb_build_object('kind', 'video', 'source', v.id))
                )
              )
            SQL, ['id' => $id]);

        return is_string($at) ? new \DateTimeImmutable($at, new \DateTimeZone('UTC')) : null;
    }

    /** @return list<VideoAsset> */
    public function page(User $owner, int $page = 1, int $limit = 12): array
    {
        return $this->findBy(['owner' => $owner, 'assemblyEpisode' => null], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit, ($page - 1) * $limit);
    }

    /** @return array{items: list<VideoAsset>, total: int, page: int, pages: int} */
    public function search(User $owner, string $query, int $page = 1): array
    {
        $builder = $this->createQueryBuilder('v')->where('v.owner = :owner')->andWhere('v.assemblyEpisode IS NULL')->setParameter('owner', $owner);
        if ('' !== $query) {
            $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($query));
            $builder->andWhere('LOWER(v.title) LIKE :query')->setParameter('query', '%'.$pattern.'%');
        }
        $total = (int) (clone $builder)->select('COUNT(v.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / 12));
        $page = min(max(1, $page), $pages);
        $items = $builder->orderBy('v.createdAt', 'DESC')->addOrderBy('v.id', 'DESC')->setFirstResult(($page - 1) * 12)->setMaxResults(12)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * @param list<VideoAsset> $videos
     *
     * @return array<int, list<string>>
     */
    public function publishedPlatforms(User $owner, array $videos): array
    {
        if ([] === $videos) {
            return [];
        }
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT IDENTITY(p.source) AS videoId')
            ->from(YouTubePublication::class, 'p')
            ->join('p.source', 'v')
            ->where('p.owner = :owner')->andWhere('v.owner = :owner')
            ->andWhere('p.status = :status')->andWhere('p.remoteId IS NOT NULL')
            ->andWhere('v.id IN (:ids)')
            ->setParameter('owner', $owner)->setParameter('status', 'published')
            ->setParameter('ids', array_map(static fn (VideoAsset $video): ?int => $video->getId(), $videos))
            ->getQuery()->getArrayResult();
        $platforms = [];
        foreach ($rows as $row) {
            $platforms[(int) $row['videoId']] = ['youtube'];
        }

        $instagram = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT IDENTITY(p.source) AS videoId')->from(\App\Entity\InstagramPublication::class, 'p')->join('p.source', 'v')
            ->where('p.owner = :owner')->andWhere('v.owner = :owner')->andWhere('p.status = :status')->andWhere('p.containerId IS NOT NULL')
            ->andWhere('v.id IN (:ids)')->setParameter('owner', $owner)->setParameter('status', 'published')
            ->setParameter('ids', array_map(static fn (VideoAsset $video): ?int => $video->getId(), $videos))->getQuery()->getArrayResult();
        foreach ($instagram as $row) {
            $platforms[(int) $row['videoId']][] = 'instagram';
        }

        $tiktok = $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT IDENTITY(p.source) AS videoId')->from(\App\Entity\TikTokPublication::class, 'p')->join('p.source', 'v')
            ->where('p.owner = :owner')->andWhere('v.owner = :owner')->andWhere('p.status = :status')->andWhere('p.publishId IS NOT NULL')
            ->andWhere('v.id IN (:ids)')->setParameter('owner', $owner)->setParameter('status', 'published')
            ->setParameter('ids', array_map(static fn (VideoAsset $video): ?int => $video->getId(), $videos))->getQuery()->getArrayResult();
        foreach ($tiktok as $row) {
            $platforms[(int) $row['videoId']][] = 'tiktok';
        }

        return $platforms;
    }

    /** @return array{count: int, bytes: int} */
    public function usage(User $owner, ?bool $temporary = null): array
    {
        $query = $this->createQueryBuilder('v')->select('COUNT(v.id) AS count, COALESCE(SUM(v.byteSize), 0) AS bytes')->where('v.owner = :owner')->setParameter('owner', $owner);
        if (null !== $temporary) {
            $query->andWhere('v.temporary = :temporary')->setParameter('temporary', $temporary);
        }
        $row = $query->getQuery()->getSingleResult();

        return ['count' => (int) $row['count'], 'bytes' => (int) $row['bytes']];
    }
}
