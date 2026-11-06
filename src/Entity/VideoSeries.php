<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'video_series_owner', columns: ['owner_id'])]
class VideoSeries
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 120)]
    private string $name;
    #[ORM\Column]
    private int $revision = 1;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $owner, string $name)
    {
        $this->owner = $owner;
        $this->name = $name;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function revise(string $name): void
    {
        $this->name = $name;
        ++$this->revision;
    }
}
