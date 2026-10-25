<?php

namespace App\Service;

use App\Entity\AiGeneration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class LtxVideoApi
{
    public function __construct(#[Autowire(service: 'ai.http')] private HttpClientInterface $http, private AiCatalog $catalog, private AiReferenceStorage $references, private ?NativeExtensions $extensions = null)
    {
    }

    /** @return array<string,string> */
    public function submit(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        $model = $this->catalog->model($job->getModel());
        $this->catalog->validate($job->getModel(), $job->getSeconds(), $job->getRatio());
        [$wide, $short] = match ($model['quality']) {
            '720p' => [1280, 720], '1080p' => [1920, 1080], '4k' => [3840, 2160],
            default => throw new \LogicException('Résolution LTX indisponible.'),
        };
        $resolution = match ($job->getRatio()) {
            '9:16' => $short.'x'.$wide, '16:9' => $wide.'x'.$short, '1:1' => $short.'x'.$short,
            default => throw new \LogicException('Format LTX indisponible.'),
        };
        $input = ['model' => $model['endpoint'], 'prompt' => $job->getPrompt(), 'duration' => $job->getSeconds(), 'resolution' => $resolution, 'fps' => 25, 'generate_audio' => true];
        $path = ($model['image'] ?? false) ? 'image-to-video' : 'text-to-video';
        if ($model['image'] ?? false) {
            $input['image_uri'] = $this->references->dataUri($job->getOwner(), $job->getReferenceImage() ?? '', $job->getRatio(), $job->getImageFit());
            if (null !== $job->getEndReferenceImage()) {
                $input['last_frame_uri'] = $this->references->dataUri($job->getOwner(), $job->getEndReferenceImage(), $job->getRatio(), $job->getImageFit());
            }
        }
        if ('extend' === ($model['operation'] ?? null)) {
            $path = 'extend';
            $input = ($this->extensions ?? throw new \LogicException('Extension indisponible.'))->input($job);
        }
        $data = $this->http->request('POST', 'https://api.ltx.io/v2/'.$path, [...$this->options($key), 'json' => $input])->toArray();
        $id = $data['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $id)) {
            throw new \RuntimeException('Référence LTX invalide.');
        }

        return ['ltx_id' => $id];
    }

    /** @return array{ready:bool,url?:string,stage?:string} */
    public function poll(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        $id = $job->getRemote()['ltx_id'] ?? '';
        if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $id)) {
            throw new \RuntimeException('Référence LTX invalide.');
        }
        $model = $this->catalog->model($job->getModel());
        $path = 'extend' === ($model['operation'] ?? null) ? 'extend' : (($model['image'] ?? false) ? 'image-to-video' : 'text-to-video');
        $data = $this->http->request('GET', 'https://api.ltx.io/v2/'.$path.'/'.$id, $this->options($key))->toArray();
        if (in_array($data['status'] ?? '', ['pending', 'processing'], true)) {
            return ['ready' => false, 'stage' => 'pending' === $data['status'] ? 'queued' : 'running'];
        }
        $url = $data['result']['video_url'] ?? null;
        if ('completed' !== ($data['status'] ?? '') || !is_string($url)) {
            throw new \RuntimeException('LTX a refusé ou interrompu la génération.');
        }

        return ['ready' => true, 'url' => $url];
    }

    /** @return array<string,mixed> */
    private function options(#[\SensitiveParameter] string $key): array
    {
        return ['headers' => ['Authorization' => 'Bearer '.$key], 'max_redirects' => 0, 'timeout' => 30, 'max_duration' => 45];
    }
}
