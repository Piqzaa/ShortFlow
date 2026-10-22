<?php

namespace App\Service;

use App\Entity\PublicationStatistic;
use App\Entity\User;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final class PublicationOverview
{
    public const STATES = [
        'active' => ['queued', 'uploading', 'processing', 'publishing'],
        'attention' => ['paused', 'failed', 'review'],
        'published' => ['published'],
        'cancelled' => ['cancelled'],
    ];

    private const FEED = <<<'SQL'
        SELECT p.id, p.owner_id, 'youtube' AS network, p.title, p.channel_title AS account,
               p.status, p.notice, p.created_at, p.remote_id, p.privacy, p.sent_bytes,
               v.byte_size, v.id AS source_id, p.effective_privacy, p.remote_id AS tracking_id,
               FALSE AS attempted
        FROM youtube_publication p
        LEFT JOIN video_asset v ON v.id = p.source_id AND v.owner_id = p.owner_id
        UNION ALL
        SELECT p.id, p.owner_id, 'instagram' AS network, p.title, p.username AS account,
               p.status, p.notice, p.created_at, p.remote_id, NULL AS privacy, 0 AS sent_bytes,
               v.byte_size, v.id AS source_id, NULL AS effective_privacy, p.container_id AS tracking_id,
               p.publish_attempted AS attempted
        FROM instagram_publication p
        LEFT JOIN video_asset v ON v.id = p.source_id AND v.owner_id = p.owner_id
        UNION ALL
        SELECT p.id, p.owner_id, 'tiktok' AS network, p.title, p.display_name AS account,
               p.status, p.notice, p.created_at, p.remote_id, p.options->>'privacy_level' AS privacy, 0 AS sent_bytes,
               v.byte_size, v.id AS source_id, NULL AS effective_privacy, p.publish_id AS tracking_id,
               p.init_attempted AS attempted
        FROM tiktok_publication p
        LEFT JOIN video_asset v ON v.id = p.source_id AND v.owner_id = p.owner_id
        SQL;

    public function __construct(private Connection $db)
    {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public function page(User $owner, int $page = 1, string $network = '', string $state = '', int $limit = 20): array
    {
        $where = 'feed.owner_id = :owner';
        $parameters = ['owner' => $owner->getId()];
        $types = [];
        if (in_array($network, ['youtube', 'instagram', 'tiktok'], true)) {
            $where .= ' AND feed.network = :network';
            $parameters['network'] = $network;
        }
        if (isset(self::STATES[$state])) {
            $where .= ' AND feed.status IN (:statuses)';
            $parameters['statuses'] = self::STATES[$state];
            $types['statuses'] = ArrayParameterType::STRING;
        }
        $from = ' FROM ('.self::FEED.') feed LEFT JOIN publication_statistic stats ON stats.owner_id = feed.owner_id AND stats.network = feed.network AND stats.publication_id = feed.id WHERE '.$where;
        $total = (int) $this->db->fetchOne('SELECT COUNT(*)'.$from, $parameters, $types);
        $limit = max(1, min(50, $limit));
        $pages = max(1, (int) ceil($total / $limit));
        $page = max(1, min($page, $pages));
        $items = $this->db->fetchAllAssociative('SELECT feed.*, stats.counts AS statistics_counts, stats.updated_at AS statistics_updated_at, stats.status AS statistics_status, stats.notice AS statistics_notice, stats.requested_at AS statistics_requested_at'.$from.' ORDER BY feed.created_at DESC, feed.network ASC, feed.id DESC LIMIT '.$limit.' OFFSET '.(($page - 1) * $limit), $parameters, $types);
        foreach ($items as &$item) {
            $tracking = null !== $item['tracking_id'];
            $item['can_resume'] = ('paused' === $item['status'] || ('review' === $item['status'] && $tracking && ('instagram' !== $item['network'] || $item['attempted']))) && !('tiktok' === $item['network'] && $item['attempted'] && !$tracking);
            $item['resume_label'] = $tracking ? 'Reprendre le suivi' : 'Reprendre l’envoi';
            $item['can_prepare_again'] = 'failed' === $item['status'] && null !== $item['source_id'];
            $item['statistics_counts'] = is_string($item['statistics_counts']) ? json_decode($item['statistics_counts'], true) : null;
            $requested = $item['statistics_requested_at'];
            $item['statistics_can_refresh'] = !$requested || strtotime($requested) <= time() - PublicationStatistic::REQUEST_INTERVAL_SECONDS;
        }
        unset($item);

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return array{active: int, attention: int, published: int, cancelled: int} */
    public function counts(User $owner): array
    {
        $counts = ['active' => 0, 'attention' => 0, 'published' => 0, 'cancelled' => 0];
        $rows = $this->db->fetchAllAssociative('SELECT status, COUNT(*) AS total FROM ('.self::FEED.') feed WHERE owner_id = :owner GROUP BY status', ['owner' => $owner->getId()]);
        foreach ($rows as $row) {
            foreach (self::STATES as $state => $statuses) {
                if (in_array($row['status'], $statuses, true)) {
                    $counts[$state] += (int) $row['total'];
                }
            }
        }

        return $counts;
    }
}
