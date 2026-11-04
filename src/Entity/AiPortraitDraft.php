<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ai_portrait_draft')]
#[ORM\Index(name: 'portrait_owner_recent', columns: ['owner_id', 'id'])]
class AiPortraitDraft
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 60)]
    private string $name = '';
    #[ORM\Column(type: 'text')]
    private string $idea = '';
    #[ORM\Column(type: 'text')]
    private string $appearance = '';
    #[ORM\Column(length: 300)]
    private string $style = '';
    #[ORM\Column(type: 'text')]
    private string $customPrompt = '';
    #[ORM\Column]
    private int $revision = 0;
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $quote = null;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $owner)
    {
        $this->owner = $owner;
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getIdea(): string
    {
        return $this->idea;
    }

    public function getAppearance(): string
    {
        return $this->appearance;
    }

    public function getStyle(): string
    {
        return $this->style;
    }

    public function getCustomPrompt(): string
    {
        return $this->customPrompt;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getQuote(): ?array
    {
        return $this->quote;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function revise(string $name, string $idea, string $appearance, string $style, string $customPrompt): void
    {
        $this->name = $name;
        $this->idea = $idea;
        $this->appearance = $appearance;
        $this->style = $style;
        $this->customPrompt = $customPrompt;
        ++$this->revision;
        $this->quote = null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function estimate(?array $quote): void
    {
        $this->quote = $quote;
    }
}
