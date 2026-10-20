<?php

namespace App\Repository;

use App\Entity\AiGeneration;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AiGeneration> */
final class AiGenerationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiGeneration::class);
    }

    /** @return array{items: list<AiGeneration>, total: int, page: int, pages: int} */
    public function history(User $owner, string $query, string $status, int $page): array
    {
        $builder = $this->createQueryBuilder('g')->where('g.owner = :owner')->setParameter('owner', $owner);
        if ('' !== $query) {
            $pattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($query));
            $builder->andWhere('LOWER(g.title) LIKE :query')->setParameter('query', '%'.$pattern.'%');
        }
        if ('' !== $status) {
            $builder->andWhere('g.status = :status')->setParameter('status', $status);
        }
        $total = (int) (clone $builder)->select('COUNT(g.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / 12));
        $page = min(max(1, $page), $pages);
        $items = $builder->leftJoin('g.video', 'v')->addSelect('v')->orderBy('g.createdAt', 'DESC')->addOrderBy('g.id', 'DESC')->setFirstResult(($page - 1) * 12)->setMaxResults(12)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
