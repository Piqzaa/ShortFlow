<?php

namespace App\Service;

final class PublicationTexts
{
    public const VERSION = '2026-10-25.1';
    public const FIELDS = ['youtube' => ['title', 'description'], 'instagram' => ['caption'], 'tiktok' => ['caption']];

    /** @param array<string,mixed> $input
     * @return array<string,string>
     */
    public function profile(array $input): array
    {
        $profile = [];
        foreach (['language' => 2, 'tone' => 200, 'audience' => 200, 'keywords' => 300, 'allowedHashtags' => 300, 'excludedHashtags' => 300, 'brand' => 200, 'cta' => 200] as $field => $limit) {
            $value = $input[$field] ?? ('language' === $field ? 'fr' : '');
            if (!is_string($value) || mb_strlen($value) > $limit) {
                throw new PublicationException('Vérifie la longueur des indications éditoriales.');
            }
            $profile[$field] = trim($value);
        }
        if (!in_array($profile['language'], ['fr', 'en'], true)) {
            throw new PublicationException('Choisis une langue de rédaction disponible.');
        }
        $this->tags($profile['allowedHashtags']);
        $this->tags($profile['excludedHashtags']);

        return $profile;
    }

    /** @return list<string> */
    private function tags(string $value): array
    {
        $tags = preg_split('/[\s,;]+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tags) > 10) {
            throw new PublicationException('Garde au maximum dix hashtags dans chaque liste.');
        }
        foreach ($tags as &$tag) {
            $tag = ltrim($tag, '#');
            if (!preg_match('/^[\p{L}\p{N}_]{1,40}$/uD', $tag)) {
                throw new PublicationException('Utilise des hashtags simples, séparés par des espaces ou des virgules.');
            }
        }

        return array_values(array_unique(array_map('mb_strtolower', $tags)));
    }

    /** @param list<string> $networks
     * @return array<string,mixed>
     */
    public function schema(array $networks): array
    {
        if (!$networks || array_diff($networks, array_keys(self::FIELDS)) || count($networks) !== count(array_unique($networks))) {
            throw new PublicationException('Choisis au moins un réseau pour proposer ses textes.');
        }
        $properties = [];
        foreach ($networks as $network) {
            $fields = [];
            foreach (self::FIELDS[$network] as $field) {
                $fields[$field] = ['type' => 'string', 'minLength' => 1, 'maxLength' => 'title' === $field ? 100 : ('description' === $field ? 1200 : 800)];
            }
            $fields['hashtags'] = ['type' => 'array', 'minItems' => 0, 'maxItems' => 5, 'items' => ['type' => 'string', 'maxLength' => 41]];
            $properties[$network] = ['type' => 'object', 'required' => array_keys($fields), 'additionalProperties' => false, 'properties' => $fields];
        }

        return $properties;
    }

    /** @param array<string,mixed> $result @param list<string> $networks @param array<string,string> $profile
     * @return array<string,array<string,string>>
     */
    public function normalize(array $result, array $networks, array $profile): array
    {
        $this->schema($networks);
        $profile = $this->profile($profile);
        if (array_diff(array_keys($result), $networks) || array_diff($networks, array_keys($result))) {
            throw new PublicationException('La proposition ne contient pas les réseaux demandés. Tes textes restent conservés.');
        }
        $allowed = $this->tags($profile['allowedHashtags']);
        $excluded = $this->tags($profile['excludedHashtags']);
        $output = [];
        foreach ($networks as $network) {
            $row = $result[$network];
            $expected = [...self::FIELDS[$network], 'hashtags'];
            if (!is_array($row) || array_diff(array_keys($row), $expected) || array_diff($expected, array_keys($row)) || !is_array($row['hashtags']) || !array_is_list($row['hashtags']) || count($row['hashtags']) > 5) {
                throw new PublicationException('La proposition est incomplète. Tes textes restent conservés.');
            }
            $tags = [];
            foreach ($row['hashtags'] as $tag) {
                if (!is_string($tag) || !preg_match('/^#?([\p{L}\p{N}_]{1,40})$/uD', $tag, $match)) {
                    throw new PublicationException('Un hashtag proposé est invalide.');
                }
                $normalized = mb_strtolower($match[1]);
                if (in_array($normalized, $excluded, true) || ($allowed && !in_array($normalized, $allowed, true))) {
                    throw new PublicationException('Les hashtags proposés ne respectent pas le profil de la série. Tes textes restent conservés.');
                }
                $tags[$normalized] = '#'.$match[1];
            }
            foreach (self::FIELDS[$network] as $field) {
                $value = $row[$field] ?? null;
                if (!is_string($value) || '' === trim($value) || preg_match('/[<>#]/u', $value)) {
                    throw new PublicationException('Un texte proposé est invalide. Les hashtags doivent rester séparés dans la réponse du rédacteur.');
                }
                $value = trim($value);
                $limit = 'title' === $field ? 100 : ('description' === $field ? 1200 : 800);
                if (mb_strlen($value) > $limit) {
                    throw new PublicationException('Un texte proposé est trop long. Aucune troncature ni application automatique.');
                }
                if ('title' !== $field && $tags) {
                    $value .= "\n\n".implode(' ', $tags);
                }
                if (('description' === $field && strlen($value) > 5000) || ('caption' === $field && mb_strlen($value) > 2200)) {
                    throw new PublicationException('Le texte et ses hashtags dépassent la limite du réseau.');
                }
                $output[$network][$field] = $value;
            }
        }

        return $output;
    }
}
