<?php

namespace App\Service;

use App\Entity\AiCredential;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AiKeys
{
    public function __construct(private EntityManagerInterface $em, #[Autowire(service: 'ai.vault')] private TokenVault $vault, private AiCatalog $catalog, private FalOAuth $falOAuth)
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

    /** Connection hints for a form, never a credential or authorization cache.
     * @return array<string,bool>
     */
    public function connectedProviders(User $owner): array
    {
        $connected = array_fill_keys(array_keys(AiCatalog::PROVIDERS), false);
        foreach ($this->em->getConnection()->fetchFirstColumn('SELECT provider FROM ai_credential WHERE owner_id = :owner', ['owner' => $owner->getId()]) as $provider) {
            if (array_key_exists($provider, $connected)) {
                $connected[$provider] = true;
            }
        }

        return $connected;
    }

    public function masked(User $owner, string $provider = 'fal'): ?string
    {
        $connection = $this->connection($owner, $provider);
        if (!$connection) {
            return null;
        }
        try {
            return '•••• '.substr($this->vault->decrypt($connection->getEncryptedKey()), -4);
        } catch (PublicationException) {
            return null;
        }
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

    public function hasAccess(User $owner, string $provider, string $mode): bool
    {
        return 'mcp' === $mode ? 'fal' === $provider && null !== $this->falOAuth->connection($owner) : null !== $this->connection($owner, $provider);
    }

    public function identity(User $owner, string $provider, string $mode): string
    {
        $credential = 'mcp' === $mode ? $this->falOAuth->connection($owner) : $this->connection($owner, $provider);
        if (!$credential) {
            throw new \RuntimeException('Reconnecte le compte fournisseur utilisé par cette série.');
        }
        $this->em->refresh($credential);

        return 'mcp' === $mode ? ($this->falOAuth->accountId($owner) ?? throw new \RuntimeException('Reconnecte ton compte Fal.')) : hash('sha256', $credential->getEncryptedKey());
    }

    public function plain(User $owner, string $provider = 'fal', string $mode = 'api'): string
    {
        if ('mcp' === $mode) {
            if ('fal' !== $provider) {
                throw new \RuntimeException('Ce fournisseur ne propose pas encore la connexion navigateur dans VydFlow.');
            }

            return $this->falOAuth->credential($owner);
        }
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
