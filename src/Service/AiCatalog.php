<?php

namespace App\Service;

final class AiCatalog
{
    public const CHECKED = '2026-10-19';
    public const PROVIDERS = ['fal' => 'Fal', 'replicate' => 'Replicate', 'ltx' => 'LTX'];
    // IDs are durable execution choices: never rebind one to another provider/schema.
    public const MODELS = [
        'kling-25' => ['name' => 'Kling 2.5 Turbo Pro', 'maker' => 'Kling', 'provider' => 'fal', 'description' => 'Mouvements et plans cinématiques.', 'endpoint' => 'fal-ai/kling-video/v2.5-turbo/pro/text-to-video', 'rateMicros' => 70000, 'audio' => false, 'seconds' => [5, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => 'Pro'],
        'wan-25' => ['name' => 'Wan 2.5', 'maker' => 'Alibaba', 'provider' => 'fal', 'description' => 'Scènes créatives avec audio généré.', 'endpoint' => 'fal-ai/wan-25-preview/text-to-video', 'rateMicros' => 100000, 'audio' => true, 'seconds' => [5, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p'],
        'kling-3' => ['name' => 'Kling 3.0 Pro', 'maker' => 'Kling', 'provider' => 'fal', 'description' => 'Plans jusqu’à 15 secondes avec audio natif. Voix chinoises ou anglaises.', 'endpoint' => 'fal-ai/kling-video/v3/pro/text-to-video', 'rateMicros' => 168000, 'audio' => true, 'seconds' => [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => 'Pro'],
        'wan-25-replicate' => ['name' => 'Wan 2.5', 'maker' => 'Alibaba', 'provider' => 'replicate', 'description' => 'Le même modèle via ton compte Replicate, avec audio généré.', 'endpoint' => 'wan-video/wan-2.5-t2v', 'rateMicros' => 100000, 'audio' => true, 'seconds' => [5, 10], 'ratios' => ['9:16', '16:9'], 'quality' => '720p'],
        'veo-31-fast-replicate' => ['name' => 'Veo 3.1 Fast', 'maker' => 'Google', 'provider' => 'replicate', 'description' => 'Plans avec sons, ambiances et dialogues synchronisés.', 'endpoint' => 'google/veo-3.1-fast', 'rateMicros' => 150000, 'audio' => true, 'seconds' => [4, 6, 8], 'ratios' => ['9:16', '16:9'], 'quality' => '720p'],
        'pika-22' => ['name' => 'Pika 2.2', 'maker' => 'Pika', 'provider' => 'fal', 'description' => 'Plans créatifs courts. Première configuration à 720p, sans audio.', 'endpoint' => 'fal-ai/pika/v2.2/text-to-video', 'rateMicros' => 40000, 'audio' => false, 'seconds' => [5], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'input' => ['resolution' => '720p'], 'durationType' => 'integer'],
        'vidu-q1' => ['name' => 'Vidu Q1', 'maker' => 'Vidu', 'provider' => 'fal', 'description' => 'Un plan de 5 secondes à partir de ton texte, sans audio.', 'endpoint' => 'fal-ai/vidu/q1/text-to-video', 'rateMicros' => 80000, 'audio' => false, 'seconds' => [5], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => 'Q1', 'input' => ['style' => 'general', 'movement_amplitude' => 'auto'], 'durationType' => 'none'],
        'grok-video' => ['name' => 'Grok Imagine Video', 'maker' => 'xAI', 'provider' => 'fal', 'description' => 'Plans avec audio natif, de 1 à 15 secondes.', 'endpoint' => 'xai/grok-imagine-video/text-to-video', 'rateMicros' => 70000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'input' => ['resolution' => '720p'], 'durationType' => 'integer'],
        'hailuo-02' => ['name' => 'Hailuo 02 Standard', 'maker' => 'MiniMax', 'provider' => 'fal', 'description' => 'Plans sans audio. Cette configuration produit uniquement du paysage.', 'endpoint' => 'fal-ai/minimax/hailuo-02/standard/text-to-video', 'rateMicros' => 45000, 'audio' => false, 'seconds' => [6, 10], 'ratios' => ['16:9'], 'quality' => 'Standard', 'input' => ['prompt_optimizer' => false], 'omitRatio' => true],
        'pixverse-56' => ['name' => 'PixVerse 5.6', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Plans créatifs à 720p, sans audio. Prix par durée.', 'endpoint' => 'fal-ai/pixverse/v5.6/text-to-video', 'rateMicros' => 90000, 'audio' => false, 'seconds' => [5, 8, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'input' => ['resolution' => '720p', 'generate_audio_switch' => false, 'thinking_type' => 'disabled'], 'pricesCents' => [5 => 45, 8 => 90, 10 => 99], 'promptBytes' => 2048],
        'pixverse-6' => ['name' => 'PixVerse V6', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Plans avec sons et dialogues, jusqu’à 15 secondes.', 'endpoint' => 'fal-ai/pixverse/v6/text-to-video', 'rateMicros' => 60000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'input' => ['resolution' => '720p', 'generate_audio_switch' => true, 'generate_multi_clip_switch' => false, 'thinking_type' => 'disabled'], 'durationType' => 'integer', 'promptBytes' => 2048],
        'seedance-25' => ['name' => 'Seedance 2.5', 'maker' => 'ByteDance', 'provider' => 'fal', 'description' => 'Vidéo native jusqu’à 30 secondes avec audio. Estimation indicative : facturation aux tokens et accès fournisseur requis.', 'endpoint' => 'bytedance/seedance-2.5/text-to-video', 'rateMicros' => 473000, 'audio' => true, 'seconds' => [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30], 'ratios' => ['9:16', '16:9'], 'quality' => '720p', 'input' => ['resolution' => '720p', 'generate_audio' => true, 'draft' => false, 'codec' => 'H264', 'bitrate_mode' => 'standard']],
        'pika-22-1080' => ['name' => 'Pika 2.2 · 1080p', 'maker' => 'Pika', 'provider' => 'fal', 'description' => 'La configuration Full HD de Pika, sans audio.', 'endpoint' => 'fal-ai/pika/v2.2/text-to-video', 'rateMicros' => 90000, 'audio' => false, 'seconds' => [5], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'input' => ['resolution' => '1080p'], 'durationType' => 'integer'],
        'pixverse-6-1080' => ['name' => 'PixVerse V6 · 1080p', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Plans Full HD avec sons et dialogues.', 'endpoint' => 'fal-ai/pixverse/v6/text-to-video', 'rateMicros' => 115000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'input' => ['resolution' => '1080p', 'generate_audio_switch' => true, 'generate_multi_clip_switch' => false, 'thinking_type' => 'disabled'], 'durationType' => 'integer', 'promptBytes' => 2048],
        'grok-15-1080' => ['name' => 'Grok Imagine 1.5 · 1080p', 'maker' => 'xAI', 'provider' => 'fal', 'description' => 'Plans Full HD avec audio natif, jusqu’à 15 secondes.', 'endpoint' => 'xai/grok-imagine-video/v1.5/text-to-video', 'rateMicros' => 250000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'input' => ['resolution' => '1080p'], 'durationType' => 'integer'],
        'ltx-23-fast-720' => ['name' => 'LTX 2.3 Fast · 720p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Plans avec audio via ton compte LTX direct.', 'endpoint' => 'ltx-2-3-fast', 'rateMicros' => 30000, 'audio' => true, 'seconds' => [6, 8, 10], 'ratios' => ['9:16', '16:9'], 'quality' => '720p'],
        'ltx-23-fast-1080' => ['name' => 'LTX 2.3 Fast · 1080p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Plans Full HD avec audio via ton compte LTX.', 'endpoint' => 'ltx-2-3-fast', 'rateMicros' => 60000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9'], 'quality' => '1080p'],
        'ltx-23-fast-4k' => ['name' => 'LTX 2.3 Fast · 4K', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Plans 2160p avec audio via ton compte LTX.', 'endpoint' => 'ltx-2-3-fast', 'rateMicros' => 240000, 'audio' => true, 'seconds' => [6, 8, 10], 'ratios' => ['9:16', '16:9'], 'quality' => '4k'],
        'ltx-25-fast-720' => ['name' => 'LTX 2.5 Fast · 720p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Scènes avec audio natif, jusqu’à 20 secondes.', 'endpoint' => 'ltx-2-5-fast', 'rateMicros' => 90000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p'],
        'ltx-25-fast-1080' => ['name' => 'LTX 2.5 Fast · 1080p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Scènes Full HD avec audio natif, jusqu’à 20 secondes.', 'endpoint' => 'ltx-2-5-fast', 'rateMicros' => 130000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p'],
        'ltx-25-fast-4k' => ['name' => 'LTX 2.5 Fast · 4K', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Scènes 2160p avec audio natif.', 'endpoint' => 'ltx-2-5-fast', 'rateMicros' => 300000, 'audio' => true, 'seconds' => [6, 8, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '4k'],
    ];

    /** @return array{name:string,maker:string,provider:string,description:string,endpoint:string,rateMicros:int,audio:bool,seconds:list<int>,ratios:list<string>,quality:string,input?:array<string,string|bool>,durationType?:string,omitRatio?:bool,pricesCents?:array<int,int>,promptBytes?:int} */
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

        if (isset($model['pricesCents'])) {
            return $model['pricesCents'][$seconds] ?? throw new \InvalidArgumentException('Le prix de cette durée doit être vérifié.');
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

    public function validatePrompt(string $id, string $prompt): void
    {
        $model = $this->model($id);
        if (isset($model['promptBytes']) && strlen($prompt) > $model['promptBytes']) {
            throw new \InvalidArgumentException('Le texte dépasse la limite de '.$model['promptBytes'].' octets de ce modèle. Raccourcis-le : les accents et emojis occupent plusieurs octets.');
        }
    }

    /** @return list<int> */
    public function durations(): array
    {
        $seconds = array_unique(array_merge(...array_column(self::MODELS, 'seconds')));
        sort($seconds);

        return $seconds;
    }
}
