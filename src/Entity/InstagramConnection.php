<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'instagram_connection')]
class InstagramConnection
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 64)]
    private string $instagramId;
    #[ORM\Column(length: 30)]
    private string $username;
    #[ORM\Column(length: 20)]
    private string $accountType;
    #[ORM\Column(type: 'text')]
    private string $accessToken;
    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    public function __construct(User $owner, string $instagramId, string $username, string $accountType, string $encryptedToken, int $expiresIn)
    {
        $this->owner = $owner;
        $this->reconnect($instagramId, $username, $accountType, $encryptedToken, $expiresIn);
    }

    public function reconnect(string $instagramId, string $username, string $accountType, string $encryptedToken, int $expiresIn): void
    {
        $this->instagramId = $instagramId;
        $this->username = $username;
        $this->accountType = $accountType;
        $this->accessToken = $encryptedToken;
        $this->expiresAt = new \DateTimeImmutable('+'.max(1, min(5184000, $expiresIn)).' seconds');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getInstagramId(): string
    {
        return $this->instagramId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function isExpiring(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable('+7 days');
    }
}
