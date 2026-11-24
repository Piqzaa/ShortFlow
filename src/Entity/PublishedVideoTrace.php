<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_published_video_trace_owner', columns: ['owner_id', 'removed_at', 'id'])]
class PublishedVideoTrace
{
    #[ORM\Id, ORM\Column]
    private int $id;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 120)]
    private string $title;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $poster;
    #[ORM\Column]
    private \DateTimeImmutable $removedAt;

    public function __construct(VideoAsset $video, ?string $poster, \DateTimeImmutable $removedAt)
    {
        $this->id = (int) $video->getId();
        $this->owner = $video->getOwner();
        $this->title = $video->getTitle();
        $this->poster = $poster;
        $this->removedAt = $removedAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function getRemovedAt(): \DateTimeImmutable
    {
        return $this->removedAt;
    }
}
