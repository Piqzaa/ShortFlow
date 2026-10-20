<?php

namespace App\Service;

use App\Entity\AiGeneration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ReplicateVideoApi
{
    public function __construct(#[Autowire(service: 'ai.http')] private HttpClientInterface $http, private AiCatalog $catalog, private AiReferenceStorage $references)
    {
    }

    /** @return array<string,string> */
    public function submit(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        $model = $this->catalog->model($job->getModel());
        $this->catalog->validate($job->getModel(), $job->getSeconds(), $job->getRatio());
        $input = ['prompt' => $job->getPrompt(), 'duration' => $job->getSeconds()];
        if ($model['image'] ?? false) {
            $input += $model['input'] ?? [];
            $input[$model['imageField'] ?? 'image'] = $this->references->dataUri($job->getOwner(), $job->getReferenceImage() ?? '', $job->getRatio(), $job->getImageFit());
            if (isset($model['endImageField'])) {
                $input['aspect_ratio'] = $job->getRatio();
                if (null !== $job->getEndReferenceImage()) {
                    $input[$model['endImageField']] = $this->references->dataUri($job->getOwner(), $job->getEndReferenceImage(), $job->getRatio(), $job->getImageFit(), 'auto' === $job->getRatio() ? $job->getReferenceImage() : null);
                }
            }
        } else {
            $input += match ($job->getModel()) {
                'wan-25-replicate' => ['size' => '9:16' === $job->getRatio() ? '720*1280' : '1280*720', 'enable_prompt_expansion' => false],
                'veo-31-fast-replicate' => ['aspect_ratio' => $job->getRatio(), 'resolution' => '720p', 'generate_audio' => true],
                default => throw new \LogicException('Schéma Replicate indisponible.'),
            };
        }
        $data = $this->http->request('POST', 'https://api.replicate.com/v1/models/'.$model['endpoint'].'/predictions', [...$this->options($key), 'json' => ['input' => $input]])->toArray();
        $id = $data['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $id)) {
            throw new \RuntimeException('Référence fournisseur invalide.');
        }

        // Construct from a validated ID, never send the credential to response-provided URLs.
        return ['prediction_id' => $id];
    }

    /** @return array{ready:bool,url?:string} */
    public function poll(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        $id = $job->getRemote()['prediction_id'] ?? '';
        if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $id)) {
            throw new \RuntimeException('Référence fournisseur invalide.');
        }
        $data = $this->http->request('GET', 'https://api.replicate.com/v1/predictions/'.$id, $this->options($key))->toArray();
        if (in_array($data['status'] ?? '', ['starting', 'processing'], true)) {
            return ['ready' => false];
        }
        if ('succeeded' !== ($data['status'] ?? '') || !is_string($data['output'] ?? null) || null !== ($data['error'] ?? null)) {
            throw new \RuntimeException('Le fournisseur a refusé ou interrompu la génération.');
        }

        return ['ready' => true, 'url' => $data['output']];
    }

    /** @return array<string,mixed> */
    private function options(#[\SensitiveParameter] string $key): array
    {
        return ['headers' => ['Authorization' => 'Bearer '.$key], 'max_redirects' => 0, 'timeout' => 30, 'max_duration' => 45];
    }
}
