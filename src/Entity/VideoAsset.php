<?php

namespace App\Entity;

use App\Repository\VideoAssetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VideoAssetRepository::class)]
#[ORM\Table(name: 'video_asset')]
class VideoAsset
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 120)]
    private string $title;
    #[ORM\Column(length: 60, unique: true)]
    private string $storageName;
    #[ORM\Column(length: 20)]
    private string $mimeType;
    #[ORM\Column]
    private int $byteSize;
    #[ORM\Column]
    private float $duration;
    #[ORM\Column]
    private int $width;
    #[ORM\Column]
    private int $height;
    #[ORM\Column(length: 20)]
    private string $codec;
    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $technicalMetadata = null;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $owner, string $title, string $storageName, string $mimeType, int $byteSize, float $duration, int $width, int $height, string $codec)
    {
        $this->owner = $owner;
        $this->title = $title;
        $this->storageName = $storageName;
        $this->mimeType = $mimeType;
        $this->byteSize = $byteSize;
        $this->duration = $duration;
        $this->width = $width;
        $this->height = $height;
        $this->codec = $codec;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function matchesRatio(string $ratio): bool
    {
        if ('auto' === $ratio) {
            return true;
        }
        if ($this->width < 1 || $this->height < 1 || !preg_match('/^([1-9][0-9]?):([1-9][0-9]?)$/D', $ratio, $parts)) {
            return false;
        }
        $expected = (int) $parts[1] / (int) $parts[2];

        // Encoders may round dimensions to a multiple of 8 or 16.
        return abs($this->width / $this->height - $expected) / $expected <= 0.03;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function rename(string $title): void
    {
        $this->title = $title;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getByteSize(): int
    {
        return $this->byteSize;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getCodec(): string
    {
        return $this->codec;
    }

    /** @return array<string, mixed>|null */
    public function getTechnicalMetadata(): ?array
    {
        return $this->technicalMetadata;
    }

    /** @param array<string, mixed> $metadata */
    public function recordTechnicalMetadata(array $metadata): void
    {
        $this->technicalMetadata = $metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
