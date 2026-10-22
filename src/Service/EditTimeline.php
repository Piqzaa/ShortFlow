<?php

namespace App\Service;

/** Timeline positions are distinct from in/out points in the source file. */
final class EditTimeline
{
    /** @param list<array<string, mixed>> $clips @return list<array<string, mixed>> */
    public static function layout(array $clips): array
    {
        $cursor = 0.0;
        $result = [];
        foreach ($clips as $clip) {
            $clip['rate'] = (float) ($clip['rate'] ?? 1);
            $clip['at'] = (float) ($clip['at'] ?? $cursor);
            $cursor = $clip['at'] + ($clip['end'] - $clip['start']) / $clip['rate'];
            $result[] = $clip;
        }
        usort($result, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return $result;
    }

    /** @param list<array<string, mixed>> $clips */
    public static function duration(array $clips): float
    {
        return array_reduce(self::layout($clips), static fn (float $end, array $clip): float => max($end, $clip['at'] + ($clip['end'] - $clip['start']) / $clip['rate']), 0.0);
    }

    public static function tempo(float $rate): string
    {
        $filters = [];
        while ($rate < 0.5) {
            $filters[] = 'atempo=0.5';
            $rate /= 0.5;
        }
        while ($rate > 2) {
            $filters[] = 'atempo=2';
            $rate /= 2;
        }
        $filters[] = 'atempo='.sprintf('%.4F', $rate);

        return implode(',', $filters);
    }

    /** @param list<array<string, mixed>> $clips @return list<array<string, mixed>> */
    public static function segments(array $clips): array
    {
        $result = [];
        $cursor = 0.0;
        foreach (self::layout($clips) as $clip) {
            if ($clip['at'] - $cursor > 0.0005) {
                $result[] = ['gap' => $clip['at'] - $cursor, 'at' => $cursor];
            }
            $last = array_key_last($result);
            $previous = null === $last ? null : $result[$last];
            $look = EditClipStyle::validate($clip['look'] ?? []);
            $previousLook = $previous && isset($previous['id']) ? EditClipStyle::validate($previous['look'] ?? []) : [];
            $innerFadesOff = $previousLook && 0.0 === $previousLook['fadeOut'] && 0.0 === $look['fadeIn'];
            $sameLook = $previousLook && [] === array_diff_assoc(array_diff_key($previousLook, ['fadeIn' => true, 'fadeOut' => true]), $look);
            if ($previous && isset($previous['id']) && $previous['id'] === $clip['id'] && abs($cursor - $clip['at']) < 0.00001 && abs($previous['end'] - $clip['start']) < 0.00001 && $previous['rate'] === $clip['rate'] && $innerFadesOff && $sameLook) {
                // A cut alone must not introduce an encoder boundary or audio priming.
                $result[$last]['end'] = $clip['end'];
                $result[$last]['look'] = array_replace($previousLook, ['fadeOut' => $look['fadeOut']]);
            } else {
                $result[] = $clip;
            }
            $cursor = $clip['at'] + ($clip['end'] - $clip['start']) / $clip['rate'];
        }

        return $result;
    }
}
