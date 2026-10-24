<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiShot;
use App\Entity\User;

final class AiPlanInputs
{
    public function __construct(private AiCatalog $catalog, private AiReferenceStorage $storage)
    {
    }

    /**
     * @return list<string>
     */
    public function subjects(User $owner, string $model, mixed $names): array
    {
        if (!is_array($names) || count($names) > $this->catalog->subjectLimit($model)) {
            throw new \RuntimeException('Le nombre de références de personnages ne correspond pas aux capacités de ce modèle.');
        }
        $result = [];
        foreach ($names as $name) {
            if (!is_string($name)) {
                throw new \RuntimeException('Référence de personnage invalide.');
            }
            $this->storage->path($owner, $name);
            $result[] = $name;
        }

        return array_values(array_unique($result));
    }

    /** @param array<string,mixed> $row
     * @return array{startReference:?string,endReference:?string,referenceFit:string}
     */
    public function row(User $owner, array $row, string $defaultFit): array
    {
        $result = ['startReference' => null, 'endReference' => null, 'referenceFit' => $row['referenceFit'] ?? $defaultFit];
        if (!in_array($result['referenceFit'], ['crop', 'contain'], true)) {
            throw new \RuntimeException('Choisis un cadrage valide pour chaque plan.');
        }
        foreach (['startReference', 'endReference'] as $field) {
            if (!empty($row[$field])) {
                if (!is_string($row[$field])) {
                    throw new \RuntimeException('Référence de plan invalide.');
                }
                $this->storage->path($owner, $row[$field]);
                $result[$field] = $row[$field];
            }
        }

        return $result;
    }

    /** @return array{start:?string,end:?string,fit:string,subjects:list<string>,fromPrevious:bool,role:string} */
    public function resolve(AiEpisode $episode, AiShot $shot, int $index, int $count): array
    {
        $recipe = $episode->getFlowRecipe() ?? throw new \RuntimeException('Prépare les plans avant leurs références.');
        $model = $this->catalog->model($recipe['model']);
        $subject = $model['subjectReference'] ?? false;
        $modern = ($recipe['inputVersion'] ?? 1) >= 2;
        $own = $modern ? $this->row($episode->getOwner(), $shot->getInputs(), $recipe['imageFit']) : [];
        $fromPrevious = 'linked' === $episode->getContinuity() && $index > 0;
        $start = $modern && !$subject ? ($own['startReference'] ?? null) : $recipe['reference'];
        if ($fromPrevious && !$modern) {
            $start = null;
        }
        $end = $modern ? ($own['endReference'] ?? null) : ($index === $count - 1 || ($model['requiresEnd'] ?? false) ? $recipe['endReference'] : null);
        if ($subject && (($own['startReference'] ?? null) || ($own['endReference'] ?? null))) {
            throw new \RuntimeException('Une référence de personnage ne fixe pas les cadres de début ou de fin des plans.');
        }
        if ($fromPrevious && $start) {
            throw new \RuntimeException('Le plan '.($index + 1).' repart du précédent : retire son image de début explicite ou choisis plusieurs scènes.');
        }
        if (!$fromPrevious) {
            $this->catalog->validateReferences($recipe['model'], $start, $end);
        } elseif ($end && !isset($model['endImageField'])) {
            throw new \RuntimeException('Ce modèle ne prend pas d’image de fin.');
        }
        foreach ([$start, $end] as $name) {
            if ($name) {
                $this->storage->path($episode->getOwner(), $name);
            }
        }
        $subjects = $this->subjects($episode->getOwner(), $recipe['model'], $recipe['subjects'] ?? []);
        if ($subject && count($subjects) + ($start ? 1 : 0) > $this->catalog->subjectLimit($recipe['model'])) {
            throw new \RuntimeException('Trop d’images de personnage pour ce modèle.');
        }

        return ['start' => $start, 'end' => $shot->getEndAnchor() ?? $end, 'fit' => $own['referenceFit'] ?? $recipe['imageFit'], 'subjects' => $subjects, 'fromPrevious' => $fromPrevious, 'role' => $subject ? 'personnage' : (($model['image'] ?? false) ? 'cadres' : 'texte')];
    }
}
