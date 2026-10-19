<?php

namespace App\Service;

use App\Entity\AiGeneration;

final class AiVideoApi
{
    public function __construct(private AiCatalog $catalog, private FalVideoApi $fal, private ReplicateVideoApi $replicate, private AiVideoDownload $files)
    {
    }

    private function api(AiGeneration $job): FalVideoApi|ReplicateVideoApi
    {
        return match ($this->catalog->model($job->getModel())['provider']) {
            'fal' => $this->fal,
            'replicate' => $this->replicate,
            default => throw new \LogicException('Fournisseur non pris en charge.'),
        };
    }

    /** @return array<string,string> */
    public function submit(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        return $this->api($job)->submit($job, $key);
    }

    /** @return array{ready:bool,url?:string} */
    public function poll(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        return $this->api($job)->poll($job, $key);
    }

    public function download(AiGeneration $job, string $url): string
    {
        return $this->files->download($url, $this->catalog->model($job->getModel())['provider']);
    }
}
