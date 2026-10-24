<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\SeriesOccurrence;
use App\Entity\SeriesRecurrence;
use App\Message\PrepareSeriesEpisode;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class SeriesRecurrences
{
    public function __construct(private EntityManagerInterface $em, private AiEpisodeFlow $flow, private AiCatalog $catalog, private AiKeys $keys, private AiReferenceStorage $references, private AiGenerations $generations, private LocalStoryWriter $writer, private WeeklySeriesTime $time, private ClockInterface $clock, private MessageBusInterface $bus)
    {
    }

    public function find(AiSeries $series): ?SeriesRecurrence
    {
        return $this->em->getRepository(SeriesRecurrence::class)->findOneBy(['series' => $series]);
    }

    /** @return array<string,mixed> */
    public function preview(AiSeries $series): array
    {
        $recipe = $series->getRecipe();
        $model = $this->catalog->model($recipe['model']);
        $this->catalog->validateReferences($recipe['model'], $recipe['reference'], $recipe['endReference']);
        $durations = $this->flow->durations($recipe['model'], $recipe['totalSeconds'] ?? 30);
        foreach ($durations as $duration) {
            $this->catalog->validate($recipe['model'], $duration, $recipe['ratio']);
        }
        if (!$this->keys->hasAccess($series->getOwner(), $model['provider'], $recipe['accessMode'])) {
            throw new \RuntimeException('Connecte l’accès BYOK de la série pour préparer sa récurrence.');
        }
        $identity = $this->keys->identity($series->getOwner(), $model['provider'], $recipe['accessMode']);
        foreach ([$recipe['reference'], $recipe['endReference']] as $reference) {
            if ($reference) {
                $this->references->path($series->getOwner(), $reference);
            }
        }
        $cents = array_sum(array_map(fn (int $s): int => $this->catalog->estimate($recipe['model'], $s), $durations));
        $hash = hash('sha256', serialize([$recipe, $series->initialPrompt(), $durations, $cents, $model['checked'] ?? AiCatalog::CHECKED, $identity]));

        return ['recipe' => $recipe, 'brief' => $series->initialPrompt(), 'durations' => $durations, 'cents' => $cents, 'hash' => $hash];
    }

    /** @param array<string,mixed> $data */
    public function activate(AiSeries $series, array $data): SeriesRecurrence
    {
        return $this->em->getConnection()->transactional(function () use ($series, $data): SeriesRecurrence {
            $this->em->lock($series->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            $recurrence = $this->find($series);
            if ($recurrence) {
                $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
            }
            if (($recurrence?->getRevision() ?? 0) !== (int) ($data['revision'] ?? -1)) {
                throw new \RuntimeException('La récurrence a changé. Recharge avant de confirmer.');
            }
            if (!$this->writer->available() || !$this->generations->enabled()) {
                throw new \RuntimeException('Active le rédacteur local et la génération BYOK avant d’activer la récurrence.');
            }
            $preview = $this->preview($series);
            if (!hash_equals($preview['hash'], $data['quote'] ?? '') || empty($data['consent'])) {
                throw new \RuntimeException('Vérifie l’estimation et confirme les coûts fournisseur avant d’activer.');
            }
            if (($data['limit'] ?? 0) < 1 || $data['limit'] > 12 || empty($data['networks']) || array_diff($data['networks'], array_keys(MultiNetworkPublishing::LABELS)) || !in_array($data['tone'], ['auto', 'humour', 'aventure', 'calme'], true) || !in_array($data['continuity'], ['independent', 'linked'], true)) {
                throw new \InvalidArgumentException('Vérifie le nombre d’épisodes, les réseaux et l’ambiance.');
            }
            $model = $this->catalog->model($preview['recipe']['model']);
            if ('linked' === $data['continuity'] && count($preview['durations']) > 1 && (!($model['image'] ?? false) || ($model['subjectReference'] ?? false) || ($model['requiresEnd'] ?? false) || !$preview['recipe']['reference'])) {
                throw new \RuntimeException('Pour prolonger l’action, configure un modèle image compatible et sa référence dans la série.');
            }
            $next = $this->time->next($data['weekday'], $data['hour'], $data['timezone'], $this->clock->now()->modify('+2 hours'));
            $recurrence ??= new SeriesRecurrence($series);
            $this->pauseEpisodes($recurrence);
            $settings = array_intersect_key($data, array_flip(['weekday', 'hour', 'timezone', 'networks', 'limit', 'tone', 'continuity'])) + $preview;
            $recurrence->activate($settings, $next, $this->clock->now());
            $this->em->persist($recurrence);
            $this->em->flush();
            $this->wake($recurrence);

            return $recurrence;
        });
    }

    public function pause(SeriesRecurrence $recurrence, int $revision): void
    {
        $this->em->getConnection()->transactional(function () use ($recurrence, $revision): void {
            $this->em->lock($recurrence->getSeries()->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
            if ($recurrence->getRevision() !== $revision) {
                throw new \RuntimeException('La récurrence a changé. Recharge la page.');
            }
            $recurrence->pause('Récurrence en pause. Une génération déjà transmise peut encore se terminer et être facturée. Les publications déjà confirmées restent dans le calendrier.');
            $this->pauseEpisodes($recurrence);
            $this->em->flush();
        });
    }

    public function wake(SeriesRecurrence $recurrence): void
    {
        if ('active' !== $recurrence->getStatus() || !$recurrence->getNextAt()) {
            return;
        }
        $delay = max(0, ($recurrence->getNextAt()->getTimestamp() - 86400 - $this->clock->now()->getTimestamp()) * 1000);
        $this->bus->dispatch(new PrepareSeriesEpisode((int) $recurrence->getId(), $recurrence->getRevision(), $recurrence->getNextAt()->format(DATE_ATOM)), $delay ? [new DelayStamp($delay)] : []);
    }

    public function produce(PrepareSeriesEpisode $message): void
    {
        $recurrence = $this->em->find(SeriesRecurrence::class, $message->id);
        if (!$recurrence) {
            return;
        }
        $occurrence = $this->em->getConnection()->transactional(function () use ($recurrence, $message): ?SeriesOccurrence {
            $this->em->lock($recurrence->getSeries()->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
            if ('active' !== $recurrence->getStatus() || $recurrence->getRevision() !== $message->revision || $recurrence->getNextAt()?->format(DATE_ATOM) !== $message->scheduledAt) {
                return null;
            }
            $at = $recurrence->getNextAt();
            if ($this->clock->now()->getTimestamp() < $at->getTimestamp() - 86400) {
                $this->wake($recurrence);

                return null;
            }
            $settings = $recurrence->getSettings();
            $series = $recurrence->getSeries();
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            try {
                if (!hash_equals($settings['hash'], $this->preview($series)['hash'])) {
                    throw new \RuntimeException('Le thème, les références, le modèle, le tarif ou le compte BYOK ont changé. Vérifie et confirme à nouveau la récurrence.');
                }
            } catch (\RuntimeException $error) {
                $recurrence->pause($error->getMessage());
                $this->em->flush();

                return null;
            }
            $next = $recurrence->getStarted() + 1 < $settings['limit'] ? $this->time->next($settings['weekday'], $settings['hour'], $settings['timezone'], max($at, $this->clock->now()->modify('+2 hours'))) : null;
            $episode = null;
            if ($at > $this->clock->now()->modify('+2 hours')) {
                $number = (int) $this->em->createQuery('SELECT MAX(e.number) FROM App\Entity\AiEpisode e WHERE e.series = :series')->setParameter('series', $series)->getSingleScalarResult() + 1;
                $episode = new AiEpisode($series, $number);
                $this->em->persist($episode);
            }
            $occurrence = new SeriesOccurrence($recurrence, $at, $settings['timezone'], $settings['networks'], $this->clock->now(), $episode);
            if (!$episode) {
                $occurrence->fail('Créneau trop proche ou dépassé. Aucune génération lancée pour ce créneau.');
            }
            $recurrence->advanceSchedule($next);
            $this->em->persist($occurrence);
            $this->em->flush();
            $this->wake($recurrence);

            return $episode ? $occurrence : null;
        });
        if (!$occurrence) {
            return;
        }
        try {
            $settings = $recurrence->getSettings();
            $series = $recurrence->getSeries();
            $episode = $occurrence->getEpisode();
            $previous = $this->em->getRepository(SeriesOccurrence::class)->findBy(['recurrence' => $recurrence], ['scheduledAt' => 'DESC'], 6);
            $memory = [];
            foreach ($previous as $old) {
                if ($old->getId() !== $occurrence->getId() && $old->getSummary()) {
                    $memory[] = ['title' => $old->getEpisode()?->getTitle(), 'summary' => $old->getSummary()];
                }
            }
            $story = $this->writer->propose($series->getTheme(), $settings['durations'], $settings['continuity'], $settings['tone'], ['style' => $series->getStyle(), 'characters' => $series->getCharacters(), 'previous' => $memory]);
            $this->em->getConnection()->transactional(function () use ($occurrence, $recurrence, $episode, $story, $message, $series, $settings): void {
                $this->em->lock($series->getOwner(), LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($series);
                $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
                if ('paused' === $recurrence->getStatus() || $recurrence->getRevision() !== $message->revision || 1 !== $episode->getRevision() || $occurrence->getScheduledAt() <= $this->clock->now()->modify('+2 minutes')) {
                    throw new \RuntimeException('La préparation a été suspendue, modifiée ou son créneau est dépassé. Aucun lancement payant automatique.');
                }
                if (!hash_equals($settings['hash'], $this->preview($series)['hash'])) {
                    throw new \RuntimeException('Les paramètres ou l’accès BYOK ont changé pendant la rédaction. Confirme à nouveau la récurrence.');
                }
                $recipe = $settings['recipe'];
                $this->flow->prepare($series->getOwner(), $recipe + ['source' => $recipe['reference'] ? 'image' : 'text', 'target' => array_sum($settings['durations']), 'shots' => $story['shots'], 'prompt' => $series->initialPrompt(), 'title' => $story['title'], 'continuity' => $settings['continuity'], 'mute' => false, 'useEnd' => (bool) $recipe['endReference'], 'revision' => 1], $episode);
                $occurrence->story($story['summary']);
                if ($story['warnings']) {
                    $issue = 'Scénario à relire dans l’épisode avant génération. '.implode(' ', $story['warnings']);
                    $occurrence->fail($issue);
                    $recurrence->pause($issue);
                    $this->em->flush();

                    return;
                }
                $quote = $this->flow->quote($episode);
                $recurrence->reserve($quote['cents']);
                $occurrence->produced($quote['cents']);
                $this->em->flush();
                $this->flow->confirm($episode, $quote['hash']);
            });
        } catch (\Throwable $failure) {
            if (!$this->em->isOpen()) {
                throw $failure;
            }
            $this->em->getConnection()->transactional(function () use ($occurrence, $recurrence, $message, $failure): void {
                $this->em->lock($recurrence->getSeries()->getOwner(), LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($occurrence, LockMode::PESSIMISTIC_WRITE);
                $issue = $failure instanceof StoryWritingException || $failure instanceof PublicationException || \RuntimeException::class === get_class($failure) ? $failure->getMessage() : 'La production a été interrompue. Ouvre l’épisode pour vérifier et reprendre manuellement.';
                $occurrence->fail($issue);
                if ($recurrence->getRevision() === $message->revision) {
                    $recurrence->pause($issue);
                }
                $this->em->flush();
            });
        }
    }

    private function pauseEpisodes(SeriesRecurrence $recurrence): void
    {
        if (!$recurrence->getId()) {
            return;
        }
        foreach ($this->em->getRepository(SeriesOccurrence::class)->findBy(['recurrence' => $recurrence]) as $occurrence) {
            if ($episode = $occurrence->getEpisode()) {
                $this->flow->pause($episode);
            }
        }
    }
}
