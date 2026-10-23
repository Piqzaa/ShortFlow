<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class EpisodeReservations
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function slots(User $owner, ?AiEpisode $completing = null, ?AiGeneration $completingGeneration = null, bool $includeActive = false): int
    {
        $db = $this->em->getConnection();
        $states = $includeActive ? "'prepared','waiting_key','review','queued','submitting','processing'" : "'prepared','waiting_key','review'";
        $plans = (int) $db->fetchOne("SELECT COUNT(*) FROM ai_shot s JOIN ai_episode e ON e.id = s.episode_id LEFT JOIN ai_series r ON r.id = e.series_id LEFT JOIN ai_generation g ON g.id = s.generation_id WHERE COALESCE(e.owner_id, r.owner_id) = ? AND e.flow_state IN ('running', 'paused', 'assembling') AND (g.id IS NULL OR g.status IN (".$states.')) AND (g.id IS NULL OR g.id <> ?)', [$owner->getId(), $completingGeneration?->getId() ?? 0]);
        $exports = (int) $db->fetchOne("SELECT COUNT(*) FROM ai_episode e LEFT JOIN ai_series r ON r.id = e.series_id LEFT JOIN video_edit v ON v.id = e.assembly_id WHERE COALESCE(e.owner_id, r.owner_id) = ? AND e.flow_state IN ('running', 'paused', 'assembling') AND (v.output_id IS NULL) AND e.id <> ?", [$owner->getId(), $completing?->getId() ?? 0]);

        return $plans + $exports;
    }
}
