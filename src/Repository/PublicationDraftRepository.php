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

    /** @return array{counts: array{drafts: int, scheduled: int, attention: int}, upcoming: list<PublicationDraft>, recent: list<PublicationDraft>, problems: list<PublicationDraft>} */
    public function dashboard(User $owner, \DateTimeImmutable $now): array
    {
        $cutoff = $now->modify('-120 seconds')->setTimezone(new \DateTimeZone('UTC'));
        $counts = $this->getEntityManager()->getConnection()->fetchAssociative("SELECT COUNT(*) FILTER (WHERE status = 'draft') AS drafts, COUNT(*) FILTER (WHERE status = 'scheduled' AND scheduled_at >= :cutoff) AS scheduled, COUNT(*) FILTER (WHERE status = 'blocked' OR (status = 'scheduled' AND scheduled_at < :cutoff)) AS attention FROM publication_draft WHERE owner_id = :owner", ['owner' => $owner->getId(), 'cutoff' => $cutoff->format('Y-m-d H:i:s')]);
        $upcoming = $this->createQueryBuilder('d')->where('d.owner = :owner AND d.status = :status AND d.scheduledAt >= :cutoff')
            ->setParameter('owner', $owner)->setParameter('status', 'scheduled')->setParameter('cutoff', $cutoff)
            ->orderBy('d.scheduledAt', 'ASC')->addOrderBy('d.id', 'ASC')->setMaxResults(3)->getQuery()->getResult();
        $problems = $this->createQueryBuilder('d')->where('d.owner = :owner')->andWhere("d.status = 'blocked' OR (d.status = 'scheduled' AND d.scheduledAt < :cutoff)")
            ->setParameter('owner', $owner)->setParameter('cutoff', $cutoff)
            ->orderBy('d.updatedAt', 'DESC')->addOrderBy('d.id', 'DESC')->setMaxResults(2)->getQuery()->getResult();

        return ['counts' => ['drafts' => (int) $counts['drafts'], 'scheduled' => (int) $counts['scheduled'], 'attention' => (int) $counts['attention']], 'upcoming' => $upcoming, 'recent' => $this->findBy(['owner' => $owner, 'status' => 'draft'], ['updatedAt' => 'DESC', 'id' => 'DESC'], 2), 'problems' => $problems];
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
