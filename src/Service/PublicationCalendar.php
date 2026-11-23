<?php

namespace App\Service;

use App\Entity\PublicationDraft;
use App\Entity\YouTubePublication;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\PersistentCollection;
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
        return $this->snapshot($drafts)['states'];
    }

    /** @param list<PublicationDraft> $drafts
     * @return array{states:array<int,string>,destinations:array<string,array<string,string>>,youtubeChannels:array<string,array<string,string>>}
     */
    public function snapshot(array $drafts): array
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
            return ['states' => $states, 'destinations' => [], 'youtubeChannels' => []];
        }
        $rows = $this->db->fetchAllAssociative("SELECT id, submission_key, status, 'youtube' AS network, channel_id FROM youtube_publication WHERE owner_id = :owner AND submission_key IN (:keys) UNION ALL SELECT id, submission_key, status, 'instagram', NULL FROM instagram_publication WHERE owner_id = :owner AND submission_key IN (:keys) UNION ALL SELECT id, submission_key, status, 'tiktok', NULL FROM tiktok_publication WHERE owner_id = :owner AND submission_key IN (:keys) ORDER BY network, id", ['owner' => $drafts[0]->getOwner()->getId(), 'keys' => array_values(array_unique($keys))], ['keys' => ArrayParameterType::STRING]);
        $jobs = [];
        $destinations = [];
        $youtubeChannels = [];
        foreach ($rows as $row) {
            $jobs[$row['submission_key']][] = $row['status'];
            $destinations[$row['submission_key']][$row['network']] ??= $row['status'];
            if ('youtube' === $row['network']) {
                $youtubeChannels[$row['submission_key']][$row['channel_id']] ??= $row['status'];
                if (in_array($row['status'], YouTubePublication::ACTIVE, true)) {
                    $destinations[$row['submission_key']]['youtube'] = $row['status'];
                }
            }
        }
        $counts = $missing = [];
        foreach ($drafts as $draft) {
            if ('dispatched' !== $draft->getStatus()) {
                continue;
            }
            $items = $draft->getItems();
            if ($items instanceof PersistentCollection && !$items->isInitialized()) {
                $missing[] = (int) $draft->getId();
            } else {
                $counts[$draft->getId()] = $items->count();
            }
        }
        if ($missing) {
            // Count sources together without hydrating every item's texts on calendar GETs.
            $counts += array_map('intval', $this->db->fetchAllKeyValue('SELECT i.draft_id, COUNT(*) FROM publication_draft_item i JOIN publication_draft d ON d.id = i.draft_id WHERE d.owner_id = :owner AND i.draft_id IN (:ids) GROUP BY i.draft_id', ['owner' => $drafts[0]->getOwner()->getId(), 'ids' => $missing], ['ids' => ArrayParameterType::INTEGER]));
        }
        foreach ($drafts as $draft) {
            if ('dispatched' !== $draft->getStatus()) {
                continue;
            }
            $statuses = $jobs[$draft->getSubmissionKey()] ?? [];
            $published = count(array_filter($statuses, static fn (string $status): bool => 'published' === $status));
            $attention = array_intersect($statuses, ['failed', 'paused', 'review', 'cancelled']);
            $expected = $draft->getDestinationCount() * ($counts[$draft->getId()] ?? 0);
            $states[$draft->getId()] = count($statuses) !== $expected ? 'blocked' : ($published === $expected ? 'published' : ($published && $attention ? 'partial' : ($attention ? 'blocked' : 'active')));
        }

        return ['states' => $states, 'destinations' => $destinations, 'youtubeChannels' => $youtubeChannels];
    }
}
