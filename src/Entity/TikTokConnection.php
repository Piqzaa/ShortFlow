<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tiktok_connection')]
#[ORM\UniqueConstraint(name: 'tiktok_owner_identity', columns: ['owner_id', 'open_id'])]
class TikTokConnection
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 128)]
    private string $openId;
    #[ORM\Column(length: 128)]
    private string $displayName;
    #[ORM\Column(type: 'text')]
    private string $accessToken;
    #[ORM\Column(type: 'text')]
    private string $refreshToken;
    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;
    #[ORM\Column]
    private \DateTimeImmutable $refreshExpiresAt;

    public function __construct(User $owner, string $openId, string $displayName, string $access, string $refresh, int $expires, int $refreshExpires)
    {
        $this->owner = $owner;
        $this->reconnect($openId, $displayName, $access, $refresh, $expires, $refreshExpires);
    }

    public function reconnect(string $openId, string $name, string $access, string $refresh, int $expires, int $refreshExpires): void
    {
        $this->openId = $openId;
        $this->displayName = $name;
        $this->renew($access, $refresh, $expires, $refreshExpires);
    }

    public function renew(string $access, string $refresh, int $expires, int $refreshExpires): void
    {
        $this->accessToken = $access;
        $this->refreshToken = $refresh;
        $this->expiresAt = new \DateTimeImmutable('+'.max(1, min(86400, $expires)).' seconds');
        $this->refreshExpiresAt = new \DateTimeImmutable('+'.max(1, min(31536000, $refreshExpires)).' seconds');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getOpenId(): string
    {
        return $this->openId;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function getRefreshExpiresAt(): \DateTimeImmutable
    {
        return $this->refreshExpiresAt;
    }

    public function isExpired(): bool
    {
        return $this->refreshExpiresAt <= new \DateTimeImmutable();
    }

    public function needsRefresh(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable('+2 minutes');
    }
}
