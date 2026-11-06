<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'video_series_episode_series', columns: ['series_id'])]
#[ORM\Index(name: 'video_series_episode_video', columns: ['video_id'])]
#[ORM\UniqueConstraint(name: 'video_series_position', columns: ['series_id', 'position'])]
#[ORM\UniqueConstraint(name: 'video_series_video', columns: ['series_id', 'video_id'])]
class VideoSeriesEpisode
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private VideoSeries $series;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private VideoAsset $video;
    #[ORM\Column]
    private int $position;

    public function __construct(VideoSeries $series, VideoAsset $video, int $position)
    {
        if ($series->getOwner()->getId() !== $video->getOwner()->getId()) {
            throw new \InvalidArgumentException('Vidéo indisponible.');
        }
        $this->series = $series;
        $this->video = $video;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeries(): VideoSeries
    {
        return $this->series;
    }

    public function getVideo(): VideoAsset
    {
        return $this->video;
    }

    public function getPosition(): int
    {
        return $this->position;
    }
}
