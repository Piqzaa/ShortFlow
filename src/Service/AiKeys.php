<?php

namespace App\Service;

use App\Entity\AiCredential;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AiKeys
{
    public function __construct(private EntityManagerInterface $em, #[Autowire(service: 'ai.vault')] private TokenVault $vault, private AiCatalog $catalog)
    {
    }

    public function ready(): bool
    {
        return $this->vault->isReady();
    }

    public function connection(User $owner, string $provider = 'fal'): ?AiCredential
    {
        $this->catalog->provider($provider);

        return $this->em->getRepository(AiCredential::class)->findOneBy(['owner' => $owner, 'provider' => $provider]);
    }

    public function save(User $owner, #[\SensitiveParameter] string $key, string $provider = 'fal'): void
    {
        $this->catalog->provider($provider);
        $key = trim($key);
        if (!$this->ready() || !preg_match('/^[\x21-\x7e]{20,512}$/D', $key)) {
            throw new \InvalidArgumentException('La clé doit contenir entre 20 et 512 caractères, sans espace.');
        }
        $this->em->wrapInTransaction(function () use ($owner, $key, $provider): void {
            $this->em->lock($owner, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $encrypted = $this->vault->encrypt($key);
            $connection = $this->connection($owner, $provider);
            if ($connection) {
                $connection->replace($encrypted);
            } else {
                $this->em->persist(new AiCredential($owner, $encrypted, $provider));
            }
        });
    }

    public function remove(User $owner, string $provider = 'fal'): void
    {
        $this->em->wrapInTransaction(function () use ($owner, $provider): void {
            $this->em->lock($owner, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $connection = $this->connection($owner, $provider);
            if ($connection) {
                $this->em->remove($connection);
            }
        });
    }

    public function plain(User $owner, string $provider = 'fal'): string
    {
        $name = $this->catalog->provider($provider);
        $connection = $this->connection($owner, $provider);
        if (!$connection) {
            throw new \RuntimeException('Ajoute ta clé '.$name.' dans Connexions pour continuer.');
        }
        try {
            return $this->vault->decrypt($connection->getEncryptedKey());
        } catch (PublicationException) {
            throw new \RuntimeException('Remplace ta clé '.$name.' dans Connexions.');
        }
    }
}
