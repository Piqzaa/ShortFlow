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
                throw new \RuntimeException('Ce modèle accepte au maximum '.$this->catalog->subjectLimit($model).' images de référence au total.');
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
     * @return array{characterIds?:list<string>,startReference:?string,endReference:?string,referenceFit:string,imagePrompt?:string,sceneDescription?:string,translatedIdentity?:string,translationSource?:string,promptOnly?:string}
     */
    public function row(User $owner, array $row, string $defaultFit): array
    {
        $result = ['startReference' => null, 'endReference' => null, 'referenceFit' => $row['referenceFit'] ?? $defaultFit];
        if ('1' === (string) ($row['characterIdsManaged'] ?? '0') || array_key_exists('characterIds', $row)) {
            $ids = $row['characterIds'] ?? [];
            if (!is_array($ids) || count($ids) > 7 || count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
                throw new \RuntimeException('Sélection de personnages invalide dans le plan.');
            }
            foreach ($ids as $id) {
                if (!is_string($id) || !preg_match('/^[1-9][0-9]*$/D', $id)) {
                    throw new \RuntimeException('Personnage invalide dans le plan.');
                }
            }
            $result['characterIds'] = array_values($ids);
        }
        if (!in_array($row['promptOnly'] ?? '', ['', '1', 'manual'], true)) {
            throw new \RuntimeException('Le mode de consignes du plan est invalide. Recharge la préparation.');
        }
        if (in_array($row['promptOnly'] ?? '', ['1', 'manual'], true)) {
            $result['promptOnly'] = $row['promptOnly'];
        }
        $identity = $row['translatedIdentity'] ?? '';
        $source = $row['translationSource'] ?? '';
        if (!is_string($identity) || mb_strlen($identity) > 300 || !is_string($source) || ('' !== $source && !preg_match('/^[a-f0-9]{64}$/D', $source)) || ('manual' !== ($row['promptOnly'] ?? '') && (('' !== trim($identity)) !== ('' !== $source)))) {
            throw new \RuntimeException('Le contexte traduit du plan est incomplet. Propose un nouveau scénario ou confirme tes prompts relus dans le Studio.');
        }
        if ('' !== trim($identity) || ('manual' === ($row['promptOnly'] ?? '') && '' !== $source)) {
            $result['translatedIdentity'] = trim($identity);
            $result['translationSource'] = $source;
        }
        $description = $row['sceneDescription'] ?? '';
        if (!is_string($description) || mb_strlen($description) > 300) {
            throw new \RuntimeException('Le résumé français du plan doit contenir au maximum 300 caractères.');
        }
        if ('' !== trim($description)) {
            $result['sceneDescription'] = trim($description);
        }
        $imagePrompt = $row['imagePrompt'] ?? '';
        if (!is_string($imagePrompt) || mb_strlen($imagePrompt) > 3000) {
            throw new \RuntimeException('Les consignes de l’image doivent contenir au maximum 3 000 caractères.');
        }
        if ('' !== trim($imagePrompt)) {
            $result['imagePrompt'] = trim($imagePrompt);
        }
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

    /** @return array{start:?string,end:?string,fit:string,subjects:list<string>,subjectDetails:list<array{reference:string,name:string,role:string,presence:string,views:list<string>,description?:string}>,fromPrevious:bool,role:string} */
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
        if (!array_key_exists('characterPool', $recipe) && !empty($own['characterIds'])) {
            throw new \RuntimeException('Choisis d’abord les fiches communes à cette préparation, puis leur présence par scène.');
        }
        $libraryDetails = null;
        if (array_key_exists('characterPool', $recipe)) {
            if (!array_key_exists('characterIds', $own)) {
                throw new \RuntimeException('Choisis les personnages présents dans chaque scène, puis enregistre les plans.');
            }
            if (!empty($recipe['subjects']) || !empty($recipe['subjectDetails']) || ($subject && $recipe['reference'])) {
                throw new \RuntimeException('Retire les anciennes références communes avant de choisir les fiches par scène. Les images de début restent distinctes.');
            }
            $pool = [];
            foreach ($recipe['characterPool'] as $character) {
                $pool[(string) $character['id']] = $character;
            }
            $libraryDetails = [];
            foreach ($own['characterIds'] as $id) {
                if (!isset($pool[$id])) {
                    throw new \RuntimeException('Un personnage du plan ne fait pas partie de cette préparation. Corrige la scène ou sa sélection commune.');
                }
                $character = $pool[$id];
                $libraryDetails[] = ['reference' => $character['portrait'], 'name' => $character['name'], 'role' => 'character', 'presence' => 'unspecified', 'views' => ($model['subjectReference'] ?? false) ? [] : $character['views']];
            }
            if ($libraryDetails && 0 === $this->catalog->subjectLimit($recipe['model'])) {
                throw new \RuntimeException('Ce modèle ne reçoit pas de références de personnages. Choisis un modèle compatible ou retire les personnages de la scène.');
            }
            if ($subject) {
                $start = $libraryDetails[0]['reference'] ?? null;
                if (!$start) {
                    throw new \RuntimeException('Aucun personnage choisi pour ce plan : ce modèle exige une référence de personnage. Choisis sa présence ou un autre modèle.');
                }
            }
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
        $names = null === $libraryDetails ? ($recipe['subjects'] ?? []) : array_column($libraryDetails, 'reference');
        if ($subject && null !== $libraryDetails) {
            array_shift($names);
        }
        $subjects = $this->subjects($episode->getOwner(), $recipe['model'], $names);
        if ($subject && count($subjects) + ($start ? 1 : 0) > $this->catalog->subjectLimit($recipe['model'])) {
            throw new \RuntimeException('Trop d’images de personnage pour ce modèle.');
        }

        $details = $this->subjectDetails($episode->getOwner(), $recipe['model'], $subjects, $libraryDetails ?? ($recipe['subjectDetails'] ?? []), $subject ? $start : null);
        if (null !== $libraryDetails) {
            foreach ($details as &$detail) {
                foreach ($recipe['characterPool'] as $character) {
                    if ($character['portrait'] === $detail['reference']) {
                        $detail['description'] = $character['description'];
                    }
                }
            }
            unset($detail);
        }

        return ['start' => $start, 'end' => $shot->getEndAnchor() ?? $end, 'fit' => $own['referenceFit'] ?? $recipe['imageFit'], 'subjects' => $subjects, 'subjectDetails' => $details, 'fromPrevious' => $fromPrevious, 'role' => $subject ? 'personnage' : (($model['image'] ?? false) ? 'cadres' : 'texte')];
    }
}
