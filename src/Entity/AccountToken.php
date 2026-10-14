<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'account_token')]
#[ORM\UniqueConstraint(name: 'uniq_account_token_hash', fields: ['tokenHash'])]
class AccountToken
{
    public const VERIFY = 'verify';
    public const RESET = 'reset';
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 64)]
    private string $tokenHash;
    #[ORM\Column(length: 10)]
    private string $purpose;
    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $consumedAt = null;

    public function __construct(User $owner, string $tokenHash, string $purpose)
    {
        $this->owner = $owner;
        $this->tokenHash = $tokenHash;
        $this->purpose = $purpose;
        $this->expiresAt = new \DateTimeImmutable(self::RESET === $purpose ? '+30 minutes' : '+24 hours');
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function isUsable(string $purpose): bool
    {
        return $this->purpose === $purpose && null === $this->consumedAt && $this->expiresAt > new \DateTimeImmutable();
    }

    public function consume(): void
    {
        $this->consumedAt = new \DateTimeImmutable();
    }
}
