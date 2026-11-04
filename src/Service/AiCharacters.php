<?php

namespace App\Service;

use App\Entity\AiCharacter;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AiCharacters
{
    public function __construct(private EntityManagerInterface $em, private AiReferenceStorage $storage)
    {
    }

    public function owned(User $owner, int $id): ?AiCharacter
    {
        return $this->em->getRepository(AiCharacter::class)->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    public function save(User $owner, ?int $id, int $revision, string $name, string $description, ?UploadedFile $portrait, array $views, bool $clearViews = false): AiCharacter
    {
        $created = [];
        try {
            return $this->em->getConnection()->transactional(function () use ($owner, $id, $revision, $name, $description, $portrait, $views, $clearViews, &$created): AiCharacter {
                $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
                if ($clearViews && $views) {
                    throw new \RuntimeException('Choisis entre retirer les vues et importer leurs remplaçantes.');
                }
                $character = $id ? $this->owned($owner, $id) : null;
                if ($id && !$character) {
                    throw new \RuntimeException('Personnage indisponible.');
                }
                if ($character) {
                    $this->em->refresh($character);
                    if ($character->getRevision() !== $revision || $character->isArchived()) {
                        throw new \RuntimeException('Cette fiche a changé ou est archivée. Recharge-la avant de modifier.');
                    }
                }
                if (!$character && !$portrait) {
                    throw new \RuntimeException('Importe un portrait pour créer ce personnage.');
                }
                if ('' === trim($name) || mb_strlen($name) > 60 || mb_strlen($description) > 2000 || count($views) > 3) {
                    throw new \RuntimeException('Vérifie le nom, la description et le nombre de vues.');
                }
                $image = null;
                if ($portrait) {
                    $image = $this->storage->store($owner, $portrait);
                    $created[] = $image;
                }
                $images = [];
                foreach ($views as $file) {
                    if (!$file instanceof UploadedFile) {
                        throw new \RuntimeException('Image invalide.');
                    }
                    $images[] = $this->storage->store($owner, $file);
                    $created[] = end($images);
                }
                if ($character) {
                    $character->update(trim($name), trim($description), $image, $views ? $images : ($clearViews ? [] : null));
                } else {
                    $character = new AiCharacter($owner, trim($name), trim($description), (string) $image, $images);
                    $this->em->persist($character);
                }
                $this->em->flush();

                return $character;
            });
        } catch (\Throwable $error) {
            $cleanupFailed = false;
            foreach ($created as $file) {
                try {
                    $this->storage->discardNew($owner, $file);
                } catch (\Throwable) {
                    $cleanupFailed = true;
                }
            }
            if ($cleanupFailed) {
                throw new \RuntimeException('L’enregistrement a échoué et le stockage doit être vérifié avant un nouvel import.', previous: $error);
            }
            throw $error;
        }
    }

    public function archive(User $owner, int $id, int $revision, bool $archived): void
    {
        $this->em->getConnection()->transactional(function () use ($owner, $id, $revision, $archived): void {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $character = $this->owned($owner, $id) ?? throw new \RuntimeException('Personnage indisponible.');
            $this->em->refresh($character);
            if ($character->getRevision() !== $revision) {
                throw new \RuntimeException('Cette fiche a changé. Recharge la liste.');
            }
            $character->archive($archived);
            $this->em->flush();
        });
    }
}
