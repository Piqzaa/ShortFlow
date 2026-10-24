<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class SeriesRecurrence
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\OneToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AiSeries $series;
    /** @var array<string,mixed> */
    #[ORM\Column(type: 'json')]
    private array $settings = [];
    #[ORM\Column(length: 12)]
    private string $status = 'paused';
    #[ORM\Column]
    private int $revision = 1;
    #[ORM\Column]
    private int $started = 0;
    #[ORM\Column]
    private int $reservedCents = 0;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextAt = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notice = null;

    public function __construct(AiSeries $series)
    {
        $this->series = $series;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): AiSeries
    {
        return $this->series;
    }

    /** @return array<string,mixed> */
    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getStarted(): int
    {
        return $this->started;
    }

    public function getReservedCents(): int
    {
        return $this->reservedCents;
    }

    public function getNextAt(): ?\DateTimeImmutable
    {
        return $this->nextAt;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getNotice(): ?string
    {
        return $this->notice;
    }

    /** @param array<string,mixed> $settings */
    public function activate(array $settings, \DateTimeImmutable $next, \DateTimeImmutable $now): void
    {
        $this->settings = $settings;
        $this->nextAt = $next;
        $this->confirmedAt = $now;
        $this->started = $this->reservedCents = 0;
        $this->notice = null;
        $this->status = 'active';
        ++$this->revision;
    }

    public function advanceSchedule(?\DateTimeImmutable $next): void
    {
        ++$this->started;
        $this->nextAt = $next;
        if (null === $next) {
            $this->status = 'finished';
            $this->notice = 'Le nombre d’épisodes autorisés est atteint. Les résultats restent à valider.';
        }
    }

    public function reserve(int $cents): void
    {
        if ($cents > $this->settings['cents'] || $this->reservedCents + $cents > $this->settings['cents'] * $this->settings['limit']) {
            throw new \RuntimeException('L’estimation dépasse le montant autorisé. Vérifie la récurrence.');
        }
        $this->reservedCents += $cents;
    }

    public function pause(string $notice): void
    {
        $this->status = 'paused';
        $this->notice = mb_substr($notice, 0, 255);
        $this->nextAt = null;
        ++$this->revision;
    }
}
