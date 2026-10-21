<?php

namespace App\Service;

final class CaptionTiming
{
    /** @param list<array{start:float,end:float,text:string,x?:float,y?:float}> $rows
     * @param list<array{id:int,start:float,end:float,origin?:string}> $before
     * @param list<array{id:int,start:float,end:float,origin?:string}> $after
     *
     * @return list<array{start:float,end:float,text:string,x?:float,y?:float}>
     */
    public function remap(array $rows, array $before, array $after): array
    {
        $result = [];
        foreach ($rows as $row) {
            $parts = [];
            $oldOffset = 0.0;
            foreach ($before as $old) {
                $start = max($row['start'], $oldOffset);
                $end = min($row['end'], $oldOffset + $old['end'] - $old['start']);
                $newOffset = 0.0;
                foreach ($after as $next) {
                    $same = $old['id'] === $next['id'] && (!isset($old['origin'], $next['origin']) || $old['origin'] === $next['origin']);
                    if ($same && $end > $start) {
                        $in = max($old['start'] + $start - $oldOffset, $next['start']);
                        $out = min($old['start'] + $end - $oldOffset, $next['end']);
                        if ($out - $in > 0.0005) {
                            $parts[] = ['start' => round($newOffset + $in - $next['start'], 3), 'end' => round($newOffset + $out - $next['start'], 3), 'text' => $row['text']] + array_intersect_key($row, array_flip(['x', 'y']));
                        }
                    }
                    $newOffset += $next['end'] - $next['start'];
                }
                $oldOffset += $old['end'] - $old['start'];
            }
            usort($parts, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);
            $merged = [];
            foreach ($parts as $part) {
                $last = count($merged) - 1;
                if ($last >= 0 && abs($merged[$last]['end'] - $part['start']) < 0.002) {
                    $merged[$last]['end'] = $part['end'];
                } else {
                    $merged[] = $part;
                }
            }
            array_push($result, ...$merged);
        }
        usort($result, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $result;
    }
}
