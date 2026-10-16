<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tiktok_publication')]
#[ORM\UniqueConstraint(name: 'uniq_tiktok_submission_video', columns: ['submission_key', 'source_id'])]
class TikTokPublication
{
    public const ACTIVE = ['queued', 'publishing', 'processing', 'paused', 'review'];
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?VideoAsset $source;
    #[ORM\Column(length: 64)]
    private string $submissionKey;
    #[ORM\Column(length: 128)]
    private string $openId;
    #[ORM\Column(length: 128)]
    private string $displayName;
    #[ORM\Column(length: 120)]
    private string $title;
    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $options;
    #[ORM\Column(length: 20)]
    private string $status = 'queued';
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $publishId = null;
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $remoteId = null;
    #[ORM\Column]
    private bool $initAttempted = false;
    #[ORM\Column]
    private int $polls = 0;
    #[ORM\Column]
    private int $failures = 0;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notice = null;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $options */
    public function __construct(User $owner, VideoAsset $source, TikTokConnection $connection, string $key, string $name, array $options)
    {
        $this->owner = $owner;
        $this->source = $source;
        $this->submissionKey = $key;
        $this->openId = $connection->getOpenId();
        $this->displayName = $name;
        $this->title = $source->getTitle();
        $this->options = $options;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getSource(): ?VideoAsset
    {
        return $this->source;
    }

    public function getOpenId(): string
    {
        return $this->openId;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return array<string, mixed> */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getPublishId(): ?string
    {
        return $this->publishId;
    }

    public function getRemoteId(): ?string
    {
        return $this->remoteId;
    }

    public function isInitAttempted(): bool
    {
        return $this->initAttempted;
    }

    public function getPolls(): int
    {
        return $this->polls;
    }

    public function getFailures(): int
    {
        return $this->failures;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function change(string $status, ?string $notice = null): void
    {
        $this->status = $status;
        $this->notice = $notice;
    }

    public function attempt(): void
    {
        $this->initAttempted = true;
        $this->change('publishing');
    }

    public function started(string $id): void
    {
        $this->publishId = $id;
        $this->change('processing');
    }

    public function published(?string $id): void
    {
        $this->remoteId = $id;
        $this->change('published');
    }

    public function poll(): void
    {
        ++$this->polls;
    }

    public function failure(): void
    {
        ++$this->failures;
    }

    public function resume(): void
    {
        $this->polls = $this->failures = 0;
        $this->change('processing');
    }
}
