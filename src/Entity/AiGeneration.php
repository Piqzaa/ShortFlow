<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\AiGenerationRepository::class)]
#[ORM\Table(name: 'ai_generation')]
class AiGeneration
{
    public const LABELS = ['prepared' => 'À confirmer', 'cancelled' => 'Abandonnée avant envoi', 'queued' => 'En attente', 'submitting' => 'Demande en cours', 'processing' => 'Génération en cours', 'waiting_key' => 'Connexion à vérifier', 'review' => 'À vérifier', 'available' => 'Vidéo prête'];
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
    /** @var array{contract?:string,subjects?:list<string>,subjectDetails?:list<array{reference:string,name:string,role:string,presence:string,views:list<string>}>,extension?:array<string,mixed>} */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $inputSettings = [];

    /** @return array{contract?:string,subjects?:list<string>,subjectDetails?:list<array{reference:string,name:string,role:string,presence:string,views:list<string>}>,extension?:array<string,mixed>} */
    public function getInputSettings(): array
    {
        return $this->inputSettings;
    }

    /** @param array<string,mixed> $source */
    public function attachExtension(array $source): void
    {
        if ('prepared' !== $this->status || 'ltx-23-extend' !== $this->model) {
            throw new \LogicException('Extension indisponible.');
        }
        $this->inputSettings['extension'] = $source;
    }

    /** @param list<string> $subjects */
    public function attachSubjects(array $subjects): void
    {
        if ('prepared' !== $this->status || count($subjects) > 7) {
            throw new \LogicException('Références de sujets indisponibles.');
        }
        foreach ($subjects as $name) {
            if (!preg_match('/^[a-f0-9]{48}\.jpg$/D', $name)) {
                throw new \LogicException('Référence de sujet invalide.');
            }
        }
        $this->inputSettings['subjects'] = array_values(array_unique($subjects));
    }

    /** @param list<array{reference:string,name:string,role:string,presence:string,views:list<string>}> $details */
    public function attachSubjectDetails(array $details): void
    {
        if ('prepared' !== $this->status || count($details) > 7) {
            throw new \LogicException('Descriptions de références indisponibles.');
        }
        $names = $this->inputSettings['subjects'] ?? [];
        if ((\App\Service\AiCatalog::MODELS[$this->model]['subjectReference'] ?? false) && $this->referenceImage) {
            array_unshift($names, $this->referenceImage);
        }
        $seen = [];
        foreach ($details as $detail) {
            if (!in_array($detail['reference'], $names, true) || in_array($detail['reference'], $seen, true) || mb_strlen($detail['name']) > 60 || !in_array($detail['role'], ['character', 'object'], true) || !in_array($detail['presence'], ['visible', 'enters', 'unspecified'], true) || count($detail['views']) > 3) {
                throw new \LogicException('Description de référence invalide.');
            }
            $seen[] = $detail['reference'];
            $views = [];
            foreach ($detail['views'] as $view) {
                if (!preg_match('/^[a-f0-9]{48}\.jpg$/D', $view) || $view === $detail['reference'] || in_array($view, $views, true)) {
                    throw new \LogicException('Vue de référence invalide.');
                }
                $views[] = $view;
            }
        }
        $this->inputSettings['subjectDetails'] = $details;
    }

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $providerStage = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $providerCheckedAt = null;

    public function getProviderStage(): ?string
    {
        return $this->providerStage;
    }

    public function getProviderCheckedAt(): ?\DateTimeImmutable
    {
        return $this->providerCheckedAt;
    }

    public function observeProvider(string $stage): void
    {
        if ('processing' !== $this->status || !in_array($stage, ['queued', 'running', 'importing'], true)) {
            throw new \LogicException('Étape fournisseur invalide.');
        }
        $this->providerStage = $stage;
        // Observations must not reset the 45-minute recovery deadline.
        $this->providerCheckedAt = new \DateTimeImmutable();
    }

    public function __construct(User $owner, string $title, string $prompt, string $model, int $seconds, string $ratio, int $estimatedCents)
    {
        $this->owner = $owner;
        $this->title = $title;
        $this->prompt = $prompt;
        $this->model = $model;
        $this->inputSettings['contract'] = hash('sha256', serialize(\App\Service\AiCatalog::MODELS[$model]));
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

    public function revisePrepared(string $title, string $prompt): void
    {
        if ('prepared' !== $this->status) {
            throw new \RuntimeException('Cette génération est déjà confirmée. Son prompt ne peut plus être modifié.');
        }
        $this->title = $title;
        $this->prompt = $prompt;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getReviewRevision(): string
    {
        $data = [$this->title, $this->prompt, $this->model, $this->seconds, $this->ratio, $this->estimatedCents, $this->priceChecked, $this->referenceImage, $this->endReferenceImage, $this->imageFit, $this->accessMode];
        if ($this->inputSettings) {
            $data[] = $this->inputSettings;
        }

        return hash('sha256', serialize($data));
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
