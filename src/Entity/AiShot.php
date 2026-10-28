<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'episode_shot_position', columns: ['episode_id', 'position'])]
class AiShot
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AiEpisode $episode;
    #[ORM\Column]
    private int $position;
    #[ORM\Column(type: 'text')]
    private string $prompt = '';
    #[ORM\Column]
    private int $seconds;
    #[ORM\OneToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?AiGeneration $generation = null;
    /** @var list<int> */
    #[ORM\Column(type: 'json')]
    private array $previousGenerations = [];
    #[ORM\Column(length: 52, nullable: true)]
    private ?string $endAnchor = null;
    /** @var array{startReference?:?string,endReference?:?string,referenceFit?:string,imagePrompt?:string,sceneDescription?:string,translatedIdentity?:string,translationSource?:string,repairPrompt?:string,confirmedPrompt?:string} */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $inputs = [];

    /** @return array{startReference?:?string,endReference?:?string,referenceFit?:string,imagePrompt?:string,sceneDescription?:string,translatedIdentity?:string,translationSource?:string,repairPrompt?:string,confirmedPrompt?:string} */
    public function getInputs(): array
    {
        return $this->inputs;
    }

    /** @param array{startReference?:?string,endReference?:?string,referenceFit?:string,imagePrompt?:string,sceneDescription?:string,translatedIdentity?:string,translationSource?:string,repairPrompt?:string,confirmedPrompt?:string} $inputs */
    public function configureInputs(array $inputs): void
    {
        if ('draft' !== $this->episode->getFlowState()) {
            throw new \RuntimeException('Les références confirmées ne sont plus modifiables.');
        }
        $this->inputs = $inputs;
    }

    public function __construct(AiEpisode $episode, int $position, int $seconds)
    {
        $this->episode = $episode;
        $this->position = $position;
        $this->seconds = $seconds;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEpisode(): AiEpisode
    {
        return $this->episode;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getPrompt(): string
    {
        return $this->prompt;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function getGeneration(): ?AiGeneration
    {
        return $this->generation;
    }

    public function getEndAnchor(): ?string
    {
        return $this->endAnchor;
    }

    /** @return list<int> */
    public function getPreviousGenerations(): array
    {
        return $this->previousGenerations;
    }

    public function revise(string $prompt, int $seconds): void
    {
        if ('draft' !== $this->episode->getFlowState()) {
            throw new \RuntimeException('Les plans confirmés ne sont plus modifiables.');
        }
        $this->prompt = trim($prompt);
        $this->seconds = $seconds;
    }

    public function bind(AiGeneration $job): void
    {
        if ($this->generation || $job->getOwner()->getId() !== $this->episode->getOwner()->getId()) {
            throw new \LogicException('Ce plan possède déjà une génération.');
        }
        $this->generation = $job;
    }

    /** @param array{startReference?:?string,endReference?:?string,referenceFit?:string,imagePrompt?:string,sceneDescription?:string,translatedIdentity?:string,translationSource?:string} $inputs */
    public function correctUnsent(string $prompt, array $inputs): void
    {
        if ('review' !== $this->episode->getFlowState() || null !== $this->generation) {
            throw new \RuntimeException('Seul un plan non transmis en révision peut être corrigé.');
        }
        $this->prompt = trim($prompt);
        $this->inputs = $inputs;
    }

    public function stageRepair(string $prompt): void
    {
        if (!in_array($this->episode->getFlowState(), ['ready', 'paused'], true) || 'available' !== $this->generation?->getStatus()) {
            throw new \RuntimeException('Attends un plan terminé avant de préparer sa réparation.');
        }
        $this->inputs['repairPrompt'] = trim($prompt);
    }

    public function confirmPrompt(string $prompt): void
    {
        $this->inputs['confirmedPrompt'] = $prompt;
    }

    public function releaseUnsent(): void
    {
        if (!$this->generation || $this->generation->getRemote() || !in_array($this->generation->getStatus(), ['prepared', 'queued', 'waiting_key'], true)) {
            throw new \RuntimeException('Une demande transmise doit être récupérée avant de réviser les plans restants.');
        }
        $this->generation->progress('cancelled', 'Ancienne préparation remplacée avant envoi, sans nouvelle génération.');
        $this->previousGenerations[] = (int) $this->generation->getId();
        $this->generation = null;
        unset($this->inputs['confirmedPrompt']);
    }

    public function replace(?string $endAnchor, ?string $prompt = null): void
    {
        if (!$this->generation || 'available' !== $this->generation->getStatus() || !$this->generation->getVideo()) {
            throw new \RuntimeException('Seul un résultat terminé peut être refait. Un résultat incertain doit être récupéré, jamais renvoyé automatiquement.');
        }
        $this->previousGenerations[] = (int) $this->generation->getId();
        $this->generation = null;
        $this->endAnchor = $endAnchor;
        if (null !== $prompt) {
            $this->prompt = $prompt;
        }
        unset($this->inputs['repairPrompt'], $this->inputs['confirmedPrompt']);
    }
}
