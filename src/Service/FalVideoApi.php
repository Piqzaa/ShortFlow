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
        $this->catalog->validate($job->getModel(), $job->getSeconds(), $job->getRatio());
        $payload = ['prompt' => $job->getPrompt(), 'duration' => (string) $job->getSeconds(), 'aspect_ratio' => $job->getRatio(), 'enable_safety_checker' => true];
        if ('wan-25' === $job->getModel()) {
            $payload['resolution'] = '720p';
            $payload['enable_prompt_expansion'] = false;
        }
        if ('kling-3' === $job->getModel()) {
            $payload['generate_audio'] = true;
            $payload['shot_type'] = 'customize';
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
}
