<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'instagram_publication')]
#[ORM\UniqueConstraint(name: 'uniq_instagram_submission_video', columns: ['submission_key', 'source_id'])]
class InstagramPublication
{
    public const ACTIVE = ['queued', 'processing', 'publishing', 'paused', 'review'];
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?VideoAsset $source;
    #[ORM\Column(length: 64)]
    private string $submissionKey;
    #[ORM\Column(length: 64)]
    private string $instagramId;
    #[ORM\Column(length: 30)]
    private string $username;
    #[ORM\Column(length: 120)]
    private string $title;
    #[ORM\Column(type: 'text')]
    private string $caption;
    #[ORM\Column]
    private bool $shareToFeed;
    #[ORM\Column]
    private bool $synthetic;
    #[ORM\Column(length: 20)]
    private string $status = 'queued';
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $containerId = null;
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $remoteId = null;
    #[ORM\Column]
    private bool $publishAttempted = false;
    #[ORM\Column]
    private int $polls = 0;
    #[ORM\Column]
    private int $failures = 0;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notice = null;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $containerCreatedAt = null;

    public function __construct(User $owner, VideoAsset $source, InstagramConnection $connection, string $submissionKey, string $caption, bool $shareToFeed, bool $synthetic)
    {
        $this->owner = $owner;
        $this->source = $source;
        $this->submissionKey = $submissionKey;
        $this->instagramId = $connection->getInstagramId();
        $this->username = $connection->getUsername();
        $this->title = $source->getTitle();
        $this->caption = $caption;
        $this->shareToFeed = $shareToFeed;
        $this->synthetic = $synthetic;
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

    public function getInstagramId(): string
    {
        return $this->instagramId;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCaption(): string
    {
        return $this->caption;
    }

    public function isShareToFeed(): bool
    {
        return $this->shareToFeed;
    }

    public function isSynthetic(): bool
    {
        return $this->synthetic;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getContainerId(): ?string
    {
        return $this->containerId;
    }

    public function getRemoteId(): ?string
    {
        return $this->remoteId;
    }

    public function isPublishAttempted(): bool
    {
        return $this->publishAttempted;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getContainerCreatedAt(): ?\DateTimeImmutable
    {
        return $this->containerCreatedAt;
    }

    public function getNotice(): ?string
    {
        return $this->notice;
    }

    public function getPolls(): int
    {
        return $this->polls;
    }

    public function getFailures(): int
    {
        return $this->failures;
    }

    public function change(string $status, ?string $notice = null): void
    {
        $this->status = $status;
        $this->notice = $notice;
    }

    public function container(string $id): void
    {
        $this->containerId = $id;
        $this->containerCreatedAt = new \DateTimeImmutable();
        $this->change('processing');
    }

    public function attempt(): void
    {
        $this->publishAttempted = true;
        $this->change('publishing');
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
        $this->change($this->containerId ? ($this->publishAttempted ? 'publishing' : 'processing') : 'queued');
    }
}
