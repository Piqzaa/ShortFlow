<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ai_character')]
#[ORM\Index(name: 'character_owner_archive', columns: ['owner_id', 'archived', 'id'])]
class AiCharacter
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 60)]
    private string $name;
    #[ORM\Column(type: 'text')]
    private string $description;
    #[ORM\Column(length: 52)]
    private string $portrait;
    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $views = [];
    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $history = [];
    #[ORM\Column]
    private int $revision = 1;
    #[ORM\Column]
    private bool $archived = false;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $owner, string $name, string $description, string $portrait, array $views = [])
    {
        $this->owner = $owner;
        $this->name = $name;
        $this->description = $description;
        $this->portrait = $portrait;
        $this->views = $views;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getPortrait(): string
    {
        return $this->portrait;
    }

    public function getViews(): array
    {
        return $this->views;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function update(string $name, string $description, ?string $portrait, ?array $views): void
    {
        $this->history = array_values(array_unique([...$this->history, $this->portrait, ...$this->views]));
        $this->name = $name;
        $this->description = $description;
        $this->portrait = $portrait ?? $this->portrait;
        $this->views = $views ?? $this->views;
        ++$this->revision;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function archive(bool $archived): void
    {
        $this->archived = $archived;
        ++$this->revision;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
