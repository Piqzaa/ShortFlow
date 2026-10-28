<?php

namespace App\Service;

/** Finite, conservative text checks. Neither narrative truth nor rendered speech is certified. */
final class StoryTextChecks
{
    private const FRENCH = ['avec', 'dans', 'une', 'sur', 'ses', 'son', 'puis', 'les', 'des', 'est', 'le', 'la', 'un', 'du', 'au', 'aux', 'et', 'vers', 'en', 'regarde', 'sourit', 'ouvre', 'lèvres', 'pendant', 'devant', 'derrière', 'reste', 'tandis'];
    private const ENGLISH = ['the', 'with', 'then', 'into', 'while', 'his', 'her', 'their', 'a', 'an', 'on', 'in', 'of', 'to', 'is', 'camera', 'shot', 'scene', 'close', 'wide', 'static', 'slowly', 'walks', 'moves', 'follows', 'holds', 'cat'];
    private const SPEECH_CUE = 'dit|disant|répond|répondant|prononce|parle|parlant|répliques?|dialogues?|paroles?|says?|speaks?|replies|speaking|dialogue|voiceover|voix off|narrateur|narration';

    /** @param array<string,mixed> $context */
    public static function translationSource(string $idea, array $context = []): string
    {
        return hash('sha256', trim($idea)."\n".trim($context['characters'] ?? '')."\n".trim($context['style'] ?? ''));
    }

    /** @param array<string,mixed> $context */
    public function directionIssue(string $prompt, string $idea = '', array $context = []): ?string
    {
        $directions = $this->withoutAllowedQuotes($prompt, array_column($this->quotes($this->source($idea, $context)), 'text'));
        $french = $this->markers($directions, [...self::FRENCH, 'blanc', 'blanche', 'jaune', 'mûre', 'courbée', 'peau', 'debout']);
        $english = $this->markers($directions, [...self::ENGLISH, 'and', 'are', 'yellow', 'red', 'white', 'skin', 'characters', 'wearing', 'named', 'background', 'light', 'door', 'banana', 'strawberry']);
        if ($french >= 3 || ($french >= 2 && $english < 2)) {
            return 'Traduction à corriger : des consignes vidéo semblent encore en français. Corrige le prompt et son identité en anglais, en conservant les citations demandées. Aucun nouvel envoi vidéo ne sera effectué.';
        }
        if ('' === trim($directions) || ($english < 2 && preg_match_all('/\p{L}+/u', $directions) >= 8)) {
            return 'Traduction à vérifier : la langue des consignes vidéo est indéterminée. Précise les directions en anglais, sans traduire les citations demandées. Aucun nouvel envoi vidéo ne sera effectué.';
        }

        return null;
    }

    /** @param array<string,mixed> $context
     * @param list<string> $prompts
     */
    public function speechIssue(string $idea, array $context, array $prompts): ?string
    {
        $proposal = ['summary' => '', 'shots' => array_map(static fn (string $prompt): array => ['prompt' => $prompt, 'description' => '', 'seconds' => 1], $prompts)];
        $warnings = $this->review($idea, $context, $proposal);
        if (isset($warnings['missingSpeech']) || isset($warnings['repeatedSpeech']) || isset($warnings['speechOrder'])) {
            return 'Répliques à corriger : les citations demandées sont absentes, modifiées, répétées ou déplacées. Corrige les prompts avant de confirmer la vidéo. Aucun nouvel envoi ne sera effectué.';
        }

        return null;
    }

    /** @param array<string,mixed> $context */
    public function requireEnglish(string $prompt, string $idea = '', array $context = []): void
    {
        if ($issue = $this->directionIssue($prompt, $idea, $context)) {
            throw new \RuntimeException($issue);
        }
    }

    /** @param array<string,mixed> $context
     * @return list<string>
     */
    public function speechLines(string $idea, array $context = []): array
    {
        $source = $this->source($idea, $context);
        $lines = [];
        foreach ($this->quotes($source) as $quote) {
            $prefix = mb_substr(substr($source, 0, $quote['start']), -180);
            $prefix = $this->withoutNegatedSpeech($prefix);
            if (preg_match('/\b(?:'.self::SPEECH_CUE.')\b[^.!?«»“”"]{0,100}$/ui', $prefix)) {
                $lines[] = $quote['text'];
            }
        }

        return $lines;
    }

    /** @param array<string,mixed> $context
     * @param array{summary:string,shots:list<array{description:string,prompt:string,seconds:int}>} $proposal
     *
     * @return array<string,string>
     */
    public function review(string $idea, array $context, array $proposal): array
    {
        $source = $this->source($idea, $context);
        $allowed = array_column($this->quotes($source), 'text');
        $warnings = [];
        $prompts = array_column($proposal['shots'], 'prompt');
        foreach ($prompts as $prompt) {
            $directions = $this->withoutAllowedQuotes($prompt, $allowed);
            if ($this->markers($directions, self::FRENCH) >= 3) {
                $warnings['prompts'] = 'Certains prompts semblent contenir des consignes en français. Vérifie leur traduction en anglais, sans traduire les citations explicitement demandées.';
            } elseif ('' === trim($directions) || ($this->markers($directions, self::ENGLISH) < 2 && preg_match_all('/\p{L}+/u', $directions) >= 8)) {
                $warnings['promptLanguageUnknown'] = 'La langue de certaines consignes vidéo est difficile à identifier. Vérifie les prompts ; ce contrôle ne confirme pas leur traduction.';
            }
        }
        foreach ([$proposal['summary'], ...array_column($proposal['shots'], 'description')] as $text) {
            $narration = $this->withoutAllowedQuotes($text, $allowed);
            if ($this->markers($narration, ['the', 'with', 'then', 'into', 'while', 'his', 'her', 'their']) >= 3) {
                $warnings['story'] = 'Une partie du scénario semble être en anglais. Vérifie le texte français, sans traduire les paroles demandées dans une autre langue.';
            }
        }

        $lines = $this->speechLines($idea, $context);
        $spoken = [];
        foreach ($prompts as $prompt) {
            array_push($spoken, ...array_column($this->quotes($prompt), 'text'));
            foreach ($this->speechLines($prompt) as $line) {
                if (!in_array($line, $lines, true)) {
                    $warnings['inventedSpeech'] = 'Un prompt introduit une réplique qui ne correspond pas aux paroles demandées. Vérifie les mots et leur attribution avant génération.';
                }
            }
        }
        if ($lines || preg_match('/\b(?:'.self::SPEECH_CUE.')\b/ui', $this->withoutNegatedSpeech($source))) {
            $available = array_count_values($spoken);
            foreach (array_count_values($lines) as $line => $requestedCount) {
                if (($available[$line] ?? 0) > $requestedCount) {
                    $warnings['repeatedSpeech'] = 'Une réplique semble répétée dans les prompts plus de fois que demandé. Vérifie sa place dans chaque plan avant génération.';
                }
            }
            foreach ($lines as $line) {
                if (0 === ($available[$line] ?? 0)) {
                    $warnings['missingSpeech'] = 'Une réplique manque ou diffère dans les prompts vidéo. Vérifie les mots et le locuteur de chaque plan ; la description seule ne suffit pas.';
                } else {
                    --$available[$line];
                }
            }
            if (count($lines) >= 2 && !isset($warnings['missingSpeech'])) {
                $next = 0;
                foreach ($spoken as $line) {
                    if ($line === ($lines[$next] ?? null)) {
                        ++$next;
                    }
                }
                if ($next !== count($lines)) {
                    $warnings['speechOrder'] = 'Les répliques semblent déplacées dans l’ordre des plans. Vérifie leur enchaînement et leurs locuteurs.';
                }
            }
            if (!$lines) {
                $warnings['unparsedSpeech'] = 'Des paroles semblent demandées sans réplique explicite identifiable. Précise les mots exacts, le locuteur et la langue ; ce contrôle ne peut pas reconstituer ton intention.';
            }
            $warnings['speechReview'] = 'Des paroles sont demandées : vérifie le locuteur, les mots exacts, leur ordre et leur langue dans chaque plan. Ce contrôle textuel ne valide ni l’attribution des voix ni la synchronisation des lèvres.';
        }

        $fantasy = 'magiques?|magic|magical|surréalistes?|surreal|rêves?|dreams?|paradoxes?|paradox';
        $physicalBrief = $this->withoutAllowedQuotes($source, $allowed);
        $physicalBrief = preg_replace('/\b(?:sans|aucun|aucune|pas de|ni|no|without)\b[^.!?\n]{0,40}\b(?:'.$fantasy.')\b/ui', ' ', $physicalBrief);
        if (!preg_match('/\b(?:'.$fantasy.')\b/ui', $physicalBrief)) {
            foreach ($prompts as $prompt) {
                $text = $this->withoutAllowedQuotes($prompt, $allowed);
                if (preg_match('/\b(?:(?:remains?|stays?)\s+(?:completely\s+)?(?:motionless|still)|does not move)\s+while\s+(?:(?:it|he|she)\s+)?(?:walking|walks|running|runs|jumping|jumps)\b|\b(?:reste|demeure)\s+(?:complètement\s+)?immobile\s+(?:tout en|en)\s+(?:marchant|courant|sautant)\b/ui', $text)) {
                    $warnings['actions'] = 'Un prompt semble demander au même sujet de rester immobile tout en se déplaçant. Vérifie cette contradiction apparente ; les autres actions et raccords restent à relire.';
                }
            }
        }
        if ($this->namedOutfitChanged($source, implode("\n", $prompts))) {
            $warnings['namedOutfit'] = 'La tenue d’un personnage nommé semble différente de celle demandée. Vérifie l’attribution des traits à chaque personnage ; un trait demandé pour un autre personnage ne suffit pas.';
        }

        return $warnings;
    }

    /** @param array<string,mixed> $context */
    private function source(string $idea, array $context): string
    {
        $parts = [$idea];
        foreach (['theme', 'style', 'characters'] as $key) {
            if (is_string($context[$key] ?? null)) {
                $parts[] = $context[$key];
            }
        }

        return implode("\n", $parts);
    }

    /** @return list<array{start:int,length:int,text:string}> */
    private function quotes(string $text): array
    {
        preg_match_all('/«([^»]{1,1500})»|“([^”]{1,1500})”|"([^"\r\n]{1,1500})"/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);
        $quotes = [];
        foreach ($matches as $match) {
            foreach (array_slice($match, 1) as [$value]) {
                if (is_string($value) && '' !== trim($value)) {
                    $quotes[] = ['start' => $match[0][1], 'length' => strlen($match[0][0]), 'text' => trim($value)];
                    break;
                }
            }
        }

        return $quotes;
    }

    /** @param list<string> $allowed */
    private function withoutAllowedQuotes(string $text, array $allowed): string
    {
        foreach (array_reverse($this->quotes($text)) as $quote) {
            if (in_array($quote['text'], $allowed, true)) {
                $text = substr_replace($text, ' ', $quote['start'], $quote['length']);
            }
        }

        return $text;
    }

    private function withoutNegatedSpeech(string $text): string
    {
        return preg_replace('/\b(?:sans|aucun|aucune|pas de|ni de|ni|no|without)\s+(?:texte\s+(?:ni|ou)\s+(?:de\s+)?)?(?:dialogues?|paroles?|répliques?|narrateur|narration|voix off|speech|speaking|dialogue|voiceover)\b/ui', ' ', $text);
    }

    /** @param list<string> $markers */
    private function markers(string $text, array $markers): int
    {
        preg_match_all('/\b(?:'.implode('|', $markers).')\b/ui', $text, $matches);

        return count(array_unique(array_map('mb_strtolower', $matches[0])));
    }

    private function fold(string $text): string
    {
        return mb_strtolower(preg_replace('/\p{Mn}/u', '', \Normalizer::normalize($text, \Normalizer::FORM_D)));
    }

    private function namedOutfitChanged(string $source, string $generated): bool
    {
        preg_match_all('/\b([\p{Lu}][\p{L}\p{M}-]{1,40})\s+(?:porte|wears|is wearing)\s+([^.!?;,\n]{1,120})/u', $source, $matches, PREG_SET_ORDER);
        $output = $this->fold($generated);
        foreach ($matches as $match) {
            $name = $this->fold($match[1]);
            $expected = $this->outfits($this->fold($match[2]));
            if (1 !== count($expected)) {
                continue;
            }
            preg_match_all('/\b'.preg_quote($name, '/').'\s+(?:porte|en|wears|wearing|is wearing|in|with)\s+([^.!?;,\n]{1,100})/u', $output, $clauses, PREG_SET_ORDER);
            foreach ($clauses as $clause) {
                $actual = $this->outfits($clause[1]);
                if (1 === count($actual) && $actual !== $expected) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<string> */
    private function outfits(string $text): array
    {
        $found = [];
        foreach (['jacket' => 'jacket|veste', 'shirt' => 'shirt|chemise', 'dress' => 'dress|robe', 'collar' => 'collar|collier'] as $garment => $words) {
            foreach (['blue' => 'blue|bleu[es]?', 'white' => 'white|blan(?:c|che)[s]?', 'green' => 'green|vert[es]?', 'red' => 'red|rouges?', 'black' => 'black|noir[es]?'] as $color => $colors) {
                if (preg_match('/\b(?:(?:'.$colors.')\s+(?:'.$words.')|(?:'.$words.')\s+(?:'.$colors.'))\b/u', $text)) {
                    $found[] = $garment.':'.$color;
                }
            }
        }

        return $found;
    }
}
