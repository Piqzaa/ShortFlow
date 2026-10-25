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

    /**
     * @param list<string> $subjects
     *
     * @return list<array{reference:string,name:string,role:string,presence:string,views:list<string>}>
     */
    public function subjectDetails(User $owner, string $model, array $subjects, mixed $rows, ?string $primary = null, bool $requireViews = true): array
    {
        $subjects = $this->subjects($owner, $model, $subjects);
        $config = $this->catalog->model($model);
        $names = $subjects;
        if ($config['subjectReference'] ?? false) {
            if ($primary) {
                $this->storage->path($owner, $primary);
                array_unshift($names, $primary);
            }
            $names = array_values(array_unique($names));
            if (count($names) > $this->catalog->subjectLimit($model)) {
                throw new \RuntimeException('Ce modèle accepte au maximum sept images de référence au total.');
            }
        }
        if (!is_array($rows) || count($rows) > 7) {
            throw new \RuntimeException('Les descriptions de références sont invalides.');
        }
        $details = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \RuntimeException('Une description de référence est invalide.');
            }
            $reference = $row['reference'] ?? '';
            if ('' === $reference) {
                continue; // A new upload card may not have an image yet.
            }
            if ('primary' === $reference && ($config['subjectReference'] ?? false)) {
                $reference = $primary;
                if (null === $reference) {
                    continue;
                }
            }
            if (!is_string($reference) || !in_array($reference, $names, true) || isset($details[$reference])) {
                throw new \RuntimeException('Associe chaque description à une référence sélectionnée, sans doublon.');
            }
            $name = $row['name'] ?? '';
            $role = $row['role'] ?? 'character';
            $presence = $row['presence'] ?? 'unspecified';
            $views = $row['views'] ?? [];
            if (!is_string($name) || mb_strlen($name) > 60 || !in_array($role, ['character', 'object'], true) || !in_array($presence, ['visible', 'enters', 'unspecified'], true) || !is_array($views) || count($views) > 3) {
                throw new \RuntimeException('Vérifie le nom, le rôle, la présence et les vues de chaque référence.');
            }
            $normalizedViews = [];
            foreach ($views as $view) {
                if ('' === $view || null === $view) {
                    continue;
                }
                if (!is_string($view) || $view === $reference || in_array($view, $normalizedViews, true)) {
                    throw new \RuntimeException('Les vues doivent être distinctes de l’image principale et ne pas être dupliquées.');
                }
                $this->storage->path($owner, $view);
                $normalizedViews[] = $view;
            }
            if ($normalizedViews && 'kling-3-image' !== $model) {
                throw new \RuntimeException('Ce modèle ne reçoit pas de vues groupées. Ajoute ses références dans la liste d’images compatible.');
            }
            $details[$reference] = ['reference' => $reference, 'name' => trim($name), 'role' => $role, 'presence' => $presence, 'views' => $normalizedViews];
        }
        $result = [];
        foreach ($names as $index => $reference) {
            $detail = $details[$reference] ?? ['reference' => $reference, 'name' => '', 'role' => 'character', 'presence' => 'unspecified', 'views' => []];
            $detail['name'] = '' === $detail['name'] ? 'Sujet '.($index + 1) : $detail['name'];
            if ($requireViews && 'kling-3-image' === $model && !$detail['views']) {
                throw new \RuntimeException('Ajoute une à trois vues supplémentaires distinctes pour chaque personnage ou objet Kling, avant de confirmer son coût.');
            }
            $result[] = $detail;
        }

        return $result;
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

    /** @return array{start:?string,end:?string,fit:string,subjects:list<string>,subjectDetails:list<array{reference:string,name:string,role:string,presence:string,views:list<string>}>,fromPrevious:bool,role:string} */
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

        $details = $this->subjectDetails($episode->getOwner(), $recipe['model'], $subjects, $recipe['subjectDetails'] ?? [], $subject ? $start : null);

        return ['start' => $start, 'end' => $shot->getEndAnchor() ?? $end, 'fit' => $own['referenceFit'] ?? $recipe['imageFit'], 'subjects' => $subjects, 'subjectDetails' => $details, 'fromPrevious' => $fromPrevious, 'role' => $subject ? 'personnage' : (($model['image'] ?? false) ? 'cadres' : 'texte')];
    }
}
