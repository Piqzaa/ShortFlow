<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'publication_statistic')]
#[ORM\UniqueConstraint(name: 'uniq_publication_statistic', columns: ['network', 'publication_id'])]
class PublicationStatistic
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 16)]
    private string $network;
    #[ORM\Column]
    private int $publicationId;
    /** @var array{views: ?int, likes: ?int, comments: ?int}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $counts = null;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;
    #[ORM\Column(length: 64)]
    private string $requestKey;
    #[ORM\Column(length: 16)]
    private string $status = 'queued';
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notice = null;

    public function __construct(User $owner, string $network, int $publicationId)
    {
        $this->owner = $owner;
        $this->network = $network;
        $this->publicationId = $publicationId;
        $this->request();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getNetwork(): string
    {
        return $this->network;
    }

    public function getPublicationId(): int
    {
        return $this->publicationId;
    }

    public function getRequestKey(): string
    {
        return $this->requestKey;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function canRequest(): bool
    {
        return $this->requestedAt->getTimestamp() <= time() - 300;
    }

    public function request(): void
    {
        $this->requestKey = bin2hex(random_bytes(32));
        $this->requestedAt = new \DateTimeImmutable();
        $this->status = 'queued';
        $this->notice = null;
    }

    /** @param array{views: ?int, likes: ?int, comments: ?int} $counts */
    public function record(array $counts): void
    {
        $this->counts = $counts;
        $this->updatedAt = new \DateTimeImmutable();
        $this->status = 'ready';
        $this->notice = null;
    }

    public function unavailable(): void
    {
        $this->status = 'unavailable';
        $this->notice = 'Données indisponibles. Vérifie les autorisations du compte et réessaie plus tard.';
    }
}
