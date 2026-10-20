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

    public function begin(User $owner, SessionInterface $session, string $redirectUri, string $back = ''): string
    {
        if (!$this->vault->isReady()) {
            throw new \RuntimeException('Le stockage sécurisé des accès IA attend sa configuration.');
        }
        $session->remove('fal_oauth');
        $resource = $this->json('GET', 'https://mcp.fal.ai/.well-known/oauth-protected-resource/mcp-relay', [], 'découverte du service');
        $metadata = $this->json('GET', self::AUTH.'/.well-known/oauth-authorization-server', [], 'découverte OAuth');
        if (($resource['resource'] ?? '') !== FalMcpClient::RESOURCE || !in_array(self::AUTH, $resource['authorization_servers'] ?? [], true) || ($metadata['issuer'] ?? '') !== self::AUTH || ($metadata['authorization_endpoint'] ?? '') !== self::AUTH.'/oauth2/authorize' || ($metadata['registration_endpoint'] ?? '') !== self::AUTH.'/oauth2/register' || ($metadata['token_endpoint'] ?? '') !== self::AUTH.'/oauth2/token' || !in_array('S256', $metadata['code_challenge_methods_supported'] ?? [], true)) {
            throw new \RuntimeException('La configuration OAuth Fal a changé. La connexion doit être revérifiée.');
        }
        $client = $this->json('POST', self::AUTH.'/oauth2/register', ['json' => ['client_name' => 'VydFlow', 'token_endpoint_auth_method' => 'none', 'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'], 'redirect_uris' => [$redirectUri]]], 'déclaration de VydFlow');
        $clientId = $client['client_id'] ?? null;
        if (!is_string($clientId) || '' === $clientId || isset($client['client_secret'])) {
            throw new \RuntimeException('Fal n’a pas accepté la connexion publique de VydFlow.');
        }
        $verifier = bin2hex(random_bytes(32));
        $state = bin2hex(random_bytes(32));
        $pending = ['flow' => 'browser', 'owner' => $owner->getId(), 'client_id' => $clientId, 'verifier' => $verifier, 'state' => $state, 'redirect_uri' => $redirectUri, 'back' => $back, 'expires' => time() + 600];
        $session->set('fal_oauth', $this->vault->encrypt(json_encode($pending, JSON_THROW_ON_ERROR)));

        return self::AUTH.'/oauth2/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => $clientId, 'redirect_uri' => $redirectUri, 'scope' => 'openid profile offline_access', 'resource' => FalMcpClient::RESOURCE, 'state' => $state, 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256'], '', '&', PHP_QUERY_RFC3986);
    }

    public function finish(User $owner, SessionInterface $session, #[\SensitiveParameter] string $code, string $state, bool $denied = false): string
    {
        $pending = $this->pending($owner, $session);
        if (!$pending || 'browser' !== ($pending['flow'] ?? '') || !hash_equals($pending['state'] ?? '', $state) || '' === $state) {
            throw new \RuntimeException('La connexion Fal a expiré ou son retour est invalide. Recommence depuis VydFlow, avec la même adresse.');
        }
        // Consume before exchange: callbacks cannot replay a billable or credential operation.
        $session->remove('fal_oauth');
        if ($denied || '' === $code) {
            throw new \RuntimeException('L’autorisation Fal a été annulée ou refusée. Ta clé API existante reste disponible.');
        }
        $data = $this->json('POST', self::AUTH.'/oauth2/token', ['body' => ['grant_type' => 'authorization_code', 'code' => $code, 'code_verifier' => $pending['verifier'], 'client_id' => $pending['client_id'], 'redirect_uri' => $pending['redirect_uri'], 'resource' => FalMcpClient::RESOURCE]], 'échange de l’autorisation');
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

        return $pending['back'];
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
        if ('ready' !== ($data['status'] ?? '')) {
            throw new \RuntimeException('Fal doit confirmer le compte actif et ses accès avant de connecter VydFlow. Vérifie les crédits et le compte MCP actif chez Fal.');
        }
        // Provider identity is opaque (personal IDs may contain "|"). Never use it as a URL/path.
        if (!is_string($id) || !preg_match('/^[\x21-\x7e]{1,128}$/D', $id)) {
            throw new \RuntimeException('Fal a autorisé l’accès, mais son identifiant de compte n’est pas reconnu par VydFlow.');
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

    /** @param array<string,mixed> $options @return array<string,mixed> */
    private function json(string $method, string $url, array $options, string $stage): array
    {
        try {
            $response = $this->http->request($method, $url, $options + $this->options());
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (\Throwable) {
            throw new \RuntimeException('Connexion Fal : '.$stage.' impossible. Réessaie dans quelques instants.');
        }
        if ($status < 200 || $status >= 300) {
            // Do not expose provider bodies, tokens or arbitrary error descriptions in flashes/logs.
            $code = in_array($data['error'] ?? '', ['invalid_client_metadata', 'invalid_client', 'invalid_scope', 'unauthorized_client', 'access_denied'], true) ? ' · '.$data['error'] : '';
            throw new \RuntimeException('Connexion Fal : '.$stage.' refusée (HTTP '.$status.$code.').');
        }

        return $data;
    }
}
