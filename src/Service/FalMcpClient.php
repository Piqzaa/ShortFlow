<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class FalMcpClient
{
    public const RESOURCE = 'https://mcp.fal.ai/mcp-relay';

    public function __construct(#[Autowire(service: 'ai.http')] private HttpClientInterface $http)
    {
    }

    /** @param array<string,mixed> $arguments @return array<string,mixed> */
    public function call(#[\SensitiveParameter] string $token, string $tool, array $arguments = []): array
    {
        if (!in_array($tool, ['check_account_status', 'submit_job', 'check_job', 'get_job_result'], true)) {
            throw new \LogicException('Outil MCP non autorisé.');
        }
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json, text/event-stream'];
        $initial = $this->http->request('POST', self::RESOURCE, ['headers' => $headers, 'json' => ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'VydFlow', 'version' => '1.0']]], 'max_redirects' => 0, 'timeout' => 20, 'max_duration' => 30]);
        $hello = $this->decode($this->body($initial), 1);
        $protocol = $hello['result']['protocolVersion'] ?? '';
        if (!in_array($protocol, ['2025-03-26', '2025-06-18', '2025-11-25', '2026-07-28'], true)) {
            throw new \RuntimeException('Version MCP Fal non prise en charge.');
        }
        $headers['MCP-Protocol-Version'] = $protocol;
        $session = $initial->getHeaders()['mcp-session-id'][0] ?? null;
        if (is_string($session) && preg_match('/^[\x21-\x7e]{1,256}$/D', $session)) {
            $headers['Mcp-Session-Id'] = $session;
        }
        $options = ['headers' => $headers, 'max_redirects' => 0, 'timeout' => 20, 'max_duration' => 30];
        $notification = $this->http->request('POST', self::RESOURCE, [...$options, 'json' => ['jsonrpc' => '2.0', 'method' => 'notifications/initialized']]);
        if (!in_array($notification->getStatusCode(), [200, 202, 204], true)) {
            throw new \RuntimeException('Initialisation MCP Fal refusée.');
        }
        $response = $this->http->request('POST', self::RESOURCE, [...$options, 'json' => ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments ?: (object) []]]]);
        $message = $this->decode($this->body($response), 2);
        $result = $message['result'] ?? [];
        if (!is_array($result) || ($result['isError'] ?? false)) {
            throw new \RuntimeException('L’accès ou la demande MCP Fal a été refusé.');
        }
        if (is_array($result['structuredContent'] ?? null)) {
            return $result['structuredContent'];
        }
        foreach ($result['content'] ?? [] as $block) {
            if ('text' === ($block['type'] ?? '') && is_string($block['text'] ?? null)) {
                $data = json_decode($block['text'], true, 64);
                if (is_array($data)) {
                    return $data;
                }
            }
        }
        throw new \RuntimeException('Réponse MCP Fal non reconnue.');
    }

    private function body(ResponseInterface $response): string
    {
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('Le serveur MCP Fal a refusé cet accès. Reconnecte Fal avant de réessayer.');
        }
        $body = '';
        foreach ($this->http->stream($response) as $chunk) {
            if ($chunk->isTimeout()) {
                throw new \RuntimeException('La réponse MCP Fal a expiré.');
            }
            $content = $chunk->getContent();
            if (strlen($body) + strlen($content) > 2097152) {
                throw new \RuntimeException('Réponse MCP trop volumineuse.');
            }
            $body .= $content;
        }

        return $body;
    }

    /** @return array<string,mixed> */
    private function decode(string $body, int $id): array
    {
        if (strlen($body) > 2097152) {
            throw new \RuntimeException('Réponse MCP trop volumineuse.');
        }
        $messages = [$body];
        if (str_starts_with(ltrim($body), 'event:') || str_starts_with(ltrim($body), 'data:')) {
            $messages = [];
            foreach (preg_split('/\r?\n\r?\n/', $body) as $event) {
                $lines = array_filter(explode("\n", $event), static fn (string $line): bool => str_starts_with($line, 'data:'));
                $messages[] = implode("\n", array_map(static fn (string $line): string => ltrim(substr($line, 5)), $lines));
            }
        }
        foreach ($messages as $json) {
            $message = json_decode($json, true, 64);
            if (is_array($message) && ($message['id'] ?? null) === $id && !isset($message['error'])) {
                return $message;
            }
        }
        throw new \RuntimeException('Réponse MCP Fal invalide.');
    }
}
