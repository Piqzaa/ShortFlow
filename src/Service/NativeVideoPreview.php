<?php

namespace App\Service;

/** Read-only native contracts. Multi-shot remains a prototype; extension is used by the supervised flow. */
final class NativeVideoPreview
{
    /** @param list<array{prompt:string,seconds:int}> $shots
     * @return array{endpoint:string,input:array<string,mixed>,cents:int,seconds:int,calls:int,continuous:bool}
     */
    public function kling(array $shots, string $ratio, bool $audio = true): array
    {
        if (count($shots) < 2 || count($shots) > 6 || !in_array($ratio, ['9:16', '16:9', '1:1'], true)) {
            throw new \InvalidArgumentException('Le prototype Kling accepte 2 à 6 scènes et un format explicite.');
        }
        $parts = [];
        $seconds = 0;
        foreach ($shots as $shot) {
            if ($shot['seconds'] < 1 || $shot['seconds'] > 15 || '' === trim($shot['prompt']) || mb_strlen($shot['prompt']) > 500) {
                throw new \InvalidArgumentException('Chaque scène native exige 1 à 15 secondes et un prompt de 500 caractères maximum.');
            }
            $seconds += $shot['seconds'];
            $parts[] = ['prompt' => trim($shot['prompt']), 'duration' => (string) $shot['seconds']];
        }
        if ($seconds < 3 || $seconds > 15) {
            throw new \InvalidArgumentException('Le total Kling natif doit rester entre 3 et 15 secondes.');
        }

        return ['endpoint' => 'fal-ai/kling-video/v3/pro/text-to-video', 'input' => ['multi_prompt' => $parts, 'duration' => (string) $seconds, 'aspect_ratio' => $ratio, 'generate_audio' => $audio, 'shot_type' => 'customize', 'enable_safety_checker' => true], 'cents' => (new AiCatalog())->estimate('kling-3', $seconds), 'seconds' => $seconds, 'calls' => 1, 'continuous' => false];
    }

    /** @return array{endpoint:string,input:array<string,mixed>,cents:int,billedSeconds:float,outputRatio:string,calls:int,continuous:bool,audio:string} */
    public function extension(string $videoUri, float $inputSeconds, float $fps, int $width, int $height, float $duration, float $context, string $prompt): array
    {
        // This prototype only accepts inline MP4, never a remote URL, local path or JPG disguised as video.
        $prefix = 'data:video/mp4;base64,';
        $bytes = str_starts_with($videoUri, $prefix) && strlen($videoUri) <= 15000000 ? base64_decode(substr($videoUri, strlen($prefix)), true) : false;
        if (false === $bytes || strlen($bytes) < 12 || 'ftyp' !== substr($bytes, 4, 4)) {
            throw new \InvalidArgumentException('L’extension native exige une vidéo MP4, pas une dernière image.');
        }
        foreach ([$inputSeconds, $fps, $duration, $context] as $number) {
            if (!is_finite($number)) {
                throw new \InvalidArgumentException('Métadonnées vidéo invalides.');
            }
        }
        if ($fps < 1 || $fps > 60 || $inputSeconds * $fps < 73 || $width < 1 || $height < 1 || max($width, $height) > 3840 || min($width, $height) > 2160 || $duration < 2 || $duration > 20 || $context < 1 || $context > min(20, $inputSeconds) || ceil(($context + $duration) * $fps) > 505 || mb_strlen($prompt) > 5000 || '' === trim($prompt)) {
            throw new \InvalidArgumentException('Durée, contexte ou format hors du contrat LTX Extend (505 images maximum).');
        }
        $ratio = $height > $width ? '9:16' : '16:9';
        $expected = '9:16' === $ratio ? 9 / 16 : 16 / 9;
        if (abs($width / $height - $expected) / $expected > 0.03) {
            throw new \InvalidArgumentException('Ce prototype refuse un recadrage implicite : fournis une vidéo 9:16 ou 16:9.');
        }
        $billed = ceil(($context + $duration) * $fps) / $fps;

        return ['endpoint' => 'https://api.ltx.io/v2/extend', 'input' => ['model' => 'ltx-2-3-pro', 'video_uri' => $videoUri, 'duration' => $duration, 'context' => $context, 'mode' => 'end', 'prompt' => trim($prompt)], 'cents' => (int) ceil($billed * 10), 'billedSeconds' => $billed, 'outputRatio' => $ratio, 'calls' => 1, 'continuous' => true, 'audio' => 'Le son de la vidéo source conditionne la continuation ; aucun interrupteur audio dans ce contrat.'];
    }
}
