<?php

namespace App\Service;

use App\Entity\AiCredential;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FalOAuth
{
    private const AUTH = 'https://authkit.fal.ai';

    public function __construct(private EntityManagerInterface $em, #[Autowire(service: 'ai.vault')] private TokenVault $vault, #[Autowire(service: 'ai.http')] private HttpClientInterface $http, private FalMcpClient $mcp)
    {
    }

    public function connection(User $owner): ?AiCredential
    {
        return $this->em->getRepository(AiCredential::class)->findOneBy(['owner' => $owner, 'provider' => 'fal-mcp']);
    }

    /** @return array<string,mixed>|null */
    public function pending(User $owner, SessionInterface $session): ?array
    {
        $value = $session->get('fal_oauth');
        if (!is_string($value)) {
            return null;
        }
        try {
            $data = json_decode($this->vault->decrypt($value), true, 32, JSON_THROW_ON_ERROR);
            if (($data['owner'] ?? null) === $owner->getId() && ($data['expires'] ?? 0) > time()) {
                return $data;
            }
        } catch (\Throwable) {
        }
        $session->remove('fal_oauth');

        return null;
    }

    public function begin(User $owner, SessionInterface $session): void
    {
        if (!$this->vault->isReady()) {
            throw new \RuntimeException('Le stockage sécurisé des accès IA attend sa configuration.');
        }
        if ($this->pending($owner, $session)) {
            return;
        }
        $resource = $this->http->request('GET', 'https://mcp.fal.ai/.well-known/oauth-protected-resource/mcp-relay', $this->options())->toArray();
        $metadata = $this->http->request('GET', self::AUTH.'/.well-known/oauth-authorization-server', $this->options())->toArray();
        if (($resource['resource'] ?? '') !== FalMcpClient::RESOURCE || !in_array(self::AUTH, $resource['authorization_servers'] ?? [], true) || ($metadata['issuer'] ?? '') !== self::AUTH || ($metadata['device_authorization_endpoint'] ?? '') !== self::AUTH.'/oauth2/device_authorization' || ($metadata['registration_endpoint'] ?? '') !== self::AUTH.'/oauth2/register' || ($metadata['token_endpoint'] ?? '') !== self::AUTH.'/oauth2/token') {
            throw new \RuntimeException('La configuration OAuth Fal a changé. La connexion doit être revérifiée.');
        }
        $client = $this->http->request('POST', self::AUTH.'/oauth2/register', [...$this->options(), 'json' => ['client_name' => 'VydFlow', 'token_endpoint_auth_method' => 'none', 'grant_types' => ['urn:ietf:params:oauth:grant-type:device_code', 'refresh_token'], 'response_types' => []]])->toArray();
        $clientId = $client['client_id'] ?? null;
        if (!is_string($clientId) || '' === $clientId || isset($client['client_secret'])) {
            throw new \RuntimeException('Fal n’a pas accepté la connexion publique de VydFlow.');
        }
        $device = $this->http->request('POST', self::AUTH.'/oauth2/device_authorization', [...$this->options(), 'body' => ['client_id' => $clientId, 'scope' => 'openid profile offline_access', 'resource' => FalMcpClient::RESOURCE]])->toArray();
        $uri = $device['verification_uri'] ?? '';
        if (!is_string($device['device_code'] ?? null) || !is_string($device['user_code'] ?? null) || !is_string($uri) || !preg_match('~^https://(?:(?:authkit|auth|www)\.)?fal\.ai/[^\s#]*$~D', $uri) || !is_numeric($device['expires_in'] ?? null)) {
            throw new \RuntimeException('Fal n’a pas renvoyé une autorisation exploitable.');
        }
        $data = ['owner' => $owner->getId(), 'client_id' => $clientId, 'device_code' => $device['device_code'], 'user_code' => mb_substr($device['user_code'], 0, 40), 'uri' => $uri, 'expires' => time() + min(1800, max(1, (int) $device['expires_in'])), 'interval' => max(5, min(60, (int) ($device['interval'] ?? 5))), 'next' => time()];
        $session->set('fal_oauth', $this->vault->encrypt(json_encode($data, JSON_THROW_ON_ERROR)));
    }

    public function finish(User $owner, SessionInterface $session): bool
    {
        $pending = $this->pending($owner, $session) ?? throw new \RuntimeException('La connexion Fal a expiré. Recommence.');
        if ($pending['next'] > time()) {
            throw new \RuntimeException('Attends quelques secondes avant de vérifier à nouveau.');
        }
        $pending['next'] = time() + $pending['interval'];
        $session->set('fal_oauth', $this->vault->encrypt(json_encode($pending, JSON_THROW_ON_ERROR)));
        $response = $this->http->request('POST', self::AUTH.'/oauth2/token', [...$this->options(), 'body' => ['grant_type' => 'urn:ietf:params:oauth:grant-type:device_code', 'device_code' => $pending['device_code'], 'client_id' => $pending['client_id'], 'resource' => FalMcpClient::RESOURCE]]);
        $data = $response->toArray(false);
        if (in_array($data['error'] ?? '', ['authorization_pending', 'slow_down'], true)) {
            if ('slow_down' === $data['error']) {
                $pending['interval'] = min(60, $pending['interval'] + 5);
                $pending['next'] = time() + $pending['interval'];
                $session->set('fal_oauth', $this->vault->encrypt(json_encode($pending, JSON_THROW_ON_ERROR)));
            }

            return false;
        }
        if (200 !== $response->getStatusCode()) {
            $session->remove('fal_oauth');
            throw new \RuntimeException('L’autorisation Fal a été refusée ou a expiré. Recommence.');
        }
        $bundle = $this->tokens($data, $pending['client_id']);
        $account = $this->account($this->mcp->call($bundle['access_token'], 'check_account_status'));
        $bundle['account_id'] = $account;
        $this->em->wrapInTransaction(function () use ($owner, $bundle): void {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $encrypted = $this->vault->encrypt(json_encode($bundle, JSON_THROW_ON_ERROR));
            $connection = $this->connection($owner);
            if ($connection) {
                $connection->replace($encrypted);
            } else {
                $this->em->persist(new AiCredential($owner, $encrypted, 'fal-mcp'));
            }
        });
        $session->remove('fal_oauth');

        return true;
    }

    /** @return array<string,mixed> */
    private function tokens(array $data, string $clientId, ?string $previousRefresh = null): array
    {
        $refresh = $data['refresh_token'] ?? $previousRefresh;
        if (!is_string($data['access_token'] ?? null) || '' === $data['access_token'] || !is_string($refresh) || '' === $refresh || 'bearer' !== strtolower($data['token_type'] ?? '') || !is_numeric($data['expires_in'] ?? null) || (int) $data['expires_in'] < 60) {
            throw new \RuntimeException('Fal doit autoriser un accès renouvelable pour la génération en arrière-plan.');
        }

        return ['access_token' => $data['access_token'], 'refresh_token' => $refresh, 'expires_at' => time() + min(2592000, (int) $data['expires_in']), 'client_id' => $clientId];
    }

    public function credential(User $owner): string
    {
        try {
            return $this->em->getConnection()->transactional(function () use ($owner): string {
                $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
                $connection = $this->connection($owner) ?? throw new \RuntimeException('Reconnecte Fal dans Connexions.');
                $bundle = json_decode($this->vault->decrypt($connection->getEncryptedKey()), true, 32, JSON_THROW_ON_ERROR);
                if ($bundle['expires_at'] <= time() + 60) {
                    $response = $this->http->request('POST', self::AUTH.'/oauth2/token', [...$this->options(), 'body' => ['grant_type' => 'refresh_token', 'refresh_token' => $bundle['refresh_token'], 'client_id' => $bundle['client_id'], 'resource' => FalMcpClient::RESOURCE]])->toArray();
                    $bundle = $this->tokens($response, $bundle['client_id'], $bundle['refresh_token']) + ['account_id' => $bundle['account_id']];
                    $connection->replace($this->vault->encrypt(json_encode($bundle, JSON_THROW_ON_ERROR)));
                    $this->em->flush();
                }

                return json_encode($bundle, JSON_THROW_ON_ERROR);
            });
        } catch (\Throwable) {
            throw new \RuntimeException('La connexion Fal a expiré ou ne peut plus être renouvelée. Reconnecte le même compte dans Connexions.');
        }
    }

    public function accountId(User $owner): ?string
    {
        try {
            $connection = $this->connection($owner);
            $bundle = $connection ? json_decode($this->vault->decrypt($connection->getEncryptedKey()), true, 32, JSON_THROW_ON_ERROR) : [];

            return $bundle['account_id'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function account(array $data): string
    {
        $id = $data['account_id'] ?? $data['account']['id'] ?? $data['account']['account_id'] ?? null;
        if ('ready' !== ($data['status'] ?? '') || !is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $id)) {
            throw new \RuntimeException('Fal doit confirmer le compte actif et ses accès avant de connecter VydFlow. Vérifie les crédits et le compte MCP actif chez Fal.');
        }

        return $id;
    }

    public function remove(User $owner): void
    {
        $connection = $this->connection($owner);
        if ($connection) {
            $this->em->remove($connection);
            $this->em->flush();
        }
    }

    /** @return array<string,mixed> */
    private function options(): array
    {
        return ['max_redirects' => 0, 'timeout' => 20, 'max_duration' => 30];
    }
}
