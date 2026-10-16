<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class InstagramApi
{
    public const SCOPES = ['instagram_business_basic', 'instagram_business_content_publish'];

    public function __construct(
        #[Autowire(service: 'instagram.http')] private HttpClientInterface $http,
        #[Autowire(service: 'instagram.vault')] private TokenVault $vault,
        #[Autowire('%env(INSTAGRAM_CLIENT_ID)%')] private string $clientId,
        #[Autowire('%env(INSTAGRAM_CLIENT_SECRET)%')] private string $clientSecret,
        #[Autowire('%env(INSTAGRAM_REDIRECT_URI)%')] private string $redirectUri,
        #[Autowire('%env(INSTAGRAM_API_VERSION)%')] private string $version,
    ) {
    }

    public function isReady(): bool
    {
        $uri = parse_url($this->redirectUri);

        return 1 === preg_match('/^[1-9][0-9]{0,63}$/D', $this->clientId)
            && '' !== $this->clientSecret && $this->vault->isReady()
            && 1 === preg_match('/^v[0-9]+\.0$/D', $this->version)
            && is_array($uri) && '' !== ($uri['host'] ?? '') && !isset($uri['user']) && !isset($uri['pass']) && !isset($uri['fragment'])
            && ('https' === ($uri['scheme'] ?? '') || ('http' === ($uri['scheme'] ?? '') && in_array($uri['host'], ['localhost', '127.0.0.1'], true)));
    }

    public function authorizationUrl(string $state): string
    {
        if (!$this->isReady()) {
            throw new PublicationException('La connexion Instagram est momentanément indisponible.');
        }

        return 'https://www.instagram.com/oauth/authorize?'.http_build_query([
            'client_id' => $this->clientId, 'redirect_uri' => $this->redirectUri,
            'response_type' => 'code', 'scope' => implode(',', self::SCOPES),
            'state' => $state, 'enable_fb_login' => 'false', 'force_reauth' => 'true',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function createReel(\App\Entity\InstagramConnection $connection, string $videoUrl, string $caption, bool $shareToFeed, bool $synthetic): string
    {
        $data = $this->json('POST', $this->endpoint($connection->getInstagramId().'/media'), ['auth_bearer' => $this->access($connection), 'body' => ['media_type' => 'REELS', 'video_url' => $videoUrl, 'caption' => $caption, 'share_to_feed' => $shareToFeed ? 'true' : 'false', 'is_ai_generated' => $synthetic ? 'true' : 'false']]);

        return $this->identifier($data['id'] ?? null);
    }

    public function containerStatus(\App\Entity\InstagramConnection $connection, string $id): string
    {
        $data = $this->json('GET', $this->endpoint($this->identifier($id)), ['auth_bearer' => $this->access($connection), 'query' => ['fields' => 'status_code']]);
        $status = $data['status_code'] ?? '';
        if (!in_array($status, ['IN_PROGRESS', 'FINISHED', 'PUBLISHED', 'ERROR', 'EXPIRED'], true)) {
            throw new PublicationException('Instagram n’a pas confirmé le traitement de la vidéo.');
        }

        return $status;
    }

    public function publishReel(\App\Entity\InstagramConnection $connection, string $container): string
    {
        $data = $this->json('POST', $this->endpoint($connection->getInstagramId().'/media_publish'), ['auth_bearer' => $this->access($connection), 'body' => ['creation_id' => $this->identifier($container)]]);

        return $this->identifier($data['id'] ?? null);
    }

    private function endpoint(string $path): string
    {
        return 'https://graph.instagram.com/'.$this->version.'/'.$path;
    }

    private function access(\App\Entity\InstagramConnection $connection): string
    {
        if (!$this->isReady() || $connection->isExpired()) {
            throw new PublicationException('Reconnecte ton compte Instagram avant de poursuivre.');
        }

        return $this->vault->decrypt($connection->getAccessToken());
    }

    /** @return array{id: string, username: string, type: string, access: string, expires: int} */
    public function connect(#[\SensitiveParameter] string $code): array
    {
        if (!$this->isReady() || '' === $code || strlen($code) > 8192) {
            throw new PublicationException('Recommence la connexion Instagram depuis VydFlow.');
        }
        $form = new FormDataPart([
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri, 'grant_type' => 'authorization_code', 'code' => $code,
        ]);
        $short = $this->record($this->json('POST', 'https://api.instagram.com/oauth/access_token', [
            'headers' => $form->getPreparedHeaders()->toArray(), 'body' => $form->bodyToIterable(),
        ]));
        $permissions = $short['permissions'] ?? null;
        if (!is_string($permissions) || array_diff(self::SCOPES, preg_split('/[,\s]+/', $permissions) ?: [])) {
            throw new PublicationException('Autorise le profil et la publication Instagram puis réessaie.');
        }
        $shortToken = $this->token($short);
        $scopedId = $this->identifier($short['user_id'] ?? null);
        $long = $this->json('GET', 'https://graph.instagram.com/access_token', ['query' => [
            'grant_type' => 'ig_exchange_token', 'client_secret' => $this->clientSecret, 'access_token' => $shortToken,
        ]]);
        $access = $this->token($long);
        $expires = $long['expires_in'] ?? null;
        if (!is_int($expires) || $expires < 1 || $expires > 5184000) {
            throw new PublicationException('Instagram n’a pas confirmé la durée de connexion.');
        }
        $profile = $this->record($this->json('GET', 'https://graph.instagram.com/'.$this->version.'/me', [
            'auth_bearer' => $access, 'query' => ['fields' => 'id,user_id,username,account_type'],
        ]));
        $type = is_string($profile['account_type'] ?? null) ? strtoupper($profile['account_type']) : '';
        $username = $profile['username'] ?? null;
        if ($this->identifier($profile['id'] ?? null) !== $scopedId || !in_array($type, ['BUSINESS', 'MEDIA_CREATOR'], true) || !is_string($username) || !preg_match('/^[A-Za-z0-9._]{1,30}$/D', $username)) {
            throw new PublicationException('Choisis un compte Instagram Créateur ou Entreprise et réessaie.');
        }

        return ['id' => $this->identifier($profile['user_id'] ?? null), 'username' => $username, 'type' => $type, 'access' => $access, 'expires' => $expires];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function record(#[\SensitiveParameter] array $data): array
    {
        if (!array_key_exists('data', $data)) {
            return $data;
        }
        if (!is_array($data['data']) || 1 !== count($data['data']) || !is_array($data['data'][0] ?? null)) {
            throw new PublicationException('Instagram n’a pas confirmé le compte connecté.');
        }

        return $data['data'][0];
    }

    private function identifier(mixed $id): string
    {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]{0,63}$/D', (string) $id)) {
            throw new PublicationException('Instagram n’a pas confirmé le compte connecté.');
        }

        return (string) $id;
    }

    /** @param array<string, mixed> $data */
    private function token(#[\SensitiveParameter] array $data): string
    {
        if (!is_string($data['access_token'] ?? null) || '' === $data['access_token']) {
            throw new PublicationException('Instagram n’a pas confirmé les autorisations.');
        }

        return $data['access_token'];
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private function json(string $method, string $url, #[\SensitiveParameter] array $options): array
    {
        try {
            $response = $this->http->request($method, $url, $options + ['max_redirects' => 0, 'timeout' => 15, 'max_duration' => 30]);
            if (200 !== $response->getStatusCode()) {
                throw new \RuntimeException();
            }

            return $response->toArray(false);
        } catch (\Throwable) {
            // Provider errors may contain secrets: never chain, log or render them.
            throw new PublicationException('La connexion Instagram n’a pas abouti. Vérifie les autorisations puis réessaie.');
        }
    }
}
