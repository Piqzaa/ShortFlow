<?php

namespace App\Service;

use App\Entity\TikTokPublication;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class TikTokMediaLink
{
    public function __construct(#[Autowire('%env(TIKTOK_MEDIA_BASE_URL)%')] private string $baseUrl, #[Autowire('%kernel.secret%')] private string $secret, #[Autowire('%env(bool:TIKTOK_URL_VERIFIED)%')] private bool $verified, #[Autowire('%env(bool:TIKTOK_PUBLISH_ENABLED)%')] private bool $enabled)
    {
    }

    public function isReady(): bool
    {
        $parts = parse_url($this->baseUrl);

        return $this->verified && $this->enabled && is_array($parts) && 'https' === ($parts['scheme'] ?? '') && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment']) && '' !== $this->secret;
    }

    public function url(TikTokPublication $job): string
    {
        if (!$this->isReady()) {
            throw new PublicationException('L’envoi TikTok attend la configuration HTTPS de VydFlow.');
        }
        $expires = $job->getCreatedAt()->getTimestamp() + 86400;

        return rtrim($this->baseUrl, '/').'/media/tiktok/'.$job->getId().'/'.$expires.'/'.$this->signature((int) $job->getId(), $expires);
    }

    public function valid(TikTokPublication $job, int $expires, string $signature): bool
    {
        return $this->isReady() && $expires > time() && $expires === $job->getCreatedAt()->getTimestamp() + 86400 && hash_equals($this->signature((int) $job->getId(), $expires), $signature) && in_array($job->getStatus(), ['queued', 'processing', 'publishing', 'paused', 'review'], true);
    }

    private function signature(int $id, int $expires): string
    {
        return hash_hmac('sha256', 'tiktok-media:'.$id.':'.$expires, $this->secret);
    }
}
