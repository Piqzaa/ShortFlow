<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PublicationStatisticsAccess
{
    public function __construct(
        #[Autowire('%env(bool:YOUTUBE_STATISTICS_ENABLED)%')] private bool $youtube,
        #[Autowire('%env(bool:INSTAGRAM_STATISTICS_ENABLED)%')] private bool $instagram,
        #[Autowire('%env(bool:TIKTOK_STATISTICS_ENABLED)%')] private bool $tiktok,
    ) {
    }

    public function enabled(string $network): bool
    {
        return $this->networks()[$network] ?? false;
    }

    /** @return array<string, bool> */
    public function networks(): array
    {
        return ['youtube' => $this->youtube, 'instagram' => $this->instagram, 'tiktok' => $this->tiktok];
    }
}
