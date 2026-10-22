<?php

namespace App\Service;

final class EditCaptions
{
    /** @param array<mixed> $rows
     * @return list<array{start:float,end:float,text:string,x?:float,y?:float}>
     */
    public function validate(array $rows, float $duration): array
    {
        if (count($rows) > 300) {
            throw new VideoLibraryException('300 blocs de sous-titres maximum.');
        }
        $result = [];
        $last = 0.0;
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['start'], $row['end'], $row['text']) || !is_scalar($row['start']) || !is_scalar($row['end']) || !is_string($row['text']) || !is_numeric($row['start']) || !is_numeric($row['end'])) {
                throw new VideoLibraryException('Vérifie le texte et les horaires des sous-titres.');
            }
            $start = (float) $row['start'];
            $end = (float) $row['end'];
            $text = trim($row['text']);
            if ('' === $text) {
                continue;
            }
            if (!mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > 160 || !is_finite($start) || !is_finite($end) || $start < $last || $end <= $start || $end > $duration + 0.1) {
                throw new VideoLibraryException('Les sous-titres doivent se suivre sans chevauchement, rester dans la vidéo et contenir 160 caractères maximum par bloc.');
            }
            $position = [];
            foreach (['x', 'y'] as $axis) {
                if (isset($row[$axis]) && '' !== $row[$axis]) {
                    if (!is_scalar($row[$axis]) || !is_numeric($row[$axis]) || !is_finite((float) $row[$axis]) || $row[$axis] < 0.05 || $row[$axis] > 0.95) {
                        throw new VideoLibraryException('La position des sous-titres doit rester dans l’image.');
                    }
                    $position[$axis] = (float) $row[$axis];
                }
            }
            $result[] = ['start' => $start, 'end' => min($duration, $end), 'text' => $text] + $position;
            $last = $end;
        }

        return $result;
    }

    /** @param list<array{start:float,end:float,text:string,x?:float,y?:float}> $rows */
    public function srt(array $rows): string
    {
        $result = '';
        foreach ($rows as $i => $row) {
            $result .= ($i + 1)."\n".$this->clock($row['start'], true).' --> '.$this->clock($row['end'], true)."\n".$row['text']."\n\n";
        }

        return $result;
    }

    /** @param list<array<string, mixed>> $rows @param array<string, mixed> $position */
    public function ass(array $rows, int $width, int $height, string $style, array $position = []): string
    {
        $color = 'social' === $style ? '&H0000FFFF' : '&H00FFFFFF';
        $size = (int) round($height * ($position['size'] ?? 4) / 100);
        $margin = (int) round($height * 0.12);
        $result = "[Script Info]\nScriptType: v4.00+\nPlayResX: $width\nPlayResY: $height\nWrapStyle: 0\n\n[V4+ Styles]\nFormat: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\nStyle: Default,DejaVu Sans,$size,$color,&H000000FF,&H00000000,&H80000000,-1,0,0,0,100,100,0,0,1,3,1,2,70,70,$margin,1\n\n[Events]\nFormat: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";
        preg_match('/Style: Default,[^\n]+/', $result, $match);
        $boxStyle = explode(',', $match[0]);
        $boxStyle[0] = 'Style: Box';
        $boxStyle[15] = '3';
        $result = str_replace("\n\n[Events]", "\n".implode(',', $boxStyle)."\n\n[Events]", $result);
        $base = EditTypography::validate($position['typography'] ?? ['color' => 'social' === $style ? '#ffff00' : '#ffffff']);
        foreach ($rows as $row) {
            $appearance = EditTypography::validate(array_intersect_key($row, EditTypography::DEFAULTS) + $base);
            $text = str_replace(['\\', '{', '}', "\r", "\n"], ['／', '（', '）', '', '\N'], $row['text']);
            $text = preg_replace('/[\x00-\x1f\x7f]/u', '', $text) ?? '';
            $x = (int) round($width * ($row['x'] ?? $position['x'] ?? 0.5));
            $y = (int) round($height * ($row['y'] ?? $position['y'] ?? 0.85));
            $fontSize = (int) round($height * ($row['size'] ?? $position['size'] ?? 4) / 100);
            $hex = ltrim($appearance['color'], '#');
            $ink = strtoupper(substr($hex, 4, 2).substr($hex, 2, 2).substr($hex, 0, 2));
            $align = ['left' => 4, 'center' => 5, 'right' => 6][$appearance['align']];
            $textWidth = $width * $appearance['width'] / 100;
            if (4 === $align) {
                $x -= (int) round($textWidth / 2);
            }
            if (6 === $align) {
                $x += (int) round($textWidth / 2);
            }
            $font = EditTypography::FONTS[$appearance['font']];
            $bold = $appearance['bold'] || str_ends_with($appearance['font'], '-bold') ? -1 : 0;
            $italic = $appearance['italic'] ? -1 : 0;
            $outline = sprintf('%.2F', ($appearance['box'] ? 6 : $appearance['outline']) * $height / 1080);
            $shadow = sprintf('%.2F', $appearance['shadow'] * $height / 1080);
            $alpha = sprintf('%02X', (int) round(255 * (1 - $appearance['opacity'] / 100)));
            $placement = '{\\an'.$align.'\\pos('.$x.','.$y.')\\fn'.$font.'\\b'.$bold.'\\i'.$italic.'\\fs'.$fontSize.'\\c&H'.$ink.'&\\alpha&H'.$alpha.'&\\bord'.$outline.'\\shad'.$shadow;
            if ($appearance['box']) {
                $bg = substr($appearance['background'], 1);
                $placement .= '\\3c&H'.strtoupper(substr($bg, 4, 2).substr($bg, 2, 2).substr($bg, 0, 2)).'&\\3a&H60&';
            }
            $placement .= '}';
            $marginH = max(0, (int) round(($width - $textWidth) / 2));
            $result .= 'Dialogue: 0,'.$this->clock($row['start'], false).','.$this->clock($row['end'], false).','.($appearance['box'] ? 'Box' : 'Default').',,'.$marginH.','.$marginH.',0,,'.$placement.$text."\n";
        }

        return $result;
    }

    private function clock(float $time, bool $srt): string
    {
        $ms = (int) round($time * ($srt ? 1000 : 100));
        $base = $srt ? 1000 : 100;
        $seconds = intdiv($ms, $base);

        return sprintf($srt ? '%02d:%02d:%02d,%03d' : '%d:%02d:%02d.%02d', intdiv($seconds, 3600), intdiv($seconds, 60) % 60, $seconds % 60, $ms % $base);
    }
}
