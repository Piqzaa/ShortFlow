<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'youtube_connection')]
#[ORM\UniqueConstraint(name: 'youtube_owner_identity', columns: ['owner_id', 'channel_id'])]
class YouTubeConnection
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 64)]
    private string $channelId;
    #[ORM\Column(length: 255)]
    private string $channelTitle;
    #[ORM\Column(type: 'text')]
    private string $accessToken;
    #[ORM\Column(type: 'text')]
    private string $refreshToken;
    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    public function __construct(User $owner, string $channelId, string $channelTitle, string $accessToken, string $refreshToken, int $expiresIn)
    {
        $this->owner = $owner;
        $this->channelId = $channelId;
        $this->channelTitle = $channelTitle;
        $this->refreshToken = $refreshToken;
        $this->updateAccess($accessToken, $expiresIn);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getChannelTitle(): string
    {
        return $this->channelTitle;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function needsRefresh(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable('+2 minutes');
    }

    public function updateAccess(string $encryptedToken, int $seconds): void
    {
        $this->accessToken = $encryptedToken;
        $this->expiresAt = new \DateTimeImmutable('+'.max(1, min(86400, $seconds)).' seconds');
    }

    public function reconnect(string $title, string $access, string $refresh, int $expires): void
    {
        $this->channelTitle = $title;
        $this->refreshToken = $refresh;
        $this->updateAccess($access, $expires);
    }
}
