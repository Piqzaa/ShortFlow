<?php

namespace App\Service;

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
               v.byte_size, v.id AS source_id
        FROM youtube_publication p
        LEFT JOIN video_asset v ON v.id = p.source_id AND v.owner_id = p.owner_id
        UNION ALL
        SELECT p.id, p.owner_id, 'instagram' AS network, p.title, p.username AS account,
               p.status, p.notice, p.created_at, p.remote_id, NULL AS privacy, 0 AS sent_bytes,
               v.byte_size, v.id AS source_id
        FROM instagram_publication p
        LEFT JOIN video_asset v ON v.id = p.source_id AND v.owner_id = p.owner_id
        UNION ALL
        SELECT p.id, p.owner_id, 'tiktok' AS network, p.title, p.display_name AS account,
               p.status, p.notice, p.created_at, p.remote_id, p.options->>'privacy_level' AS privacy, 0 AS sent_bytes,
               v.byte_size, v.id AS source_id
        FROM tiktok_publication p
        LEFT JOIN video_asset v ON v.id = p.source_id AND v.owner_id = p.owner_id
        SQL;

    public function __construct(private Connection $db)
    {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public function page(User $owner, int $page = 1, string $network = '', string $state = '', int $limit = 20): array
    {
        $where = 'owner_id = :owner';
        $parameters = ['owner' => $owner->getId()];
        $types = [];
        if (in_array($network, ['youtube', 'instagram', 'tiktok'], true)) {
            $where .= ' AND network = :network';
            $parameters['network'] = $network;
        }
        if (isset(self::STATES[$state])) {
            $where .= ' AND status IN (:statuses)';
            $parameters['statuses'] = self::STATES[$state];
            $types['statuses'] = ArrayParameterType::STRING;
        }
        $from = ' FROM ('.self::FEED.') feed WHERE '.$where;
        $total = (int) $this->db->fetchOne('SELECT COUNT(*)'.$from, $parameters, $types);
        $limit = max(1, min(50, $limit));
        $pages = max(1, (int) ceil($total / $limit));
        $page = max(1, min($page, $pages));
        $items = $this->db->fetchAllAssociative('SELECT *'.$from.' ORDER BY created_at DESC, network ASC, id DESC LIMIT '.$limit.' OFFSET '.(($page - 1) * $limit), $parameters, $types);

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
