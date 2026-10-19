<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ai_credential')]
#[ORM\UniqueConstraint(name: 'uniq_ai_owner_provider', columns: ['owner_id', 'provider'])]
class AiCredential
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 16)]
    private string $provider = 'fal';
    #[ORM\Column(type: 'text')]
    private string $encryptedKey;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $owner, #[\SensitiveParameter] string $encryptedKey, string $provider = 'fal')
    {
        $this->owner = $owner;
        $this->provider = $provider;
        $this->replace($encryptedKey);
    }

    public function replace(#[\SensitiveParameter] string $encryptedKey): void
    {
        $this->encryptedKey = $encryptedKey;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getEncryptedKey(): string
    {
        return $this->encryptedKey;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
