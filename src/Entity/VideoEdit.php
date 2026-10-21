<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(columns: ['owner_id', 'updated_at'])]
class VideoEdit
{
    public const FORMATS = ['9:16' => [1080, 1920], '16:9' => [1920, 1080], '1:1' => [1080, 1080]];
    public const LABELS = ['draft' => 'À monter', 'transcribing' => 'Création des sous-titres', 'queued' => 'Export en attente', 'rendering' => 'Export en cours', 'ready' => 'Export terminé', 'failed' => 'À vérifier'];

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;
    #[ORM\Column(length: 120)]
    private string $title;
    /** @var list<array{id:int,start:float,end:float,origin?:string}> */
    #[ORM\Column(type: 'json')]
    private array $clips;
    /** @var array{fit:string,captionStyle:string,intro:bool,language?:string,sourceVolume?:float|int,musicVolume?:float|int,musicOffset?:float|int,musicLoop?:bool,captionX?:float,captionY?:float,captionSize?:float|int} */
    #[ORM\Column(type: 'json')]
    private array $settings = ['fit' => 'contain', 'captionStyle' => 'off', 'intro' => false, 'language' => 'fr'];
    /** @var list<array{start:float,end:float,text:string,x?:float,y?:float}> */
    #[ORM\Column(type: 'json')]
    private array $captions = [];
    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $texts = [];

    /** @return list<array<string, mixed>> */
    public function getTexts(): array
    {
        return $this->texts;
    }

    /** @param list<array<string, mixed>> $rows */
    public function textLayers(array $rows): void
    {
        $this->texts = $rows;
    }

    #[ORM\Column(length: 5)]
    private string $format = '9:16';
    #[ORM\Column(length: 12)]
    private string $status = 'draft';
    #[ORM\Column(length: 240, nullable: true)]
    private ?string $issue = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?VideoAsset $output = null;
    #[ORM\Column(length: 52, nullable: true)]
    private ?string $musicName = null;
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $musicTitle = null;
    #[ORM\Column(options: ['default' => 1])]
    private int $revision = 1;
    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function getRevision(): int
    {
        return $this->revision;
    }

    /** @var array{draft:int,revision:int,source:int,applied:bool}|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $publicationContext = null;

    /** @return array{draft:int,revision:int,source:int,applied:bool}|null */
    public function getPublicationContext(): ?array
    {
        return $this->publicationContext;
    }

    public function forPublication(int $draft, int $revision, int $source): void
    {
        $this->publicationContext = ['draft' => $draft, 'revision' => $revision, 'source' => $source, 'applied' => false];
    }

    public function appliedToPublication(): void
    {
        if ($this->publicationContext) {
            $this->publicationContext['applied'] = true;
        }
    }

    /** @param list<array{id:int,start:float,end:float,origin?:string}> $clips */
    public function __construct(User $owner, string $title, array $clips)
    {
        $this->owner = $owner;
        $this->title = $title;
        $this->clips = $clips;
        $this->updatedAt = new \DateTimeImmutable();
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

    /** @return list<array{id:int,start:float,end:float,origin?:string}> */
    public function getClips(): array
    {
        return array_map(static fn (array $clip, int $index): array => $clip + ['origin' => 'legacy-'.$index], $this->clips, array_keys($this->clips));
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getIssue(): ?string
    {
        return $this->issue;
    }

    public function getOutput(): ?VideoAsset
    {
        return $this->output;
    }

    public function getMusicName(): ?string
    {
        return $this->musicName;
    }

    public function getMusicTitle(): ?string
    {
        return $this->musicTitle;
    }

    public function music(?string $name, ?string $title): void
    {
        $this->musicName = $name;
        $this->musicTitle = $title;
        $this->progress('draft');
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getDuration(): float
    {
        return array_sum(array_map(static fn (array $clip): float => $clip['end'] - $clip['start'], $this->clips));
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['queued', 'rendering', 'transcribing'], true);
    }

    /** @return array{fit:string,captionStyle:string,intro:bool,language?:string,sourceVolume?:float|int,musicVolume?:float|int,musicOffset?:float|int,musicLoop?:bool,captionX?:float,captionY?:float,captionSize?:float|int} */
    public function getSettings(): array
    {
        return $this->settings + ['captionX' => 0.5, 'captionY' => 0.85, 'captionSize' => 4, 'language' => 'fr', 'sourceVolume' => 100, 'musicVolume' => 20, 'musicOffset' => 0, 'musicLoop' => true];
    }

    /** @return list<array{start:float,end:float,text:string,x?:float,y?:float}> */
    public function getCaptions(): array
    {
        return $this->captions;
    }

    /** @param array{fit:string,captionStyle:string,intro:bool,language?:string,sourceVolume?:float|int,musicVolume?:float|int,musicOffset?:float|int,musicLoop?:bool,captionX?:float,captionY?:float,captionSize?:float|int} $settings */
    public function configure(array $settings): void
    {
        $this->settings = $settings;
    }

    /** @param list<array{start:float,end:float,text:string,x?:float,y?:float}> $captions */
    public function caption(array $captions): void
    {
        $this->captions = $captions;
    }

    /** @param list<array{id:int,start:float,end:float,origin?:string}> $clips */
    public function revise(string $title, string $format, array $clips): void
    {
        $this->title = $title;
        $this->format = $format;
        $this->clips = $clips;
        $this->progress('draft');
    }

    public function progress(string $status, ?string $issue = null): void
    {
        ++$this->revision;
        $this->status = $status;
        $this->issue = $issue;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function complete(VideoAsset $video): void
    {
        $this->output = $video;
        $this->progress('ready');
    }
}
