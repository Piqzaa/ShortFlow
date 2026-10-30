<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\SeriesOccurrence;
use App\Entity\SeriesRecurrence;
use App\Message\InspectSeriesWriting;
use App\Message\PrepareSeriesEpisode;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class SeriesRecurrences
{
    private const WRITING_TIMEOUT_SECONDS = 600;

    public function __construct(private EntityManagerInterface $em, private AiEpisodeFlow $flow, private AiCatalog $catalog, private AiReferenceStorage $references, private LocalStoryWriter $writer, private WeeklySeriesTime $time, private ClockInterface $clock, private MessageBusInterface $bus, private SocialAccounts $accounts)
    {
    }

    public function find(AiSeries $series): ?SeriesRecurrence
    {
        return $this->em->getRepository(SeriesRecurrence::class)->findOneBy(['series' => $series]);
    }

    /** @return array{items:list<SeriesOccurrence>,total:int,page:int,pages:int} */
    public function historyPage(SeriesRecurrence $recurrence, int $page = 1): array
    {
        $total = $this->em->getRepository(SeriesOccurrence::class)->count(['recurrence' => $recurrence]);
        $pages = max(1, (int) ceil($total / 12));
        $page = max(1, min($page, $pages));
        $items = $this->em->createQueryBuilder()->select('o', 'e')->from(SeriesOccurrence::class, 'o')->leftJoin('o.episode', 'e')->where('o.recurrence = :recurrence')->setParameter('recurrence', $recurrence)->orderBy('o.scheduledAt', 'DESC')->addOrderBy('o.id', 'DESC')->setFirstResult(($page - 1) * 12)->setMaxResults(12)->getQuery()->getResult();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return array<string,mixed> */
    public function preview(AiSeries $series): array
    {
        $recipe = $series->getRecipe();
        if ('auto' === $recipe['ratio']) {
            throw new \RuntimeException('Choisis un format final explicite dans la série avant d’autoriser sa récurrence.');
        }
        $model = $this->catalog->model($recipe['model']);
        $this->catalog->validateReferences($recipe['model'], $recipe['reference'], $recipe['endReference']);
        foreach ($recipe['subjects'] ?? [] as $subject) {
            $this->references->path($series->getOwner(), $subject);
        }
        (new AiPlanInputs($this->catalog, $this->references))->subjectDetails($series->getOwner(), $recipe['model'], $recipe['subjects'] ?? [], $recipe['subjectDetails'] ?? [], ($model['subjectReference'] ?? false) ? $recipe['reference'] : null);
        $durations = $this->flow->durations($recipe['model'], $recipe['totalSeconds'] ?? 30);
        foreach ($durations as $duration) {
            $this->catalog->validate($recipe['model'], $duration, $recipe['ratio']);
        }
        foreach ([$recipe['reference'], $recipe['endReference']] as $reference) {
            if ($reference) {
                $this->references->path($series->getOwner(), $reference);
            }
        }
        $cents = array_sum(array_map(fn (int $s): int => $this->catalog->estimate($recipe['model'], $s), $durations));
        $hash = (new SeriesAuthorization($this->catalog))->fingerprint($recipe, $series->initialPrompt(), $durations, 0, 'preparation-only');

        return ['mode' => 'preparation', 'recipe' => $recipe, 'brief' => $series->initialPrompt(), 'durations' => $durations, 'cents' => $cents, 'hash' => $hash, 'authorizationVersion' => SeriesAuthorization::VERSION];
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
            if (!$this->writer->available()) {
                throw new \RuntimeException('La préparation automatique est indisponible : active la rédaction locale dans cette installation.');
            }
            $preview = $this->preview($series);
            if (!hash_equals($preview['hash'], $data['quote'] ?? '') || empty($data['consent'])) {
                throw new \RuntimeException('Vérifie les réglages et confirme la préparation des scénarios avant d’activer. Aucun coût vidéo n’est autorisé ici.');
            }
            if (($data['limit'] ?? 0) < 1 || $data['limit'] > 12 || empty($data['networks']) || array_diff($data['networks'], array_keys(MultiNetworkPublishing::LABELS)) || !in_array($data['tone'], ['auto', 'humour', 'aventure', 'calme'], true) || !in_array($data['continuity'], ['independent', 'linked'], true)) {
                throw new \InvalidArgumentException('Vérifie le nombre d’épisodes, les réseaux et l’ambiance.');
            }
            $model = $this->catalog->model($preview['recipe']['model']);
            if ('independent' === $data['continuity'] && count($preview['durations']) > 1 && ($model['image'] ?? false) && !($model['subjectReference'] ?? false)) {
                throw new \RuntimeException('Pour plusieurs scènes récurrentes, choisis une référence de personnage. Les cadres de début/fin doivent être propres à chaque plan ; ils ne sont pas répétés automatiquement. Tu peux aussi choisir un clip unique ou un raccord compatible.');
            }
            if ('linked' === $data['continuity'] && count($preview['durations']) > 1 && (!($model['image'] ?? false) || ($model['subjectReference'] ?? false) || ($model['requiresEnd'] ?? false) || !$preview['recipe']['reference'])) {
                throw new \RuntimeException('Pour prolonger l’action, configure un modèle image compatible et sa référence dans la série.');
            }
            $next = $this->time->next($data['weekday'], $data['hour'], $data['timezone'], $this->clock->now()->modify('+2 hours'));
            if ($recurrence) {
                while ($this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $recurrence, 'scheduledAt' => $next])) {
                    $next = $this->time->next($data['weekday'], $data['hour'], $data['timezone'], $next);
                }
            }
            $recurrence ??= new SeriesRecurrence($series);
            $this->pauseEpisodes($recurrence);
            $settings = array_intersect_key($data, array_flip(['weekday', 'hour', 'timezone', 'networks', 'limit', 'tone', 'continuity'])) + $preview;
            $settings['accounts'] = $this->accounts->select($series->getOwner(), $data['networks'], $data['accounts'] ?? []);
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

    public function inspectWriting(int $id): bool
    {
        $occurrence = $this->em->find(SeriesOccurrence::class, $id);
        if (!$occurrence) {
            return false;
        }

        return $this->em->getConnection()->transactional(function () use ($occurrence): bool {
            $recurrence = $occurrence->getRecurrence();
            $this->em->lock($recurrence->getSeries()->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($occurrence, LockMode::PESSIMISTIC_WRITE);
            if ('writing' !== $occurrence->getStatus()) {
                return false;
            }
            if (!$occurrence->isAutomatic()) {
                $occurrence->manageManually();
                $this->em->flush();

                return true;
            }
            $expiresAt = $occurrence->getCreatedAt()->modify('+'.self::WRITING_TIMEOUT_SECONDS.' seconds');
            if ($expiresAt > $this->clock->now()) {
                $delay = max(1000, ($expiresAt->getTimestamp() - $this->clock->now()->getTimestamp()) * 1000);
                $this->bus->dispatch(new InspectSeriesWriting((int) $occurrence->getId()), [new DelayStamp($delay)]);

                return false;
            }
            $issue = 'La rédaction a été interrompue ou a dépassé dix minutes. Ouvre l’épisode pour le préparer manuellement ; aucun appel vidéo n’a été relancé.';
            $occurrence->fail($issue);
            $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
            if ($recurrence->getRevision() === $occurrence->getAuthorizationRevision()) {
                $recurrence->pause($issue);
                $this->pauseEpisodes($recurrence);
            }
            $this->em->flush();

            return true;
        });
    }

    public function recoverInterrupted(): int
    {
        $rows = $this->em->createQueryBuilder()->select('o.id')->from(SeriesOccurrence::class, 'o')->where('o.status = :status AND o.createdAt <= :before')->setParameter('status', 'writing')->setParameter('before', $this->clock->now()->modify('-'.self::WRITING_TIMEOUT_SECONDS.' seconds'))->getQuery()->getScalarResult();
        $recovered = 0;
        foreach ($rows as $row) {
            if ($this->inspectWriting((int) $row['id'])) {
                ++$recovered;
            }
        }

        return $recovered;
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
                if ('preparation' !== ($settings['mode'] ?? null)) {
                    throw new \RuntimeException('Ancienne autorisation de génération automatique : reconfigure la préparation des scénarios. Chaque vidéo nécessite désormais ta relecture et une confirmation de coût.');
                }
                if (!hash_equals($settings['hash'], $this->preview($series)['hash'])) {
                    throw new \RuntimeException('Le thème, les références ou le modèle ont changé. Vérifie et confirme à nouveau la préparation.');
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
            $occurrence = new SeriesOccurrence($recurrence, $at, $settings['timezone'], $settings['networks'], $this->clock->now(), $episode, $settings['accounts'] ?? []);
            if (!$episode) {
                $occurrence->fail('Créneau trop proche ou dépassé. Aucune génération lancée pour ce créneau.');
            }
            $recurrence->advanceSchedule($next);
            $this->em->persist($occurrence);
            $this->em->flush();
            if ($episode) {
                $this->bus->dispatch(new InspectSeriesWriting((int) $occurrence->getId()), [new DelayStamp(self::WRITING_TIMEOUT_SECONDS * 1000)]);
            }
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
            $story = $this->writer->propose($series->getTheme(), $settings['durations'], $settings['continuity'], $settings['tone'], ['style' => $series->getStyle(), 'characters' => $series->getCharacters(), 'previous' => $memory, 'generation' => (new AiShotPrompts($this->catalog))->writingContext($settings['recipe']['model'], $settings['recipe']['subjectDetails'] ?? [])]);
            $this->em->getConnection()->transactional(function () use ($occurrence, $recurrence, $episode, $story, $message, $series, $settings): void {
                $this->em->lock($series->getOwner(), LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($series);
                $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($occurrence, LockMode::PESSIMISTIC_WRITE);
                if (!$occurrence->isAutomatic()) {
                    $occurrence->manageManually();
                    $this->em->flush();

                    return;
                }
                if ('writing' !== $occurrence->getStatus()) {
                    return;
                }
                if ('paused' === $recurrence->getStatus() || $recurrence->getRevision() !== $message->revision || 1 !== $episode->getRevision() || $occurrence->getScheduledAt() <= $this->clock->now()->modify('+2 minutes')) {
                    throw new \RuntimeException('La préparation a été suspendue, modifiée ou son créneau est dépassé. Aucun lancement payant automatique.');
                }
                if (!hash_equals($settings['hash'], $this->preview($series)['hash'])) {
                    throw new \RuntimeException('Les paramètres ont changé pendant la rédaction. Confirme à nouveau la préparation.');
                }
                $recipe = $settings['recipe'];
                foreach ($story['shots'] as &$shot) {
                    $shot['sceneDescription'] = $shot['description'];
                    $shot['translationSource'] = StoryTextChecks::translationSource($series->initialPrompt(), ['characters' => $series->getCharacters(), 'style' => $series->getStyle()]);
                }
                unset($shot);
                $this->flow->prepare($series->getOwner(), $recipe + ['source' => $this->catalog->referenceMode($recipe['model']), 'target' => array_sum($settings['durations']), 'shots' => $story['shots'], 'prompt' => $series->initialPrompt(), 'title' => $story['title'], 'continuity' => $settings['continuity'], 'mute' => false, 'useEnd' => (bool) $recipe['endReference'], 'revision' => 1], $episode);
                $occurrence->story($story['summary']);
                $issue = 'Scénario préparé : relis les descriptions et les prompts, puis confirme le coût dans l’épisode pour générer la vidéo. Même sans alerte, des incohérences peuvent subsister.';
                if ($story['warnings']) {
                    // Persist a complete summary within both entities' 255-character bounds.
                    $prefix = 'Scénario à relire dans l’épisode avant génération. '.count($story['warnings']).' point(s) à vérifier. ';
                    $issue = $prefix.$story['warnings'][0];
                    if (mb_strlen($issue) > 255) {
                        $issue = $prefix.'Relis les consignes, les paroles, leurs locuteurs et l’identité des sujets avant tout lancement.';
                    }
                }
                $occurrence->preparedForReview($issue);
                $this->em->flush();
            });
        } catch (\Throwable $failure) {
            if (!$this->em->isOpen()) {
                throw $failure;
            }
            $this->em->getConnection()->transactional(function () use ($occurrence, $recurrence, $message, $failure): void {
                $this->em->lock($recurrence->getSeries()->getOwner(), LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($recurrence, LockMode::PESSIMISTIC_WRITE);
                $this->em->refresh($occurrence, LockMode::PESSIMISTIC_WRITE);
                if (!$occurrence->isAutomatic()) {
                    $occurrence->manageManually();
                    $this->em->flush();

                    return;
                }
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
            if (!$occurrence->isAutomatic()) {
                continue;
            }
            if ($episode = $occurrence->getEpisode()) {
                $this->flow->pause($episode);
            }
        }
    }
}
