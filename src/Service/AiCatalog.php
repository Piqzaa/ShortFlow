<?php

namespace App\Service;

final class AiCatalog
{
    public const CHECKED = '2026-10-19';
    public const MODELS = [
        'kling-25' => ['name' => 'Kling 2.5 Turbo Pro', 'maker' => 'Kling', 'description' => 'Mouvements et plans cinématiques.', 'endpoint' => 'fal-ai/kling-video/v2.5-turbo/pro/text-to-video', 'rate' => 7, 'audio' => false],
        'wan-25' => ['name' => 'Wan 2.5', 'maker' => 'Alibaba', 'description' => 'Scènes créatives avec audio généré.', 'endpoint' => 'fal-ai/wan-25-preview/text-to-video', 'rate' => 10, 'audio' => true],
    ];

    /** @return array{name:string,maker:string,description:string,endpoint:string,rate:int,audio:bool} */
    public function model(string $id): array
    {
        return self::MODELS[$id] ?? throw new \InvalidArgumentException('Choisis un modèle disponible.');
    }

    public function estimate(string $id, int $seconds): int
    {
        if (!in_array($seconds, [5, 10], true)) {
            throw new \InvalidArgumentException('Choisis une durée de 5 ou 10 secondes.');
        }

        return $this->model($id)['rate'] * $seconds;
    }
}
