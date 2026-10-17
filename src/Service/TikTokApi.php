<?php

namespace App\Service;

use App\Entity\TikTokConnection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TikTokApi
{
    public const SCOPES = ['user.info.basic', 'video.publish', 'video.list'];
    private const REQUIRED_SCOPES = ['user.info.basic', 'video.publish'];
    public const PRIVACY = ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'FOLLOWER_OF_CREATOR', 'SELF_ONLY'];

    public function __construct(#[Autowire(service: 'tiktok.http')] private HttpClientInterface $http, #[Autowire(service: 'tiktok.vault')] private TokenVault $vault, private EntityManagerInterface $em,
        #[Autowire('%env(TIKTOK_CLIENT_KEY)%')] private string $clientKey,
        #[Autowire('%env(TIKTOK_CLIENT_SECRET)%')] private string $clientSecret,
        #[Autowire('%env(TIKTOK_REDIRECT_URI)%')] private string $redirectUri,
        #[Autowire('%env(bool:TIKTOK_AUDITED)%')] private bool $audited)
    {
    }

    public function isReady(): bool
    {
        $uri = parse_url($this->redirectUri);

        return '' !== $this->clientKey && '' !== $this->clientSecret && $this->vault->isReady() && is_array($uri) && 'https' === ($uri['scheme'] ?? '') && !empty($uri['host']) && !isset($uri['user']) && !isset($uri['pass']) && !isset($uri['query']) && !isset($uri['fragment']);
    }

    public function authorizationUrl(string $state): string
    {
        if (!$this->isReady()) {
            throw new PublicationException('La connexion TikTok n’est pas encore disponible.');
        }

        return 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query(['client_key' => $this->clientKey, 'scope' => implode(',', self::SCOPES), 'response_type' => 'code', 'redirect_uri' => $this->redirectUri, 'state' => $state, 'disable_auto_auth' => 1], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{openId: string, name: string, access: string, refresh: string, expires: int, refreshExpires: int} */
    public function connect(#[\SensitiveParameter] string $code): array
    {
        if (!$this->isReady() || '' === $code || strlen($code) > 8192) {
            throw new PublicationException('Recommence la connexion TikTok.');
        }
        $tokens = $this->tokens($this->request('POST', '/v2/oauth/token/', ['body' => ['client_key' => $this->clientKey, 'client_secret' => $this->clientSecret, 'code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => $this->redirectUri]]));
        $data = $this->request('GET', '/v2/user/info/', ['auth_bearer' => $tokens['access'], 'query' => ['fields' => 'open_id,display_name']]);
        $user = $data['data']['user'] ?? [];
        if (($user['open_id'] ?? null) !== $tokens['openId'] || !is_string($user['display_name'] ?? null) || '' === trim($user['display_name']) || mb_strlen($user['display_name']) > 128) {
            throw new PublicationException('TikTok n’a pas confirmé l’identité du compte.');
        }

        return $tokens + ['name' => $user['display_name']];
    }

    public function access(TikTokConnection $connection): string
    {
        if (!$this->isReady() || $connection->isExpired()) {
            throw new PublicationException('Reconnecte le compte TikTok destinataire.');
        }
        if ($connection->needsRefresh()) {
            // Provider failures must not close Doctrine: the worker still needs to save its pause/retry state.
            $this->em->getConnection()->transactional(function () use ($connection): void {
                $this->em->refresh($connection, LockMode::PESSIMISTIC_WRITE);
                if (!$connection->needsRefresh()) {
                    return;
                }
                if ($connection->isExpired()) {
                    throw new PublicationException('Reconnecte TikTok.');
                }
                $tokens = $this->tokens($this->request('POST', '/v2/oauth/token/', ['body' => ['client_key' => $this->clientKey, 'client_secret' => $this->clientSecret, 'grant_type' => 'refresh_token', 'refresh_token' => $this->vault->decrypt($connection->getRefreshToken())]]));
                if ($tokens['openId'] !== $connection->getOpenId()) {
                    throw new PublicationException('Le compte TikTok a changé.');
                }
                $connection->renew($this->vault->encrypt($tokens['access']), $this->vault->encrypt($tokens['refresh']), $tokens['expires'], $tokens['refreshExpires']);
                $this->em->flush();
            });
        }

        return $this->vault->decrypt($connection->getAccessToken());
    }

    /** @return array{views: mixed, likes: mixed, comments: mixed} */
    public function statistics(TikTokConnection $connection, string $id): array
    {
        if (!preg_match('/^[1-9][0-9]{0,31}$/D', $id)) {
            throw new PublicationException('Identifiant TikTok indisponible.');
        }
        $data = $this->request('POST', '/v2/video/query/', ['auth_bearer' => $this->access($connection), 'timeout' => 10, 'max_duration' => 15, 'query' => ['fields' => 'id,view_count,like_count,comment_count'], 'json' => ['filters' => ['video_ids' => [$id]]]]);
        $video = $data['data']['videos'][0] ?? [];
        if (($video['id'] ?? null) !== $id) {
            throw new PublicationException('Statistiques TikTok indisponibles.');
        }

        return ['views' => $video['view_count'] ?? null, 'likes' => $video['like_count'] ?? null, 'comments' => $video['comment_count'] ?? null];
    }

    /** @return array<string, mixed> */
    public function creator(TikTokConnection $connection): array
    {
        $response = $this->request('POST', '/v2/post/publish/creator_info/query/', ['auth_bearer' => $this->access($connection), 'json' => new \stdClass()]);
        $data = $response['data'] ?? [];
        if (!is_array($data) || !is_string($data['creator_nickname'] ?? null) || '' === trim($data['creator_nickname']) || mb_strlen($data['creator_nickname']) > 128 || !is_array($data['privacy_level_options'] ?? null) || !$data['privacy_level_options'] || !is_int($data['max_video_post_duration_sec'] ?? null) || $data['max_video_post_duration_sec'] < 1) {
            throw new PublicationException('TikTok n’a pas confirmé les possibilités de publication.');
        }
        foreach ($data['privacy_level_options'] as $privacy) {
            if (!is_string($privacy) || !in_array($privacy, self::PRIVACY, true)) {
                throw new PublicationException('Les visibilités TikTok sont indisponibles.');
            }
        }
        foreach (['comment_disabled', 'duet_disabled', 'stitch_disabled'] as $flag) {
            if (!is_bool($data[$flag] ?? null)) {
                throw new PublicationException('Les réglages TikTok sont indisponibles.');
            }
        }
        if (!$this->audited) {
            if (!in_array('FOLLOWER_OF_CREATOR', $data['privacy_level_options'], true) || !in_array('SELF_ONLY', $data['privacy_level_options'], true)) {
                throw new PublicationException('Les essais TikTok non audités nécessitent un compte privé.');
            }
            $data['privacy_level_options'] = ['SELF_ONLY'];
        }

        return $data;
    }

    /** @param array<string, mixed> $options */
    public function start(TikTokConnection $connection, string $url, array $options): string
    {
        $data = $this->request('POST', '/v2/post/publish/video/init/', ['auth_bearer' => $this->access($connection), 'json' => ['post_info' => $options, 'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $url]]]);
        $id = $data['data']['publish_id'] ?? null;
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_.~:-]{1,64}$/D', $id)) {
            throw new PublicationException('TikTok n’a pas confirmé la demande.');
        }

        return $id;
    }

    /** @return array{status: string, remoteId: ?string} */
    public function status(TikTokConnection $connection, string $id): array
    {
        $response = $this->request('POST', '/v2/post/publish/status/fetch/', ['auth_bearer' => $this->access($connection), 'json' => ['publish_id' => $id]]);
        $data = $response['data'] ?? [];
        if (!in_array($data['status'] ?? null, ['PROCESSING_UPLOAD', 'PROCESSING_DOWNLOAD', 'PUBLISH_COMPLETE', 'FAILED'], true)) {
            throw new PublicationException('Le résultat TikTok est indisponible.');
        }
        $remoteId = $data['publicaly_available_post_id'][0] ?? null;
        if (null !== $remoteId && (!is_string($remoteId) || !preg_match('/^[0-9]{1,32}$/D', $remoteId))) {
            throw new PublicationException('Le résultat TikTok est invalide.');
        }

        return ['status' => $data['status'], 'remoteId' => $remoteId];
    }

    public function revoke(TikTokConnection $connection): void
    {
        $this->request('POST', '/v2/oauth/revoke/', ['body' => ['client_key' => $this->clientKey, 'client_secret' => $this->clientSecret, 'token' => $this->access($connection)]]);
    }

    /** @param array<string, mixed> $data @return array{openId: string, access: string, refresh: string, expires: int, refreshExpires: int} */
    private function tokens(#[\SensitiveParameter] array $data): array
    {
        foreach (['open_id', 'access_token', 'refresh_token'] as $key) {
            if (!is_string($data[$key] ?? null) || '' === $data[$key] || strlen($data[$key]) > ('open_id' === $key ? 128 : 8192)) {
                throw new PublicationException('L’autorisation TikTok est invalide.');
            }
        }
        foreach (['expires_in' => 86400, 'refresh_expires_in' => 31536000] as $key => $max) {
            if (!is_int($data[$key] ?? null) || $data[$key] < 1 || $data[$key] > $max) {
                throw new PublicationException('L’expiration TikTok est invalide.');
            }
        }
        if (!is_string($data['scope'] ?? null) || array_diff(self::REQUIRED_SCOPES, explode(',', $data['scope'])) || ($data['token_type'] ?? '') !== 'Bearer') {
            throw new PublicationException('Autorise le profil et la publication TikTok.');
        }

        return ['openId' => $data['open_id'], 'access' => $data['access_token'], 'refresh' => $data['refresh_token'], 'expires' => $data['expires_in'], 'refreshExpires' => $data['refresh_expires_in']];
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private function request(string $method, string $path, #[\SensitiveParameter] array $options): array
    {
        try {
            $response = $this->http->request($method, 'https://open.tiktokapis.com'.$path, $options + ['timeout' => 15, 'max_duration' => 30, 'max_redirects' => 0]);
            $data = $response->toArray(false);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300 || (isset($data['error']) && (!is_array($data['error']) || ($data['error']['code'] ?? '') !== 'ok'))) {
                throw new \RuntimeException();
            }

            return $data;
        } catch (\Throwable) {
            throw new PublicationException('TikTok n’a pas répondu à la demande. Réessaie plus tard ou reconnecte le compte.');
        }
    }
}
