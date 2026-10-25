<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'ai_episode_number', columns: ['series_id', 'number'])]
class AiEpisode
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?AiSeries $series = null;

    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column]
    private int $number;

    #[ORM\Column(length: 120)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $prompt;

    #[ORM\Column]
    private int $revision = 1;

    #[ORM\OneToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?AiGeneration $generation = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $flowState = null;

    /** @var array{totalSeconds?:int,model:string,seconds:int,ratio:string,accessMode:string,reference:?string,endReference:?string,imageFit:string,inputVersion?:int,subjects?:list<string>}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $flowRecipe = null;

    #[ORM\Column(length: 16, options: ['default' => 'independent'])]
    private string $continuity = 'independent';

    #[ORM\Column(options: ['default' => false])]
    private bool $mutePlans = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $flowIssue = null;
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $flowTariff = null;
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $flowAccount = null;
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $consentHash = null;

    public function getFlowTariff(): ?string
    {
        return $this->flowTariff;
    }

    public function getFlowAccount(): ?string
    {
        return $this->flowAccount;
    }

    public function getConsentHash(): ?string
    {
        return $this->consentHash;
    }

    public function consent(string $tariff, ?string $account, string $hash): void
    {
        $this->flowTariff = $tariff;
        $this->flowAccount = $account;
        $this->consentHash = $hash;
        $this->flow('running');
    }

    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?VideoEdit $assembly = null;

    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?VideoAsset $approvedVideo = null;

    public function getFlowState(): ?string
    {
        return $this->flowState;
    }

    public function getContinuity(): string
    {
        return $this->continuity;
    }

    public function isMutePlans(): bool
    {
        return $this->mutePlans;
    }

    public function getFlowIssue(): ?string
    {
        return $this->flowIssue;
    }

    public function getAssembly(): ?VideoEdit
    {
        return $this->assembly;
    }

    public function getApprovedVideo(): ?VideoAsset
    {
        return $this->approvedVideo;
    }

    /** @return array{totalSeconds?:int,model:string,seconds:int,ratio:string,accessMode:string,reference:?string,endReference:?string,imageFit:string,inputVersion?:int,subjects?:list<string>}|null */
    public function getFlowRecipe(): ?array
    {
        return $this->flowRecipe;
    }

    public function beginStory(string $continuity, bool $mute): void
    {
        if ($this->flowState || ($this->generation && 'prepared' !== $this->generation->getStatus())) {
            throw new \RuntimeException('Cet épisode possède déjà une préparation.');
        }
        $this->flowRecipe = $this->getPreparationRecipe();
        $this->generation?->progress('replaced', 'Préparation remplacée par les plans de cette vidéo. Aucun lancement avec cette ancienne estimation.');
        $this->continuity = $continuity;
        $this->mutePlans = $mute;
        $this->flowState = 'draft';
        ++$this->revision;
    }

    /** @param array{totalSeconds?:int,model:string,seconds:int,ratio:string,accessMode:string,reference:?string,endReference:?string,imageFit:string,inputVersion?:int,subjects?:list<string>} $recipe */
    public function configurePreparation(array $recipe, string $context, string $continuity, bool $mute): void
    {
        if ($this->flowState && 'draft' !== $this->flowState) {
            throw new \RuntimeException('Cette vidéo a déjà été confirmée.');
        }
        if (!$this->flowState) {
            $this->beginStory($continuity, $mute);
        }
        $this->flowRecipe = $recipe;
        $this->prompt = trim($context);
        $this->continuity = $continuity;
        $this->mutePlans = $mute;
        $this->consentHash = null;
        ++$this->revision;
    }

    public function flow(string $state, ?string $issue = null): void
    {
        $this->flowState = $state;
        $this->flowIssue = $issue;
        ++$this->revision;
    }

    public function assembleWith(VideoEdit $edit): void
    {
        $this->assembly = $edit;
        $this->approvedVideo = null;
        $this->flow('assembling');
    }

    public function repair(): void
    {
        $this->assembly = null;
        $this->approvedVideo = null;
        $this->flow('running');
    }

    public function touchRepair(): void
    {
        if (!in_array($this->flowState, ['ready', 'paused'], true)) {
            throw new \RuntimeException('La vidéo n’est pas prête pour une réparation.');
        }
        ++$this->revision;
    }

    public function approve(VideoAsset $video): void
    {
        if ('ready' !== $this->flowState || $video->getOwner()->getId() !== $this->getOwner()->getId()) {
            throw new \RuntimeException('Valide un résultat disponible de ta bibliothèque.');
        }
        $this->approvedVideo = $video;
        ++$this->revision;
    }

    /** @param array{totalSeconds?:int,model:string,seconds:int,ratio:string,accessMode:string,reference:?string,endReference:?string,imageFit:string,inputVersion?:int,subjects?:list<string>}|null $recipe */
    public function __construct(AiSeries|User $series, int $number, string $title = '', string $prompt = '', ?array $recipe = null)
    {
        $this->series = $series instanceof AiSeries ? $series : null;
        $this->owner = $series instanceof User ? $series : null;
        $this->number = $number;
        $this->title = $this->series ? 'Épisode '.$number : $title;
        $this->prompt = $prompt;
        $this->flowRecipe = $this->series ? null : $recipe;
    }

    public function isStandalone(): bool
    {
        return null === $this->series;
    }

    public function getOwner(): User
    {
        return $this->owner ?? $this->getSeries()->getOwner();
    }

    /** @return array{totalSeconds?:int,model:string,seconds:int,ratio:string,accessMode:string,reference:?string,endReference:?string,imageFit:string,inputVersion?:int,subjects?:list<string>} */
    public function getPreparationRecipe(): array
    {
        if ($this->flowRecipe) {
            return $this->flowRecipe;
        }
        if ($job = $this->generation) {
            return ['model' => $job->getModel(), 'seconds' => $job->getSeconds(), 'ratio' => $job->getRatio(), 'accessMode' => $job->getAccessMode(), 'reference' => $job->getReferenceImage(), 'endReference' => $job->getEndReferenceImage(), 'imageFit' => $job->getImageFit()];
        }

        return $this->getSeries()->getRecipe();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): AiSeries
    {
        return $this->series ?? throw new \LogicException('Cette vidéo appartient directement au studio.');
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getGeneration(): ?AiGeneration
    {
        return $this->generation;
    }

    public function getStatusLabel(): string
    {
        if ($this->flowState) {
            return ['draft' => 'Plans à préparer', 'running' => 'Génération des plans', 'paused' => 'En pause', 'stopped' => 'Arrêté', 'assembling' => 'Assemblage en cours', 'ready' => $this->approvedVideo ? 'Validé' : 'À valider'][$this->flowState];
        }

        return $this->generation ? AiGeneration::LABELS[$this->generation->getStatus()] : 'À préparer';
    }

    public function edit(string $title, string $prompt): void
    {
        if ($this->flowState && 'draft' !== $this->flowState) {
            throw new \RuntimeException('Cet épisode a déjà été confirmé.');
        }
        if (!$this->flowState && null !== $this->generation && 'prepared' !== $this->generation->getStatus()) {
            throw new \RuntimeException('Cet épisode possède déjà une préparation. Retrouve-la depuis son suivi.');
        }
        $this->title = $title;
        $this->prompt = $prompt;
        if (!$this->flowState) {
            $this->generation?->revisePrepared($title, $prompt);
        }
        ++$this->revision;
    }

    public function prepare(AiGeneration $generation): void
    {
        if ($this->flowState || null !== $this->generation || $generation->getOwner()->getId() !== $this->series->getOwner()->getId()) {
            throw new \LogicException('Préparation indisponible.');
        }
        $this->generation = $generation;
        ++$this->revision;
    }
}
