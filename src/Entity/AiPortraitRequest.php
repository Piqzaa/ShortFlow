<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'portrait_once', columns: ['draft_id', 'quote_hash'])]
#[ORM\Index(name: 'portrait_request_owner', columns: ['owner_id', 'id'])]
class AiPortraitRequest
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AiPortraitDraft $draft;
    #[ORM\Column(length: 64)]
    private string $quoteHash;
    #[ORM\Column(type: Types::JSON)]
    private array $snapshot;
    #[ORM\Column(length: 16)]
    private string $status = 'queued';
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $remote = null;
    #[ORM\Column(length: 52, nullable: true)]
    private ?string $resultReference = null;
    #[ORM\ManyToOne, ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?AiCharacter $acceptedCharacter = null;
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $lease = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $issue = null;
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $resultMeta = null;
    #[ORM\Column(length: 64)]
    private string $recoveryConnection;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $owner, AiPortraitDraft $draft, array $snapshot, \DateTimeImmutable $now)
    {
        $this->owner = $owner;
        $this->draft = $draft;
        $this->snapshot = $snapshot;
        $this->quoteHash = $snapshot['hash'];
        $this->recoveryConnection = $snapshot['connection'];
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getDraft(): AiPortraitDraft
    {
        return $this->draft;
    }

    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRemote(): ?array
    {
        return $this->remote;
    }

    public function getResultReference(): ?string
    {
        return $this->resultReference;
    }

    public function getAcceptedCharacter(): ?AiCharacter
    {
        return $this->acceptedCharacter;
    }

    public function getLease(): ?string
    {
        return $this->lease;
    }

    public function getIssue(): ?string
    {
        return $this->issue;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getRecoveryConnection(): string
    {
        return $this->recoveryConnection;
    }

    public function getResultMeta(): ?array
    {
        return $this->resultMeta;
    }

    public function state(string $status, \DateTimeImmutable $now, ?string $issue = null, ?string $lease = null): void
    {
        $this->status = $status;
        $this->updatedAt = $now;
        $this->issue = $issue;
        $this->lease = $lease;
    }

    public function receipt(array $remote): void
    {
        $this->remote = $remote;
    }

    public function metadata(array $meta): void
    {
        $this->resultMeta = $meta;
    }

    public function recoveredWith(string $connection): void
    {
        $this->recoveryConnection = $connection;
    }

    public function image(string $reference): void
    {
        $this->resultReference = $reference;
    }

    public function accept(AiCharacter $character): void
    {
        $this->acceptedCharacter = $character;
    }

    public function discard(): void
    {
        $this->resultReference = null;
    }
}
