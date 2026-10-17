<?php

namespace App\Entity;

use App\Repository\PublicationDraftRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PublicationDraftRepository::class)]
#[ORM\Index(name: 'draft_owner_date', columns: ['owner_id', 'scheduled_at'])]
class PublicationDraft
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 120)]
    private string $title;
    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $networks = [];
    /** @var array<string, ?string> */
    #[ORM\Column(type: 'json')]
    private array $accounts = [];
    #[ORM\Column(length: 16)]
    private string $status = 'draft';
    #[ORM\Column]
    private int $revision = 1;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;
    #[ORM\Column(length: 64)]
    private string $timezone = 'Europe/Paris';
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $localTime = null;
    #[ORM\Column(length: 64, nullable: true, unique: true)]
    private ?string $submissionKey = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dispatchedAt = null;
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notice = null;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;
    /** @var Collection<int, PublicationDraftItem> */
    #[ORM\OneToMany(mappedBy: 'draft', targetEntity: PublicationDraftItem::class, cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    public function __construct(User $owner, string $title, \DateTimeImmutable $now)
    {
        $this->owner = $owner;
        $this->title = $title;
        $this->createdAt = $this->updatedAt = $now;
        $this->items = new ArrayCollection();
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

    /** @return list<string> */
    public function getNetworks(): array
    {
        return $this->networks;
    }

    /** @return array<string, ?string> */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function getLocalTime(): ?string
    {
        return $this->localTime;
    }

    public function getSubmissionKey(): ?string
    {
        return $this->submissionKey;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getDispatchedAt(): ?\DateTimeImmutable
    {
        return $this->dispatchedAt;
    }

    public function getNotice(): ?string
    {
        return $this->notice;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, PublicationDraftItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /** @param list<string> $networks @param array<string, ?string> $accounts */
    public function edit(string $title, array $networks, array $accounts, \DateTimeImmutable $now): void
    {
        $this->title = $title;
        $this->networks = $networks;
        $this->accounts = $accounts;
        $this->touch($now);
    }

    public function addItem(PublicationDraftItem $item): void
    {
        $this->items->add($item);
    }

    public function removeItem(PublicationDraftItem $item): void
    {
        $this->items->removeElement($item);
    }

    public function plan(?\DateTimeImmutable $date, string $timezone, ?string $localTime, \DateTimeImmutable $now): void
    {
        $this->scheduledAt = $date;
        $this->timezone = $timezone;
        $this->localTime = $localTime;
        $this->touch($now);
    }

    public function arm(\DateTimeImmutable $now): void
    {
        $this->status = 'scheduled';
        $this->submissionKey = bin2hex(random_bytes(32));
        $this->confirmedAt = $now;
        $this->touch($now);
    }

    public function beginImmediate(\DateTimeImmutable $now): void
    {
        $this->submissionKey = bin2hex(random_bytes(32));
        $this->confirmedAt = $now;
    }

    public function dispatched(\DateTimeImmutable $now): void
    {
        $this->status = 'dispatched';
        $this->dispatchedAt = $now;
        $this->notice = null;
        $this->touch($now);
    }

    public function change(string $status, ?string $notice, \DateTimeImmutable $now): void
    {
        $this->status = $status;
        $this->notice = $notice;
        if ('draft' === $status) {
            $this->confirmedAt = null;
            $this->submissionKey = null;
        }
        $this->touch($now);
    }

    private function touch(\DateTimeImmutable $now): void
    {
        ++$this->revision;
        $this->updatedAt = $now;
    }

    /** @return list<int> */
    public function videoIds(): array
    {
        return array_map(static fn (PublicationDraftItem $item): int => $item->getVideoId(), $this->items->toArray());
    }

    /** @return array<string, array<string, mixed>> */
    public function content(): array
    {
        $content = [];
        foreach ($this->items as $item) {
            foreach ($item->getContent() as $network => $row) {
                $content[$network]['videos'][$item->getVideoId()] = $row;
            }
        }

        return $content;
    }
}
