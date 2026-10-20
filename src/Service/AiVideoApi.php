<?php

namespace App\Service;

use App\Entity\AiGeneration;

final class AiVideoApi
{
    public function __construct(private AiCatalog $catalog, private FalVideoApi $fal, private ReplicateVideoApi $replicate, private AiVideoDownload $files, private LtxVideoApi $ltx, private FalMcpVideoApi $falMcp)
    {
    }

    private function api(AiGeneration $job): FalVideoApi|ReplicateVideoApi|LtxVideoApi|FalMcpVideoApi
    {
        if ('mcp' === $job->getAccessMode()) {
            if ('fal' !== $this->catalog->model($job->getModel())['provider']) {
                throw new \LogicException('Mode MCP incompatible.');
            }

            return $this->falMcp;
        }

        return match ($this->catalog->model($job->getModel())['provider']) {
            'fal' => $this->fal,
            'replicate' => $this->replicate,
            'ltx' => $this->ltx,
            default => throw new \LogicException('Fournisseur non pris en charge.'),
        };
    }

    public function checkAccess(AiGeneration $job, #[\SensitiveParameter] string $credential): void
    {
        if ('mcp' === $job->getAccessMode()) {
            $this->falMcp->checkAccess($job, $credential);
        }
    }

    /** @return array<string,string> */
    public function submit(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        return $this->api($job)->submit($job, $key);
    }

    /** @return array{ready:bool,url?:string,stage?:string} */
    public function poll(AiGeneration $job, #[\SensitiveParameter] string $key): array
    {
        return $this->api($job)->poll($job, $key);
    }

    public function download(AiGeneration $job, string $url): string
    {
        return $this->files->download($url, $this->catalog->model($job->getModel())['provider']);
    }
}
