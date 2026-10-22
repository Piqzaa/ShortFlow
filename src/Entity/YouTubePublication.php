<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'youtube_publication')]
#[ORM\UniqueConstraint(name: 'uniq_youtube_submission_video', columns: ['submission_key', 'source_id'])]
class YouTubePublication
{
    public const ACTIVE = ['queued', 'uploading', 'processing', 'paused'];
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?VideoAsset $source;
    #[ORM\Column(length: 64)]
    private string $submissionKey;
    #[ORM\Column(length: 64)]
    private string $channelId;
    #[ORM\Column(length: 255)]
    private string $channelTitle;
    #[ORM\Column(length: 100)]
    private string $title;
    #[ORM\Column(type: 'text')]
    private string $description;
    #[ORM\Column(length: 10)]
    private string $privacy;
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $effectivePrivacy = null;
    #[ORM\Column]
    private bool $madeForKids;
    #[ORM\Column]
    private bool $synthetic;
    #[ORM\Column(length: 20)]
    private string $status = 'queued';
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $uploadSession = null;
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $remoteId = null;
    #[ORM\Column]
    private int $sentBytes = 0;
    #[ORM\Column]
    private int $failures = 0;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notice = null;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $owner, VideoAsset $source, YouTubeConnection $connection, string $submissionKey, string $title, string $description, string $privacy, bool $madeForKids, bool $synthetic)
    {
        $this->owner = $owner;
        $this->source = $source;
        $this->channelId = $connection->getChannelId();
        $this->channelTitle = $connection->getChannelTitle();
        $this->submissionKey = $submissionKey;
        $this->title = $title;
        $this->description = $description;
        $this->privacy = $privacy;
        $this->madeForKids = $madeForKids;
        $this->synthetic = $synthetic;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
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

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getChannelTitle(): string
    {
        return $this->channelTitle;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPrivacy(): string
    {
        return $this->privacy;
    }

    public function getEffectivePrivacy(): ?string
    {
        return $this->effectivePrivacy;
    }

    public function complete(?string $privacy): void
    {
        $this->effectivePrivacy = in_array($privacy, ['private', 'unlisted', 'public'], true) ? $privacy : null;
        $notice = null === $this->effectivePrivacy ? 'Traitement confirmé par YouTube. La visibilité effective reste à vérifier dans YouTube Studio.' : ($this->privacy !== $this->effectivePrivacy ? 'YouTube confirme une visibilité différente de celle demandée. Vérifie YouTube Studio avant de modifier la visibilité ; aucun nouvel envoi nécessaire.' : null);
        $this->change('published', $notice);
    }

    public function isMadeForKids(): bool
    {
        return $this->madeForKids;
    }

    public function isSynthetic(): bool
    {
        return $this->synthetic;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getUploadSession(): ?string
    {
        return $this->uploadSession;
    }

    public function getRemoteId(): ?string
    {
        return $this->remoteId;
    }

    public function getSentBytes(): int
    {
        return $this->sentBytes;
    }

    public function getNotice(): ?string
    {
        return $this->notice;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getFailures(): int
    {
        return $this->failures;
    }

    public function change(string $status, ?string $notice = null): void
    {
        $this->status = $status;
        $this->notice = $notice;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function session(string $encrypted): void
    {
        $this->uploadSession = $encrypted;
        $this->change('uploading');
    }

    public function progress(int $bytes): void
    {
        $this->sentBytes = $bytes;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function uploaded(string $id): void
    {
        $this->remoteId = $id;
        $this->change('processing');
    }

    public function failure(): void
    {
        ++$this->failures;
    }

    public function resume(): void
    {
        $this->failures = 0;
        $this->change($this->remoteId ? 'processing' : ($this->uploadSession ? 'uploading' : 'queued'));
    }
}
