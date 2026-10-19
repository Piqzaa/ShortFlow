<?php

namespace App\Service;

final class AiCatalog
{
    public const CHECKED = '2026-10-19';
    public const PROVIDERS = ['fal' => 'Fal', 'replicate' => 'Replicate'];
    // IDs are durable execution choices: never rebind one to another provider/schema.
    public const MODELS = [
        'kling-25' => ['name' => 'Kling 2.5 Turbo Pro', 'maker' => 'Kling', 'provider' => 'fal', 'description' => 'Mouvements et plans cinématiques.', 'endpoint' => 'fal-ai/kling-video/v2.5-turbo/pro/text-to-video', 'rateMicros' => 70000, 'audio' => false, 'seconds' => [5, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => 'Pro'],
        'wan-25' => ['name' => 'Wan 2.5', 'maker' => 'Alibaba', 'provider' => 'fal', 'description' => 'Scènes créatives avec audio généré.', 'endpoint' => 'fal-ai/wan-25-preview/text-to-video', 'rateMicros' => 100000, 'audio' => true, 'seconds' => [5, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p'],
        'kling-3' => ['name' => 'Kling 3.0 Pro', 'maker' => 'Kling', 'provider' => 'fal', 'description' => 'Plans jusqu’à 15 secondes avec audio natif. Voix chinoises ou anglaises.', 'endpoint' => 'fal-ai/kling-video/v3/pro/text-to-video', 'rateMicros' => 168000, 'audio' => true, 'seconds' => [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => 'Pro'],
        'wan-25-replicate' => ['name' => 'Wan 2.5', 'maker' => 'Alibaba', 'provider' => 'replicate', 'description' => 'Le même modèle via ton compte Replicate, avec audio généré.', 'endpoint' => 'wan-video/wan-2.5-t2v', 'rateMicros' => 100000, 'audio' => true, 'seconds' => [5, 10], 'ratios' => ['9:16', '16:9'], 'quality' => '720p'],
        'veo-31-fast-replicate' => ['name' => 'Veo 3.1 Fast', 'maker' => 'Google', 'provider' => 'replicate', 'description' => 'Plans avec sons, ambiances et dialogues synchronisés.', 'endpoint' => 'google/veo-3.1-fast', 'rateMicros' => 150000, 'audio' => true, 'seconds' => [4, 6, 8], 'ratios' => ['9:16', '16:9'], 'quality' => '720p'],
    ];

    /** @return array{name:string,maker:string,provider:string,description:string,endpoint:string,rateMicros:int,audio:bool,seconds:list<int>,ratios:list<string>,quality:string} */
    public function model(string $id): array
    {
        return self::MODELS[$id] ?? throw new \InvalidArgumentException('Choisis un modèle disponible.');
    }

    public function estimate(string $id, int $seconds): int
    {
        $model = $this->model($id);
        if (!in_array($seconds, $model['seconds'], true)) {
            throw new \InvalidArgumentException('Cette durée n’est pas disponible pour ce modèle.');
        }

        // Round up to the cent using integer microdollars, including fractional-cent rates.
        return intdiv($model['rateMicros'] * $seconds + 9999, 10000);
    }

    public function provider(string $id): string
    {
        return self::PROVIDERS[$id] ?? throw new \InvalidArgumentException('Choisis un fournisseur pris en charge.');
    }

    public function validate(string $id, int $seconds, string $ratio): void
    {
        $this->estimate($id, $seconds);
        if (!in_array($ratio, $this->model($id)['ratios'], true)) {
            throw new \InvalidArgumentException('Ce format n’est pas disponible pour ce modèle et ce fournisseur.');
        }
    }
}
