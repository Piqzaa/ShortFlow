<?php

namespace App\Service;

final class EditTextLayers
{
    /** @param array<mixed> $rows @return list<array{id:string,start:float,end:float,text:string,x:float,y:float,size:float,color:string}> */
    public function validate(array $rows, float $duration): array
    {
        if (count($rows) > 30) {
            throw new VideoLibraryException('30 textes libres maximum par montage.');
        }
        $result = [];
        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['text'] ?? null) || !is_string($row['id'] ?? null) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $row['id']) || isset($ids[$row['id']])) {
                throw new VideoLibraryException('Vérifie les blocs de texte du montage.');
            }
            $ids[$row['id']] = true;
            if ('' === trim($row['text'])) {
                continue;
            }
            $values = [];
            foreach (['start' => [0, $duration], 'end' => [0, $duration + 0.001], 'x' => [0.05, 0.95], 'y' => [0.05, 0.95], 'size' => [2, 10]] as $key => [$min, $max]) {
                if (!is_scalar($row[$key] ?? null) || !is_numeric($row[$key]) || !is_finite((float) $row[$key]) || $row[$key] < $min || $row[$key] > $max) {
                    throw new VideoLibraryException('Vérifie la durée, la position et la taille des textes.');
                }
                $values[$key] = (float) $row[$key];
            }
            if ($values['end'] - $values['start'] < 0.1 || !mb_check_encoding($row['text'], 'UTF-8') || mb_strlen($row['text']) > 160 || !in_array($row['color'] ?? '', ['#ffffff', '#ffff00', '#67e8f9', '#f9a8d4'], true)) {
                throw new VideoLibraryException('Chaque texte doit durer au moins 0,1 seconde, contenir 160 caractères maximum et utiliser une couleur proposée.');
            }
            $result[] = ['id' => $row['id'], 'text' => trim($row['text']), 'color' => $row['color']] + $values;
        }

        return $result;
    }
}
