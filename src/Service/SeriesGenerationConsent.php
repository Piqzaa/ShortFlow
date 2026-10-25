<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\SeriesOccurrence;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class SeriesGenerationConsent
{
    public function __construct(private EntityManagerInterface $em, private AiCatalog $catalog, private AiKeys $keys, private ClockInterface $clock)
    {
    }

    public function assertAllowed(AiEpisode $episode): void
    {
        $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['episode' => $episode]);
        if (!$occurrence) {
            return;
        }
        $this->em->refresh($occurrence);
        if (!$occurrence->isAutomatic()) {
            return;
        }
        $recurrence = $occurrence->getRecurrence();
        $this->em->refresh($recurrence);
        $settings = $recurrence->getSettings();
        $recipe = $settings['recipe'];
        $model = $this->catalog->model($recipe['model']);
        $cents = array_sum(array_map(fn (int $s): int => $this->catalog->estimate($recipe['model'], $s), $settings['durations']));
        $hash = (new SeriesAuthorization($this->catalog))->fingerprint($recipe, $settings['brief'], $settings['durations'], $cents, $this->keys->identity($episode->getOwner(), $model['provider'], $recipe['accessMode']));
        if (($settings['authorizationVersion'] ?? 0) !== SeriesAuthorization::VERSION || 'paused' === $recurrence->getStatus() || $recurrence->getRevision() !== $occurrence->getAuthorizationRevision() || $occurrence->getReservedCents() !== $cents || !hash_equals($settings['hash'], $hash) || $occurrence->getScheduledAt() <= $this->clock->now()->modify('+2 minutes')) {
            throw new \RuntimeException('L’autorisation récurrente a changé, est suspendue ou son créneau est dépassé. Aucun nouvel appel payant. Vérifie puis reprends manuellement l’épisode.');
        }
    }

    public function manual(AiEpisode $episode, callable $action): void
    {
        $this->em->getConnection()->transactional(function () use ($episode, $action): void {
            $this->em->lock($episode->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $action();
            $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['episode' => $episode]);
            $occurrence?->manageManually();
            $this->em->flush();
        });
    }
}
