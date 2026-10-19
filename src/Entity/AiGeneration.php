<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ai_generation')]
class AiGeneration
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 120)]
    private string $title;
    #[ORM\Column(type: 'text')]
    private string $prompt;
    #[ORM\Column(length: 32)]
    private string $model;
    #[ORM\Column]
    private int $seconds;
    #[ORM\Column(length: 4)]
    private string $ratio;
    #[ORM\Column]
    private int $estimatedCents;
    #[ORM\Column(length: 10)]
    private string $priceChecked;
    #[ORM\Column(length: 20)]
    private string $status = 'prepared';
    #[ORM\Column(type: 'json')]
    private array $remote = [];
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $issue = null;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?VideoAsset $video = null;

    public function __construct(User $owner, string $title, string $prompt, string $model, int $seconds, string $ratio, int $estimatedCents)
    {
        $this->owner = $owner;
        $this->title = $title;
        $this->prompt = $prompt;
        $this->model = $model;
        $this->seconds = $seconds;
        $this->ratio = $ratio;
        $this->estimatedCents = $estimatedCents;
        $this->priceChecked = \App\Service\AiCatalog::CHECKED;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function getRatio(): string
    {
        return $this->ratio;
    }

    public function getEstimatedCents(): int
    {
        return $this->estimatedCents;
    }

    public function getPriceChecked(): string
    {
        return $this->priceChecked;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @return array<string,string> */
    public function getRemote(): array
    {
        return $this->remote;
    }

    public function getIssue(): ?string
    {
        return $this->issue;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getVideo(): ?VideoAsset
    {
        return $this->video;
    }

    /** @param array<string,string>|null $remote */
    public function progress(string $status, ?string $issue = null, ?array $remote = null): void
    {
        $this->status = $status;
        $this->issue = $issue;
        if (null !== $remote) {
            $this->remote = $remote;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function complete(VideoAsset $video): void
    {
        $this->video = $video;
        $this->progress('available');
    }
}
