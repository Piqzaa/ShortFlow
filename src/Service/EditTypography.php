<?php

namespace App\Service;

final class EditTypography
{
    public const FONTS = ['inter' => 'Inter', 'montserrat' => 'Montserrat', 'poppins' => 'Poppins', 'roboto' => 'Roboto', 'lato' => 'Lato', 'opensans' => 'Open Sans', 'anton' => 'Anton', 'bebas' => 'Bebas Neue', 'oswald' => 'Oswald', 'sans' => 'DejaVu Sans', 'sans-bold' => 'DejaVu Sans', 'serif' => 'DejaVu Serif', 'serif-bold' => 'DejaVu Serif', 'mono' => 'DejaVu Sans Mono', 'mono-bold' => 'DejaVu Sans Mono'];
    public const DEFAULTS = ['font' => 'sans-bold', 'bold' => false, 'italic' => false, 'align' => 'center', 'outline' => 0, 'shadow' => 0, 'box' => false, 'background' => '#000000', 'opacity' => 100, 'width' => 80, 'color' => '#ffffff'];

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public static function validate(array $input): array
    {
        $style = array_intersect_key($input, self::DEFAULTS) + self::DEFAULTS;
        if (!is_string($style['font']) || !isset(self::FONTS[$style['font']]) || !in_array($style['align'], ['left', 'center', 'right'], true)) {
            throw new VideoLibraryException('Choisis une police et un alignement proposés.');
        }
        foreach (['bold', 'italic', 'box'] as $key) {
            if (!in_array($style[$key], [true, false, 0, 1, '0', '1'], true)) {
                throw new VideoLibraryException('Vérifie les options du texte.');
            }
            $style[$key] = (bool) $style[$key];
        }
        foreach (['color', 'background'] as $key) {
            if (!is_string($style[$key]) || !preg_match('/^#[a-fA-F0-9]{6}$/D', $style[$key])) {
                throw new VideoLibraryException('Choisis une couleur au format #RRGGBB.');
            }
            $style[$key] = strtolower($style[$key]);
        }
        foreach (['outline' => [0, 6], 'shadow' => [0, 6], 'opacity' => [0, 100], 'width' => [10, 95]] as $key => [$min, $max]) {
            if (!is_scalar($style[$key]) || !is_numeric($style[$key]) || !is_finite((float) $style[$key]) || $style[$key] < $min || $style[$key] > $max) {
                throw new VideoLibraryException('Vérifie la largeur, l’opacité et les contours du texte.');
            }
            $style[$key] = (float) $style[$key];
        }

        return $style;
    }
}
