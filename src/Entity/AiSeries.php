<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class AiSeries
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $theme;

    #[ORM\Column(type: 'text')]
    private string $style;

    #[ORM\Column(type: 'text')]
    private string $characters;

    /** @var array{totalSeconds?:int,model:string, seconds:int, ratio:string, accessMode:string, reference:?string, endReference:?string, imageFit:string,inputVersion?:int,subjects?:list<string>,subjectDetails?:list<array{reference:string,name:string,role:string,presence:string,views:list<string>}>} */
    #[ORM\Column(type: 'json')]
    private array $recipe;
    /** @var array<string,string> */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $editorialProfile = [];

    /** @return array<string,string> */
    public function getEditorialProfile(): array
    {
        return $this->editorialProfile;
    }

    /** @param array<string,string> $profile */
    public function configureEditorial(array $profile): void
    {
        $this->editorialProfile = $profile;
        ++$this->revision;
    }

    #[ORM\Column]
    private int $revision = 1;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @param array{totalSeconds?:int,model:string, seconds:int, ratio:string, accessMode:string, reference:?string, endReference:?string, imageFit:string,inputVersion?:int,subjects?:list<string>,subjectDetails?:list<array{reference:string,name:string,role:string,presence:string,views:list<string>}>} $recipe */
    public function __construct(User $owner, string $name, string $theme, string $style, string $characters, array $recipe)
    {
        $this->owner = $owner;
        $this->name = $name;
        $this->theme = $theme;
        $this->style = $style;
        $this->characters = $characters;
        $this->recipe = $recipe;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    public function getStyle(): string
    {
        return $this->style;
    }

    public function getCharacters(): string
    {
        return $this->characters;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return array{totalSeconds?:int,model:string, seconds:int, ratio:string, accessMode:string, reference:?string, endReference:?string, imageFit:string,inputVersion?:int,subjects?:list<string>,subjectDetails?:list<array{reference:string,name:string,role:string,presence:string,views:list<string>}>} */
    public function getRecipe(): array
    {
        return $this->recipe;
    }

    /** @param array{totalSeconds?:int,model:string, seconds:int, ratio:string, accessMode:string, reference:?string, endReference:?string, imageFit:string,inputVersion?:int,subjects?:list<string>,subjectDetails?:list<array{reference:string,name:string,role:string,presence:string,views:list<string>}>} $recipe */
    public function configure(string $name, string $theme, string $style, string $characters, array $recipe): void
    {
        $this->name = $name;
        $this->theme = $theme;
        $this->style = $style;
        $this->characters = $characters;
        $this->recipe = $recipe;
        ++$this->revision;
    }

    public function initialPrompt(): string
    {
        return implode("\n\n", array_filter([$this->theme, '' !== $this->style ? 'Style : '.$this->style : '', '' !== $this->characters ? 'Personnages : '.$this->characters : '']));
    }

    public function episodesAdded(): void
    {
        ++$this->revision;
    }
}
