<?php

namespace App\Service;

use App\Entity\InstagramPublication;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class InstagramMediaLink
{
    public function __construct(#[Autowire('%env(INSTAGRAM_MEDIA_BASE_URL)%')] private string $baseUrl, #[Autowire('%kernel.secret%')] private string $secret)
    {
    }

    public function isReady(): bool
    {
        $parts = parse_url($this->baseUrl);

        return is_array($parts) && 'https' === ($parts['scheme'] ?? '') && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment']) && '' !== $this->secret;
    }

    public function url(InstagramPublication $job): string
    {
        if (!$this->isReady()) {
            throw new PublicationException('L’envoi Instagram attend la configuration HTTPS de VydFlow.');
        }
        $expires = $job->getCreatedAt()->getTimestamp() + 86400;

        return rtrim($this->baseUrl, '/').'/media/instagram/'.$job->getId().'/'.$expires.'/'.$this->signature((int) $job->getId(), $expires);
    }

    public function valid(InstagramPublication $job, int $expires, string $signature): bool
    {
        return $this->isReady() && $expires > time() && $expires === $job->getCreatedAt()->getTimestamp() + 86400 && hash_equals($this->signature((int) $job->getId(), $expires), $signature) && in_array($job->getStatus(), ['queued', 'processing', 'publishing', 'paused'], true);
    }

    private function signature(int $id, int $expires): string
    {
        return hash_hmac('sha256', 'instagram-media:'.$id.':'.$expires, $this->secret);
    }
}
