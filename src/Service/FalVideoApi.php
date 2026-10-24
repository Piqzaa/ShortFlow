<?php

namespace App\Service;

use App\Entity\AiGeneration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FalVideoApi
{
    public function __construct(#[Autowire(service: 'ai.http')] private HttpClientInterface $http, private AiCatalog $catalog, private AiReferenceStorage $references)
    {
    }

    /** @return array<string,mixed> */
    public function input(AiGeneration $job): array
    {
        $model = $this->catalog->model($job->getModel());
        $this->catalog->validate($job->getModel(), $job->getSeconds(), $job->getRatio());
        $this->catalog->validatePrompt($job->getModel(), $job->getPrompt());
        $payload = ['prompt' => $job->getPrompt(), 'duration' => (string) $job->getSeconds(), 'aspect_ratio' => $job->getRatio(), 'enable_safety_checker' => true];
        $payload = array_replace($payload, $model['input'] ?? []);
        if ('integer' === ($model['durationType'] ?? '')) {
            $payload['duration'] = $job->getSeconds();
        } elseif ('none' === ($model['durationType'] ?? '')) {
            unset($payload['duration']);
        }
        if ($model['omitRatio'] ?? false) {
            unset($payload['aspect_ratio']);
        }
        if (str_starts_with($model['endpoint'], 'bytedance/seedance-2.5/')) {
            $payload['end_user_id'] = hash('sha256', 'vydflow-user:'.$job->getOwner()->getId());
            unset($payload['enable_safety_checker']);
        }
        if ('wan-25' === $job->getModel()) {
            $payload['resolution'] = '720p';
            $payload['enable_prompt_expansion'] = false;
        }
        if ('kling-3' === $job->getModel()) {
            $payload['generate_audio'] = true;
            $payload['shot_type'] = 'customize';
        }

        if ($model['omitSafety'] ?? false) {
            unset($payload['enable_safety_checker']);
        }
        if ($model['image'] ?? false) {
            if ($model['subjectReference'] ?? false) {
                if (null !== $job->getEndReferenceImage()) {
                    throw new \RuntimeException('Une référence de personnage ne prend pas d’image de fin.');
                }
                // Keep the whole subject; this is an identity guide, not a first frame.
                $names = array_values(array_unique([$job->getReferenceImage(), ...($job->getInputSettings()['subjects'] ?? [])]));
                if (count($names) > $this->catalog->subjectLimit($job->getModel())) {
                    throw new \RuntimeException('Trop de références de personnages pour ce modèle.');
                }
                $payload['reference_image_urls'] = array_map(fn (string $name): string => $this->references->dataUri($job->getOwner(), $name), $names);

                return $payload;
            }
            if ($job->getInputSettings()['subjects'] ?? []) {
                if ('kling-3-image' !== $job->getModel() || count($job->getInputSettings()['subjects']) > 2) {
                    throw new \RuntimeException('Les références de personnages ne sont pas compatibles avec cet endpoint.');
                }
                $payload['elements'] = array_map(fn (string $name): array => ['frontal_image_url' => $this->references->dataUri($job->getOwner(), $name)], $job->getInputSettings()['subjects']);
            }
            $first = $this->references->dataUri($job->getOwner(), $job->getReferenceImage() ?? '', $job->getRatio(), $job->getImageFit());
            $endName = $job->getEndReferenceImage();
            if (($model['requiresEnd'] ?? false) && null === $endName) {
                throw new \RuntimeException('Ce modèle nécessite une image de fin.');
            }
            $last = null !== $endName ? $this->references->dataUri($job->getOwner(), $endName, $job->getRatio(), $job->getImageFit(), 'auto' === $job->getRatio() ? $job->getReferenceImage() : null) : null;
            $field = $model['imageField'] ?? 'image_url';
            if ('image_urls' === $field) {
                $payload[$field] = [$first, $last];
                $payload['transitions'] = [['duration' => $job->getSeconds(), 'prompt' => $job->getPrompt()]];
            } else {
                $payload[$field] = $first;
                if (null !== $last) {
                    $endField = $model['endImageField'] ?? throw new \RuntimeException('Image de fin incompatible.');
                    $payload[$endField] = $last;
                }
            }
        }

        return $payload;
    }

    /** @return array<string,string> */
    public function submit(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        $model = $this->catalog->model($job->getModel());
        $data = $this->http->request('POST', 'https://queue.fal.run/'.$model['endpoint'], ['headers' => ['Authorization' => 'Key '.$key], 'json' => $this->input($job), 'max_redirects' => 0, 'timeout' => 30, 'max_duration' => 45])->toArray();
        $remote = [];
        foreach (['status_url', 'response_url'] as $field) {
            if (!is_string($data[$field] ?? null)) {
                throw new \RuntimeException('Réponse fournisseur incomplète.');
            }
            $this->assertQueueUrl($data[$field], $job);
            $remote[$field] = $data[$field];
        }

        return $remote;
    }

    /** @return array{ready:bool,url?:string,stage?:string} */
    public function poll(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        $remote = $job->getRemote();
        $this->assertQueueUrl($remote['status_url'], $job);
        $options = ['headers' => ['Authorization' => 'Key '.$key], 'max_redirects' => 0, 'timeout' => 20, 'max_duration' => 30];
        $status = $this->http->request('GET', $remote['status_url'], $options)->toArray();
        if (in_array($status['status'] ?? '', ['IN_QUEUE', 'IN_PROGRESS'], true)) {
            return ['ready' => false, 'stage' => 'IN_QUEUE' === $status['status'] ? 'queued' : 'running'];
        }
        if ('COMPLETED' !== ($status['status'] ?? '') || isset($status['error'])) {
            throw new \RuntimeException('Le fournisseur a refusé ou interrompu la génération.');
        }
        $this->assertQueueUrl($remote['response_url'], $job);
        $result = $this->http->request('GET', $remote['response_url'], $options)->toArray();
        $url = $result['video']['url'] ?? null;
        if (!is_string($url)) {
            throw new \RuntimeException('Le fournisseur ne renvoie pas de vidéo.');
        }

        return ['ready' => true, 'url' => $url];
    }

    private function assertQueueUrl(string $url, AiGeneration $job): void
    {
        // Fal queue references use either the full endpoint or its owner/application prefix.
        $endpoint = $this->catalog->model($job->getModel())['endpoint'];
        $application = implode('/', array_slice(explode('/', $endpoint), 0, 2));
        $prefix = preg_quote($application, '~');
        if (!preg_match('~^https://queue\.fal\.run/'.$prefix.'(?:/[a-zA-Z0-9_-][a-zA-Z0-9._-]*)*/requests/[a-zA-Z0-9_-]+(?:/status|/response)?$~D', $url)) {
            throw new \RuntimeException('Adresse fournisseur invalide.');
        }
    }
}
