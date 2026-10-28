<?php

namespace App\Service;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\AiShot;

/** Guards unsent directions only. This is not a semantic or universal language certification. */
final class VideoPromptLanguage
{
    /** @return array{characters:string,style:string} */
    public function context(AiEpisode $episode): array
    {
        if (isset($episode->getFlowRecipe()['translationContext'])) {
            return $episode->getFlowRecipe()['translationContext'];
        }

        return $episode->isStandalone() ? ['characters' => '', 'style' => ''] : ['characters' => $episode->getSeries()->getCharacters(), 'style' => $episode->getSeries()->getStyle()];
    }

    public function assertScene(AiEpisode $episode, AiShot $shot, string $scene): void
    {
        $this->requireDirections($scene, $episode->getPrompt(), $this->context($episode));
        $identity = $shot->getInputs()['translatedIdentity'] ?? '';
        if ('' !== $identity) {
            $this->requireDirections($identity, $episode->getPrompt(), $this->context($episode));
            if (($shot->getInputs()['translationSource'] ?? '') !== StoryTextChecks::translationSource($episode->getPrompt(), $this->context($episode))) {
                throw new VideoPromptReviewRequired('Le contexte source a changé depuis sa traduction. Propose un nouveau scénario ou détache le contexte traduit pour reprendre manuellement. Tes textes sont conservés.');
            }
        }
    }

    /** @throws \RuntimeException */
    public function assertGeneration(AiGeneration $job, ?AiShot $shot): void
    {
        if ($shot) {
            $this->assertScene($shot->getEpisode(), $shot, $shot->getPrompt());
            $directions = preg_split('/\n(?:Visual identity\/style only, never replay context actions:|Visual identity and style only:|\nSHARED CONTEXT —)/u', $job->getPrompt(), 2)[0];
            if (str_contains($directions, "\nScene: ")) {
                $directions = explode("\nScene: ", $directions, 2)[1];
            }
            $this->requireDirections($directions, $shot->getEpisode()->getPrompt(), $this->context($shot->getEpisode()));
        } else {
            $this->requireDirections($job->getPrompt());
        }
    }

    /** @param list<string> $prompts */
    public function assertSpeech(AiEpisode $episode, array $prompts): void
    {
        if ($issue = (new StoryTextChecks())->speechIssue($episode->getPrompt(), $this->context($episode), $prompts)) {
            throw new VideoPromptReviewRequired($issue);
        }
    }

    /** @param array<string,mixed> $context */
    private function requireDirections(string $prompt, string $idea = '', array $context = []): void
    {
        try {
            (new StoryTextChecks())->requireEnglish($prompt, $idea, $context);
        } catch (\RuntimeException $failure) {
            throw new VideoPromptReviewRequired($failure->getMessage());
        }
    }
}
