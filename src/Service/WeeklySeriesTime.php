<?php

namespace App\Service;

final class WeeklySeriesTime
{
    public function __construct(private PublicationTime $time)
    {
    }

    public function next(int $weekday, string $hour, string $timezone, \DateTimeImmutable $after): \DateTimeImmutable
    {
        if ($weekday < 1 || $weekday > 7 || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $hour) || !in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new \InvalidArgumentException('Vérifie le jour, l’heure et le fuseau.');
        }
        $date = $after->setTimezone(new \DateTimeZone($timezone))->setTime(12, 0);
        $date = $date->modify('+'.(($weekday - (int) $date->format('N') + 7) % 7).' days');
        for ($i = 0; $i < 4; ++$i, $date = $date->modify('+7 days')) {
            try {
                $candidate = $this->time->resolve($date->format('Y-m-d').'T'.$hour, $timezone);
                if ($candidate > $after) {
                    return $candidate;
                }
            } catch (PublicationException) {
                // Skip a non-existent or ambiguous wall time; never guess an offset.
            }
        }
        throw new \RuntimeException('Aucun créneau hebdomadaire valide trouvé.');
    }
}
