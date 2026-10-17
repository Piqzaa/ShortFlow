<?php

namespace App\Service;

use App\Entity\PublicationDraft;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\ClockInterface;

final class PublicationCalendar
{
    public const LABELS = ['draft' => 'Brouillon', 'scheduled' => 'Programmée', 'missed' => 'À reprogrammer', 'blocked' => 'À traiter', 'cancelled' => 'Annulée', 'active' => 'En cours', 'published' => 'Publiée', 'partial' => 'Réussite partielle'];

    public function __construct(private Connection $db, private ClockInterface $clock)
    {
    }

    /** @param list<PublicationDraft> $drafts @return array<int, string> */
    public function states(array $drafts): array
    {
        $states = [];
        $keys = [];
        foreach ($drafts as $draft) {
            $state = $draft->getStatus();
            if ('scheduled' === $state && $draft->getScheduledAt() && $this->clock->now()->getTimestamp() > $draft->getScheduledAt()->getTimestamp() + 120) {
                $state = 'missed';
            }
            $states[$draft->getId()] = $state;
            if ('dispatched' === $state && $draft->getSubmissionKey()) {
                $keys[] = $draft->getSubmissionKey();
            }
        }
        if (!$keys) {
            return $states;
        }
        $rows = $this->db->fetchAllAssociative('SELECT submission_key, status FROM youtube_publication WHERE owner_id = :owner AND submission_key IN (:keys) UNION ALL SELECT submission_key, status FROM instagram_publication WHERE owner_id = :owner AND submission_key IN (:keys) UNION ALL SELECT submission_key, status FROM tiktok_publication WHERE owner_id = :owner AND submission_key IN (:keys)', ['owner' => $drafts[0]->getOwner()->getId(), 'keys' => $keys], ['keys' => ArrayParameterType::STRING]);
        $jobs = [];
        foreach ($rows as $row) {
            $jobs[$row['submission_key']][] = $row['status'];
        }
        foreach ($drafts as $draft) {
            if ('dispatched' !== $draft->getStatus()) {
                continue;
            }
            $statuses = $jobs[$draft->getSubmissionKey()] ?? [];
            $published = count(array_filter($statuses, static fn (string $status): bool => 'published' === $status));
            $attention = array_intersect($statuses, ['failed', 'paused', 'review', 'cancelled']);
            $expected = count($draft->getNetworks()) * $draft->getItems()->count();
            $states[$draft->getId()] = count($statuses) !== $expected ? 'blocked' : ($published === $expected ? 'published' : ($published && $attention ? 'partial' : ($attention ? 'blocked' : 'active')));
        }

        return $states;
    }
}
