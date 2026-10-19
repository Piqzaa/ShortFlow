<?php

namespace App\Service;

use App\Entity\AiGeneration;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class FalMcpVideoApi
{
    public function __construct(private FalMcpClient $client, private FalOAuth $oauth, private AiCatalog $catalog, private FalVideoApi $fal, #[Autowire(service: 'ai.vault')] private TokenVault $vault)
    {
    }

    public function checkAccess(AiGeneration $job, #[\SensitiveParameter] string $credential): void
    {
        $bundle = json_decode($credential, true, 32, JSON_THROW_ON_ERROR);
        $account = $this->oauth->account($this->client->call($bundle['access_token'], 'check_account_status'));
        if (!hash_equals($job->getAccessAccount() ?? '', $account) || !hash_equals($bundle['account_id'], $account)) {
            throw new \RuntimeException('Le compte MCP actif chez Fal a changé. Reconnecte Fal et vérifie le compte facturé avant de confirmer.');
        }
    }

    /** @return array<string,string> */
    public function submit(AiGeneration $job, #[\SensitiveParameter] string $credential): array
    {
        $bundle = json_decode($credential, true, 32, JSON_THROW_ON_ERROR);
        if (!hash_equals($job->getAccessAccount() ?? '', $bundle['account_id'])) {
            throw new \RuntimeException('Reconnecte le compte Fal confirmé pour cette génération.');
        }
        $data = $this->client->call($bundle['access_token'], 'submit_job', ['endpoint_id' => $this->catalog->model($job->getModel())['endpoint'], 'input' => $this->fal->input($job), 'store_payload' => false]);
        $id = $data['request_id'] ?? null;
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $id)) {
            throw new \RuntimeException('Référence MCP Fal invalide.');
        }
        $receipt = ['request_id' => $id];
        foreach (['status_url', 'response_url'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $receipt[$field] = $data[$field];
            }
        }

        // Receipt URLs may carry account recovery data. They stay encrypted, never used as HTTP targets.
        return ['mcp_receipt' => $this->vault->encrypt(json_encode($receipt, JSON_THROW_ON_ERROR))];
    }

    /** @return array{ready:bool,url?:string} */
    public function poll(AiGeneration $job, #[\SensitiveParameter] string $credential): array
    {
        $bundle = json_decode($credential, true, 32, JSON_THROW_ON_ERROR);
        if (!hash_equals($job->getAccessAccount() ?? '', $bundle['account_id'])) {
            throw new \RuntimeException('Reconnecte le compte Fal utilisé pour cette génération avant de récupérer son résultat.');
        }
        $receipt = json_decode($this->vault->decrypt($job->getRemote()['mcp_receipt']), true, 32, JSON_THROW_ON_ERROR);
        $arguments = ['endpoint_id' => $this->catalog->model($job->getModel())['endpoint'], 'request_id' => $receipt['request_id']];
        $status = $this->client->call($bundle['access_token'], 'check_job', $arguments + ['wait_seconds' => 0] + (isset($receipt['status_url']) ? ['status_url' => $receipt['status_url']] : []));
        if (in_array($status['status'] ?? '', ['IN_QUEUE', 'IN_PROGRESS', 'processing'], true)) {
            return ['ready' => false];
        }
        if ('COMPLETED' !== ($status['status'] ?? '') || isset($status['error'])) {
            throw new \RuntimeException('La génération MCP Fal n’a pas abouti.');
        }
        $data = $this->client->call($bundle['access_token'], 'get_job_result', $arguments + (isset($receipt['response_url']) ? ['response_url' => $receipt['response_url']] : []));
        $url = $data['video']['url'] ?? $data['result']['video']['url'] ?? $data['data']['video']['url'] ?? null;
        if (!is_string($url)) {
            throw new \RuntimeException('Fal ne renvoie pas de vidéo récupérable.');
        }

        return ['ready' => true, 'url' => $url];
    }
}
