<?php

namespace App\Service;

final class SeriesMontage
{
    /** @return array{sourceVolume:int,intro:bool,fit:string} */
    public static function normalize(mixed $input): array
    {
        if (!is_array($input) || array_diff(array_keys($input), ['sourceVolume', 'intro', 'fit'])) {
            throw new \InvalidArgumentException('Vérifie les réglages de montage de la série.');
        }
        $settings = array_replace(['sourceVolume' => 100, 'intro' => false, 'fit' => 'contain'], $input);
        if (!is_int($settings['sourceVolume']) || $settings['sourceVolume'] < 0 || $settings['sourceVolume'] > 100) {
            throw new \InvalidArgumentException('Choisis un volume des clips entier entre 0 et 100 %.');
        }
        if (!is_bool($settings['intro']) || !in_array($settings['fit'], ['contain', 'cover'], true)) {
            throw new \InvalidArgumentException('Choisis un titre et un cadrage de montage valides.');
        }

        return $settings;
    }
}
