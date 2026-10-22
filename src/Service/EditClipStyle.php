<?php

namespace App\Service;

final class EditClipStyle
{
    public const DEFAULTS = ['zoom' => 1, 'x' => 0.5, 'y' => 0.5, 'rotation' => 0, 'flip' => false, 'filter' => 'none', 'intensity' => 100, 'brightness' => 1, 'contrast' => 1, 'saturation' => 1, 'fadeIn' => 0, 'fadeOut' => 0];

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function validate(array $input): array
    {
        $look = array_intersect_key($input, self::DEFAULTS) + self::DEFAULTS;
        foreach (['zoom' => [1, 3], 'x' => [0, 1], 'y' => [0, 1], 'intensity' => [0, 100], 'brightness' => [0.5, 1.5], 'contrast' => [0.5, 1.5], 'saturation' => [0, 2], 'fadeIn' => [0, 2], 'fadeOut' => [0, 2]] as $key => [$min, $max]) {
            if (!is_scalar($look[$key]) || !is_numeric($look[$key]) || !is_finite((float) $look[$key]) || $look[$key] < $min || $look[$key] > $max) {
                throw new VideoLibraryException('Vérifie le cadrage, le filtre et les fondus du clip.');
            }
            $look[$key] = (float) $look[$key];
        }
        if (!in_array($look['rotation'], [0, 90, 180, 270, '0', '90', '180', '270'], true) || !in_array($look['flip'], [false, true, 0, 1, '0', '1'], true) || !in_array($look['filter'], ['none', 'warm', 'cool', 'vivid', 'soft', 'bw'], true)) {
            throw new VideoLibraryException('Choisis une rotation et un filtre proposés.');
        }
        $look['rotation'] = (int) $look['rotation'];
        $look['flip'] = (bool) $look['flip'];

        return $look;
    }

    /** @param array<string,mixed> $look @return array{brightness:float,contrast:float,matrix:list<float>} */
    public static function grade(array $look): array
    {
        $amount = $look['intensity'] / 100;
        $preset = ['none' => [1, 1, 1, 1, 1], 'warm' => [1.03, 1.03, 1.05, 1.08, 0.91], 'cool' => [1.01, 1.04, 0.95, 0.93, 1.08], 'vivid' => [1, 1.12, 1.3, 1, 1], 'soft' => [1.05, 0.9, 0.85, 1, 1], 'bw' => [1, 1.05, 0, 1, 1]][$look['filter']];
        [$b,$c,$s,$red,$blue] = array_map(static fn (float $n): float => 1 + ($n - 1) * $amount, $preset);
        $s *= $look['saturation'];

        return ['brightness' => $b * $look['brightness'], 'contrast' => $c * $look['contrast'], 'matrix' => array_map(static fn (float $n): float => round(max(-2, min(2, $n)), 6), [($s * .787 + .213) * $red, (.715 - .715 * $s) * $red, (.072 - .072 * $s) * $red, .213 - .213 * $s, .285 * $s + .715, .072 - .072 * $s, (.213 - .213 * $s) * $blue, (.715 - .715 * $s) * $blue, (.928 * $s + .072) * $blue])];
    }

    /** @param array<string,mixed> $clip */
    public static function audioFades(array $clip): string
    {
        $look = self::validate($clip['look'] ?? []);
        $duration = ($clip['end'] - $clip['start']) / ($clip['rate'] ?? 1);
        $filters = '';
        foreach (['fadeIn' => 'in', 'fadeOut' => 'out'] as $key => $direction) {
            $length = min($look[$key], $duration / 2);
            if ($length > 0) {
                $filters .= ',afade=t='.$direction.':st='.sprintf('%.6F', 'in' === $direction ? 0 : $duration - $length).':d='.sprintf('%.6F', $length);
            }
        }

        return $filters;
    }

    /** @param array<string,mixed> $clip */
    public static function filters(array $clip, int $width, int $height, string $fit, string $background): string
    {
        $look = self::validate($clip['look'] ?? []);
        $filters = [];
        if (90 === $look['rotation']) {
            $filters[] = 'transpose=clock';
        }
        if (180 === $look['rotation']) {
            $filters[] = 'hflip,vflip';
        }
        if (270 === $look['rotation']) {
            $filters[] = 'transpose=cclock';
        }
        if ($look['flip']) {
            $filters[] = 'hflip';
        }
        $x = sprintf('%.4F', $look['x']);
        $y = sprintf('%.4F', $look['y']);
        $zoom = sprintf('%.4F', $look['zoom']);
        $filters[] = "scale=$width:$height:force_original_aspect_ratio=".('cover' === $fit ? 'increase' : 'decrease').':force_divisible_by=2';
        $filters[] = "scale=ceil(iw*$zoom/2)*2:ceil(ih*$zoom/2)*2";
        $filters[] = "pad=max(iw\\,$width):max(ih\\,$height):(ow-iw)*$x:(oh-ih)*$y:color=".str_replace('#', '0x', $background);
        $filters[] = "crop=$width:$height:(iw-$width)*$x:(ih-$height)*$y";
        $grade = self::grade($look);
        $slope = sprintf('%.6F', $grade['brightness'] * $grade['contrast']);
        $offset = sprintf('%.6F', 127.5 * (1 - $grade['contrast']));
        $expression = "clip(val*$slope+$offset\\,0\\,255)";
        $filters[] = "lutrgb=r=$expression:g=$expression:b=$expression";
        $matrix = [];
        foreach (['rr', 'rg', 'rb', 'gr', 'gg', 'gb', 'br', 'bg', 'bb'] as $i => $key) {
            $matrix[] = $key.'='.sprintf('%.6F', $grade['matrix'][$i]);
        }
        $filters[] = 'colorchannelmixer='.implode(':', $matrix);
        $duration = ($clip['end'] - $clip['start']) / ($clip['rate'] ?? 1);
        foreach (['fadeIn' => 'in', 'fadeOut' => 'out'] as $key => $direction) {
            $length = min($look[$key], $duration / 2);
            if ($length > 0) {
                $filters[] = 'fade=t='.$direction.':st='.sprintf('%.6F', 'in' === $direction ? 0 : $duration - $length).':d='.sprintf('%.6F', $length).':color='.str_replace('#', '0x', $background);
            }
        }

        return implode(',', $filters);
    }
}
