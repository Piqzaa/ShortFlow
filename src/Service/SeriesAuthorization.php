<?php

namespace App\Service;

final class SeriesAuthorization
{
    public const VERSION = 2;

    public function __construct(private AiCatalog $catalog)
    {
    }

    /** @param array<string,mixed> $recipe @param list<int> $durations */
    public function fingerprint(array $recipe, string $brief, array $durations, int $cents, string $identity): string
    {
        return hash('sha256', serialize([self::VERSION, $recipe, $brief, $durations, $cents, $identity, $this->catalog->contract($recipe['model']), AiShotPrompts::VERSION]));
    }
}
