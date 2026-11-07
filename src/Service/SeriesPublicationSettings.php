<?php

namespace App\Service;

use App\Entity\User;

final class SeriesPublicationSettings
{
    public function __construct(private SocialAccounts $accounts)
    {
    }

    /** @param array<string,mixed> $input
     * @return array{networks:list<string>,accounts:array<string,?string>}
     */
    public function normalize(User $owner, array $input, array $previous = []): array
    {
        $networks = $input['networks'] ?? [];
        $accounts = $input['accounts'] ?? [];
        if (!is_array($networks) || !array_is_list($networks) || array_filter($networks, static fn ($value): bool => !is_string($value)) || array_diff($networks, array_keys(MultiNetworkPublishing::LABELS)) || count(array_unique($networks)) !== count($networks) || !is_array($accounts) || array_filter($accounts, static fn ($value): bool => null !== $value && !is_string($value))) {
            throw new PublicationException('Vérifie les réseaux et les comptes de la série.');
        }
        // Null is deliberate: never replace a missing account by a current default.
        $explicit = array_fill_keys($networks, null);
        foreach ($networks as $network) {
            $explicit[$network] = $accounts[$network] ?? null;
        }
        $selected = [];
        foreach ($networks as $network) {
            if ($explicit[$network] && $explicit[$network] === ($previous['accounts'][$network] ?? null) && !$this->accounts->find($owner, $network, $explicit[$network])) {
                $selected[$network] = $explicit[$network];
            } else {
                $selected += $this->accounts->select($owner, [$network], [$network => $explicit[$network]]);
            }
        }

        return ['networks' => $networks, 'accounts' => $selected];
    }

    /** @param array<string,?string> $saved @return array<string,array<string,string>> */
    public function choices(User $owner, array $saved = []): array
    {
        $choices = [];
        foreach (MultiNetworkPublishing::LABELS as $network => $label) {
            $choices[$network] = [];
            foreach ($this->accounts->all($owner, $network) as $index => $connection) {
                $name = SocialAccounts::label($connection);
                if (isset($choices[$network][$name])) {
                    $name .= ' · compte '.($index + 1);
                }
                $choices[$network][$name] = SocialAccounts::identity($connection);
            }
            if (($saved[$network] ?? null) && !in_array($saved[$network], $choices[$network], true)) {
                $choices[$network]['Compte enregistré indisponible'] = $saved[$network];
            }
        }

        return $choices;
    }
}
