<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'story_owner_scope', columns: ['owner_id', 'scope', 'created_at'])]
class StoryProposal
{
    #[ORM\Id, ORM\Column(length: 32)]
    private string $id;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column]
    private int $scope;
    #[ORM\Column(type: 'text')]
    private string $fingerprint;
    /** @var array<string,mixed> */
    #[ORM\Column(type: 'json')]
    private array $input;
    /** @var array<string,mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $result = null;
    #[ORM\Column(length: 16)]
    private string $status = 'queued';
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @param array<string,mixed> $input */
    public function __construct(User $owner, int $scope, string $fingerprint, array $input)
    {
        // Keep latest deterministic when several jobs share a database timestamp second.
        $this->id = sprintf('%014x', (int) (microtime(true) * 1000000)).bin2hex(random_bytes(9));
        $this->owner = $owner;
        $this->scope = $scope;
        $this->fingerprint = $fingerprint;
        $this->input = $input;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getScope(): int
    {
        return $this->scope;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return array<string,mixed> */
    public function getInput(): array
    {
        return $this->input;
    }

    /** @return array<string,mixed>|null */
    public function getResult(): ?array
    {
        return $this->result;
    }

    public function phase(string $phase): void
    {
        $this->status = $phase;
    }

    /** @param array<string,mixed> $result */
    public function complete(array $result): void
    {
        $this->result = $result;
        $this->input = [];
        $this->status = 'ready';
    }

    public function fail(string $error): void
    {
        $this->error = $error;
        $this->input = [];
        $this->status = 'failed';
    }
}
