<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;

final class ProductionCalendar
{
    public const LABELS = ['production_planned' => 'Épisode à préparer', 'production_running' => 'Préparation en cours', 'video_review' => 'Vidéo à valider', 'publication_prepare' => 'Publication à préparer', 'production_attention' => 'Production à reprendre'];

    private const FEED = <<<'SQL'
        WITH prepared AS (
            SELECT d.id, d.episode_id, i.video_id, d.scheduled_at, d.status, d.networks
            FROM publication_draft d JOIN publication_draft_item i ON i.draft_id = d.id
            WHERE d.owner_id = :owner AND d.status <> 'cancelled'
              AND (SELECT COUNT(*) FROM publication_draft_item n WHERE n.draft_id = d.id) = 1
        ), episodes AS (
            SELECT e.id, e.title, e.series_id, e.flow_state, e.approved_video_id,
                   COALESCE(s.name, 'Studio') AS origin,
                   p.id AS draft_id, COALESCE(p.networks, r.settings->'networks', '[]'::json) AS networks,
                   CASE WHEN e.flow_state = 'ready' AND e.approved_video_id IS NULL THEN 'video_review'
                        WHEN e.flow_state = 'ready' THEN 'publication_prepare'
                        WHEN e.flow_state = 'paused' OR g.status IN ('waiting_key','review') THEN 'production_attention'
                        WHEN e.flow_state IN ('running','assembling') OR g.status IN ('queued','submitting','processing') THEN 'production_running'
                        ELSE 'production_planned' END AS state
            FROM ai_episode e
            LEFT JOIN ai_series s ON s.id = e.series_id
            LEFT JOIN series_recurrence r ON r.series_id = s.id
            LEFT JOIN ai_generation g ON g.id = e.generation_id AND g.owner_id = :owner
            LEFT JOIN LATERAL (
                SELECT d.* FROM prepared d WHERE d.episode_id = e.id AND d.video_id = e.approved_video_id ORDER BY d.id DESC LIMIT 1
            ) p ON TRUE
            WHERE (e.owner_id = :owner OR (e.owner_id IS NULL AND s.owner_id = :owner))
              AND (s.id IS NULL OR s.owner_id = :owner)
              AND (e.flow_state IS NULL OR e.flow_state <> 'stopped')
              AND NOT (e.flow_state IS NULL AND COALESCE(g.status, '') IN ('cancelled','available'))
              AND NOT EXISTS (
                  SELECT 1 FROM prepared d WHERE d.episode_id = e.id AND d.video_id = e.approved_video_id
                    AND (d.scheduled_at IS NOT NULL OR d.status = 'dispatched')
              )
              AND (e.approved_video_id IS NULL OR EXISTS (SELECT 1 FROM video_asset v WHERE v.id = e.approved_video_id AND v.owner_id = :owner))
        ), feed AS (
            SELECT o.id, 'occurrence' AS kind, o.scheduled_at AS at, s.id AS series_id, e.id AS episode_id, e.draft_id,
                   COALESCE(e.title, 'Épisode à préparer') AS title, s.name AS origin, o.networks,
                   CASE WHEN e.state IN ('video_review','publication_prepare') THEN e.state
                        WHEN o.status = 'attention' THEN 'production_attention'
                        ELSE COALESCE(e.state, 'production_running') END AS state
            FROM series_occurrence o JOIN series_recurrence r ON r.id = o.recurrence_id
            JOIN ai_series s ON s.id = r.series_id AND s.owner_id = :owner
            LEFT JOIN episodes e ON e.id = o.episode_id AND e.series_id = s.id
            WHERE o.scheduled_at >= :start AND o.scheduled_at < :end
              AND (o.episode_id IS NULL OR e.id IS NOT NULL)
            UNION ALL
            SELECT r.id, 'next', r.next_at, s.id, NULL, NULL, 'Prochain épisode', s.name,
                   COALESCE(r.settings->'networks', '[]'::json), 'production_planned'
            FROM series_recurrence r JOIN ai_series s ON s.id = r.series_id AND s.owner_id = :owner
            WHERE r.status = 'active' AND r.next_at >= :start AND r.next_at < :end
              AND r.started < COALESCE((r.settings->>'limit')::int, 0)
              AND NOT EXISTS (SELECT 1 FROM series_occurrence o WHERE o.recurrence_id = r.id AND o.scheduled_at = r.next_at)
            UNION ALL
            SELECT e.id, 'undated', NULL, e.series_id, e.id, e.draft_id, e.title, e.origin, e.networks, e.state
            FROM episodes e WHERE NOT EXISTS (SELECT 1 FROM series_occurrence o WHERE o.episode_id = e.id)
        )
        SQL;

    public function __construct(private Connection $db)
    {
    }

    /** @return array{dated:list<array<string,mixed>>,undated:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function page(User $owner, \DateTimeImmutable $start, \DateTimeImmutable $end, string $network = '', string $state = '', int $page = 1): array
    {
        if ($state && !isset(self::LABELS[$state])) {
            return ['dated' => [], 'undated' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
        }
        $utc = new \DateTimeZone('UTC');
        $parameters = ['owner' => $owner->getId(), 'start' => $start->setTimezone($utc)->format('Y-m-d H:i:s'), 'end' => $end->setTimezone($utc)->format('Y-m-d H:i:s')];
        $where = ' WHERE TRUE';
        if ($network) {
            $where .= ' AND EXISTS (SELECT 1 FROM json_array_elements_text(networks) n WHERE n = :network)';
            $parameters['network'] = $network;
        }
        if ($state) {
            $where .= ' AND state = :state';
            $parameters['state'] = $state;
        }
        $total = (int) $this->db->fetchOne(self::FEED.' SELECT COUNT(*) FROM feed'.$where." AND kind = 'undated'", $parameters);
        $pages = max(1, (int) ceil($total / 12));
        $page = max(1, min($page, $pages));
        $dated = $this->db->fetchAllAssociative(self::FEED.' SELECT * FROM feed'.$where." AND kind <> 'undated' ORDER BY at ASC, kind ASC, id ASC", $parameters);
        foreach ($dated as &$entry) {
            $entry['at'] = new \DateTimeImmutable($entry['at'], $utc);
        }
        unset($entry);
        $undated = $this->db->fetchAllAssociative(self::FEED.' SELECT * FROM feed'.$where." AND kind = 'undated' ORDER BY id ASC LIMIT 12 OFFSET ".(($page - 1) * 12), $parameters);

        return ['dated' => $dated, 'undated' => $undated, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }
}
