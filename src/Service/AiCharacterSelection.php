<?php

namespace App\Service;

use App\Entity\AiCharacter;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class AiCharacterSelection
{
    public function __construct(private EntityManagerInterface $em, private AiReferenceStorage $storage)
    {
    }

    /** @return array<string,string> */
    public function choices(User $owner, array $pool = []): array
    {
        $choices = [];
        foreach ($pool as $row) {
            $this->addChoice($choices, $row['name'].' · référence conservée', $row['id'].':'.$row['revision']);
        }
        foreach ($this->em->getRepository(AiCharacter::class)->findBy(['owner' => $owner, 'archived' => false], ['id' => 'DESC']) as $character) {
            $token = $character->getId().':'.$character->getRevision();
            if (!in_array($token, $choices, true)) {
                $this->addChoice($choices, $character->getName().' · version actuelle', $token);
            }
        }

        return $choices;
    }

    private function addChoice(array &$choices, string $label, string $token): void
    {
        $base = $label;
        $number = 1;
        while (isset($choices[$label])) {
            $label = $base.' ('.++$number.')';
        }
        $choices[$label] = $token;
    }

    public function formData(array $data): array
    {
        if ('upload' !== ($data['referenceOrigin'] ?? null) || !array_key_exists('characterSelection', $data)) {
            $data['characterSelection'] = array_map(static fn (array $row): string => $row['id'].':'.$row['revision'], $data['characterPool'] ?? []);
        }

        return $data;
    }

    /** Only the chosen reference source may enter a new preparation. Older forms keep their existing validation. */
    public static function activeReferenceData(array $data): array
    {
        if ('subject' !== ($data['source'] ?? null)) {
            return $data;
        }
        if ('library' === ($data['referenceOrigin'] ?? null)) {
            $data['reference'] = '';
            $data['subjects'] = $data['subjectDetails'] = [];
        } elseif ('upload' === ($data['referenceOrigin'] ?? null)) {
            unset($data['characterPool']);
            $data['characterSelection'] = [];
            foreach (is_array($data['shots'] ?? null) ? $data['shots'] : [] as $index => $shot) {
                if (is_array($shot)) {
                    $data['shots'][$index]['characterIds'] = [];
                    $data['shots'][$index]['characterIdsManaged'] = '1';
                }
            }
        }

        return $data;
    }

    /** Resolve only server-owned snapshots from the previous database/session value. */
    public function resolve(User $owner, array $data, array $previous = []): ?array
    {
        if ('subject' === ($data['source'] ?? null) && 'upload' === ($data['referenceOrigin'] ?? null)) {
            return null;
        }
        if ('1' !== (string) ($data['characterSelectionManaged'] ?? '0')) {
            return $previous['characterPool'] ?? null;
        }

        return $this->em->getConnection()->transactional(function () use ($owner, $data, $previous): ?array {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $tokens = $data['characterSelection'] ?? [];
            if (!is_array($tokens) || count($tokens) > 7) {
                throw new \RuntimeException('Choisis au maximum sept fiches de personnages pour cette préparation.');
            }
            $saved = [];
            foreach ($previous['characterPool'] ?? [] as $row) {
                $saved[$row['id'].':'.$row['revision']] = $row;
            }
            $pool = [];
            foreach ($tokens as $token) {
                if (!is_string($token) || !preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/D', $token, $match) || isset($pool[$match[1]])) {
                    throw new \RuntimeException('Choisis une seule version de chaque personnage.');
                }
                $character = $this->em->getRepository(AiCharacter::class)->findOneBy(['id' => $match[1], 'owner' => $owner]);
                if (!$character) {
                    throw new \RuntimeException('Un personnage est indisponible. Revois ta sélection.');
                }
                $this->em->refresh($character);
                if (isset($saved[$token])) {
                    $row = $saved[$token];
                } else {
                    if ($character->isArchived() || $character->getRevision() !== (int) $match[2]) {
                        throw new \RuntimeException('Une fiche a changé ou est archivée. Recharge les choix avant de continuer.');
                    }
                    $row = ['id' => $character->getId(), 'revision' => $character->getRevision(), 'name' => $character->getName(), 'description' => $character->getDescription(), 'portrait' => $character->getPortrait(), 'views' => $character->getViews()];
                }
                foreach ([$row['portrait'], ...$row['views']] as $file) {
                    $this->storage->path($owner, $file);
                }
                $pool[$match[1]] = $row;
            }

            // Absence in historical data must remain different from an explicit cleared pool.
            return $pool || array_key_exists('characterPool', $previous) ? array_values($pool) : null;
        });
    }
}
