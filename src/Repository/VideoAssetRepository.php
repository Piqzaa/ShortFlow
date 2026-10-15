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

        return $platforms;
    }

    /** @return array{count: int, bytes: int} */
    public function usage(User $owner): array
    {
        $row = $this->createQueryBuilder('v')->select('COUNT(v.id) AS count, COALESCE(SUM(v.byteSize), 0) AS bytes')->where('v.owner = :owner')->setParameter('owner', $owner)->getQuery()->getSingleResult();

        return ['count' => (int) $row['count'], 'bytes' => (int) $row['bytes']];
    }
}
