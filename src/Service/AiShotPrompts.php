<?php

namespace App\Service;

final class AiShotPrompts
{
    public const VERSION = '2026-10-25.2';

    public function __construct(private AiCatalog $catalog)
    {
    }

    /** @param list<array{name:string,role:string,presence:string,reference?:string,views?:list<string>}> $subjectDetails */
    public function build(string $model, int $seconds, string $scene, string $context, string $continuity, int $subjects = 0, array $subjectDetails = []): string
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
        if ('kling-3-image' === $model && $subjects && !$subjectDetails) {
            $guide .= ' Use '.implode(' and ', array_map(static fn (int $n): string => '@Element'.$n, range(1, $subjects))).' for the corresponding subject identities.';
        }
        foreach ($subjectDetails as $index => $detail) {
            if ('subject' === $role) {
                $guide .= ' Reference'.($index + 1).'='.json_encode($detail['name'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).'('.$detail['role'].','.$detail['presence'].').';
                continue;
            }
            $token = 'kling-3-image' === $model ? '@Element'.($index + 1) : 'Reference image '.($index + 1);
            $guide .= ' '.$token.' identifies the '.$detail['role'].' named '.json_encode($detail['name'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).'.';
            $guide .= match ($detail['presence']) {
                'visible' => 'subject' === $role ? ' This subject is visible in the scene; keep its identity consistent.' : ' This subject is already visible in the supplied starting frame; preserve it.',
                'enters' => 'subject' === $role ? ' This subject enters during the action.' : ' Do not add this subject to the starting frame: it enters during the action.',
                default => ' Its appearance is guided by the reference; its participation follows the scene.',
            };
        }
        $prefix = $seconds.' seconds. '.$rule.' '.$guide."\nScene: ";
        $prompt = $prefix.trim($scene);
        $config = $this->catalog->model($model);
        $bytes = isset($config['promptBytes']);
        $limit = $bytes ? $config['promptBytes'] : $this->catalog->promptCharacters($model);
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

    /**
     * @param list<array{name:string,role:string,presence:string}> $subjectDetails
     *
     * @return array{model:string,referenceRole:string,sceneCharacters:int,visualInput:bool,subjectCount:int,subjects:list<array{name:string,role:string,presence:string}>}
     */
    public function writingContext(string $model, array $subjectDetails = []): array
    {
        $config = $this->catalog->model($model);
        $hints = array_map(static fn (array $detail): array => ['name' => $detail['name'], 'role' => $detail['role'], 'presence' => $detail['presence']], $subjectDetails);
        $instructions = $this->build($model, $config['seconds'][0], '', '', 'independent', count($hints), $hints);
        // The writer adds up to 300 characters of translated identity to each scene.
        $sceneLimit = max(200, min(650, $this->catalog->promptCharacters($model) - mb_strlen($instructions) - 352));

        return ['model' => $config['name'], 'referenceRole' => $this->catalog->referenceMode($model), 'sceneCharacters' => $sceneLimit, 'visualInput' => false, 'subjectCount' => count($hints), 'subjects' => $hints];
    }
}
