<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_app_user_email', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 254)]
    private string $email;
    #[ORM\Column]
    private string $password = '';
    #[ORM\Column]
    private bool $verified = false;
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 80, options: ['default' => ''])]
    private string $displayName = '';
    #[ORM\Column(length: 64, options: ['default' => 'Europe/Paris'])]
    private string $timezone = 'Europe/Paris';
    #[ORM\Column(length: 52, nullable: true)]
    private ?string $avatarName = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $aiFavorites = [];

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $name): void
    {
        $this->displayName = $name;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function getAvatarName(): ?string
    {
        return $this->avatarName;
    }

    public function setAvatarName(?string $name): void
    {
        $this->avatarName = $name;
    }

    public function __construct(string $email)
    {
        $this->email = strtolower(trim($email));
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @return list<string> */
    public function getAiFavorites(): array
    {
        return $this->aiFavorites;
    }

    public function favoriteAiModel(string $id, bool $favorite): void
    {
        $this->aiFavorites = array_values(array_filter($this->aiFavorites, static fn (string $value): bool => $value !== $id));
        if ($favorite) {
            $this->aiFavorites[] = $id;
        }
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function verify(): void
    {
        $this->verified = true;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return $this->verified ? ['ROLE_USER', 'ROLE_VERIFIED'] : ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        $data = (array) $this;
        // Symfony checks this fingerprint on refresh: a reset invalidates old sessions.
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
