<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'series_occurrence_slot', columns: ['recurrence_id', 'scheduled_at'])]
class SeriesOccurrence
{
    /** @var array<string,?string> */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $accounts = [];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SeriesRecurrence $recurrence;
    #[ORM\OneToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?AiEpisode $episode = null;
    #[ORM\Column]
    private \DateTimeImmutable $scheduledAt;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(length: 64)]
    private string $timezone;
    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $networks;
    #[ORM\Column(length: 12)]
    private string $status = 'writing';
    #[ORM\Column(length: 700, nullable: true)]
    private ?string $summary = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $issue = null;
    #[ORM\Column]
    private int $reservedCents = 0;
    #[ORM\Column]
    private int $authorizationRevision;
    #[ORM\Column]
    private bool $automatic = true;

    /** @param list<string> $networks @param array<string,?string> $accounts */
    public function __construct(SeriesRecurrence $recurrence, \DateTimeImmutable $scheduledAt, string $timezone, array $networks, \DateTimeImmutable $now, ?AiEpisode $episode = null, array $accounts = [])
    {
        $this->recurrence = $recurrence;
        $this->scheduledAt = $scheduledAt;
        $this->timezone = $timezone;
        $this->networks = $networks;
        $this->accounts = $accounts;
        $this->createdAt = $now;
        $this->episode = $episode;
        $this->authorizationRevision = $recurrence->getRevision();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecurrence(): SeriesRecurrence
    {
        return $this->recurrence;
    }

    public function getEpisode(): ?AiEpisode
    {
        return $this->episode;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    /** @return list<string> */
    public function getNetworks(): array
    {
        return $this->networks;
    }

    /** @return array<string,?string> */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function getIssue(): ?string
    {
        return $this->issue;
    }

    public function getReservedCents(): int
    {
        return $this->reservedCents;
    }

    public function getAuthorizationRevision(): int
    {
        return $this->authorizationRevision;
    }

    public function isAutomatic(): bool
    {
        return $this->automatic;
    }

    public function manageManually(): void
    {
        $this->automatic = false;
        if (in_array($this->status, ['writing', 'attention'], true)) {
            $this->status = 'manual';
            $this->issue = null;
        }
    }

    public function story(string $summary): void
    {
        $this->summary = $summary;
    }

    public function produced(int $cents): void
    {
        $this->reservedCents = $cents;
        $this->status = 'production';
    }

    public function fail(string $issue): void
    {
        $this->status = 'attention';
        $this->issue = mb_substr($issue, 0, 255);
    }
}
