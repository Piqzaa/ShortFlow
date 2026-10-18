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

    /** @return list<VideoAsset> */
    public function page(User $owner, int $page = 1, int $limit = 12): array
    {
        return $this->findBy(['owner' => $owner], ['createdAt' => 'DESC', 'id' => 'DESC'], $limit, ($page - 1) * $limit);
    }

    /** @return array{items: list<VideoAsset>, total: int, page: int, pages: int} */
    public function search(User $owner, string $query, int $page = 1): array
    {
        $builder = $this->createQueryBuilder('v')->where('v.owner = :owner')->setParameter('owner', $owner);
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
    public function usage(User $owner): array
    {
        $row = $this->createQueryBuilder('v')->select('COUNT(v.id) AS count, COALESCE(SUM(v.byteSize), 0) AS bytes')->where('v.owner = :owner')->setParameter('owner', $owner)->getQuery()->getSingleResult();

        return ['count' => (int) $row['count'], 'bytes' => (int) $row['bytes']];
    }
}
