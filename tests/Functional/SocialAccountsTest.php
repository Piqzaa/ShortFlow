<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\InstagramConnection;
use App\Entity\PublicationDraft;
use App\Entity\PublicationStatistic;
use App\Entity\SeriesOccurrence;
use App\Entity\SeriesRecurrence;
use App\Entity\TikTokConnection;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Message\PublishYouTube;
use App\MessageHandler\PublishYouTubeHandler;
use App\Service\EpisodePublications;
use App\Service\InstagramApi;
use App\Service\PublicationDrafts;
use App\Service\PublicationException;
use App\Service\PublicationStatistics;
use App\Service\SocialAccounts;
use App\Service\TikTokApi;
use App\Service\TokenVault;
use App\Service\YouTubeApi;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SocialAccountsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private User $other;
    private VideoAsset $video;
    /** @var array<string,MockHttpClient> */
    private array $http = [];
    /** @var array<string,list<YouTubeConnection|InstagramConnection|TikTokConnection>> */
    private array $connections = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $this->em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        static::getContainer()->set('app.publication_clock', new MockClock('2026-10-26T08:00:00Z'));
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['youtube', 'instagram', 'tiktok', 'ai'] as $network) {
            $this->http[$network] = new MockHttpClient(static function (): never { throw new \LogicException('Unexpected remote call.'); });
            static::getContainer()->set($network.'.http', $this->http[$network]);
        }
        foreach (['owner', 'other'] as $name) {
            $user = new User($name.'-social-accounts@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em->persist($user);
            $this->$name = $user;
        }
        foreach (['youtube', 'instagram', 'tiktok'] as $network) {
            $vault = new TokenVault($_ENV[strtoupper($network).'_TOKEN_KEY']);
            foreach (['a', 'b'] as $letter) {
                $identity = $this->identity($network, $letter);
                $token = $vault->encrypt($network.'-'.$letter.'-token');
                $connection = match ($network) {
                    'youtube' => new YouTubeConnection($this->owner, $identity, 'Chaîne '.$letter, $token, $vault->encrypt('refresh-'.$letter), 3600),
                    'instagram' => new InstagramConnection($this->owner, $identity, 'profil_'.$letter, 'MEDIA_CREATOR', $token, 5184000),
                    'tiktok' => new TikTokConnection($this->owner, $identity, 'TikTok '.$letter, $token, $vault->encrypt('refresh-'.$letter), 86400, 31536000),
                };
                $this->connections[$network][] = $connection;
                $this->em->persist($connection);
            }
        }
        $this->video = new VideoAsset($this->owner, 'Ma vidéo', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1000, 8, 720, 1280, 'h264');
        $this->video->recordTechnicalMetadata(['fps' => 30, 'videoBitrate' => 3000000]);
        $this->em->persist($this->video);
        $this->em->flush();
        $this->client->loginUser($this->owner);
    }

    private function identity(string $network, string $letter): string
    {
        return match ($network) {
            'youtube' => 'UC'.str_repeat($letter, 22),
            'instagram' => '1784140000000000'.(ord($letter) - 96),
            'tiktok' => 'creator-'.$letter,
        };
    }

    public function testMultipleConnectionsAreListedWithoutChoosingAnArbitraryDefaultOrLeakingTokens(): void
    {
        $accounts = static::getContainer()->get(SocialAccounts::class);
        foreach (['youtube', 'instagram', 'tiktok'] as $network) {
            self::assertNull($accounts->find($this->owner, $network));
            self::assertSame($this->connections[$network][1], $accounts->find($this->owner, $network, $this->identity($network, 'b')));
            self::assertNull($accounts->find($this->other, $network, $this->identity($network, 'b')));
        }
        $this->client->request('GET', '/studio/connexions');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(6, '.connection-panel');
        self::assertSelectorTextContains('.social-connection-group', 'Chaîne a');
        foreach ($this->connections as $connections) {
            foreach ($connections as $connection) {
                self::assertStringNotContainsString($connection->getAccessToken(), $this->client->getResponse()->getContent());
            }
        }
        $this->expectException(PublicationException::class);
        $accounts->select($this->owner, ['youtube'], [], false);
    }

    public function testComposerSavesTheChosenAccountAndRejectsAnotherOwnersAccount(): void
    {
        $this->client->request('GET', '/studio/composer', ['videos' => [$this->video->getId()]]);
        $crawler = $this->client->followRedirect();
        $url = $this->client->getRequest()->getUri();
        $payload = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $payload['publication_composer']['networks'] = ['youtube', 'instagram'];
        $payload['publication_composer']['accounts'] = ['youtube' => $this->identity('youtube', 'b'), 'instagram' => $this->identity('instagram', 'b')];
        $payload['intent'] = 'refresh';
        $this->client->request('POST', $url, $payload);
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('button[name="intent"][value="refresh"].composer-refresh-needed');
        self::assertSelectorNotExists('#publication_composer_accounts_youtube[data-action]');
        $payload = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $payload['intent'] = 'save';
        $this->client->request('POST', $url, $payload);
        self::assertResponseStatusCodeSame(303);
        $draft = $this->em->getRepository(PublicationDraft::class)->findOneBy(['owner' => $this->owner]);
        self::assertSame(['youtube' => $this->identity('youtube', 'b'), 'instagram' => $this->identity('instagram', 'b')], $draft->getAccounts());
        $foreign = new YouTubeConnection($this->em->find(User::class, $this->other->getId()), $this->identity('youtube', 'c'), 'Autre propriétaire', 'not-used', 'not-used', 3600);
        $this->em->persist($foreign);
        $this->em->flush();
        $crawler = $this->client->request('GET', $url);
        $payload = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $payload['publication_composer']['accounts']['youtube'] = $foreign->getChannelId();
        $payload['intent'] = 'save';
        $this->client->request('POST', $url, $payload);
        self::assertResponseStatusCodeSame(422);
        $draft = $this->em->find(PublicationDraft::class, $draft->getId());
        self::assertSame($this->identity('youtube', 'b'), $draft->getAccounts()['youtube']);
        self::assertSame(0, $this->em->getRepository(YouTubePublication::class)->count([]));
    }

    public function testRemovingAnotherAccountDoesNotChangeAFrozenDraftAndMissingTargetNeverFallsBack(): void
    {
        $drafts = static::getContainer()->get(PublicationDrafts::class);
        $id = $this->video->getId();
        $draft = $drafts->save($this->owner, [$id], ['youtube'], ['youtube' => ['videos' => [$id => ['title' => 'Ma vidéo', 'description' => '', 'privacy' => 'private', 'audience' => 'no', 'synthetic' => false]]]], ['youtube' => $this->identity('youtube', 'a')], 'Préparation');
        self::assertTrue($drafts->review($draft)['ready']);
        $this->em->remove($this->connections['youtube'][1]);
        $this->em->flush();
        self::assertTrue($drafts->review($draft)['ready']);
        $this->em->remove($this->connections['youtube'][0]);
        $this->em->persist(new YouTubeConnection($this->owner, $this->identity('youtube', 'c'), 'Autre chaîne', 'not-used', 'not-used', 3600));
        $this->em->flush();
        self::assertFalse($drafts->review($draft)['ready']);
        self::assertSame($this->identity('youtube', 'a'), $draft->getAccounts()['youtube']);
        self::assertSame(0, $this->em->getRepository(YouTubePublication::class)->count([]));
    }

    public function testWorkerAndStatisticsUseTheSecondAccountCredentials(): void
    {
        $connection = $this->connections['youtube'][1];
        self::assertInstanceOf(YouTubeConnection::class, $connection);
        $job = new YouTubePublication($this->owner, $this->video, $connection, bin2hex(random_bytes(32)), 'Ma vidéo', '', 'private', false, false);
        $job->uploaded('abcdefghijk');
        $this->em->persist($job);
        $this->em->flush();
        $calls = 0;
        $this->http['youtube']->setResponseFactory(function (string $method, string $url, array $options) use (&$calls): MockResponse {
            ++$calls;
            self::assertStringContainsString('Bearer youtube-b-token', implode("\n", $options['headers']));

            return new MockResponse(json_encode(['items' => [['id' => 'abcdefghijk', 'snippet' => ['channelId' => $this->identity('youtube', 'b')], 'status' => ['uploadStatus' => 'processed', 'privacyStatus' => 'private'], 'processingDetails' => ['processingStatus' => 'succeeded'], 'statistics' => ['viewCount' => '12', 'likeCount' => '3', 'commentCount' => '1']]]], JSON_THROW_ON_ERROR));
        });
        static::getContainer()->get(PublishYouTubeHandler::class)(new PublishYouTube($job->getId()));
        self::assertSame('published', $job->getStatus());
        $statistics = static::getContainer()->get(PublicationStatistics::class);
        self::assertTrue($statistics->queue($this->owner, 'youtube', $job->getId()));
        $snapshot = $this->em->getRepository(PublicationStatistic::class)->findOneBy(['owner' => $this->owner]);
        $statistics->refresh($snapshot->getId(), $snapshot->getRequestKey());
        self::assertSame('ready', $snapshot->getStatus());
        self::assertSame(2, $calls);
    }

    /** @return iterable<string,array{string}> */
    public static function networks(): iterable
    {
        foreach (['youtube', 'instagram', 'tiktok'] as $network) {
            yield $network => [$network];
        }
    }

    public function testSeriesOccurrenceKeepsItsAccountAndLegacyOccurrenceRequiresAChoice(): void
    {
        $series = new AiSeries($this->owner, 'Milo', 'Milo joue', 'réaliste', 'chat blanc', ['model' => 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $recurrence = new SeriesRecurrence($series);
        $this->em->persist($series);
        $this->em->persist($recurrence);
        foreach ([1 => ['youtube' => $this->identity('youtube', 'b')], 2 => []] as $position => $snapshot) {
            $episode = new AiEpisode($series, $position);
            $episode->flow('ready');
            $episode->approve($this->video);
            $occurrence = new SeriesOccurrence($recurrence, new \DateTimeImmutable('2026-10-'.(26 + $position).'T18:00:00Z'), 'Europe/Paris', ['youtube'], new \DateTimeImmutable('2026-10-26T08:00:00Z'), $episode, $snapshot);
            $this->em->persist($episode);
            $this->em->persist($occurrence);
            $this->em->flush();
            $draft = static::getContainer()->get(EpisodePublications::class)->prepare($this->owner, $episode, $episode->getRevision());
            self::assertSame(['youtube' => $snapshot['youtube'] ?? null], $draft->getAccounts());
            self::assertSame($episode, $draft->getEpisode());
        }
        self::assertSame(0, $this->em->getRepository(YouTubePublication::class)->count([]));
    }

    #[DataProvider('networks')]
    public function testReconnectSameIdentityUpdatesCredentialsWithoutReplacingItsRow(string $network): void
    {
        $this->http[$network]->setResponseFactory($this->oauthResponses($network, 'a'));
        $connection = $this->connections[$network][0];
        $id = $connection->getId();
        $before = $connection->getAccessToken();
        $crawler = $this->client->request('GET', '/studio/connexions');
        $form = $crawler->filter('form[action="/studio/connexions/'.$network.'"]')->reduce(fn ($node): bool => $node->filter('input[name="account"][value="'.$this->identity($network, 'a').'"]')->count() > 0)->form();
        $this->client->submit($form);
        parse_str(parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->client->request('GET', '/studio/connexions/'.$network.'/retour', ['state' => $query['state'], 'code' => 'synthetic']);
        self::assertResponseStatusCodeSame(303);
        $connection = $this->em->find($connection::class, $id);
        self::assertSame($id, $connection->getId());
        self::assertNotSame($before, $connection->getAccessToken());
        self::assertCount(2, static::getContainer()->get(SocialAccounts::class)->all($this->owner, $network));
    }

    /** @return list<MockResponse> */
    private function oauthResponses(string $network, string $letter): array
    {
        $identity = $this->identity($network, $letter);
        $data = match ($network) {
            'youtube' => [['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 3600, 'scope' => YouTubeApi::SCOPES], ['items' => [['id' => $identity, 'snippet' => ['title' => 'Nouveau nom']]]]],
            'instagram' => [['data' => [['access_token' => 'short-access', 'user_id' => '123456', 'permissions' => InstagramApi::SCOPES]]], ['access_token' => 'long-access', 'expires_in' => 5184000], ['id' => '123456', 'user_id' => $identity, 'username' => 'nouveau_nom', 'account_type' => 'MEDIA_CREATOR']],
            'tiktok' => [['open_id' => $identity, 'access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'expires_in' => 86400, 'refresh_expires_in' => 31536000, 'scope' => implode(',', TikTokApi::SCOPES), 'token_type' => 'Bearer'], ['data' => ['user' => ['open_id' => $identity, 'display_name' => 'Nouveau nom']], 'error' => ['code' => 'ok']]],
        };

        return array_map(static fn (array $row): MockResponse => new MockResponse(json_encode($row, JSON_THROW_ON_ERROR)), $data);
    }

    #[DataProvider('networks')]
    public function testAddingAThirdAccountKeepsTheTwoExistingIdentities(string $network): void
    {
        $this->http[$network]->setResponseFactory($this->oauthResponses($network, 'c'));
        $crawler = $this->client->request('GET', '/studio/connexions');
        $label = ['youtube' => 'YouTube', 'instagram' => 'Instagram', 'tiktok' => 'TikTok'][$network];
        $this->client->submit($crawler->selectButton('Ajouter un compte '.$label.' ↗')->form());
        parse_str(parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->client->request('GET', '/studio/connexions/'.$network.'/retour', ['state' => $query['state'], 'code' => 'synthetic']);
        self::assertResponseStatusCodeSame(303);
        $accounts = static::getContainer()->get(SocialAccounts::class);
        self::assertCount(3, $accounts->all($this->owner, $network));
        foreach (['a', 'b', 'c'] as $letter) {
            self::assertNotNull($accounts->find($this->owner, $network, $this->identity($network, $letter)));
        }
    }

    #[DataProvider('networks')]
    public function testTargetedReconnectRejectsAnotherIdentityWithoutOverwritingAnyAccount(string $network): void
    {
        $this->http[$network]->setResponseFactory($this->oauthResponses($network, 'b'));
        $before = $this->connections[$network][0]->getAccessToken();
        $crawler = $this->client->request('GET', '/studio/connexions');
        $form = $crawler->filter('form[action="/studio/connexions/'.$network.'"]')->reduce(fn ($node): bool => $node->filter('input[name="account"][value="'.$this->identity($network, 'a').'"]')->count() > 0)->form();
        $this->client->submit($form);
        parse_str(parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->client->request('GET', '/studio/connexions/'.$network.'/retour', ['state' => $query['state'], 'code' => 'synthetic']);
        self::assertResponseStatusCodeSame(303);
        self::assertCount(2, static::getContainer()->get(SocialAccounts::class)->all($this->owner, $network));
        self::assertSame($before, $this->connections[$network][0]->getAccessToken());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.account-notice.error', 'youtube' === $network ? 'autre chaîne' : 'autre compte');
    }

    #[DataProvider('networks')]
    public function testDisconnectOnlyRemovesTheExplicitlyChosenAccount(string $network): void
    {
        if ('tiktok' === $network) {
            $this->http[$network]->setResponseFactory(new MockResponse('{"error":{"code":"ok"}}'));
        }
        $crawler = $this->client->request('GET', '/studio/connexions');
        $form = $crawler->filter('form[action="/studio/connexions/'.$network.'/deconnecter"]')->reduce(fn ($node): bool => $node->filter('input[name="account"][value="'.$this->identity($network, 'b').'"]')->count() > 0)->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $accounts = static::getContainer()->get(SocialAccounts::class);
        self::assertNotNull($accounts->find($this->owner, $network, $this->identity($network, 'a')));
        self::assertNull($accounts->find($this->owner, $network, $this->identity($network, 'b')));
    }
}
