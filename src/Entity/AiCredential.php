<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ai_credential')]
class AiCredential
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(type: 'text')]
    private string $encryptedKey;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $owner, #[\SensitiveParameter] string $encryptedKey)
    {
        $this->owner = $owner;
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
