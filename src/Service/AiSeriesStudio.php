<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\AiSeries;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AiSeriesStudio
{
    public function __construct(private EntityManagerInterface $em, private AiCatalog $catalog, private AiReferenceStorage $references)
    {
    }

    public function owned(int $id, User $owner): ?AiSeries
    {
        return $this->em->getRepository(AiSeries::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    /** @return list<AiEpisode> */
    public function episodes(AiSeries $series): array
    {
        return $this->em->createQueryBuilder()->select('e', 'g')->from(AiEpisode::class, 'e')->leftJoin('e.generation', 'g')->where('e.series = :series')->setParameter('series', $series)->orderBy('e.number', 'ASC')->getQuery()->getResult();
    }

    public function episodeFor(AiGeneration $job): ?AiEpisode
    {
        return $this->em->getRepository(AiEpisode::class)->findOneBy(['generation' => $job]);
    }

    /** @param array<string,mixed> $data */
    public function configure(User $owner, array $data, ?AiSeries $series = null): AiSeries
    {
        $count = $data['episodeCount'] ?? 1;
        if (!is_int($count) || $count < 0 || $count > 12) {
            throw new \InvalidArgumentException('Choisis de 0 à 12 épisodes à préparer.');
        }

        return $this->em->getConnection()->transactional(function () use ($owner, $data, $series): AiSeries {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            if ($series) {
                if ($series->getOwner()->getId() !== $owner->getId()) {
                    throw new \RuntimeException('Série indisponible.');
                }
                $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
                $this->assertRevision($series->getRevision(), (int) $data['revision']);
            }
            $model = $this->catalog->model($data['model']);
            $this->catalog->validate($data['model'], $data['seconds'], $data['ratio']);
            $previous = $series?->getRecipe();
            $image = $previous['reference'] ?? null;
            $endImage = $previous['endReference'] ?? null;
            $managedSubjects = '1' === (string) ($data['subjectsManaged'] ?? '0');
            $subjects = $managedSubjects ? ($data['subjects'] ?? []) : ($previous['subjects'] ?? []);
            if (!is_array($subjects)) {
                throw new \RuntimeException('La liste des références est invalide.');
            }
            $subjects = array_values(array_diff($subjects, $data['removeSubjects'] ?? []));
            $uploads = $data['subjectImages'] ?? [];
            $limit = $this->catalog->subjectLimit($data['model']) - (($model['subjectReference'] ?? false) ? 1 : 0);
            if (count($subjects) + count($uploads) > max(0, $limit)) {
                throw new \RuntimeException('Trop de références de personnages pour ce modèle. Retire les références incompatibles ou choisis explicitement un autre modèle.');
            }
            foreach ($subjects as $name) {
                $this->references->path($owner, $name);
            }
            foreach ($uploads as $file) {
                if (!$file instanceof UploadedFile) {
                    throw new \RuntimeException('Référence de personnage invalide.');
                }
                $subjects[] = $this->references->store($owner, $file);
            }
            if ($data['removeImage'] ?? false) {
                $image = null;
            }
            if ($data['removeEndImage'] ?? false) {
                $endImage = null;
            }
            if ($model['image'] ?? false) {
                if (!$image && !$data['image'] instanceof UploadedFile && !($series && ($data['removeImage'] ?? false))) {
                    throw new \RuntimeException('Ajoute une image de départ pour ce modèle.');
                }
                if (($model['requiresEnd'] ?? false) && !$endImage && !$data['endImage'] instanceof UploadedFile && !($series && ($data['removeEndImage'] ?? false))) {
                    throw new \RuntimeException('Ce modèle demande aussi une image de fin.');
                }
                if (!isset($model['endImageField']) && $data['endImage'] instanceof UploadedFile) {
                    throw new \RuntimeException('Ce modèle n’accepte pas d’image de fin.');
                }
                if ($data['image'] instanceof UploadedFile) {
                    $image = $this->references->store($owner, $data['image']);
                }
                if ($data['endImage'] instanceof UploadedFile) {
                    $endImage = $this->references->store($owner, $data['endImage']);
                }
                if ($image) {
                    $this->references->path($owner, $image);
                }
                $endImage = isset($model['endImageField']) ? $endImage : null;
                if ($endImage) {
                    $this->references->path($owner, $endImage);
                }
            } else {
                if ($data['image'] instanceof UploadedFile || $data['endImage'] instanceof UploadedFile) {
                    throw new \RuntimeException('Choisis un modèle image vers vidéo pour utiliser ces images.');
                }
                $image = $endImage = null;
            }
            if ('mcp' === $data['accessMode'] && 'fal' !== $model['provider']) {
                throw new \RuntimeException('Choisis un accès API pour ce fournisseur.');
            }
            $inputs = new AiPlanInputs($this->catalog, $this->references);
            $subjects = $inputs->subjects($owner, $data['model'], $subjects);
            $details = $data['subjectDetails'] ?? [];
            if (!$managedSubjects && !$details) {
                $details = array_values(array_filter($previous['subjectDetails'] ?? [], static fn (array $detail): bool => in_array($detail['reference'], $subjects, true) || $detail['reference'] === ($previous['reference'] ?? null)));
            }
            if (($model['subjectReference'] ?? false) && $image && ($previous['reference'] ?? null) !== $image) {
                foreach ($details as &$detail) {
                    if (($detail['reference'] ?? null) === ($previous['reference'] ?? null)) {
                        $detail['reference'] = $image;
                    }
                }
                unset($detail);
            }
            $details = $inputs->subjectDetails($owner, $data['model'], $subjects, $details, ($model['subjectReference'] ?? false) ? $image : null, false);
            $recipe = ['totalSeconds' => $data['totalSeconds'] ?? 30, 'model' => $data['model'], 'seconds' => $data['seconds'], 'ratio' => $data['ratio'], 'accessMode' => $data['accessMode'], 'reference' => $image, 'endReference' => $endImage, 'imageFit' => $data['imageFit'], 'subjects' => $subjects, 'subjectDetails' => $details];
            if ($series) {
                $series->configure(trim($data['name']), trim($data['theme']), trim($data['style'] ?? ''), trim($data['characters'] ?? ''), $recipe);
            } else {
                $series = new AiSeries($owner, trim($data['name']), trim($data['theme']), trim($data['style'] ?? ''), trim($data['characters'] ?? ''), $recipe);
                $this->em->persist($series);
                for ($number = 1; $number <= ($data['episodeCount'] ?? 1); ++$number) {
                    $this->em->persist(new AiEpisode($series, $number));
                }
            }

            $this->em->flush();

            return $series;
        });
    }

    public function addEpisodes(User $owner, AiSeries $series, int $count, int $revision): void
    {
        if ($count < 1 || $count > 12) {
            throw new \RuntimeException('Ajoute de 1 à 12 épisodes à la fois.');
        }
        $this->em->getConnection()->transactional(function () use ($owner, $series, $count, $revision): void {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            if ($series->getOwner()->getId() !== $owner->getId()) {
                throw new \RuntimeException('Série indisponible.');
            }
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            $this->assertRevision($series->getRevision(), $revision);
            $last = (int) $this->em->createQueryBuilder()->select('MAX(e.number)')->from(AiEpisode::class, 'e')->where('e.series = :series')->setParameter('series', $series)->getQuery()->getSingleScalarResult();
            for ($i = 1; $i <= $count; ++$i) {
                $this->em->persist(new AiEpisode($series, $last + $i));
            }
            $series->episodesAdded();
            $this->em->flush();
        });
    }

    /** @param array<string,mixed> $data */
    public function saveEpisode(User $owner, AiEpisode $episode, array $data, bool $prepare): ?AiGeneration
    {
        return $this->em->getConnection()->transactional(function () use ($owner, $episode, $data, $prepare): ?AiGeneration {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $series = $episode->getSeries();
            if ($series->getOwner()->getId() !== $owner->getId()) {
                throw new \RuntimeException('Épisode indisponible.');
            }
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($episode, LockMode::PESSIMISTIC_WRITE);
            if ($episode->getFlowState()) {
                throw new \RuntimeException('Cet épisode se prépare depuis sa liste de plans.');
            }
            // A repeated preparation always returns the same generation, including after a double click.
            $existing = $episode->getGeneration();
            if ($existing) {
                $this->em->refresh($existing, LockMode::PESSIMISTIC_WRITE);
                if ($prepare && $episode->getRevision() !== (int) $data['revision'] && $episode->getTitle() === trim($data['title']) && $episode->getPrompt() === trim($data['prompt'])) {
                    return $existing;
                }
            }
            $this->assertRevision($episode->getRevision(), (int) $data['revision']);
            $this->assertRevision($series->getRevision(), (int) $data['seriesRevision']);
            $recipe = $series->getRecipe();
            if ($prepare || $existing) {
                if (!$existing) {
                    $this->catalog->validateReferences($recipe['model'], $recipe['reference'], $recipe['endReference']);
                }
                $this->catalog->validate($recipe['model'], $recipe['seconds'], $recipe['ratio']);
                $this->catalog->validatePrompt($existing?->getModel() ?? $recipe['model'], $data['prompt']);
                foreach ([$recipe['reference'], $recipe['endReference']] as $image) {
                    if ($image) {
                        $this->references->path($owner, $image);
                    }
                }
            }
            $episode->edit(trim($data['title']), trim($data['prompt']));
            if (!$prepare) {
                $this->em->flush();

                return null;
            }
            if ($existing) {
                $this->em->flush();

                return $existing;
            }
            $job = new AiGeneration($owner, $episode->getTitle(), $episode->getPrompt(), $recipe['model'], $recipe['seconds'], $recipe['ratio'], $this->catalog->estimate($recipe['model'], $recipe['seconds']));
            $job->attachSubjects($recipe['subjects'] ?? []);
            if ($recipe['reference']) {
                $job->attachReference($recipe['reference']);
                $job->frameImages($recipe['imageFit']);
                if ($recipe['endReference']) {
                    $job->attachEndReference($recipe['endReference']);
                }
            }
            $job->attachSubjectDetails((new AiPlanInputs($this->catalog, $this->references))->subjectDetails($owner, $recipe['model'], $recipe['subjects'] ?? [], $recipe['subjectDetails'] ?? [], ($this->catalog->model($recipe['model'])['subjectReference'] ?? false) ? $recipe['reference'] : null));
            if ('mcp' === $recipe['accessMode']) {
                $job->useMcp();
            }
            $this->em->persist($job);
            $episode->prepare($job);
            $this->em->flush();

            return $job;
        });
    }

    private function assertRevision(int $current, int $submitted): void
    {
        if ($current !== $submitted) {
            throw new \RuntimeException('Cette page a changé dans un autre onglet. Recharge-la avant d’enregistrer ; ton texte saisi reste affiché.');
        }
    }

    public function delete(AiSeries $series, User $owner, int $revision): void
    {
        $this->em->getConnection()->transactional(function () use ($series, $owner, $revision): void {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($series, LockMode::PESSIMISTIC_WRITE);
            if ($series->getOwner()->getId() !== $owner->getId()) {
                throw new \RuntimeException('Série indisponible.');
            }
            $this->assertRevision($series->getRevision(), $revision);
            $db = $this->em->getConnection();
            if ((int) $db->fetchOne("SELECT COUNT(*) FROM series_recurrence WHERE series_id = ? AND status = 'active'", [$series->getId()]) > 0) {
                throw new \RuntimeException('Mets la récurrence en pause avant de supprimer cette série.');
            }
            $busy = $db->fetchOne("SELECT COUNT(DISTINCT e.id) FROM ai_episode e
                LEFT JOIN ai_generation g ON g.id = e.generation_id
                LEFT JOIN ai_shot shot ON shot.episode_id = e.id
                LEFT JOIN ai_generation clip ON clip.id = shot.generation_id
                LEFT JOIN video_edit edit ON edit.id = e.assembly_id
                WHERE e.series_id = :series AND (
                    e.flow_state IN ('running','assembling')
                    OR g.status IN ('queued','submitting','processing')
                    OR clip.status IN ('queued','submitting','processing')
                    OR edit.status IN ('queued','rendering','transcribing')
                    OR EXISTS (SELECT 1 FROM series_occurrence o WHERE o.episode_id = e.id AND o.status = 'writing')
                    OR EXISTS (SELECT 1 FROM story_proposal p WHERE p.scope = e.id AND p.owner_id = :owner AND p.status IN ('queued','story','shots'))
                )", ['series' => $series->getId(), 'owner' => $owner->getId()]);
            if ((int) $busy > 0) {
                throw new \RuntimeException('Un épisode est encore en rédaction, génération ou assemblage. Attends sa fin ou arrête les étapes restantes avant de supprimer la série.');
            }
            // Episode relations cascade; videos, generation history and publication drafts remain.
            $this->em->remove($series);
            $this->em->flush();
        });
    }
}
