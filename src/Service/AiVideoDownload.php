<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiVideoDownload
{
    public function __construct(#[Autowire(service: 'ai.http')] private HttpClientInterface $http)
    {
    }

    public function download(string $url, string $provider): string
    {
        // Result requests never carry the API key; redirects cannot escape the allowlist.
        $allowed = match ($provider) {
            'fal' => preg_match('~^https://(?:[a-z0-9-]+\.)?fal\.media/[^\s#]+$~D', $url) || preg_match('~^https://storage\.googleapis\.com/falserverless/[^\s#]+$~D', $url),
            'replicate' => preg_match('~^https://(?:[a-z0-9-]+\.)?replicate\.delivery/[^\s#]+$~D', $url),
            // Official async API returns public/signed Google Storage URLs; never send the LTX key.
            'ltx' => preg_match('~^https://storage\.googleapis\.com/[^\s#]+$~D', $url),
            default => false,
        };
        if (!$allowed) {
            throw new \RuntimeException('Le domaine du résultat n’est pas pris en charge.');
        }
        $path = tempnam(sys_get_temp_dir(), 'vf-ai-');
        if (false === $path) {
            throw new \RuntimeException('Stockage temporaire indisponible.');
        }
        $handle = fopen($path, 'wb');
        try {
            $response = $this->http->request('GET', $url, ['max_redirects' => 0, 'timeout' => 20, 'max_duration' => 90]);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException('Résultat temporairement indisponible.');
            }
            $bytes = 0;
            foreach ($this->http->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new \RuntimeException('Téléchargement interrompu.');
                }
                $content = $chunk->getContent();
                $bytes += strlen($content);
                if ($bytes > VideoLibrary::MAX_BYTES || false === fwrite($handle, $content)) {
                    throw new \RuntimeException('La vidéo dépasse la limite de 100 Mo.');
                }
            }
            fclose($handle);

            return $path;
        } catch (\Throwable $error) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            @unlink($path);
            throw $error;
        }
    }
}
