<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PublicationDraftItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'items'), ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PublicationDraft $draft;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?VideoAsset $source;
    #[ORM\Column]
    private int $videoId;
    #[ORM\Column(length: 120)]
    private string $title;
    #[ORM\Column]
    private int $position;
    /** @var array<string, array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $content = [];

    public function __construct(PublicationDraft $draft, VideoAsset $source, int $position)
    {
        $this->draft = $draft;
        $this->source = $source;
        $this->videoId = (int) $source->getId();
        $this->title = $source->getTitle();
        $this->position = $position;
    }

    public function replaceSource(VideoAsset $source): void
    {
        $this->source = $source;
        $this->videoId = (int) $source->getId();
        $this->title = $source->getTitle();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): ?VideoAsset
    {
        return $this->source;
    }

    public function getVideoId(): int
    {
        return $this->videoId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** @return array<string, array<string, mixed>> */
    public function getContent(): array
    {
        return $this->content;
    }

    /** @param array<string, array<string, mixed>> $content */
    public function setContent(array $content): void
    {
        $this->content = $content;
    }
}
