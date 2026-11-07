<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class SeriesCalendar
{
    public function __construct(private EntityManagerInterface $em, private PublicationTime $time, private ClockInterface $clock)
    {
    }

    /** @param array<string,mixed> $data
     * @return array{start:string,end:?string,weekdays:list<int>,hour:string,timezone:string,count:int}
     */
    private function rules(array $data): array
    {
        $days = $data['weekdays'] ?? [];
        if (!is_array($days) || !$days || count($days) > 7 || array_filter($days, static fn ($day): bool => !is_int($day) || $day < 1 || $day > 7) || count(array_unique($days)) !== count($days) || !is_int($data['count'] ?? null) || $data['count'] < 1 || $data['count'] > 90) {
            throw new PublicationException('Choisis les jours et un nombre de 1 à 90 épisodes.');
        }
        foreach (['start', 'hour', 'timezone'] as $field) {
            if (!is_string($data[$field] ?? null)) {
                throw new PublicationException('Vérifie les dates, l’heure et le fuseau.');
            }
        }
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $data['hour']) || !in_array($data['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            throw new PublicationException('Vérifie l’heure et le fuseau.');
        }
        $end = $data['end'] ?? null;
        if (null !== $end && !is_string($end)) {
            throw new PublicationException('Vérifie la date de fin.');
        }
        sort($days);

        return ['start' => $data['start'], 'end' => $end ?: null, 'weekdays' => $days, 'hour' => $data['hour'], 'timezone' => $data['timezone'], 'count' => $data['count']];
    }

    /** @param array<string,mixed> $data @return list<string> */
    public function dates(array $data): array
    {
        $rules = $this->rules($data);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $rules['start'], new \DateTimeZone('UTC'));
        $end = $rules['end'] ? \DateTimeImmutable::createFromFormat('!Y-m-d', $rules['end'], new \DateTimeZone('UTC')) : null;
        if (!$date || $date->format('Y-m-d') !== $rules['start'] || (null !== $rules['end'] && (!$end || $end->format('Y-m-d') !== $rules['end'] || $end < $date))) {
            throw new PublicationException('Vérifie la période : la fin doit suivre ou égaler la date de début.');
        }
        $today = $this->clock->now()->setTimezone(new \DateTimeZone($rules['timezone']));
        if ($rules['start'] < $today->format('Y-m-d') || ($rules['end'] && $rules['end'] > $today->modify('+90 days')->format('Y-m-d'))) {
            throw new PublicationException('Choisis une période dans les 90 prochains jours.');
        }
        $dates = [];
        for ($i = 0; $i <= 90 && count($dates) < $rules['count']; ++$i, $date = $date->modify('+1 day')) {
            if ($end && $date > $end) {
                break;
            }
            if (!in_array((int) $date->format('N'), $rules['weekdays'], true)) {
                continue;
            }
            try {
                $at = $this->time->resolve($date->format('Y-m-d').'T'.$rules['hour'], $rules['timezone']);
                $this->time->validateFuture($at);
            } catch (PublicationException $error) {
                throw new PublicationException($date->format('d/m/Y').' : '.$error->getMessage());
            }
            $dates[] = $at->format(DATE_ATOM);
        }
        if (count($dates) !== $rules['count']) {
            throw new PublicationException('La période ne contient pas assez de créneaux. Réduis le nombre d’épisodes ou étends la période, dans les 90 prochains jours.');
        }

        return $dates;
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    public function preview(AiSeries $series, array $data): array
    {
        $rules = $this->rules($data);
        $dates = $this->dates($rules);
        // A confirmed source, including a partial/failed dispatched publication, keeps its own calendar.
        $episodes = $this->em->createQueryBuilder()->select('e.id', 'e.number', 'e.title')->from(AiEpisode::class, 'e')->where('e.series = :series')->andWhere("NOT EXISTS (SELECT d.id FROM App\Entity\PublicationDraft d WHERE d.episode = e AND d.status NOT IN ('draft', 'cancelled'))")->setParameter('series', $series)->orderBy('e.number', 'ASC')->addOrderBy('e.id', 'ASC')->setMaxResults($rules['count'])->getQuery()->getArrayResult();
        $next = 1 + (int) $this->em->createQuery('SELECT MAX(e.number) FROM App\Entity\AiEpisode e WHERE e.series = :series')->setParameter('series', $series)->getSingleScalarResult();
        $slots = [];
        foreach ($dates as $i => $at) {
            $episode = $episodes[$i] ?? null;
            $slots[] = ['episode' => $episode ? (int) $episode['id'] : null, 'number' => $episode ? (int) $episode['number'] : $next++, 'title' => $episode['title'] ?? 'Épisode à préparer', 'at' => $at];
        }
        $plan = ['rules' => $rules, 'slots' => $slots];

        return $plan + ['hash' => hash('sha256', serialize([$series->getId(), $series->getRevision(), $plan]))];
    }

    /** @param array<string,mixed> $data */
    public function save(User $owner, AiSeries $series, array $data): void
    {
        $this->em->getConnection()->transactional(function () use ($owner, $series, $data): void {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            if ($series->getOwner()->getId() !== $owner->getId() || $series->getRevision() !== (int) ($data['revision'] ?? -1)) {
                throw new PublicationException('La série a changé. Recharge son calendrier avant d’enregistrer.');
            }
            $plan = $this->preview($series, $data);
            if (!is_string($data['snapshot'] ?? null) || !hash_equals($plan['hash'], $data['snapshot'])) {
                throw new PublicationException('Les réglages ou les épisodes ont changé. Vérifie le nouvel aperçu avant d’enregistrer.');
            }
            unset($plan['hash']);
            $series->planCalendar($plan + ['savedAt' => $this->clock->now()->format(DATE_ATOM)]);
            $this->em->flush();
        });
    }
}
