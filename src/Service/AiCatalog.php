<?php

namespace App\Service;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

final class AiCatalog
{
    public function __construct(private ClockInterface $clock = new NativeClock('UTC'))
    {
    }

    public const CHECKED = '2026-10-19';
    public const PROVIDERS = ['fal' => 'Fal', 'replicate' => 'Replicate', 'ltx' => 'LTX'];
    // IDs are durable execution choices: never rebind one to another provider/schema.
    public const MODELS = [
        'seedance-25-us-image' => ['name' => 'Seedance 2.5 US · Image', 'maker' => 'ByteDance', 'provider' => 'fal', 'description' => 'Hébergé aux États-Unis. Image de départ, fin facultative et audio natif. Estimation indicative aux tokens ; dialogues français à tester.', 'endpoint' => 'bytedance/seedance-2.5/us/image-to-video', 'rateMicros' => 473000, 'audio' => true, 'seconds' => [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => false, 'checked' => '2026-10-26', 'endImageField' => 'end_image_url', 'input' => ['resolution' => '720p', 'aspect_ratio' => 'auto', 'generate_audio' => true, 'codec' => 'H264', 'bitrate_mode' => 'standard']],
        'ltx-23-extend' => ['name' => 'LTX 2.3 Pro · Extension vidéo', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Prolonge une vidéo existante avec son mouvement et son contexte audio.', 'endpoint' => 'ltx-2-3-pro', 'operation' => 'extend', 'rateMicros' => 100000, 'audio' => true, 'seconds' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20], 'ratios' => ['9:16', '16:9'], 'quality' => '1080p', 'checked' => '2026-10-24'],
        'vidu-q1-subject' => ['name' => 'Vidu Q1 · Référence de personnage', 'maker' => 'Vidu', 'provider' => 'fal', 'description' => 'Guide l’apparence du personnage sans imposer une image de début ou de fin. Scènes indépendantes, identité non garantie.', 'endpoint' => 'fal-ai/vidu/q1/reference-to-video', 'rateMicros' => 80000, 'audio' => false, 'seconds' => [5], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'subjectReference' => true, 'subjectLimit' => 7, 'durationType' => 'none', 'checked' => '2026-10-24', 'input' => ['bgm' => false, 'movement_amplitude' => 'auto']],
        'kling-25' => ['name' => 'Kling 2.5 Turbo Pro', 'maker' => 'Kling', 'provider' => 'fal', 'description' => 'Mouvements et plans cinématiques.', 'endpoint' => 'fal-ai/kling-video/v2.5-turbo/pro/text-to-video', 'rateMicros' => 70000, 'audio' => false, 'seconds' => [5, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p'],
        'wan-25' => ['name' => 'Wan 2.5', 'maker' => 'Alibaba', 'provider' => 'fal', 'description' => 'Scènes créatives avec audio généré.', 'endpoint' => 'fal-ai/wan-25-preview/text-to-video', 'rateMicros' => 100000, 'audio' => true, 'seconds' => [5, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p'],
        'kling-3' => ['name' => 'Kling 3.0 Pro', 'maker' => 'Kling', 'provider' => 'fal', 'description' => 'Plans jusqu’à 15 secondes avec audio natif. Voix chinoises ou anglaises.', 'endpoint' => 'fal-ai/kling-video/v3/pro/text-to-video', 'rateMicros' => 168000, 'audio' => true, 'seconds' => [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p'],
        'wan-25-replicate' => ['name' => 'Wan 2.5', 'maker' => 'Alibaba', 'provider' => 'replicate', 'description' => 'Le même modèle via ton compte Replicate, avec audio généré.', 'endpoint' => 'wan-video/wan-2.5-t2v', 'rateMicros' => 100000, 'audio' => true, 'seconds' => [5, 10], 'ratios' => ['9:16', '16:9'], 'quality' => '720p'],
        'veo-31-fast-replicate' => ['name' => 'Veo 3.1 Fast', 'maker' => 'Google', 'provider' => 'replicate', 'description' => 'Plans avec sons, ambiances et dialogues synchronisés.', 'endpoint' => 'google/veo-3.1-fast', 'rateMicros' => 150000, 'audio' => true, 'seconds' => [4, 6, 8], 'ratios' => ['9:16', '16:9'], 'quality' => '720p'],
        'pika-22' => ['name' => 'Pika 2.2', 'maker' => 'Pika', 'retireOn' => '2026-10-29', 'provider' => 'fal', 'description' => 'Plans créatifs courts. Première configuration à 720p, sans audio.', 'endpoint' => 'fal-ai/pika/v2.2/text-to-video', 'rateMicros' => 40000, 'audio' => false, 'seconds' => [5], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'input' => ['resolution' => '720p'], 'durationType' => 'integer'],
        'vidu-q1' => ['name' => 'Vidu Q1', 'maker' => 'Vidu', 'provider' => 'fal', 'description' => 'Un plan de 5 secondes à partir de ton texte, sans audio.', 'endpoint' => 'fal-ai/vidu/q1/text-to-video', 'rateMicros' => 80000, 'audio' => false, 'seconds' => [5], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'input' => ['style' => 'general', 'movement_amplitude' => 'auto'], 'durationType' => 'none'],
        'grok-video' => ['name' => 'Grok Imagine Video', 'maker' => 'xAI', 'provider' => 'fal', 'description' => 'Plans avec audio natif, de 1 à 15 secondes.', 'endpoint' => 'xai/grok-imagine-video/text-to-video', 'rateMicros' => 70000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'input' => ['resolution' => '720p'], 'durationType' => 'integer'],
        'hailuo-02' => ['name' => 'Hailuo 02 Standard', 'maker' => 'MiniMax', 'provider' => 'fal', 'description' => 'Plans sans audio. Cette configuration produit uniquement du paysage.', 'endpoint' => 'fal-ai/minimax/hailuo-02/standard/text-to-video', 'rateMicros' => 45000, 'audio' => false, 'seconds' => [6, 10], 'ratios' => ['16:9'], 'quality' => '768p', 'input' => ['prompt_optimizer' => false], 'omitRatio' => true],
        'pixverse-56' => ['name' => 'PixVerse 5.6', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Plans créatifs à 720p, sans audio. Prix par durée.', 'endpoint' => 'fal-ai/pixverse/v5.6/text-to-video', 'rateMicros' => 90000, 'audio' => false, 'seconds' => [5, 8, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'input' => ['resolution' => '720p', 'generate_audio_switch' => false, 'thinking_type' => 'disabled'], 'pricesCents' => [5 => 45, 8 => 90, 10 => 99], 'promptBytes' => 2048],
        'pixverse-6' => ['name' => 'PixVerse V6', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Plans avec sons et dialogues, jusqu’à 15 secondes.', 'endpoint' => 'fal-ai/pixverse/v6/text-to-video', 'rateMicros' => 60000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'input' => ['resolution' => '720p', 'generate_audio_switch' => true, 'generate_multi_clip_switch' => false, 'thinking_type' => 'disabled'], 'durationType' => 'integer', 'promptBytes' => 2048],
        'seedance-25' => ['name' => 'Seedance 2.5', 'maker' => 'ByteDance', 'provider' => 'fal', 'description' => 'Vidéo native jusqu’à 30 secondes avec audio. Estimation indicative : facturation aux tokens et accès fournisseur requis.', 'endpoint' => 'bytedance/seedance-2.5/text-to-video', 'rateMicros' => 473000, 'audio' => true, 'seconds' => [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30], 'ratios' => ['9:16', '16:9'], 'quality' => '720p', 'input' => ['resolution' => '720p', 'generate_audio' => true, 'draft' => false, 'codec' => 'H264', 'bitrate_mode' => 'standard']],
        'pika-22-1080' => ['name' => 'Pika 2.2 · 1080p', 'maker' => 'Pika', 'retireOn' => '2026-10-29', 'provider' => 'fal', 'description' => 'La configuration Full HD de Pika, sans audio.', 'endpoint' => 'fal-ai/pika/v2.2/text-to-video', 'rateMicros' => 90000, 'audio' => false, 'seconds' => [5], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'input' => ['resolution' => '1080p'], 'durationType' => 'integer'],
        'pixverse-6-1080' => ['name' => 'PixVerse V6 · 1080p', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Plans Full HD avec sons et dialogues.', 'endpoint' => 'fal-ai/pixverse/v6/text-to-video', 'rateMicros' => 115000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'input' => ['resolution' => '1080p', 'generate_audio_switch' => true, 'generate_multi_clip_switch' => false, 'thinking_type' => 'disabled'], 'durationType' => 'integer', 'promptBytes' => 2048],
        'grok-15-1080' => ['name' => 'Grok Imagine 1.5 · 1080p', 'maker' => 'xAI', 'provider' => 'fal', 'description' => 'Plans Full HD avec audio natif, jusqu’à 15 secondes.', 'endpoint' => 'xai/grok-imagine-video/v1.5/text-to-video', 'rateMicros' => 250000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'input' => ['resolution' => '1080p'], 'durationType' => 'integer'],
        'ltx-23-fast-720' => ['name' => 'LTX 2.3 Fast · 720p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Plans avec audio via ton compte LTX direct.', 'endpoint' => 'ltx-2-3-fast', 'rateMicros' => 30000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9'], 'quality' => '720p'],
        'ltx-23-fast-1080' => ['name' => 'LTX 2.3 Fast · 1080p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Plans Full HD avec audio via ton compte LTX.', 'endpoint' => 'ltx-2-3-fast', 'rateMicros' => 60000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9'], 'quality' => '1080p'],
        'ltx-23-fast-4k' => ['name' => 'LTX 2.3 Fast · 4K', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Plans 2160p avec audio via ton compte LTX.', 'endpoint' => 'ltx-2-3-fast', 'rateMicros' => 240000, 'audio' => true, 'seconds' => [6, 8, 10], 'ratios' => ['9:16', '16:9'], 'quality' => '4k'],
        'ltx-25-fast-720' => ['name' => 'LTX 2.5 Fast · 720p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Scènes avec audio natif, jusqu’à 20 secondes.', 'endpoint' => 'ltx-2-5-fast', 'rateMicros' => 90000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p'],
        'ltx-25-fast-1080' => ['name' => 'LTX 2.5 Fast · 1080p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Scènes Full HD avec audio natif, jusqu’à 20 secondes.', 'endpoint' => 'ltx-2-5-fast', 'rateMicros' => 130000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p'],
        'ltx-25-fast-4k' => ['name' => 'LTX 2.5 Fast · 4K', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Scènes 2160p avec audio natif.', 'endpoint' => 'ltx-2-5-fast', 'rateMicros' => 300000, 'audio' => true, 'seconds' => [6, 8, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '4k'],
        'kling-25-image' => ['name' => 'Kling 2.5 Turbo Pro · Image', 'maker' => 'Kling', 'provider' => 'fal', 'description' => 'Anime ton image de départ. Ajoute une image de départ et, si tu veux, une image de fin.', 'endpoint' => 'fal-ai/kling-video/v2.5-turbo/pro/image-to-video', 'rateMicros' => 70000, 'audio' => false, 'seconds' => [5, 10], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'endImageField' => 'tail_image_url', 'omitRatio' => true, 'checked' => '2026-10-20'],
        'kling-3-image' => ['name' => 'Kling 3.0 Pro · Image', 'maker' => 'Kling', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/kling-video/v3/pro/image-to-video', 'rateMicros' => 168000, 'audio' => true, 'seconds' => [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'subjectLimit' => 2, 'subjectViewsMin' => 1, 'subjectViewsMax' => 3, 'omitRatio' => true, 'checked' => '2026-10-20', 'imageField' => 'start_image_url', 'endImageField' => 'end_image_url', 'input' => ['generate_audio' => true, 'shot_type' => 'customize']],
        'wan-25-image-720p' => ['name' => 'Wan 2.5 · Image · 720p', 'maker' => 'Alibaba', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/wan-25-preview/image-to-video', 'rateMicros' => 100000, 'audio' => true, 'seconds' => [5, 10], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'input' => ['resolution' => '720p', 'enable_prompt_expansion' => false]],
        'wan-25-image-1080p' => ['name' => 'Wan 2.5 · Image · 1080p', 'maker' => 'Alibaba', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/wan-25-preview/image-to-video', 'rateMicros' => 150000, 'audio' => true, 'seconds' => [5, 10], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'input' => ['resolution' => '1080p', 'enable_prompt_expansion' => false]],
        'pika-22-image-720p' => ['name' => 'Pika 2.2 · Image · 720p', 'maker' => 'Pika', 'retireOn' => '2026-10-29', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pika/v2.2/image-to-video', 'rateMicros' => 40000, 'audio' => false, 'seconds' => [5], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'durationType' => 'integer', 'omitSafety' => true, 'input' => ['resolution' => '720p']],
        'pika-22-image-1080p' => ['name' => 'Pika 2.2 · Image · 1080p', 'maker' => 'Pika', 'retireOn' => '2026-10-29', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pika/v2.2/image-to-video', 'rateMicros' => 90000, 'audio' => false, 'seconds' => [5], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'durationType' => 'integer', 'omitSafety' => true, 'input' => ['resolution' => '1080p']],
        'pika-frames-720p' => ['name' => 'Pika 2.2 · Début + fin · 720p', 'maker' => 'Pika', 'retireOn' => '2026-10-29', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pika/v2.2/pikaframes', 'rateMicros' => 40000, 'audio' => false, 'seconds' => [5, 10, 15, 20, 25], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'imageField' => 'image_urls', 'endImageField' => 'image_urls', 'requiresEnd' => true, 'durationType' => 'none', 'omitSafety' => true, 'input' => ['resolution' => '720p']],
        'pika-frames-1080p' => ['name' => 'Pika 2.2 · Début + fin · 1080p', 'maker' => 'Pika', 'retireOn' => '2026-10-29', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pika/v2.2/pikaframes', 'rateMicros' => 60000, 'audio' => false, 'seconds' => [5, 10, 15, 20, 25], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'imageField' => 'image_urls', 'endImageField' => 'image_urls', 'requiresEnd' => true, 'durationType' => 'none', 'omitSafety' => true, 'input' => ['resolution' => '1080p']],
        'vidu-q1-image' => ['name' => 'Vidu Q1 · Image', 'maker' => 'Vidu', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/vidu/q1/image-to-video', 'rateMicros' => 80000, 'audio' => false, 'seconds' => [5], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'durationType' => 'none', 'omitSafety' => true, 'input' => ['movement_amplitude' => 'auto']],
        'vidu-q1-frames' => ['name' => 'Vidu Q1 · Début + fin', 'maker' => 'Vidu', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/vidu/q1/start-end-to-video', 'rateMicros' => 100000, 'audio' => false, 'seconds' => [4], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'durationType' => 'none', 'omitSafety' => true, 'imageField' => 'start_image_url', 'endImageField' => 'end_image_url', 'requiresEnd' => true, 'input' => ['movement_amplitude' => 'auto']],
        'grok-image-720' => ['name' => 'Grok Imagine Video · Image', 'maker' => 'xAI', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'xai/grok-imagine-video/image-to-video', 'rateMicros' => 70000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'durationType' => 'integer', 'fixedMicros' => 2000, 'input' => ['resolution' => '720p']],
        'grok-15-image-720p' => ['name' => 'Grok Imagine 1.5 · Image · 720p', 'maker' => 'xAI', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'xai/grok-imagine-video/v1.5/image-to-video', 'rateMicros' => 140000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'durationType' => 'integer', 'fixedMicros' => 10000, 'input' => ['resolution' => '720p']],
        'grok-15-image-1080p' => ['name' => 'Grok Imagine 1.5 · Image · 1080p', 'maker' => 'xAI', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'xai/grok-imagine-video/v1.5/image-to-video', 'rateMicros' => 250000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'durationType' => 'integer', 'fixedMicros' => 10000, 'input' => ['resolution' => '1080p']],
        'hailuo-02-image' => ['name' => 'Hailuo 02 Standard · Image', 'maker' => 'MiniMax', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/minimax/hailuo-02/standard/image-to-video', 'rateMicros' => 45000, 'audio' => false, 'seconds' => [6, 10], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '768p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'endImageField' => 'end_image_url', 'input' => ['resolution' => '768P', 'prompt_optimizer' => false]],
        'pixverse-56-image-720p' => ['name' => 'PixVerse 5.6 · Image · 720p', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pixverse/v5.6/image-to-video', 'rateMicros' => 90000, 'audio' => false, 'seconds' => [5, 8, 10], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'input' => ['resolution' => '720p', 'generate_audio_switch' => false, 'thinking_type' => 'disabled'], 'pricesCents' => [5 => 45, 8 => 90, 10 => 99], 'promptBytes' => 2048],
        'pixverse-56-image-1080p' => ['name' => 'PixVerse 5.6 · Image · 1080p', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pixverse/v5.6/image-to-video', 'rateMicros' => 150000, 'audio' => false, 'seconds' => [5, 8], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'input' => ['resolution' => '1080p', 'generate_audio_switch' => false, 'thinking_type' => 'disabled'], 'pricesCents' => [5 => 75, 8 => 150], 'promptBytes' => 2048],
        'pixverse-56-frames-720p' => ['name' => 'PixVerse 5.6 · Début + fin · 720p', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pixverse/v5.6/transition', 'rateMicros' => 90000, 'audio' => false, 'seconds' => [5, 8, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => false, 'checked' => '2026-10-20', 'imageField' => 'first_image_url', 'endImageField' => 'end_image_url', 'requiresEnd' => true, 'input' => ['resolution' => '720p', 'generate_audio_switch' => false, 'thinking_type' => 'disabled'], 'pricesCents' => [5 => 45, 8 => 90, 10 => 99], 'promptBytes' => 2048],
        'pixverse-56-frames-1080p' => ['name' => 'PixVerse 5.6 · Début + fin · 1080p', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pixverse/v5.6/transition', 'rateMicros' => 150000, 'audio' => false, 'seconds' => [5, 8], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => false, 'checked' => '2026-10-20', 'imageField' => 'first_image_url', 'endImageField' => 'end_image_url', 'requiresEnd' => true, 'input' => ['resolution' => '1080p', 'generate_audio_switch' => false, 'thinking_type' => 'disabled'], 'pricesCents' => [5 => 75, 8 => 150], 'promptBytes' => 2048],
        'pixverse-6-image-720p' => ['name' => 'PixVerse V6 · Image · 720p', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pixverse/v6/image-to-video', 'rateMicros' => 60000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'input' => ['resolution' => '720p', 'generate_audio_switch' => true, 'generate_multi_clip_switch' => false, 'thinking_type' => 'disabled'], 'durationType' => 'integer', 'promptBytes' => 2048],
        'pixverse-6-image-1080p' => ['name' => 'PixVerse V6 · Image · 1080p', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pixverse/v6/image-to-video', 'rateMicros' => 115000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'input' => ['resolution' => '1080p', 'generate_audio_switch' => true, 'generate_multi_clip_switch' => false, 'thinking_type' => 'disabled'], 'durationType' => 'integer', 'promptBytes' => 2048],
        'pixverse-6-frames-720p' => ['name' => 'PixVerse V6 · Début + fin · 720p', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pixverse/v6/transition', 'rateMicros' => 60000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => false, 'checked' => '2026-10-20', 'imageField' => 'first_image_url', 'endImageField' => 'end_image_url', 'requiresEnd' => true, 'input' => ['resolution' => '720p', 'generate_audio_switch' => true, 'generate_multi_clip_switch' => false, 'thinking_type' => 'disabled'], 'durationType' => 'integer', 'promptBytes' => 2048],
        'pixverse-6-frames-1080p' => ['name' => 'PixVerse V6 · Début + fin · 1080p', 'maker' => 'PixVerse', 'provider' => 'fal', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'fal-ai/pixverse/v6/transition', 'rateMicros' => 115000, 'audio' => true, 'seconds' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => false, 'checked' => '2026-10-20', 'imageField' => 'first_image_url', 'endImageField' => 'end_image_url', 'requiresEnd' => true, 'input' => ['resolution' => '1080p', 'generate_audio_switch' => true, 'generate_multi_clip_switch' => false, 'thinking_type' => 'disabled'], 'durationType' => 'integer', 'promptBytes' => 2048],
        'seedance-25-image' => ['name' => 'Seedance 2.5 · Image', 'maker' => 'ByteDance', 'provider' => 'fal', 'description' => 'Image de départ, fin facultative et audio jusqu’à 30 secondes. Tarif indicatif aux tokens.', 'endpoint' => 'bytedance/seedance-2.5/image-to-video', 'rateMicros' => 473000, 'audio' => true, 'seconds' => [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'endImageField' => 'end_image_url', 'input' => ['resolution' => '720p', 'aspect_ratio' => 'auto', 'generate_audio' => true, 'draft' => false, 'codec' => 'H264', 'bitrate_mode' => 'standard']],
        'ltx-23-fast-720p-image' => ['name' => 'LTX 2.3 Fast · Image · 720p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'ltx-2-3-fast', 'rateMicros' => 30000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'endImageField' => 'last_frame_uri'],
        'ltx-23-fast-1080p-image' => ['name' => 'LTX 2.3 Fast · Image · 1080p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'ltx-2-3-fast', 'rateMicros' => 60000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'endImageField' => 'last_frame_uri'],
        'ltx-23-fast-4k-image' => ['name' => 'LTX 2.3 Fast · Image · 4K', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'ltx-2-3-fast', 'rateMicros' => 240000, 'audio' => true, 'seconds' => [6, 8, 10], 'ratios' => ['9:16', '16:9'], 'quality' => '4k', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'endImageField' => 'last_frame_uri'],
        'ltx-25-fast-720p-image' => ['name' => 'LTX 2.5 Fast · Image · 720p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'ltx-2-5-fast', 'rateMicros' => 90000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'endImageField' => 'last_frame_uri'],
        'ltx-25-fast-1080p-image' => ['name' => 'LTX 2.5 Fast · Image · 1080p', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'ltx-2-5-fast', 'rateMicros' => 130000, 'audio' => true, 'seconds' => [6, 8, 10, 12, 14, 16, 18, 20], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'endImageField' => 'last_frame_uri'],
        'ltx-25-fast-4k-image' => ['name' => 'LTX 2.5 Fast · Image · 4K', 'maker' => 'Lightricks', 'provider' => 'ltx', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'ltx-2-5-fast', 'rateMicros' => 300000, 'audio' => true, 'seconds' => [6, 8, 10], 'ratios' => ['9:16', '16:9', '1:1'], 'quality' => '4k', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'endImageField' => 'last_frame_uri'],
        'wan-image-replicate-720p' => ['name' => 'Wan 2.5 · Image · 720p', 'maker' => 'Alibaba', 'provider' => 'replicate', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'wan-video/wan-2.5-i2v', 'rateMicros' => 100000, 'audio' => true, 'seconds' => [5, 10], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'imageField' => 'image', 'input' => ['resolution' => '720p', 'enable_prompt_expansion' => false]],
        'wan-image-replicate-1080p' => ['name' => 'Wan 2.5 · Image · 1080p', 'maker' => 'Alibaba', 'provider' => 'replicate', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'wan-video/wan-2.5-i2v', 'rateMicros' => 150000, 'audio' => true, 'seconds' => [5, 10], 'ratios' => ['auto', '9:16', '16:9', '1:1'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'imageField' => 'image', 'input' => ['resolution' => '1080p', 'enable_prompt_expansion' => false]],
        'veo-image-replicate-720p' => ['name' => 'Veo 3.1 Fast · Image · 720p', 'maker' => 'Google', 'provider' => 'replicate', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'google/veo-3.1-fast', 'rateMicros' => 150000, 'audio' => true, 'seconds' => [4, 6, 8], 'ratios' => ['9:16', '16:9'], 'quality' => '720p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'imageField' => 'image', 'endImageField' => 'last_frame', 'input' => ['resolution' => '720p', 'generate_audio' => true]],
        'veo-image-replicate-1080p' => ['name' => 'Veo 3.1 Fast · Image · 1080p', 'maker' => 'Google', 'provider' => 'replicate', 'description' => 'Anime une image privée. Le cadrage est vérifié avant confirmation.', 'endpoint' => 'google/veo-3.1-fast', 'rateMicros' => 150000, 'audio' => true, 'seconds' => [4, 6, 8], 'ratios' => ['9:16', '16:9'], 'quality' => '1080p', 'image' => true, 'omitRatio' => true, 'checked' => '2026-10-20', 'imageField' => 'image', 'endImageField' => 'last_frame', 'input' => ['resolution' => '1080p', 'generate_audio' => true]],
    ];

    /** @return array{name:string,maker:string,provider:string,description:string,endpoint:string,rateMicros:int,audio:bool,seconds:list<int>,ratios:list<string>,quality:string,input?:array<string,string|bool>,durationType?:string,omitRatio?:bool,pricesCents?:array<int,int>,promptBytes?:int,image?:bool,subjectReference?:bool,subjectLimit?:int,subjectViewsMin?:int,subjectViewsMax?:int,retireOn?:string,checked?:string,imageField?:string,endImageField?:string,requiresEnd?:bool,omitSafety?:bool,fixedMicros?:int,operation?:string} */
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
        return intdiv($model['rateMicros'] * $seconds + ($model['fixedMicros'] ?? 0) + 9999, 10000);
    }

    public function provider(string $id): string
    {
        return self::PROVIDERS[$id] ?? throw new \InvalidArgumentException('Choisis un fournisseur pris en charge.');
    }

    public function validate(string $id, int $seconds, string $ratio): void
    {
        $this->assertAvailable($id);
        $this->estimate($id, $seconds);
        if (!in_array($ratio, $this->model($id)['ratios'], true)) {
            throw new \InvalidArgumentException('Ce format n’est pas disponible pour ce modèle et ce fournisseur.');
        }
    }

    public function referenceMode(string $id): string
    {
        $model = $this->model($id);

        return ($model['subjectReference'] ?? false) ? 'subject' : (($model['image'] ?? false) ? 'image' : 'text');
    }

    public function subjectLimit(string $id): int
    {
        $model = $this->model($id);

        return $model['subjectLimit'] ?? 0;
    }

    public function contract(string $id): string
    {
        return hash('sha256', serialize($this->model($id)));
    }

    public function assertAvailable(string $id, ?\DateTimeImmutable $now = null): void
    {
        $retireOn = $this->model($id)['retireOn'] ?? null;
        if ($retireOn && ($now ?? $this->clock->now()) >= new \DateTimeImmutable($retireOn.'T00:00:00Z')) {
            throw new \RuntimeException('Ce modèle a été retiré par son fournisseur. Choisis explicitement un autre modèle et vérifie un nouveau devis. Les résultats existants restent disponibles.');
        }
    }

    public function validateReferences(string $id, ?string $reference, ?string $endReference): void
    {
        $model = $this->model($id);
        if (!($model['image'] ?? false) && ($reference || $endReference)) {
            throw new \RuntimeException('Ce modèle texte n’accepte pas de cadres de référence. Retire-les ou choisis un modèle compatible.');
        }
        if (($model['image'] ?? false) && !$reference) {
            throw new \RuntimeException('Ajoute une image dans la configuration avant de préparer ou générer cette vidéo.');
        }
        if (($model['requiresEnd'] ?? false) && !$endReference) {
            throw new \RuntimeException('Ajoute l’image de fin requise dans la configuration avant de continuer.');
        }
        if ($endReference && !isset($model['endImageField'])) {
            throw new \RuntimeException('Ce modèle ne prend pas d’image de fin.');
        }
    }

    public function validatePrompt(string $id, string $prompt): void
    {
        $model = $this->model($id);
        $characters = $this->promptCharacters($id);
        if (mb_strlen($prompt) > $characters) {
            throw new \InvalidArgumentException('Le texte final dépasse la limite de '.$characters.' caractères de ce modèle. Raccourcis la scène ou son contexte avant de confirmer.');
        }
        if (isset($model['promptBytes']) && strlen($prompt) > $model['promptBytes']) {
            throw new \InvalidArgumentException('Le texte dépasse la limite de '.$model['promptBytes'].' octets de ce modèle. Raccourcis-le : les accents et emojis occupent plusieurs octets.');
        }
    }

    public function promptCharacters(string $id): int
    {
        $endpoint = $this->model($id)['endpoint'];

        return match (true) {
            str_starts_with($endpoint, 'fal-ai/kling-video/v2.5-turbo/'), str_starts_with($endpoint, 'fal-ai/kling-video/v3/') => 2500,
            str_starts_with($endpoint, 'fal-ai/vidu/q1/') => 1500,
            str_starts_with($endpoint, 'xai/grok-imagine-video/') => 4096,
            'fal-ai/minimax/hailuo-02/standard/image-to-video' === $endpoint => 2000,
            default => 5000, // Application ceiling when no tighter endpoint limit is recorded.
        };
    }

    /** @return list<int> */
    public function durations(): array
    {
        $seconds = array_unique(array_merge(...array_column(self::MODELS, 'seconds')));
        sort($seconds);

        return $seconds;
    }

    /** @return array<string,array{name:string,variants:list<string>}> */
    public function groups(): array
    {
        $groups = [];
        $keys = [];
        foreach (self::MODELS as $id => $model) {
            if (isset($model['operation'])) {
                continue;
            }
            $key = $model['provider'].'/'.$model['endpoint'].'/'.(($model['image'] ?? false) ? 'image' : 'text');
            $first = $keys[$key] ??= $id;
            $groups[$first] ??= ['name' => preg_replace('/ · (720p|1080p|4K)$/i', '', $model['name']), 'variants' => []];
            $groups[$first]['variants'][] = $id;
        }

        return $groups;
    }
}
