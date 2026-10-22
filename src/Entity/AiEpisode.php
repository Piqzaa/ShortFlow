<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'ai_episode_number', columns: ['series_id', 'number'])]
class AiEpisode
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AiSeries $series;

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

    public function __construct(AiSeries $series, int $number)
    {
        $this->series = $series;
        $this->number = $number;
        $this->title = 'Épisode '.$number;
        $this->prompt = $series->initialPrompt();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): AiSeries
    {
        return $this->series;
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
        return $this->generation ? AiGeneration::LABELS[$this->generation->getStatus()] : 'À préparer';
    }

    public function edit(string $title, string $prompt): void
    {
        if (null !== $this->generation && 'prepared' !== $this->generation->getStatus()) {
            throw new \RuntimeException('Cet épisode possède déjà une préparation. Retrouve-la depuis son suivi.');
        }
        $this->title = $title;
        $this->prompt = $prompt;
        $this->generation?->revisePrepared($title, $prompt);
        ++$this->revision;
    }

    public function prepare(AiGeneration $generation): void
    {
        if (null !== $this->generation || $generation->getOwner()->getId() !== $this->series->getOwner()->getId()) {
            throw new \LogicException('Préparation indisponible.');
        }
        $this->generation = $generation;
        ++$this->revision;
    }
}
