<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\AiShot;
use App\Entity\User;
use App\Entity\VideoEdit;
use App\Message\AdvanceAiEpisode;
use App\Message\GenerateAiVideo;
use App\Repository\VideoAssetRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class AiEpisodeFlow
{
    public function __construct(private EntityManagerInterface $em, private AiCatalog $catalog, private AiGenerations $generations, private AiKeys $keys, private AiReferenceStorage $references, private AiFrameExtractor $frames, private VideoEdits $edits, private VideoAssetRepository $videos, private EpisodeReservations $reservations, private MessageBusInterface $bus, private SeriesGenerationConsent $seriesConsent, private AiPlanInputs $inputs, private AiShotPrompts $prompts)
    {
    }

    public function owned(int $id, User $owner): ?AiEpisode
    {
        return $this->em->createQueryBuilder()->select('e', 's')->from(AiEpisode::class, 'e')->leftJoin('e.series', 's')->where('e.id = :id AND (s.owner = :owner OR e.owner = :owner)')->setParameter('id', $id)->setParameter('owner', $owner)->getQuery()->getOneOrNullResult();
    }

    /** @return list<AiShot> */
    public function shots(AiEpisode $episode): array
    {
        return $this->em->createQueryBuilder()->select('s', 'g', 'v')->from(AiShot::class, 's')->leftJoin('s.generation', 'g')->leftJoin('g.video', 'v')->where('s.episode = :episode')->setParameter('episode', $episode)->orderBy('s.position', 'ASC')->getQuery()->getResult();
    }

    /** @param array<string,mixed> $data */
    public function prepare(User $owner, array $data, ?AiEpisode $episode = null): AiEpisode
    {
        $model = $this->catalog->model($data['model']);
        if (isset($model['operation'])) {
            throw new \RuntimeException('Une extension native se prépare depuis une vidéo existante de la bibliothèque.');
        }
        if ('auto' === $data['ratio']) {
            throw new \RuntimeException('Choisis un format final explicite : vertical 9:16, paysage 16:9 ou carré 1:1. Le format original de l’image ne garantit pas celui du montage.');
        }
        $rows = array_values($data['shots'] ?? []);
        if (!$rows || count($rows) > 8) {
            throw new \RuntimeException('Prépare de 1 à 8 plans dans le même écran.');
        }
        $total = array_sum(array_column($rows, 'seconds'));
        if ($total < 1 || $total > 60 || $total !== $data['target']) {
            throw new \RuntimeException('Le total des plans doit correspondre à la durée finale choisie, jusqu’à 60 secondes. Ajuste les plans ou utilise « Répartir la durée ».');
        }
        foreach ($rows as $row) {
            $this->catalog->validate($data['model'], $row['seconds'], $data['ratio']);
        }
        $image = $model['image'] ?? false;
        $reference = $image ? ($data['reference'] ?: null) : null;
        $end = $image && ($data['useEnd'] ?? false) ? ($data['endReference'] ?: null) : null;
        $subjects = $this->inputs->subjects($owner, $data['model'], $data['subjects'] ?? []);
        if (($model['subjectReference'] ?? false) && !$reference && $subjects) {
            $reference = array_shift($subjects);
        }
        $subjectDetails = $this->inputs->subjectDetails($owner, $data['model'], $subjects, $data['subjectDetails'] ?? [], ($model['subjectReference'] ?? false) ? $reference : null, false);
        if ($image && !$reference && empty($rows[0]['startReference'])) {
            throw new \RuntimeException('Ajoute une référence ou le premier cadre du premier plan avant de continuer.');
        }
        if (!($model['subjectReference'] ?? false)) {
            if ($reference && empty($rows[0]['startReference'])) {
                $rows[0]['startReference'] = $reference;
            }
            if ($end && empty($rows[count($rows) - 1]['endReference'])) {
                $rows[count($rows) - 1]['endReference'] = $end;
            }
        }
        foreach ([$reference, $end] as $name) {
            if ($name) {
                $this->references->path($owner, $name);
            }
        }
        $continuity = $data['continuity'] ?: 'independent';
        if (!in_array($continuity, ['single', 'independent', 'linked'], true) || ('single' === $continuity && 1 !== count($rows))) {
            throw new \RuntimeException('Le plan unique exige un seul clip de durée native. Utilise « Préparer un plan unique » ou choisis plusieurs scènes.');
        }
        if (1 === count($rows) && 'single' !== $continuity) {
            $continuity = 'independent';
        }
        if ('linked' === $continuity && (!$image || ($model['subjectReference'] ?? false) || ($model['requiresEnd'] ?? false))) {
            throw new \RuntimeException('Pour les raccords, choisis ici un modèle image compatible et ajoute sa référence. Tes scènes restent conservées.');
        }
        $recipe = ['model' => $data['model'], 'seconds' => min($model['seconds']), 'ratio' => $data['ratio'], 'accessMode' => $data['accessMode'], 'reference' => ($model['subjectReference'] ?? false) ? $reference : null, 'endReference' => null, 'imageFit' => $data['imageFit'], 'inputVersion' => 4, 'subjects' => $subjects, 'subjectDetails' => $subjectDetails];
        $title = trim($data['title'] ?? '') ?: mb_substr(trim($data['prompt']), 0, 80);

        return $this->em->getConnection()->transactional(function () use ($owner, $data, $episode, $recipe, $title, $continuity, $rows): AiEpisode {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            if ($episode) {
                if ($episode->getOwner()->getId() !== $owner->getId()) {
                    throw new \RuntimeException('Vidéo indisponible.');
                }
                $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
                $this->revision($episode, (int) $data['revision']);
                if ($episode->getGeneration()) {
                    $this->em->refresh($episode->getGeneration(), LockMode::PESSIMISTIC_WRITE);
                }
            } else {
                $episode = new AiEpisode($owner, 1, $title, trim($data['prompt']), $recipe);
                $this->em->persist($episode);
            }
            if (!$episode->isStandalone()) {
                $this->em->refresh($episode->getSeries());
            }
            $recipe['visualContext'] = $episode->isStandalone() ? trim($data['prompt']) : $episode->getSeries()->getCharacters().' '.$episode->getSeries()->getStyle();
            $episode->configurePreparation($recipe, $data['prompt'], $continuity, (bool) $data['mute']);
            $this->em->flush();
            $this->save($episode, $episode->getRevision(), $title, $rows);

            return $episode;
        });
    }

    /** @return list<array{value:string,label:string,seconds:int}> */
    public function suggestions(string $model, int $target): array
    {
        if ($target < 1 || $target > 60) {
            throw new \RuntimeException('Choisis une durée entre 1 et 60 secondes.');
        }
        $durations = $this->catalog->model($model)['seconds'];
        rsort($durations);
        $states = [0 => [0 => [[]]]];
        $paths = [];
        for ($count = 1; $count <= 8; ++$count) {
            $next = [];
            foreach ($states[$count - 1] as $total => $variants) {
                foreach ($variants as $parts) {
                    foreach ($durations as $seconds) {
                        if ($total + $seconds > 60) {
                            continue;
                        }
                        $candidate = [...$parts, $seconds];
                        rsort($candidate);
                        $next[$total + $seconds][implode(',', $candidate)] = $candidate;
                    }
                }
            }
            foreach ($next as $total => $variants) {
                usort($variants, static fn (array $a, array $b): int => [max($a) - min($a), $a] <=> [max($b) - min($b), $b]);
                $states[$count][$total] = array_slice($variants, 0, 12);
                $paths[$total] = [...($paths[$total] ?? []), ...$states[$count][$total]];
            }
            $states[$count] ??= [];
        }
        $totals = isset($paths[$target]) ? [$target] : array_keys($paths);
        usort($totals, static fn (int $a, int $b): int => [abs($a - $target), $a] <=> [abs($b - $target), $b]);
        $result = [];
        foreach (array_slice($totals, 0, 2) as $total) {
            usort($paths[$total], static fn (array $a, array $b): int => [count($a), max($a) - min($a), $a] <=> [count($b), max($b) - min($b), $b]);
            foreach (array_slice($paths[$total], 0, 12) as $parts) {
                $result[] = ['value' => implode(',', $parts), 'label' => implode(' + ', $parts).' s · '.$total.' s au total', 'seconds' => $total];
            }
        }

        return $result;
    }

    /** @return list<int> */
    public function durations(string $model, int $target): array
    {
        foreach ($this->suggestions($model, $target) as $suggestion) {
            if ($suggestion['seconds'] === $target) {
                return array_map('intval', explode(',', $suggestion['value']));
            }
        }
        throw new \RuntimeException('Le total exact est impossible avec ce modèle. Choisis une des durées proches proposées.');
    }

    public function initialize(AiEpisode $episode, int $revision, int $target, string $continuity, bool $mute, string $layout = ''): void
    {
        $this->locked($episode, function () use ($episode, $revision, $target, $continuity, $mute, $layout): void {
            $this->revision($episode, $revision);
            $recipe = $episode->getPreparationRecipe();
            $model = $this->catalog->model($recipe['model']);
            if (!in_array($continuity, ['independent', 'linked'], true) || ('linked' === $continuity && (!($model['image'] ?? false) || ($model['subjectReference'] ?? false) || !$recipe['reference'] || ($model['requiresEnd'] ?? false)))) {
                throw new \RuntimeException('Les raccords automatiques demandent un modèle image vers vidéo et une image de départ dans la préparation.');
            }
            $durations = '' === $layout ? $this->durations($recipe['model'], $target) : [];
            if ('' !== $layout) {
                $choices = $this->suggestions($recipe['model'], $target);
                if (!in_array($layout, array_column($choices, 'value'), true)) {
                    throw new \RuntimeException('Ce découpage n’est plus disponible. Vérifie les propositions.');
                }
                $durations = array_map('intval', explode(',', $layout));
            }
            if ($episode->getGeneration()) {
                $this->em->refresh($episode->getGeneration(), LockMode::PESSIMISTIC_WRITE);
            }
            $episode->beginStory($continuity, $mute);
            foreach ($durations as $index => $seconds) {
                $this->em->persist(new AiShot($episode, $index + 1, $seconds));
            }
        });
    }

    /** @param list<array{prompt:string,seconds:int}> $rows */
    public function save(AiEpisode $episode, int $revision, string $title, array $rows): void
    {
        $this->locked($episode, function () use ($episode, $revision, $title, $rows): void {
            $this->revision($episode, $revision);
            if ('draft' !== $episode->getFlowState()) {
                throw new \RuntimeException('Les plans sont déjà confirmés.');
            }
            $shots = $this->shots($episode);
            if (count($rows) < 1 || count($rows) > 8) {
                throw new \RuntimeException('Prépare entre 1 et 8 plans.');
            }
            $total = 0;
            foreach ($rows as $row) {
                $this->catalog->estimate($episode->getFlowRecipe()['model'], $row['seconds']);
                $total += $row['seconds'];
            }
            if ($total < 1 || $total > 60) {
                throw new \RuntimeException('La durée prévue doit rester entre 1 et 60 secondes.');
            }
            foreach ($rows as $index => $row) {
                $shot = $shots[$index] ?? new AiShot($episode, $index + 1, $row['seconds']);
                $shot->revise($row['prompt'], $row['seconds']);
                $shot->configureInputs($this->inputs->row($episode->getOwner(), $row, $episode->getFlowRecipe()['imageFit']));
                $this->em->persist($shot);
            }
            foreach (array_slice($shots, count($rows)) as $removed) {
                $this->em->remove($removed);
            }
            $episode->edit($title, $episode->getPrompt());
        });
    }

    /** @return array{hash:string,cents:int,seconds:int,count:int,redo:int,references:list<array<string,mixed>>} */
    public function quote(AiEpisode $episode, int $redo = 0): array
    {
        $recipe = $episode->getFlowRecipe() ?? throw new \RuntimeException('Prépare les plans avant de continuer.');
        if ('auto' === $recipe['ratio']) {
            throw new \RuntimeException('Choisis un format final explicite dans la préparation avant de confirmer les coûts.');
        }
        $shots = $this->shots($episode);
        $data = [];
        $cents = $seconds = 0;
        $references = [];
        foreach ($shots as $index => $shot) {
            if ('review' === $episode->getFlowState() && 'available' === $shot->getGeneration()?->getStatus()) {
                continue;
            }
            if ($redo && $shot->getId() !== $redo) {
                continue;
            }
            $scene = $redo ? ($shot->getInputs()['repairPrompt'] ?? $shot->getPrompt()) : $shot->getPrompt();
            if ('' === trim($scene) || mb_strlen($scene) > 1500) {
                throw new \RuntimeException('Complète le prompt de chaque plan avant de vérifier l’estimation.');
            }
            $this->catalog->validate($recipe['model'], $shot->getSeconds(), $recipe['ratio']);
            $effectivePrompt = $this->prompt($episode, $shot, $scene);
            $this->catalog->validatePrompt($recipe['model'], $effectivePrompt);
            $price = $this->catalog->estimate($recipe['model'], $shot->getSeconds());
            try {
                $resolved = $this->inputs->resolve($episode, $shot, $index, count($shots));
            } catch (\RuntimeException $failure) {
                throw new \RuntimeException('Plan '.($index + 1).' : '.$failure->getMessage());
            }
            if ($redo && 'linked' === $episode->getContinuity() && isset($this->catalog->model($recipe['model'])['endImageField'])) {
                $resolved['endFromExisting'] = true;
            }
            $references[] = ['position' => $shot->getPosition(), 'seconds' => $shot->getSeconds(), 'model' => $recipe['model'], 'prompt' => $effectivePrompt] + $resolved;
            $data[] = [$shot->getId(), $shot->getPrompt(), $shot->getSeconds(), $price, $shot->getGeneration()?->getId(), $resolved];
            if (($recipe['inputVersion'] ?? 1) >= 3 || $redo) {
                $data[] = $effectivePrompt;
            }
            $cents += $price;
            $seconds += $shot->getSeconds();
        }
        if (!$data || (!$redo && (count($shots) > 8 || $seconds < 1 || $seconds > 60))) {
            throw new \RuntimeException('Prépare entre 1 et 8 plans pour une durée totale de 1 à 60 secondes.');
        }
        if ($redo) {
            $shot = $this->em->find(AiShot::class, $redo);
            if ('linked' === $episode->getContinuity() && $shot && $shot->getPosition() < count($shots) && !isset($this->catalog->model($recipe['model'])['endImageField'])) {
                throw new \RuntimeException('Ce plan alimente le suivant et ce modèle ne fixe pas l’image de fin. Prépare une nouvelle vidéo pour modifier cet enchaînement ; aucun plan suivant ne sera régénéré sans accord.');
            }
            $unsafe = array_filter($shots, static fn (AiShot $s): bool => $s->getGeneration() && 'available' !== $s->getGeneration()->getStatus());
            if (!in_array($episode->getFlowState(), ['ready', 'paused'], true) || $unsafe || $episode->getAssembly()?->isActive() || !$shot || 'available' !== $shot->getGeneration()?->getStatus() || !$shot->getGeneration()->getVideo()) {
                throw new \RuntimeException('Attends un épisode terminé pour refaire uniquement ce plan.');
            }
        }

        return ['hash' => hash('sha256', serialize([$episode->getId(), $episode->getRevision(), $recipe, $episode->getContinuity(), $episode->isMutePlans(), $data, $this->tariff($episode)])), 'cents' => $cents, 'seconds' => $seconds, 'count' => count($references), 'redo' => $redo, 'references' => $references];
    }

    public function confirm(AiEpisode $episode, string $hash, int $redo = 0): void
    {
        $this->locked($episode, function () use ($episode, $hash, $redo): void {
            if ($hash && $hash === $episode->getConsentHash()) {
                return;
            }
            if (!$redo && in_array($episode->getFlowState(), ['running', 'assembling', 'ready'], true)) {
                return;
            }
            if (!$redo && !in_array($episode->getFlowState(), ['draft', 'review'], true)) {
                throw new \RuntimeException('Reprends l’épisode depuis son suivi.');
            }
            $quote = $this->quote($episode, $redo);
            if (!hash_equals($quote['hash'], $hash)) {
                throw new \RuntimeException('Les plans ou leur tarif ont changé. Vérifie une nouvelle estimation avant de confirmer.');
            }
            $this->access($episode);
            if ($redo && 'paused' === $episode->getFlowState() && $episode->getFlowTariff() !== $this->tariff($episode)) {
                throw new \RuntimeException('Le tarif des plans restants a changé. Aucun lancement supplémentaire sans nouvelle estimation complète.');
            }
            $this->capacity($episode, $quote['count'] + ($redo && 'paused' === $episode->getFlowState() ? 0 : 1));
            $account = null;
            $recipe = $episode->getFlowRecipe();
            if ('mcp' === $recipe['accessMode']) {
                $account = json_decode($this->keys->plain($episode->getOwner(), $this->catalog->model($recipe['model'])['provider'], 'mcp'), true, 32, JSON_THROW_ON_ERROR)['account_id'];
            }
            $tariff = $this->tariff($episode);
            if ($redo) {
                $shot = $this->em->find(AiShot::class, $redo);
                $anchor = 'linked' === $episode->getContinuity() && isset($this->catalog->model($recipe['model'])['endImageField']) ? $this->frames->lastFrame($shot->getGeneration()->getVideo()) : null;
                $shot->replace($anchor, $shot->getInputs()['repairPrompt'] ?? null);
                $episode->repair();
            }
            if (($episode->getFlowRecipe()['inputVersion'] ?? 1) >= 3) {
                $episode->freezeVisualContext($this->visualContext($episode));
            }
            foreach ($this->shots($episode) as $confirmedShot) {
                foreach ($quote['references'] as $reference) {
                    if ($confirmedShot->getPosition() === $reference['position']) {
                        $confirmedShot->confirmPrompt($reference['prompt']);
                    }
                }
            }
            $episode->consent($tariff, $account, $hash);
            $this->em->flush();
            $this->later($episode, 0);
        });
    }

    public function pause(AiEpisode $episode): void
    {
        $this->locked($episode, function () use ($episode): void {
            if (in_array($episode->getFlowState(), ['running', 'assembling'], true)) {
                $episode->flow('paused', 'Les plans suivants sont suspendus. Une demande déjà transmise ou un assemblage en cours peut se terminer.');
            }
        });
    }

    public function stageRepair(AiEpisode $episode, int $shotId, int $revision, string $prompt): void
    {
        $this->locked($episode, function () use ($episode, $shotId, $revision, $prompt): void {
            $this->revision($episode, $revision);
            $shot = $this->em->find(AiShot::class, $shotId);
            if (!$shot || $shot->getEpisode()->getId() !== $episode->getId()) {
                throw new \RuntimeException('Plan indisponible.');
            }
            if ('' === trim($prompt) || mb_strlen($prompt) > 1500) {
                throw new \RuntimeException('Décris la réparation en 1 à 1 500 caractères.');
            }
            $this->quote($episode, $shotId);
            $this->catalog->validatePrompt($episode->getFlowRecipe()['model'], $this->prompt($episode, $shot, $prompt));
            $shot->stageRepair($prompt);
            $episode->touchRepair();
        });
    }

    public function resume(AiEpisode $episode): void
    {
        $this->locked($episode, function () use ($episode): void {
            if ('paused' !== $episode->getFlowState()) {
                return;
            }
            $this->access($episode);
            if ($this->tariff($episode) !== $episode->getFlowTariff()) {
                throw new \RuntimeException('Le tarif a changé. Aucun nouveau plan ne sera lancé avec l’ancien consentement.');
            }
            foreach ($this->shots($episode) as $shot) {
                $job = $shot->getGeneration();
                if ($job && 'queued' === $job->getStatus()) {
                    $this->bus->dispatch(new GenerateAiVideo((int) $job->getId()));
                }
                if ($job && in_array($job->getStatus(), ['review', 'waiting_key'], true)) {
                    if ('review' === $job->getStatus() && !$job->getRemote()) {
                        throw new \RuntimeException('Une demande fournisseur est incertaine sans identifiant de récupération. Vérifie son historique ; aucun renvoi payant automatique.');
                    }
                    $this->generations->retry($job->getOwner(), (int) $job->getId(), true);
                }
            }
            if ($episode->getAssembly() && 'failed' === $episode->getAssembly()->getStatus()) {
                $this->edits->assembleEpisode($episode, []);
            }
            $episode->flow('running');
            $this->em->flush();
            $this->later($episode, 0);
        });
    }

    public function reviewRemaining(AiEpisode $episode, int $revision): void
    {
        $this->locked($episode, function () use ($episode, $revision): void {
            $this->revision($episode, $revision);
            if ('paused' !== $episode->getFlowState()) {
                throw new \RuntimeException('Suspends les plans avant de revoir leur devis.');
            }
            $shots = $this->shots($episode);
            if (!array_filter($shots, static fn (AiShot $shot): bool => 'available' !== $shot->getGeneration()?->getStatus())) {
                throw new \RuntimeException('Tous les plans sont terminés. Reprends l’assemblage sans régénérer les clips.');
            }
            foreach ($shots as $shot) {
                if ($job = $shot->getGeneration()) {
                    $this->em->refresh($job, LockMode::PESSIMISTIC_WRITE);
                    if ('available' !== $job->getStatus() && ($job->getRemote() || !in_array($job->getStatus(), ['prepared', 'queued', 'waiting_key'], true))) {
                        throw new \RuntimeException('Récupère les demandes déjà transmises avant de revoir les plans restants. Aucun renvoi automatique.');
                    }
                }
            }
            foreach ($shots as $shot) {
                if ($shot->getGeneration() && 'available' !== $shot->getGeneration()->getStatus()) {
                    $shot->releaseUnsent();
                }
            }
            $recipe = $episode->getFlowRecipe();
            if (!$episode->isStandalone()) {
                $this->em->refresh($episode->getSeries());
            }
            $recipe['visualContext'] = $this->visualContext($episode);
            $recipe['inputVersion'] = max(4, $recipe['inputVersion'] ?? 1);
            $episode->reviewRemaining($recipe);
        });
    }

    public function stop(AiEpisode $episode): void
    {
        $this->locked($episode, function () use ($episode): void {
            if (in_array($episode->getFlowState(), ['running', 'paused', 'assembling'], true)) {
                $episode->flow('stopped', 'Les étapes restantes sont arrêtées et leurs réservations libérées. Les résultats restent dans la bibliothèque. Une demande déjà transmise peut encore se terminer et être facturée.');
            }
        });
    }

    public function advance(int $id): void
    {
        $episode = $this->em->find(AiEpisode::class, $id);
        if (!$episode) {
            return;
        }
        $this->locked($episode, function () use ($episode): void {
            if (!in_array($episode->getFlowState(), ['running', 'assembling'], true)) {
                return;
            }
            try {
                $recipe = $episode->getFlowRecipe();
                $shots = $this->shots($episode);
                $previous = null;
                foreach ($shots as $index => $shot) {
                    $this->em->refresh($shot, LockMode::PESSIMISTIC_WRITE);
                    $job = $shot->getGeneration();
                    if ($job) {
                        $this->em->refresh($job);
                    }
                    if ($job && 'available' === $job->getStatus()) {
                        if (!$job->getVideo()) {
                            throw new \RuntimeException('Un clip a été retiré de la bibliothèque. Aucun plan suivant n’a été demandé.');
                        }
                        if (!$job->getVideo()->matchesRatio($recipe['ratio'])) {
                            throw new \RuntimeException('Le plan '.$shot->getPosition().' a été demandé en '.$recipe['ratio'].' mais le fournisseur a livré '.$job->getVideo()->getWidth().' × '.$job->getVideo()->getHeight().' px. Le clip est conservé. Les étapes suivantes sont suspendues avant tout nouvel envoi ou assemblage ; aucun recadrage ni nouvel essai payant automatique.');
                        }
                        if ($job->getVideo()->getDuration() < $shot->getSeconds() - 0.25) {
                            throw new \RuntimeException('Le plan '.$shot->getPosition().' est plus court que prévu. Vérifie son résultat ; tu peux refaire ce plan terminé avec une nouvelle confirmation de coût.');
                        }
                        $previous = $job->getVideo();
                        continue;
                    }
                    if ($job && in_array($job->getStatus(), ['review', 'waiting_key'], true)) {
                        throw new \RuntimeException($job->getIssue() ?? 'Vérifie la génération de ce plan avant de continuer.');
                    }
                    if (!$job || 'prepared' === $job->getStatus()) {
                        $this->seriesConsent->assertAllowed($episode);
                        $this->access($episode);
                        if ($this->tariff($episode) !== $episode->getFlowTariff()) {
                            throw new \RuntimeException('Le tarif a changé depuis la confirmation. Les plans suivants restent suspendus.');
                        }
                        $owner = $episode->getOwner();
                        if ($this->em->getRepository(AiGeneration::class)->count(['owner' => $owner, 'status' => ['queued', 'submitting', 'processing']]) >= 3) {
                            $this->later($episode);

                            return;
                        }
                        $this->capacity($episode);
                        if ('mcp' === $recipe['accessMode']) {
                            $account = json_decode($this->keys->plain($owner, $this->catalog->model($recipe['model'])['provider'], 'mcp'), true, 32, JSON_THROW_ON_ERROR)['account_id'];
                            if ($account !== $episode->getFlowAccount()) {
                                throw new \RuntimeException('Le compte Fal a changé. Rétablis le compte confirmé avant de poursuivre.');
                            }
                        }
                        if (!$job) {
                            if (3 === ($recipe['inputVersion'] ?? 1) && !isset($shot->getInputs()['confirmedPrompt'])) {
                                throw new \RuntimeException('Cette ancienne préparation ne conserve pas son prompt accepté. Utilise « Revoir les plans restants » pour vérifier un nouveau devis. Les clips existants sont conservés.');
                            }
                            $resolved = $this->inputs->resolve($episode, $shot, $index, count($shots));
                            $reference = $resolved['fromPrevious'] && $previous ? $this->frames->lastFrame($previous) : $resolved['start'];
                            $job = new AiGeneration($owner, mb_substr($episode->getTitle(), 0, 100).' · plan '.$shot->getPosition(), $shot->getInputs()['confirmedPrompt'] ?? $this->prompt($episode, $shot), $recipe['model'], $shot->getSeconds(), $recipe['ratio'], $this->catalog->estimate($recipe['model'], $shot->getSeconds()));
                            if ($reference) {
                                $job->attachReference($reference);
                                $job->frameImages($resolved['fit']);
                            }
                            $job->attachSubjects($resolved['subjects']);
                            $job->attachSubjectDetails($resolved['subjectDetails']);
                            $end = $resolved['end'];
                            if ($end) {
                                $job->attachEndReference($end);
                            }
                            if ('mcp' === $recipe['accessMode']) {
                                $job->useMcp();
                            }
                            $this->em->persist($job);
                            $shot->bind($job);
                            $this->em->flush();
                        }
                        $this->generations->confirm($job->getOwner(), (int) $job->getId(), $job->getReviewRevision());
                    }
                    $this->later($episode);

                    return;
                }
                $assembly = $episode->getAssembly();
                if ($assembly && 'failed' === $assembly->getStatus()) {
                    throw new \RuntimeException($assembly->getIssue() ?? 'L’assemblage a échoué. Tu peux le relancer sans régénérer les plans.');
                }
                if (!$assembly) {
                    if ($this->em->getRepository(VideoEdit::class)->count(['owner' => $episode->getOwner(), 'status' => ['queued', 'rendering', 'transcribing']]) > 0) {
                        $this->later($episode);

                        return;
                    }
                    $this->edits->assembleEpisode($episode, array_map(static fn (AiShot $s): int => (int) $s->getGeneration()->getVideo()->getId(), $shots), array_map(static fn (AiShot $s): int => $s->getSeconds(), $shots));
                    $this->later($episode);

                    return;
                }
                $this->em->refresh($assembly);
                if ('ready' === $assembly->getStatus()) {
                    if (!$assembly->getOutput()) {
                        throw new \RuntimeException('La vidéo assemblée a été retirée de la bibliothèque.');
                    }
                    $episode->flow('ready');

                    return;
                }
                $this->later($episode);
            } catch (\RuntimeException|\InvalidArgumentException $error) {
                if (!$this->em->isOpen()) {
                    throw $error;
                }
                $episode->flow('paused', $error->getMessage());
            }
        });
    }

    private function access(AiEpisode $episode): void
    {
        if (!$this->generations->enabled()) {
            throw new \RuntimeException('La génération payante est désactivée. Tes plans restent enregistrés.');
        }
        $recipe = $episode->getFlowRecipe();
        $model = $this->catalog->model($recipe['model']);
        if (($recipe['inputVersion'] ?? 1) < 2) {
            $this->catalog->validateReferences($recipe['model'], $recipe['reference'], $recipe['endReference']);
        }
        if (!$this->keys->hasAccess($episode->getOwner(), $model['provider'], $recipe['accessMode'])) {
            throw new \RuntimeException('Connecte l’accès BYOK choisi avant de continuer.');
        }
        if (new \DateTimeImmutable($model['checked'] ?? AiCatalog::CHECKED) < new \DateTimeImmutable('-14 days')) {
            throw new \RuntimeException('Les tarifs de ce modèle doivent être revérifiés avant de lancer.');
        }
        foreach ([$recipe['reference'], $recipe['endReference']] as $reference) {
            if ($reference) {
                $this->references->path($episode->getOwner(), $reference);
            }
        }
    }

    private function prompt(AiEpisode $episode, AiShot $shot, ?string $scene = null): string
    {
        $scene ??= $shot->getPrompt();
        $recipe = $episode->getFlowRecipe();
        if (($recipe['inputVersion'] ?? 1) >= 3) {
            $context = $recipe['visualContext'] ?? $this->visualContext($episode);

            return $this->prompts->build($recipe['model'], $shot->getSeconds(), $scene, $context, $episode->getContinuity(), count($recipe['subjects'] ?? []), $recipe['subjectDetails'] ?? []);
        }
        if ('single' === $episode->getContinuity()) {
            return 'ONE CONTINUOUS TAKE — '.$shot->getSeconds()." seconds. No cuts, no montage, no scene change. Complete one simple action smoothly within this shot:\n".$scene."\nVisual identity and style only:\n".$episode->getPrompt();
        }

        return 'SHOT TO GENERATE — perform only this scene within '.$shot->getSeconds()." seconds, with a clear ending before the cut:\n".$scene
            ."\n\nSHARED CONTEXT — use only for visual identity and style. Do not replay the full story or actions from other scenes:\n".$episode->getPrompt();
    }

    public function revisionHash(AiEpisode $episode): string
    {
        $revision = [$episode->getRevision(), $episode->getAssembly()?->getRevision()];
        foreach ($this->shots($episode) as $shot) {
            $revision[] = [$shot->getGeneration()?->getStatus(), $shot->getGeneration()?->getUpdatedAt()->format('U.u'), $shot->getGeneration()?->getProviderStage()];
        }

        return hash('sha256', serialize($revision));
    }

    private function visualContext(AiEpisode $episode): string
    {
        return $episode->getFlowRecipe()['visualContext'] ?? ($episode->isStandalone() ? $episode->getPrompt() : $episode->getSeries()->getCharacters().' '.$episode->getSeries()->getStyle());
    }

    private function tariff(AiEpisode $episode): string
    {
        $model = $episode->getFlowRecipe()['model'];

        $parts = [$this->catalog->model($model)['checked'] ?? AiCatalog::CHECKED, array_map(fn (AiShot $s): int => $this->catalog->estimate($model, $s->getSeconds()), $this->shots($episode))];
        if (($episode->getFlowRecipe()['inputVersion'] ?? 1) >= 2) {
            $parts[] = $this->catalog->contract($model);
        }
        if (($episode->getFlowRecipe()['inputVersion'] ?? 1) >= 3) {
            $parts[] = AiShotPrompts::VERSION;
        }

        return hash('sha256', serialize($parts));
    }

    private function capacity(AiEpisode $episode, int $additional = 0): void
    {
        $owner = $episode->getOwner();
        $usage = $this->videos->usage($owner);
        $active = $this->em->getRepository(AiGeneration::class)->count(['owner' => $owner, 'status' => ['queued', 'submitting', 'processing']]);
        $slots = $active + $this->reservations->slots($owner) + $additional;
        if ($usage['count'] + $slots > VideoLibrary::MAX_VIDEOS || $usage['bytes'] + $slots * VideoLibrary::MAX_BYTES > VideoLibrary::MAX_STORAGE) {
            throw new \RuntimeException('Place insuffisante : prévois 100 Mo et une place par plan restant, plus la vidéo assemblée.');
        }
    }

    private function revision(AiEpisode $episode, int $revision): void
    {
        if ($episode->getRevision() !== $revision) {
            throw new \RuntimeException('Cet épisode a changé dans un autre onglet. Recharge avant de continuer ; ta saisie reste affichée.');
        }
    }

    private function locked(AiEpisode $episode, callable $action): void
    {
        $this->em->getConnection()->transactional(function () use ($episode, $action): void {
            $this->em->lock($episode->getOwner(), LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
            $action();
            $this->em->flush();
        });
    }

    private function later(AiEpisode $episode, int $delay = 20000): void
    {
        $this->bus->dispatch(new AdvanceAiEpisode((int) $episode->getId()), $delay ? [new DelayStamp($delay)] : []);
    }
}
