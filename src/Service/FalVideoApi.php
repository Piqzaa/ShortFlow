<?php

namespace App\Service;

use App\Entity\AiGeneration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FalVideoApi
{
    public function __construct(#[Autowire(service: 'ai.http')] private HttpClientInterface $http, private AiCatalog $catalog)
    {
    }

    /** @return array<string,string> */
    public function submit(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        $model = $this->catalog->model($job->getModel());
        $payload = ['prompt' => $job->getPrompt(), 'duration' => (string) $job->getSeconds(), 'aspect_ratio' => $job->getRatio(), 'enable_safety_checker' => true];
        if ('wan-25' === $job->getModel()) {
            $payload['resolution'] = '720p';
            $payload['enable_prompt_expansion'] = false;
        }
        $data = $this->http->request('POST', 'https://queue.fal.run/'.$model['endpoint'], ['headers' => ['Authorization' => 'Key '.$key], 'json' => $payload, 'max_redirects' => 0, 'timeout' => 30, 'max_duration' => 45])->toArray();
        $remote = [];
        foreach (['status_url', 'response_url'] as $field) {
            if (!is_string($data[$field] ?? null)) {
                throw new \RuntimeException('Réponse fournisseur incomplète.');
            }
            $this->assertQueueUrl($data[$field]);
            $remote[$field] = $data[$field];
        }

        return $remote;
    }

    /** @return array{ready:bool,url?:string} */
    public function poll(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        $remote = $job->getRemote();
        $this->assertQueueUrl($remote['status_url']);
        $options = ['headers' => ['Authorization' => 'Key '.$key], 'max_redirects' => 0, 'timeout' => 20, 'max_duration' => 30];
        $status = $this->http->request('GET', $remote['status_url'], $options)->toArray();
        if (in_array($status['status'] ?? '', ['IN_QUEUE', 'IN_PROGRESS'], true)) {
            return ['ready' => false];
        }
        if ('COMPLETED' !== ($status['status'] ?? '') || isset($status['error'])) {
            throw new \RuntimeException('Le fournisseur a refusé ou interrompu la génération.');
        }
        $this->assertQueueUrl($remote['response_url']);
        $result = $this->http->request('GET', $remote['response_url'], $options)->toArray();
        $url = $result['video']['url'] ?? null;
        if (!is_string($url)) {
            throw new \RuntimeException('Le fournisseur ne renvoie pas de vidéo.');
        }

        return ['ready' => true, 'url' => $url];
    }

    private function assertQueueUrl(string $url): void
    {
        if (!preg_match('~^https://queue\.fal\.run/fal-ai/(kling-video|wan-25-preview)(/[a-zA-Z0-9._/-]+)?/requests/[a-zA-Z0-9_-]+(/status|/response)?$~D', $url)) {
            throw new \RuntimeException('Adresse fournisseur invalide.');
        }
    }

    public function download(string $url): string
    {
        // Result requests never carry the API key; redirects cannot escape the allowlist.
        if (!preg_match('~^https://(?:[a-z0-9-]+\.)?fal\.media/[^\s#]+$~D', $url) && !preg_match('~^https://storage\.googleapis\.com/falserverless/[^\s#]+$~D', $url)) {
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
