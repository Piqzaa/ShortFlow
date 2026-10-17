<?php

namespace App\Repository;

use App\Entity\PublicationDraft;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PublicationDraft> */
final class PublicationDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicationDraft::class);
    }

    public function owned(int $id, User $owner): ?PublicationDraft
    {
        return $this->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /** @return list<PublicationDraft> */
    public function month(User $owner, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        return $this->createQueryBuilder('d')->where('d.owner = :owner')
            ->andWhere('(d.scheduledAt >= :start AND d.scheduledAt < :end) OR (d.scheduledAt IS NULL AND d.dispatchedAt >= :start AND d.dispatchedAt < :end)')
            ->setParameter('owner', $owner)->setParameter('start', $start)->setParameter('end', $end)
            ->addSelect('COALESCE(d.scheduledAt, d.dispatchedAt) AS HIDDEN calendarAt')
            ->orderBy('calendarAt', 'ASC')->addOrderBy('d.id', 'ASC')->getQuery()->getResult();
    }
}
