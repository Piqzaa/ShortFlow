<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\AiGenerationRepository::class)]
#[ORM\Table(name: 'ai_generation')]
class AiGeneration
{
    public const LABELS = ['prepared' => 'À confirmer', 'queued' => 'En attente', 'submitting' => 'Demande en cours', 'processing' => 'Génération en cours', 'waiting_key' => 'Connexion à vérifier', 'review' => 'À vérifier', 'available' => 'Vidéo prête'];
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
    #[ORM\Column(length: 8, options: ['default' => 'api'])]
    private string $accessMode = 'api';
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $accessAccount = null;
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
    #[ORM\Column(length: 52, nullable: true)]
    private ?string $referenceImage = null;
    #[ORM\Column(length: 52, nullable: true)]
    private ?string $endReferenceImage = null;
    #[ORM\Column(length: 8, options: ['default' => 'crop'])]
    private string $imageFit = 'crop';

    public function __construct(User $owner, string $title, string $prompt, string $model, int $seconds, string $ratio, int $estimatedCents)
    {
        $this->owner = $owner;
        $this->title = $title;
        $this->prompt = $prompt;
        $this->model = $model;
        $this->seconds = $seconds;
        $this->ratio = $ratio;
        $this->estimatedCents = $estimatedCents;
        $this->priceChecked = \App\Service\AiCatalog::MODELS[$model]['checked'] ?? \App\Service\AiCatalog::CHECKED;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getReferenceImage(): ?string
    {
        return $this->referenceImage;
    }

    public function attachReference(string $name): void
    {
        if ('prepared' !== $this->status || !preg_match('/^[a-f0-9]{48}\.jpg$/D', $name)) {
            throw new \LogicException('Image de référence invalide ou déjà fixée.');
        }
        $this->referenceImage = $name;
    }

    public function getEndReferenceImage(): ?string
    {
        return $this->endReferenceImage;
    }

    public function attachEndReference(string $name): void
    {
        if ('prepared' !== $this->status || !preg_match('/^[a-f0-9]{48}\.jpg$/D', $name)) {
            throw new \LogicException('Image de fin invalide ou déjà fixée.');
        }
        $this->endReferenceImage = $name;
    }

    public function getImageFit(): string
    {
        return $this->imageFit;
    }

    public function frameImages(string $fit): void
    {
        if ('prepared' !== $this->status || !in_array($fit, ['crop', 'contain'], true)) {
            throw new \LogicException('Cadrage invalide ou déjà fixé.');
        }
        $this->imageFit = $fit;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccessMode(): string
    {
        return $this->accessMode;
    }

    public function useMcp(): void
    {
        if ('prepared' !== $this->status) {
            throw new \LogicException('Le mode d’accès est déjà fixé.');
        }
        $this->accessMode = 'mcp';
    }

    public function bindAccessAccount(string $id): void
    {
        if ('prepared' !== $this->status || 'mcp' !== $this->accessMode) {
            throw new \LogicException('Le compte facturé est déjà fixé.');
        }
        $this->accessAccount = $id;
    }

    public function getAccessAccount(): ?string
    {
        return $this->accessAccount;
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
