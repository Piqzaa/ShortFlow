<?php

namespace App\Service;

use App\Entity\AiCredential;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AiKeys
{
    public function __construct(private EntityManagerInterface $em, #[Autowire(service: 'ai.vault')] private TokenVault $vault)
    {
    }

    public function ready(): bool
    {
        return $this->vault->isReady();
    }

    public function connection(User $owner): ?AiCredential
    {
        return $this->em->getRepository(AiCredential::class)->findOneBy(['owner' => $owner]);
    }

    public function save(User $owner, #[\SensitiveParameter] string $key): void
    {
        $key = trim($key);
        if (!$this->ready() || !preg_match('/^[\x21-\x7e]{20,512}$/D', $key)) {
            throw new \InvalidArgumentException('La clé doit contenir entre 20 et 512 caractères, sans espace.');
        }
        $this->em->wrapInTransaction(function () use ($owner, $key): void {
            $this->em->lock($owner, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $encrypted = $this->vault->encrypt($key);
            $connection = $this->connection($owner);
            if ($connection) {
                $connection->replace($encrypted);
            } else {
                $this->em->persist(new AiCredential($owner, $encrypted));
            }
        });
    }

    public function remove(User $owner): void
    {
        $this->em->wrapInTransaction(function () use ($owner): void {
            $this->em->lock($owner, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $connection = $this->connection($owner);
            if ($connection) {
                $this->em->remove($connection);
            }
        });
    }

    public function plain(User $owner): string
    {
        $connection = $this->connection($owner);
        if (!$connection) {
            throw new \RuntimeException('Ajoute ta clé Fal dans Connexions pour continuer.');
        }
        try {
            return $this->vault->decrypt($connection->getEncryptedKey());
        } catch (PublicationException) {
            throw new \RuntimeException('Remplace ta clé Fal dans Connexions.');
        }
    }
}
