<?php

namespace App\Tests\Functional;

use App\Entity\InstagramConnection;
use App\Entity\User;
use App\Entity\YouTubeConnection;
use App\Service\InstagramApi;
use App\Service\TokenVault;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class InstagramConnectionTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $alice;
    private User $bob;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM app_user');
        $this->alice = new User('instagram-alice@example.test');
        $this->bob = new User('instagram-bob@example.test');
        foreach ([$this->alice, $this->bob] as $user) {
            $user->verify();
            $user->setPassword('unused-by-loginUser');
            $this->em()->persist($user);
        }
        $this->em()->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function begin(): string
    {
        $crawler = $this->client->request('GET', '/studio/connexions');
        $this->client->submit($crawler->selectButton('Connecter Instagram ↗')->form());
        self::assertResponseStatusCodeSame(303);
        $url = $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://www.instagram.com/oauth/authorize?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertArrayHasKey('state', $query);

        return $query['state'];
    }

    /** @return list<MockResponse> */
    private function responses(): array
    {
        return [
            new MockResponse(json_encode(['data' => [['access_token' => 'ig-short-private', 'user_id' => '123456', 'permissions' => InstagramApi::SCOPES]]], JSON_THROW_ON_ERROR)),
            new MockResponse('{"access_token":"ig-long-private","expires_in":5183944}'),
            new MockResponse('{"id":"123456","user_id":"17841400000000001","username":"vydflow_qa","account_type":"MEDIA_CREATOR"}'),
        ];
    }

    public function testCallbackStoresEncryptedAccessAndDisconnectOnlyRemovesOwnAccount(): void
    {
        static::getContainer()->set('instagram.http', new MockHttpClient($this->responses()));
        $this->client->loginUser($this->alice);
        $state = $this->begin();
        $this->client->request('GET', '/studio/connexions/instagram/retour', ['state' => $state, 'code' => 'valid-test-code']);
        self::assertResponseRedirects('/studio/connexions', 303);
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        $row = $this->em()->getRepository(InstagramConnection::class)->findOneBy(['owner' => $this->alice]);
        self::assertNotNull($row);
        self::assertSame('17841400000000001', $row->getInstagramId());
        self::assertGreaterThan(new \DateTimeImmutable('+59 days'), $row->getExpiresAt());
        self::assertNotSame('ig-long-private', $row->getAccessToken());
        $vault = new TokenVault($_ENV['INSTAGRAM_TOKEN_KEY']);
        self::assertSame('ig-long-private', $vault->decrypt($row->getAccessToken()));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.instagram-connection', '@vydflow_qa');
        self::assertStringNotContainsString('ig-long-private', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString($row->getAccessToken(), $this->client->getResponse()->getContent());
        // A second account can only see and disconnect its own row.
        $bob = $this->em()->find(User::class, $this->bob->getId());
        $this->em()->persist(new InstagramConnection($bob, '17841400000000002', 'other_qa', 'BUSINESS', $vault->encrypt('other-token'), 5183944));
        $this->em()->flush();
        $this->client->loginUser($bob);
        $crawler = $this->client->request('GET', '/studio/connexions');
        self::assertSelectorTextNotContains('.instagram-connection', 'vydflow_qa');
        self::assertSelectorTextContains('.instagram-connection', '@other_qa');
        $this->client->submit($crawler->filter('form[action="/studio/connexions/instagram/deconnecter"]')->selectButton('Déconnecter de VydFlow')->form());
        self::assertResponseRedirects('/studio/connexions', 303);
        self::assertSame(1, $this->em()->getRepository(InstagramConnection::class)->count([]));
        self::assertNotNull($this->em()->getRepository(InstagramConnection::class)->findOneBy(['owner' => $this->alice]));
    }

    public function testInvalidStateReplayExpiredStateAndDenialNeverCallMeta(): void
    {
        static::getContainer()->set('instagram.http', new MockHttpClient(static function (): never { self::fail('Invalid flow must not call Meta'); }));
        $this->client->loginUser($this->alice);
        $state = $this->begin();
        $this->client->request('GET', '/studio/connexions/instagram/retour', ['state' => 'wrong', 'code' => 'code']);
        self::assertResponseRedirects('/studio/connexions', 303);
        $this->client->request('GET', '/studio/connexions/instagram/retour', ['state' => $state, 'code' => 'code']);
        self::assertResponseRedirects('/studio/connexions', 303);
        $state = $this->begin();
        $this->client->request('GET', '/studio/connexions/instagram/retour', ['state' => $state, 'error' => 'access_denied']);
        self::assertResponseRedirects('/studio/connexions', 303);
        $state = $this->begin();
        $session = $this->client->getRequest()->getSession();
        $flow = $session->get('instagram_oauth');
        $flow['expires'] = time() - 1;
        $session->set('instagram_oauth', $flow);
        $session->save();
        $this->client->request('GET', '/studio/connexions/instagram/retour', ['state' => $state, 'code' => 'code']);
        self::assertResponseRedirects('/studio/connexions', 303);
        self::assertSame(0, $this->em()->getRepository(InstagramConnection::class)->count([]));
    }

    public function testAnonymousUnverifiedAndInvalidCsrfCannotConnectOrDisconnect(): void
    {
        static::getContainer()->set('instagram.http', new MockHttpClient(static function (): never { self::fail('Unauthorized flow must not call Meta'); }));
        $this->client->request('POST', '/studio/connexions/instagram');
        self::assertResponseRedirects('http://localhost/connexion');
        $this->client->loginUser($this->alice);
        foreach (['/studio/connexions/instagram', '/studio/connexions/instagram/deconnecter'] as $url) {
            $this->client->request('POST', $url, ['_token' => 'bad']);
            self::assertResponseStatusCodeSame(403);
        }
        $unverified = new User('instagram-unverified@example.test');
        $unverified->setPassword('unused');
        $this->em()->persist($unverified);
        $this->em()->flush();
        $this->client->loginUser($unverified);
        $this->client->request('POST', '/studio/connexions/instagram');
        self::assertResponseStatusCodeSame(403);
    }

    public function testProviderFailurePreservesExistingAccountAndHidesSecret(): void
    {
        $vault = new TokenVault($_ENV['INSTAGRAM_TOKEN_KEY']);
        $connection = new InstagramConnection($this->alice, '17841400000000001', 'old_profile', 'MEDIA_CREATOR', $vault->encrypt('old-private-token'), 5183944);
        $this->em()->persist($connection);
        $this->em()->flush();
        static::getContainer()->set('instagram.http', new MockHttpClient(static function (): never { throw new \RuntimeException('provider-private-secret'); }));
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', '/studio/connexions');
        $this->client->submit($crawler->selectButton('Reconnecter Instagram ↗')->form());
        parse_str((string) parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->client->request('GET', '/studio/connexions/instagram/retour', ['state' => $query['state'], 'code' => 'code']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.instagram-connection', '@old_profile');
        self::assertSelectorTextContains('.account-notice.error', 'Instagram a refusé l’échange du code de connexion');
        self::assertStringNotContainsString('provider-private-secret', $this->client->getResponse()->getContent());
        self::assertSame(1, $this->em()->getRepository(InstagramConnection::class)->count([]));
    }

    public function testFailedAndSuccessfulInstagramConnectionsPreserveYouTubeCredentialsAndDisplay(): void
    {
        $youtube = new YouTubeConnection($this->alice, 'UC'.str_repeat('a', 22), 'Chaîne conservée', 'youtube-access-ciphertext', 'youtube-refresh-ciphertext', 3600);
        $this->em()->persist($youtube);
        $this->em()->flush();
        $id = $youtube->getId();
        $this->client->loginUser($this->alice);
        static::getContainer()->set('instagram.http', new MockHttpClient([
            new MockResponse('{"error":{"code":100,"message":"private-provider-message"}}', ['http_code' => 400]),
            ...$this->responses(),
        ]));
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $state = $this->begin();
            $this->client->request('GET', '/studio/connexions/instagram/retour', ['state' => $state, 'code' => 'test-code']);
            self::assertResponseRedirects('/studio/connexions', 303);
            $this->client->followRedirect();
            self::assertSelectorTextContains('#youtube-title + p', 'Chaîne conservée');
            self::assertSelectorExists('form[action="/studio/connexions/youtube/deconnecter"]');
            self::assertStringNotContainsString('private-provider-message', $this->client->getResponse()->getContent());
            $db = static::getContainer()->get(Connection::class);
            $row = $db->fetchAssociative('SELECT id, access_token, refresh_token FROM youtube_connection WHERE owner_id = ?', [$this->alice->getId()]);
            self::assertIsArray($row);
            self::assertSame($id, $row['id']);
            self::assertSame('youtube-access-ciphertext', $row['access_token']);
            self::assertSame('youtube-refresh-ciphertext', $row['refresh_token']);
        }
    }

    public function testWrongOriginDoesNotStartEitherOAuthFlowOrChangeExistingYouTube(): void
    {
        $youtube = new YouTubeConnection($this->alice, 'UC'.str_repeat('a', 22), 'Chaîne conservée', 'access-ciphertext', 'refresh-ciphertext', 3600);
        $this->em()->persist($youtube);
        $this->em()->flush();
        $http = new MockHttpClient(static function (): never { self::fail('Wrong origin must never contact a social API'); });
        static::getContainer()->set('instagram.http', $http);
        static::getContainer()->set('youtube.http', $http);
        $this->client->loginUser($this->alice);
        foreach (['Instagram' => 'Connecter Instagram ↗', 'YouTube' => 'Changer / reconnecter ↗'] as $network => $button) {
            $crawler = $this->client->request('GET', 'https://localhost/studio/connexions');
            self::assertResponseIsSuccessful();
            $this->client->submit($crawler->selectButton($button)->form());
            self::assertResponseRedirects('/studio/connexions', 303);
            self::assertFalse($this->client->getRequest()->getSession()->has(strtolower($network).'_oauth'));
            $this->client->followRedirect();
            self::assertSelectorTextContains('.account-notice.error', 'La connexion et son retour doivent utiliser la même adresse');
            self::assertSelectorTextContains('#youtube-title + p', 'Chaîne conservée');
            self::assertSame(1, $this->em()->getRepository(YouTubeConnection::class)->count([]));
        }
        self::assertSame(0, $http->getRequestsCount());
    }

    public function testExpiredConnectionIsNeverDisplayedAsActive(): void
    {
        $connection = new InstagramConnection($this->alice, '17841400000000001', 'expired_profile', 'BUSINESS', 'synthetic-ciphertext-not-used', 1);
        $this->em()->persist($connection);
        $this->em()->flush();
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE instagram_connection SET expires_at = ? WHERE id = ?', ['2020-01-01 00:00:00', $connection->getId()]);
        $this->em()->clear();
        $owner = $this->em()->find(User::class, $this->alice->getId());
        $this->client->loginUser($owner);
        $this->client->request('GET', '/studio/connexions');
        self::assertSelectorTextContains('.instagram-connection [role="status"]', 'Autorisation expirée');
        self::assertSelectorTextNotContains('.instagram-connection', 'Compte connecté');
    }
}
