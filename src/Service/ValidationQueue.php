<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\ClockInterface;

final class ValidationQueue
{
    public const LABELS = ['watch' => 'Vidéos à regarder', 'prepare' => 'Publications à préparer', 'confirm' => 'Brouillons à confirmer', 'blocked' => 'À débloquer'];

    private const FEED = <<<'SQL'
        WITH jobs AS (
            SELECT id, owner_id, submission_key, status, title, created_at, 'youtube' AS network FROM youtube_publication WHERE owner_id = :owner
            UNION ALL SELECT id, owner_id, submission_key, status, title, created_at, 'instagram' FROM instagram_publication WHERE owner_id = :owner
            UNION ALL SELECT id, owner_id, submission_key, status, title, created_at, 'tiktok' FROM tiktok_publication WHERE owner_id = :owner
        ), job_states AS (
            SELECT submission_key, COUNT(*) AS total,
                   BOOL_OR(status IN ('failed','paused','review','cancelled')) AS needs_attention
            FROM jobs GROUP BY submission_key
        ), feed AS (
            SELECT e.id, 'episode' AS resource, NULL::text AS network, e.title,
                   COALESCE(s.name, 'Studio') AS origin,
                   CASE WHEN e.flow_state = 'paused' THEN 'blocked' WHEN e.approved_video_id IS NULL THEN 'watch' ELSE 'prepare' END AS kind,
                   CASE WHEN e.flow_state = 'paused' THEN 'La préparation de cette vidéo est en pause.' WHEN e.approved_video_id IS NULL THEN 'Regarde le résultat et choisis la version à valider.' ELSE 'La version est validée. Prépare ses textes et ses destinations.' END AS reason,
                   NULL::timestamp AS scheduled_at, NULL::text AS timezone,
                   COALESCE(a.updated_at, s.created_at, TIMESTAMP '1970-01-01') AS changed_at
            FROM ai_episode e
            LEFT JOIN ai_series s ON s.id = e.series_id
            LEFT JOIN video_edit a ON a.id = e.assembly_id AND a.owner_id = :owner
            WHERE (e.owner_id = :owner OR (e.owner_id IS NULL AND s.owner_id = :owner))
              AND (s.id IS NULL OR s.owner_id = :owner)
              AND (e.flow_state = 'paused' OR (e.flow_state = 'ready' AND (
                  e.approved_video_id IS NULL OR (
                      EXISTS (SELECT 1 FROM video_asset v WHERE v.id = e.approved_video_id AND v.owner_id = :owner)
                      AND NOT EXISTS (
                          SELECT 1 FROM publication_draft d JOIN publication_draft_item i ON i.draft_id = d.id
                          WHERE d.owner_id = :owner AND d.episode_id = e.id AND i.video_id = e.approved_video_id
                      )
                  )
              )))
            UNION ALL
            SELECT d.id, 'draft', NULL, d.title, 'Publication',
                   CASE WHEN d.status = 'draft' THEN 'confirm' ELSE 'blocked' END,
                   CASE WHEN d.status = 'draft' THEN 'Complète les réglages et vérifie avant de confirmer.'
                        WHEN d.status = 'scheduled' THEN 'Le créneau est dépassé. Choisis une nouvelle date.'
                        ELSE 'La publication attend une correction ou une vérification.' END,
                   d.scheduled_at, d.timezone, d.updated_at
            FROM publication_draft d
            LEFT JOIN job_states j ON j.submission_key = d.submission_key
            WHERE d.owner_id = :owner AND (
                d.status IN ('draft','blocked') OR (d.status = 'scheduled' AND d.scheduled_at < :cutoff)
                OR (d.status = 'dispatched' AND (
                    COALESCE(j.needs_attention, FALSE) OR COALESCE(j.total, 0) <>
                    json_array_length(d.networks) * (SELECT COUNT(*) FROM publication_draft_item i WHERE i.draft_id = d.id)
                ))
            )
            UNION ALL
            SELECT j.id, 'job', j.network, j.title, 'Envoi ' || j.network, 'blocked',
                   'Vérifie le suivi et les accès avant toute reprise.', NULL, NULL, j.created_at
            FROM jobs j WHERE j.status IN ('failed','paused','review')
              AND NOT EXISTS (SELECT 1 FROM publication_draft d WHERE d.owner_id = :owner AND d.submission_key = j.submission_key)
        )
        SQL;

    public function __construct(private Connection $db, private ClockInterface $clock)
    {
    }

    /** @return array{items:list<array<string,mixed>>,counts:array<string,int>,total:int,page:int,pages:int,filter:string} */
    public function page(User $owner, string $filter = '', int $page = 1): array
    {
        $filter = isset(self::LABELS[$filter]) ? $filter : '';
        $parameters = ['owner' => $owner->getId(), 'cutoff' => $this->clock->now()->modify('-120 seconds')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
        $counts = array_fill_keys(array_keys(self::LABELS), 0);
        foreach ($this->db->fetchAllAssociative(self::FEED.' SELECT kind, COUNT(*) AS total FROM feed GROUP BY kind', $parameters) as $row) {
            $counts[$row['kind']] = (int) $row['total'];
        }
        $total = '' === $filter ? array_sum($counts) : $counts[$filter];
        $pages = max(1, (int) ceil($total / 20));
        $page = max(1, min($page, $pages));
        $where = '' === $filter ? '' : ' WHERE kind = :kind';
        if ('' !== $filter) {
            $parameters['kind'] = $filter;
        }
        $items = $this->db->fetchAllAssociative(self::FEED.' SELECT * FROM feed'.$where." ORDER BY CASE kind WHEN 'blocked' THEN 0 WHEN 'watch' THEN 1 WHEN 'prepare' THEN 2 ELSE 3 END, changed_at ASC, resource ASC, id ASC LIMIT 20 OFFSET ".(($page - 1) * 20), $parameters);

        return ['items' => $items, 'counts' => $counts, 'total' => $total, 'page' => $page, 'pages' => $pages, 'filter' => $filter];
    }
}
