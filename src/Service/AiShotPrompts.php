<?php

namespace App\Service;

final class AiShotPrompts
{
    public const VERSION = '2026-10-25.1';

    public function __construct(private AiCatalog $catalog)
    {
    }

    public function build(string $model, int $seconds, string $scene, string $context, string $continuity, int $subjects = 0): string
    {
        $rule = match ($continuity) {
            'single' => 'One continuous take. No cuts, montage or scene change. Finish one simple action smoothly.',
            'linked' => 'Continue the action from the supplied first frame. No reset, replay or invented transition. Preserve direction and setting.',
            default => 'One scene only. Complete one simple action before the final second, then hold its visible result.',
        };
        $role = $this->catalog->referenceMode($model);
        $guide = match ($role) {
            'subject' => 'Reference images guide identity only; do not recreate their framing or invent accessories.',
            'image' => 'Begin at the supplied first frame. Keep the visible subject consistent. Respect the end frame when supplied.',
            default => 'Keep the supplied visual identity and style consistent.',
        };
        if ('kling-3-image' === $model && $subjects) {
            $guide .= ' Use '.implode(' and ', array_map(static fn (int $n): string => '@Element'.$n, range(1, $subjects))).' for the corresponding subject identities.';
        }
        $prefix = $seconds.' seconds. '.$rule.' '.$guide."\nScene: ";
        $prompt = $prefix.trim($scene);
        $config = $this->catalog->model($model);
        $bytes = isset($config['promptBytes']);
        $limit = $bytes ? $config['promptBytes'] : ('subject' === $role ? 1500 : 5000);
        $length = $bytes ? strlen($prompt) : mb_strlen($prompt);
        if ($length > $limit) {
            throw new \RuntimeException('Le prompt du plan dépasse la place disponible avec les consignes de ce modèle. Raccourcis la scène avant de confirmer.');
        }
        $separator = "\nVisual identity/style only, never replay context actions: ";
        $available = min(500, $limit - $length - strlen($separator));
        if ($available > 0 && '' !== trim($context)) {
            $excerpt = $bytes ? mb_strcut(trim($context), 0, $available, 'UTF-8') : mb_substr(trim($context), 0, $available);
            $prompt .= $separator.$excerpt;
        }
        $this->catalog->validatePrompt($model, $prompt);

        return $prompt;
    }

    /** @return array{model:string,referenceRole:string,sceneCharacters:int,visualInput:bool} */
    public function writingContext(string $model): array
    {
        $config = $this->catalog->model($model);

        return ['model' => $config['name'], 'referenceRole' => $this->catalog->referenceMode($model), 'sceneCharacters' => 650, 'visualInput' => false];
    }
}
