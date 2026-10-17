<?php

namespace App\Service;

use Symfony\Component\Clock\ClockInterface;

final class PublicationTime
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function resolve(string $local, string $timezone): \DateTimeImmutable
    {
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $local)) {
            throw new PublicationException('Choisis une date, une heure et un fuseau valides.');
        }
        $wall = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $local, new \DateTimeZone('UTC'));
        if (!$wall || $wall->format('Y-m-d\TH:i') !== $local) {
            throw new PublicationException('Cette date est invalide.');
        }
        $zone = new \DateTimeZone($timezone);
        $offsets = [];
        foreach ($zone->getTransitions($wall->getTimestamp() - 172800, $wall->getTimestamp() + 172800) ?: [] as $transition) {
            $offsets[$transition['offset']] = true;
        }
        $matches = [];
        foreach (array_keys($offsets) as $offset) {
            $candidate = $wall->modify(sprintf('%+d seconds', -$offset));
            if ($candidate->setTimezone($zone)->format('Y-m-d\TH:i') === $local) {
                $matches[] = $candidate;
            }
        }
        if (1 !== count($matches)) {
            throw new PublicationException('Cette heure locale est inexistante ou ambiguë lors du changement d’heure. Choisis une autre heure.');
        }

        return $matches[0];
    }

    public function validateFuture(\DateTimeImmutable $date): void
    {
        $delay = $date->getTimestamp() - $this->clock->now()->getTimestamp();
        if ($delay < 120 || $delay > 90 * 86400) {
            throw new PublicationException('Choisis un créneau entre deux minutes et 90 jours dans le futur.');
        }
    }

    public function assertOnTime(\DateTimeImmutable $date): void
    {
        if ($this->clock->now()->getTimestamp() > $date->getTimestamp() + 120) {
            throw new PublicationException('Le créneau est dépassé. Choisis une nouvelle date et confirme à nouveau.');
        }
    }
}
