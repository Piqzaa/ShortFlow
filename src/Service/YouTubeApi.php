<?php

namespace App\Service;

use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class YouTubeApi
{
    public const SCOPES = 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly';

    public function __construct(#[Autowire(service: 'youtube.http')] private HttpClientInterface $http, private TokenVault $vault, private EntityManagerInterface $em,
        #[Autowire('%env(YOUTUBE_CLIENT_ID)%')] private string $clientId,
        #[Autowire('%env(YOUTUBE_CLIENT_SECRET)%')] private string $clientSecret,
        #[Autowire('%env(YOUTUBE_REDIRECT_URI)%')] private string $redirectUri)
    {
    }

    public function isReady(): bool
    {
        return '' !== $this->clientId && '' !== $this->clientSecret && $this->vault->isReady();
    }

    public function authorizationUrl(string $state, string $verifier): string
    {
        if (!$this->isReady()) {
            throw new PublicationException('La connexion YouTube sera disponible prochainement.');
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id' => $this->clientId, 'redirect_uri' => $this->redirectUri, 'response_type' => 'code', 'scope' => self::SCOPES, 'access_type' => 'offline', 'prompt' => 'consent', 'state' => $state, 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256'], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{access: string, refresh: string, expires: int} */
    public function exchange(#[\SensitiveParameter] string $code, #[\SensitiveParameter] string $verifier): array
    {
        $data = $this->json('POST', 'https://oauth2.googleapis.com/token', ['body' => ['client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $this->redirectUri, 'grant_type' => 'authorization_code']]);
        if (!is_string($data['access_token'] ?? null) || !is_string($data['refresh_token'] ?? null) || !is_string($data['scope'] ?? null)) {
            throw new PublicationException('Autorise les permissions YouTube et réessaie la connexion.');
        }
        foreach (explode(' ', self::SCOPES) as $scope) {
            if (!in_array($scope, explode(' ', $data['scope']), true)) {
                throw new PublicationException('Autorise les permissions YouTube et réessaie la connexion.');
            }
        }

        return ['access' => $data['access_token'], 'refresh' => $data['refresh_token'], 'expires' => (int) ($data['expires_in'] ?? 3600)];
    }

    /** @return array{id: string, title: string} */
    public function channel(#[\SensitiveParameter] string $access): array
    {
        $data = $this->json('GET', 'https://www.googleapis.com/youtube/v3/channels', ['auth_bearer' => $access, 'query' => ['part' => 'snippet', 'mine' => 'true']]);
        $items = $data['items'] ?? [];
        if (1 !== count($items) || !preg_match('/^UC[A-Za-z0-9_-]{22}$/D', $items[0]['id'] ?? '')) {
            throw new PublicationException('Choisis un compte possédant une chaîne YouTube.');
        }

        return ['id' => $items[0]['id'], 'title' => mb_substr($items[0]['snippet']['title'] ?? 'Ma chaîne', 0, 255)];
    }

    public function access(YouTubeConnection $connection): string
    {
        if (!$this->isReady()) {
            throw new PublicationException('La connexion YouTube est indisponible.');
        }
        if ($connection->needsRefresh()) {
            $data = $this->json('POST', 'https://oauth2.googleapis.com/token', ['body' => ['client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'refresh_token' => $this->vault->decrypt($connection->getRefreshToken()), 'grant_type' => 'refresh_token']]);
            if (!is_string($data['access_token'] ?? null)) {
                throw new PublicationException('Reconnecte ton compte YouTube.');
            }
            $connection->updateAccess($this->vault->encrypt($data['access_token']), (int) ($data['expires_in'] ?? 3600));
            $this->em->flush();
        }

        return $this->vault->decrypt($connection->getAccessToken());
    }

    public function start(YouTubePublication $job, #[\SensitiveParameter] string $access): string
    {
        $source = $job->getSource() ?? throw new PublicationException('La vidéo source est indisponible.');
        $response = $this->request('POST', 'https://www.googleapis.com/upload/youtube/v3/videos', ['auth_bearer' => $access, 'query' => ['uploadType' => 'resumable', 'part' => 'snippet,status', 'notifySubscribers' => 'false'], 'headers' => ['X-Upload-Content-Length' => (string) $source->getByteSize(), 'X-Upload-Content-Type' => $source->getMimeType()], 'json' => ['snippet' => ['title' => $job->getTitle(), 'description' => $job->getDescription(), 'categoryId' => '22'], 'status' => ['privacyStatus' => $job->getPrivacy(), 'selfDeclaredMadeForKids' => $job->isMadeForKids(), 'containsSyntheticMedia' => $job->isSynthetic()]]]);
        if (!in_array($response->getStatusCode(), [200, 201], true)) {
            throw new PublicationException('YouTube n’a pas accepté la préparation. Vérifie ta connexion puis réessaie.');
        }
        $url = $response->getHeaders(false)['location'][0] ?? '';
        $this->checkSession($url);

        return $url;
    }

    /** @return array{offset: int, id: ?string, expired: bool} */
    public function probe(#[\SensitiveParameter] string $session, #[\SensitiveParameter] string $access, int $total): array
    {
        $this->checkSession($session);

        return $this->transferResult($this->request('PUT', $session, ['auth_bearer' => $access, 'headers' => ['Content-Length' => '0', 'Content-Range' => 'bytes */'.$total], 'body' => '']), $total);
    }

    /** @return array{offset: int, id: ?string, expired: bool} */
    public function chunk(#[\SensitiveParameter] string $session, #[\SensitiveParameter] string $access, string $path, int $offset, int $total, string $mime): array
    {
        $this->checkSession($session);
        $handle = fopen($path, 'rb');
        if (false === $handle) {
            throw new PublicationException('La vidéo source est indisponible.');
        }
        try {
            if (0 !== fseek($handle, $offset)) {
                throw new PublicationException('Lecture de la vidéo impossible.');
            } $body = fread($handle, min(8388608, $total - $offset));
        } finally {
            fclose($handle);
        }
        if (false === $body || '' === $body) {
            throw new PublicationException('Lecture de la vidéo impossible.');
        }

        return $this->transferResult($this->request('PUT', $session, ['auth_bearer' => $access, 'headers' => ['Content-Type' => $mime, 'Content-Length' => (string) strlen($body), 'Content-Range' => 'bytes '.$offset.'-'.($offset + strlen($body) - 1).'/'.$total], 'body' => $body]), $total);
    }

    /** @return array{offset: int, id: ?string, expired: bool} */
    private function transferResult(ResponseInterface $response, int $total): array
    {
        $status = $response->getStatusCode();
        if (in_array($status, [404, 410], true)) {
            return ['offset' => 0, 'id' => null, 'expired' => true];
        }
        if (308 === $status) {
            $range = $response->getHeaders(false)['range'][0] ?? '';
            $offset = 0;
            if ('' !== $range) {
                if (!preg_match('/^bytes=0-(\d+)$/D', $range, $match)) {
                    throw new PublicationException('Réponse de transfert inattendue.');
                } $offset = (int) $match[1] + 1;
            }
            if ($offset > $total) {
                throw new PublicationException('Réponse de transfert inattendue.');
            }

            return ['offset' => $offset, 'id' => null, 'expired' => false];
        }
        if (in_array($status, [200, 201], true)) {
            $data = $response->toArray(false);
            $id = $data['id'] ?? '';
            if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) {
                throw new PublicationException('YouTube doit confirmer le résultat du transfert.');
            }

            return ['offset' => $total, 'id' => $id, 'expired' => false];
        }
        throw new PublicationException('Le transfert a été interrompu. Tu peux le reprendre sans recommencer l’envoi.');
    }

    /** @return array<string, mixed> */
    public function processing(#[\SensitiveParameter] string $access, string $id): array
    {
        $data = $this->json('GET', 'https://www.googleapis.com/youtube/v3/videos', ['auth_bearer' => $access, 'query' => ['part' => 'status,processingDetails', 'id' => $id]]);

        return $data['items'][0] ?? [];
    }

    private function checkSession(#[\SensitiveParameter] string $url): void
    {
        $parts = parse_url($url);
        if (false === $parts || 'https' !== ($parts['scheme'] ?? '') || !in_array($parts['host'] ?? '', ['www.googleapis.com', 'youtube.googleapis.com'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || (isset($parts['port']) && 443 !== $parts['port']) || !preg_match('~^/(?:resumable/)?upload/youtube/v3/videos$~D', $parts['path'] ?? '')) {
            throw new PublicationException('Adresse de transfert YouTube invalide.');
        }
    }

    /** @param array<string, mixed> $options */
    private function request(string $method, #[\SensitiveParameter] string $url, #[\SensitiveParameter] array $options): ResponseInterface
    {
        try {
            $response = $this->http->request($method, $url, $options + ['max_redirects' => 0, 'timeout' => 30, 'max_duration' => 120]);
            $response->getStatusCode();

            return $response;
        } catch (\Throwable) {
            throw new PublicationException('YouTube est momentanément indisponible. Réessaie dans quelques instants.');
        }
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private function json(string $method, #[\SensitiveParameter] string $url, #[\SensitiveParameter] array $options): array
    {
        try {
            $response = $this->request($method, $url, $options);
            if (200 !== $response->getStatusCode()) {
                throw new PublicationException('La demande YouTube n’a pas abouti. Vérifie ta connexion puis réessaie.');
            }

            return $response->toArray(false);
        } catch (\Throwable) {
            throw new PublicationException('La demande YouTube n’a pas abouti. Vérifie ta connexion puis réessaie.');
        }
    }
}
