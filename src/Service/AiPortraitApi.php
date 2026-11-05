<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiPortraitApi
{
    public function __construct(#[Autowire(service: 'ai.http')] private HttpClientInterface $http, #[Autowire('%env(bool:PORTRAIT_GENERATION_ENABLED)%')] private bool $enabled)
    {
    }

    private function json(string $method, string $url, string $key, ?array $input = null): array
    {
        if (!$this->enabled) {
            throw new \RuntimeException('La génération de portraits est désactivée.');
        }
        $options = ['headers' => ['Authorization' => 'Key '.$key], 'max_redirects' => 0, 'timeout' => 20, 'max_duration' => 45];
        if (null !== $input) {
            $options['json'] = $input;
        }
        $response = $this->http->request($method, $url, $options);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $response->cancel();
            throw new \RuntimeException('Réponse fournisseur indisponible.');
        }
        $raw = '';
        try {
            foreach ($this->http->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new \RuntimeException('Réponse interrompue.');
                }
                $raw .= $chunk->getContent();
                if (strlen($raw) > 65536) {
                    throw new \RuntimeException('Réponse trop volumineuse.');
                }
            }
            $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new \RuntimeException('Réponse invalide.');
            }

            return $data;
        } finally {
            $response->cancel();
        }
    }

    private function receipt(array $data): array
    {
        $id = $data['request_id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $id)) {
            throw new \RuntimeException('Reçu invalide.');
        }
        foreach (['status_url' => '/status', 'response_url' => ''] as $field => $suffix) {
            if (($data[$field] ?? null) !== 'https://queue.fal.run/fal-ai/flux-2-pro/requests/'.$id.$suffix) {
                throw new \RuntimeException('Adresse de suivi invalide.');
            }
        }

        return array_intersect_key($data, array_flip(['request_id', 'status_url', 'response_url']));
    }

    public function submit(array $snapshot, string $key): array
    {
        return $this->receipt($this->json('POST', 'https://queue.fal.run/fal-ai/flux-2-pro', $key, $snapshot['input']));
    }

    public function poll(array $receipt, string $key): ?array
    {
        $receipt = $this->receipt($receipt);
        $status = $this->json('GET', $receipt['status_url'], $key)['status'] ?? '';
        if (in_array($status, ['IN_QUEUE', 'IN_PROGRESS'], true)) {
            return null;
        }
        if ('COMPLETED' !== $status) {
            throw new \RuntimeException('État fournisseur inconnu.');
        }

        return $this->json('GET', $receipt['response_url'], $key);
    }

    public function imageUrl(array $result): string
    {
        if (!is_int($result['seed'] ?? null) || !isset($result['images']) || !is_array($result['images']) || !array_is_list($result['images']) || 1 !== count($result['images']) || !is_array($result['images'][0])) {
            throw new \RuntimeException('Le fournisseur doit renvoyer exactement un portrait et sa graine.');
        }
        $url = $result['images'][0]['url'] ?? null;
        if (!is_string($url) || strlen($url) > 4096) {
            throw new \RuntimeException('Image indisponible.');
        }
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if ('https' !== ($parts['scheme'] ?? '') || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment']) || (!preg_match('/^(?:[a-z0-9-]+\.)?fal\.media$/D', $host) && !('storage.googleapis.com' === $host && str_starts_with($parts['path'] ?? '', '/falserverless/')))) {
            throw new \RuntimeException('Hébergement du portrait refusé.');
        }

        return $url;
    }

    public function download(string $url): string
    {
        if (!$this->enabled) {
            throw new \RuntimeException('La génération de portraits est désactivée.');
        }
        // Recheck at the trust boundary; no provider credentials on media requests.
        $this->imageUrl(['seed' => 0, 'images' => [['url' => $url]]]);
        $path = tempnam(sys_get_temp_dir(), 'portrait-');
        if (false === $path) {
            throw new \RuntimeException('Stockage temporaire indisponible.');
        }
        $handle = null;
        $response = null;
        try {
            $handle = fopen($path, 'wb');
            if (false === $handle) {
                throw new \RuntimeException('Stockage temporaire indisponible.');
            }
            $response = $this->http->request('GET', $url, ['max_redirects' => 0, 'timeout' => 20, 'max_duration' => 45]);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException('Téléchargement indisponible.');
            }
            $bytes = 0;
            foreach ($this->http->stream($response) as $chunk) {
                if ($chunk->isTimeout()) {
                    throw new \RuntimeException('Téléchargement interrompu.');
                }
                $content = $chunk->getContent();
                $bytes += strlen($content);
                if ($bytes > 10485760 || fwrite($handle, $content) !== strlen($content)) {
                    throw new \RuntimeException('Portrait trop volumineux ou stockage indisponible.');
                }
            }

            return $path;
        } catch (\Throwable $error) {
            @unlink($path);
            throw $error;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            $response?->cancel();
        }
    }
}
