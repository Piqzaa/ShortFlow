<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\VideoAsset;
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

    /** @return array{count: int, bytes: int} */
    public function usage(User $owner): array
    {
        $row = $this->createQueryBuilder('v')->select('COUNT(v.id) AS count, COALESCE(SUM(v.byteSize), 0) AS bytes')->where('v.owner = :owner')->setParameter('owner', $owner)->getQuery()->getSingleResult();

        return ['count' => (int) $row['count'], 'bytes' => (int) $row['bytes']];
    }
}
