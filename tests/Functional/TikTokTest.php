<?php

namespace App\Tests\Functional;

use App\Entity\TikTokConnection;
use App\Entity\TikTokPublication;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Message\PublishTikTok;
use App\MessageHandler\PublishTikTokHandler;
use App\Repository\VideoAssetRepository;
use App\Service\MultiNetworkPublishing;
use App\Service\PrivateVideoStorage;
use App\Service\PublicationException;
use App\Service\TikTokApi;
use App\Service\TikTokMediaLink;
use App\Service\TikTokPublishing;
use App\Service\TokenVault;
use App\Service\VideoLibrary;
use App\Service\VideoLibraryException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TikTokTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    /** @var list<string> */ private array $files = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        $this->owner = new User('tiktok@example.test');
        $this->other = new User('tiktok-other@example.test');
        foreach ([$this->owner, $this->other] as $user) {
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
        }
        $this->em()->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function vault(): TokenVault
    {
        return new TokenVault($_ENV['TIKTOK_TOKEN_KEY']);
    }

    private function connection(): TikTokConnection
    {
        $connection = new TikTokConnection($this->owner, 'creator-1', 'Créateur QA', $this->vault()->encrypt('private-access'), $this->vault()->encrypt('private-refresh'), 86400, 31536000);
        $this->em()->persist($connection);
        $this->em()->flush();

        return $connection;
    }

    /** @return array<string, mixed> */
    private function creator(): array
    {
        return ['creator_nickname' => 'Créateur QA', 'creator_username' => 'qa', 'privacy_level_options' => ['FOLLOWER_OF_CREATOR', 'SELF_ONLY'], 'max_video_post_duration_sec' => 60, 'comment_disabled' => false, 'duet_disabled' => false, 'stitch_disabled' => false];
    }

    /** @return array<string, mixed> */
    private function row(): array
    {
        return ['caption' => 'Vidéo de test', 'privacy' => 'SELF_ONLY', 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => false];
    }

    /** @param array<string, mixed> $data */
    private function response(array $data): MockResponse
    {
        return new MockResponse(json_encode(['data' => $data, 'error' => ['code' => 'ok']], JSON_THROW_ON_ERROR));
    }

    private function video(?User $owner = null): VideoAsset
    {
        $storage = static::getContainer()->get(PrivateVideoStorage::class);
        self::assertStringEndsWith('/var/storage/videos/test', str_replace('\\', '/', $storage->directory()));
        $name = bin2hex(random_bytes(24)).'.mp4';
        $temp = tempnam(sys_get_temp_dir(), 'tiktok-');
        copy(dirname(__DIR__).'/Fixtures/reels-valid.mp4', $temp);
        $storage->store(new UploadedFile($temp, 'clip.mp4', 'video/mp4', null, true), $name);
        $this->files[] = $storage->path($name);
        // Declared probe metadata is sufficient here; format probing has dedicated library tests.
        $video = new VideoAsset($owner ?? $this->owner, 'Mon TikTok <test>', $name, 'video/mp4', (int) filesize($storage->path($name)), 3, 720, 1280, 'h264');
        $this->em()->persist($video);
        $this->em()->flush();

        return $video;
    }

    private function job(): TikTokPublication
    {
        $connection = $this->connection();
        $publishing = static::getContainer()->get(TikTokPublishing::class);
        $job = new TikTokPublication($this->owner, $this->video(), $connection, bin2hex(random_bytes(32)), 'Créateur QA', $publishing->options($this->row(), $this->creator()));
        $this->em()->persist($job);
        $this->em()->flush();

        return $job;
    }

    public function testProfileLinkUsesCurrentUsernameAndNeverDisplayName(): void
    {
        $this->connection();
        static::getContainer()->set('tiktok.http', new MockHttpClient(function (string $method, string $url): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://open.tiktokapis.com/v2/post/publish/creator_info/query/', $url);

            return $this->response(['creator_username' => 'real.handle_12']);
        }));
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/connexions');
        self::assertSelectorExists('a[href="/studio/connexions/tiktok/profil"][rel="noopener noreferrer"]');
        self::assertSelectorTextSame('title', 'Mes connexions — VydFlow');
        self::assertSelectorCount(1, '#ai-connections-title');
        $this->client->request('GET', '/studio/connexions/tiktok/profil');
        self::assertResponseRedirects('https://www.tiktok.com/@real.handle_12', 303);
        self::assertSame('no-referrer', $this->client->getResponse()->headers->get('Referrer-Policy'));
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/connexions/tiktok/profil');
        self::assertResponseRedirects('/studio/connexions', 303);
    }

    public function testProfileLinkRejectsUnexpectedProviderUsername(): void
    {
        $this->connection();
        static::getContainer()->set('tiktok.http', new MockHttpClient(fn (): MockResponse => $this->response(['creator_username' => 'evil.example/?next=bad'])));
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/connexions/tiktok/profil');
        self::assertResponseRedirects('/studio/connexions', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.account-notice.error', 'TikTok n’a pas fourni le lien du profil');
    }

    /** @return iterable<string, array{bool, bool, string}> */
    public static function blockedSendingModes(): iterable
    {
        yield 'posting disabled' => [true, false, 'L’envoi TikTok est encore désactivé'];
        yield 'media URL unverified' => [false, true, 'L’adresse de téléchargement des vidéos doit être vérifiée'];
    }

    #[DataProvider('blockedSendingModes')]
    public function testConnectedAccountExplainsWhySendingIsBlocked(bool $verified, bool $enabled, string $message): void
    {
        $connection = $this->connection();
        $video = $this->video();
        static::getContainer()->set(TikTokMediaLink::class, new TikTokMediaLink('https://vydflow.example.test', 'synthetic-secret', $verified, $enabled));
        static::getContainer()->set('tiktok.http', new MockHttpClient(static function (): never {
            self::fail('A blocked destination must not call TikTok.');
        }));
        $context = static::getContainer()->get(MultiNetworkPublishing::class)->context($this->owner, [$video->getId()], ['tiktok'], ['tiktok' => $connection->getOpenId()]);
        self::assertFalse($context['ready']);
        self::assertStringStartsWith($message, $context['issues']['tiktok'][0]);
        self::assertSame($connection->getOpenId(), $context['destinations']['tiktok']['account']);
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    private function begin(): string
    {
        $crawler = $this->client->request('GET', '/studio/connexions');
        $this->client->submit($crawler->selectButton('Connecter TikTok ↗')->form());
        self::assertResponseStatusCodeSame(303);
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        $url = $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize/', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        self::assertSame('user.info.basic,video.publish,video.list', $query['scope']);

        return $query['state'];
    }

    public function testOauthEncryptionRefreshRotationAndRevocation(): void
    {
        $tokens = ['open_id' => 'creator-1', 'access_token' => 'access-original', 'refresh_token' => 'refresh-original', 'expires_in' => 3600, 'refresh_expires_in' => 31536000, 'scope' => implode(',', TikTokApi::SCOPES), 'token_type' => 'Bearer'];
        static::getContainer()->set('tiktok.http', new MockHttpClient([
            new MockResponse(json_encode($tokens, JSON_THROW_ON_ERROR)), $this->response(['user' => ['open_id' => 'creator-1', 'display_name' => 'Créateur QA']]),
            new MockResponse(json_encode(array_replace($tokens, ['access_token' => 'access-rotated', 'refresh_token' => 'refresh-rotated']), JSON_THROW_ON_ERROR)),
            new MockResponse('{}'),
        ]));
        $this->client->loginUser($this->owner);
        $state = $this->begin();
        $this->client->request('GET', '/studio/connexions/tiktok/retour', ['state' => $state, 'code' => 'test-code']);
        self::assertResponseRedirects('/studio/connexions', 303);
        $row = $this->em()->getRepository(TikTokConnection::class)->findOneBy(['owner' => $this->owner]);
        self::assertNotNull($row);
        self::assertSame('access-original', $this->vault()->decrypt($row->getAccessToken()));
        self::assertNotSame('access-original', $row->getAccessToken());
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE tiktok_connection SET expires_at = ? WHERE id = ?', ['2020-01-01', $row->getId()]);
        $this->em()->refresh($row);
        self::assertSame('access-rotated', static::getContainer()->get(TikTokApi::class)->access($row));
        self::assertSame('refresh-rotated', $this->vault()->decrypt($row->getRefreshToken()));
        $crawler = $this->client->request('GET', '/studio/connexions');
        self::assertStringNotContainsString('access-rotated', $this->client->getResponse()->getContent());
        $form = $crawler->filter('form[action="/studio/connexions/tiktok/deconnecter"]')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/connexions', 303);
        self::assertSame(0, $this->em()->getRepository(TikTokConnection::class)->count([]));
    }

    public function testUnauthorizedStateReplayDenialAndCsrfNeverCallTikTok(): void
    {
        static::getContainer()->set('tiktok.http', new MockHttpClient(static function (): never { self::fail('No HTTP for unauthorized flow'); }));
        $this->client->request('POST', '/studio/connexions/tiktok');
        self::assertResponseRedirects('http://localhost/connexion');
        $this->client->loginUser($this->owner);
        foreach (['', '/deconnecter'] as $suffix) {
            $this->client->request('POST', '/studio/connexions/tiktok'.$suffix);
            self::assertResponseStatusCodeSame(403);
        }
        $state = $this->begin();
        foreach (['wrong', $state] as $value) {
            $this->client->request('GET', '/studio/connexions/tiktok/retour', ['state' => $value, 'code' => 'code']);
            self::assertResponseRedirects('/studio/connexions', 303);
        }
        $state = $this->begin();
        $this->client->request('GET', '/studio/connexions/tiktok/retour', ['state' => $state, 'error' => 'access_denied']);
        self::assertResponseRedirects('/studio/connexions', 303);
        $state = $this->begin();
        $session = $this->client->getRequest()->getSession();
        $flow = $session->get('tiktok_oauth');
        $flow['expires'] = time() - 1;
        $session->set('tiktok_oauth', $flow);
        $session->save();
        $this->client->request('GET', '/studio/connexions/tiktok/retour', ['state' => $state, 'code' => 'code']);
        self::assertResponseRedirects('/studio/connexions', 303);
        $state = $this->begin();
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/connexions/tiktok/retour', ['state' => $state, 'code' => 'code']);
        self::assertSame(0, $this->em()->getRepository(TikTokConnection::class)->count([]));
    }

    public function testPreparationRequiresExplicitPrivacyConsentAndQueuesIdempotently(): void
    {
        $connection = $this->connection();
        $video = $this->video();
        $foreign = $this->video($this->other);
        static::getContainer()->set('tiktok.http', new MockHttpClient(fn (): MockResponse => $this->response($this->creator())));
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/publications/tiktok/preparer', ['videos' => [$foreign->getId()]]);
        self::assertResponseStatusCodeSame(404);
        $crawler = $this->client->request('GET', '/studio/publications/tiktok/preparer', ['videos' => [$video->getId()]]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select option[value=""]');
        self::assertSelectorNotExists('input[type="checkbox"][checked]');
        $form = $crawler->selectButton('Publier 1 vidéo sur TikTok ↗')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $form['tiktok_publish[confirm]'] = true;
        $form['tiktok_publish[videos]['.$video->getId().'][privacy]'] = 'SELF_ONLY';
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/publications', 303);
        self::assertSame(1, $this->em()->getRepository(TikTokPublication::class)->count([]));
        $job = $this->em()->getRepository(TikTokPublication::class)->findOneBy([]);
        $key = static::getContainer()->get(Connection::class)->fetchOne('SELECT submission_key FROM tiktok_publication WHERE id = ?', [$job->getId()]);
        $owner = $this->em()->find(User::class, $this->owner->getId());
        static::getContainer()->get(TikTokPublishing::class)->enqueue($owner, [(int) $video->getId()], [$video->getId() => $this->row()], $key, $connection->getOpenId());
        self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->client->request('GET', '/studio/publications?network=tiktok');
        self::assertSelectorExists('[data-network="tiktok"]');
        self::assertStringNotContainsString('<test>', $this->client->getResponse()->getContent());
    }

    public function testHandlerPersistsIntentAndPrivateCompletionWithoutPublicId(): void
    {
        $job = $this->job();
        $calls = 0;
        static::getContainer()->get('tiktok.http')->setResponseFactory(function (string $method, string $url, array $options) use ($job, &$calls): MockResponse {
            ++$calls;
            if (str_contains($url, 'creator_info')) {
                return $this->response($this->creator());
            }
            if (str_contains($url, '/video/init/')) {
                self::assertTrue((bool) static::getContainer()->get(Connection::class)->fetchOne('SELECT init_attempted FROM tiktok_publication WHERE id = ?', [$job->getId()]));
                $body = json_decode($options['body'], true, flags: JSON_THROW_ON_ERROR);
                self::assertSame('PULL_FROM_URL', $body['source_info']['source']);
                self::assertStringContainsString('/media/tiktok/'.$job->getId().'/', $body['source_info']['video_url']);
                self::assertSame('SELF_ONLY', $body['post_info']['privacy_level']);
                self::assertTrue($body['post_info']['disable_comment']);

                return $this->response(['publish_id' => 'publish-1']);
            }
            self::assertSame('publish-1', $job->getPublishId());

            return $this->response(['status' => 'PUBLISH_COMPLETE']);
        });
        $handler = static::getContainer()->get(PublishTikTokHandler::class);
        $message = new PublishTikTok((int) $job->getId());
        $handler($message);
        self::assertSame('processing', $job->getStatus());
        $handler($message);
        self::assertSame('published', $job->getStatus());
        self::assertNull($job->getRemoteId());
        $handler($message);
        self::assertSame(3, $calls);
        self::assertSame([$job->getSource()->getId() => ['tiktok']], static::getContainer()->get(VideoAssetRepository::class)->publishedPlatforms($this->owner, [$job->getSource()]));
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/bibliotheque');
        self::assertSelectorExists('svg[aria-label="TikTok"]');
        $this->client->request('GET', '/studio/publications?network=tiktok');
        self::assertSelectorTextContains('.publication-result', 'Le lien public n’est pas disponible');
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/publications?network=tiktok');
        self::assertSelectorNotExists('.publication-item');
    }

    public function testLostInitResponseAndCrashNeverRepeatInit(): void
    {
        $job = $this->job();
        $posts = 0;
        static::getContainer()->get('tiktok.http')->setResponseFactory(function (string $method, string $url) use (&$posts): MockResponse {
            if (str_contains($url, 'creator_info')) {
                return $this->response($this->creator());
            }
            ++$posts;

            return new MockResponse('private-provider-error', ['http_code' => 503]);
        });
        $handler = static::getContainer()->get(PublishTikTokHandler::class);
        $message = new PublishTikTok((int) $job->getId());
        $handler($message);
        self::assertSame('review', $job->getStatus());
        $handler($message);
        self::assertSame(1, $posts);
        // Simulate a process dying immediately after intent was saved.
        $job->change('publishing');
        $this->em()->flush();
        $handler($message);
        self::assertSame('review', $job->getStatus());
        self::assertSame(1, $posts);
    }

    public function testSignedMediaRangeExpirySourceProtectionAndRevocation(): void
    {
        $job = $this->job();
        $path = parse_url(static::getContainer()->get(TikTokMediaLink::class)->url($job), PHP_URL_PATH);
        $this->client->request('GET', $path, server: ['HTTP_RANGE' => 'bytes=0-9']);
        self::assertResponseStatusCodeSame(206);
        self::assertResponseHeaderSame('Content-Length', '10');
        $this->client->request('GET', str_replace('/'.($job->getCreatedAt()->getTimestamp() + 86400).'/', '/1/', $path));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', substr($path, 0, -1).('a' === substr($path, -1) ? 'b' : 'a'));
        self::assertResponseStatusCodeSame(404);
        try {
            $source = $this->em()->find(VideoAsset::class, $job->getSource()->getId());
            static::getContainer()->get(VideoLibrary::class)->delete($source);
            self::fail('Active source must stay');
        } catch (VideoLibraryException $error) {
            self::assertStringContainsString('envoi en cours', $error->getMessage());
        }
        // The rollback closes Doctrine; use a fresh kernel for the HTTP actions.
        $id = $job->getId();
        $ownerId = $this->owner->getId();
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $owner = $this->em()->find(User::class, $ownerId);
        $this->client->loginUser($owner);
        $this->client->request('POST', '/studio/publications/tiktok/'.$id.'/arreter');
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/studio/publications');
        $this->client->submit($crawler->selectButton('Arrêter le suivi')->form());
        self::assertResponseRedirects('/studio/publications', 303);
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
    }

    public function testBatchRollbackForForeignSource(): void
    {
        $connection = $this->connection();
        $own = $this->video();
        $foreign = $this->video($this->other);
        static::getContainer()->set('tiktok.http', new MockHttpClient(fn (): MockResponse => $this->response($this->creator())));
        try {
            static::getContainer()->get(TikTokPublishing::class)->enqueue($this->owner, [(int) $own->getId(), (int) $foreign->getId()], [$own->getId() => $this->row(), $foreign->getId() => $this->row()], str_repeat('b', 64), $connection->getOpenId());
            self::fail('Foreign source must reject the entire batch');
        } catch (PublicationException) {
            $db = static::getContainer()->get(Connection::class);
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM tiktok_publication'));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        }
    }

    public function testChangedAccountPausesWithoutHttpAndResumeOnlyPollsExistingId(): void
    {
        $job = $this->job();
        $connection = $this->em()->getRepository(TikTokConnection::class)->findOneBy(['owner' => $this->owner]);
        $connection->reconnect('different-creator', 'Other', $connection->getAccessToken(), $connection->getRefreshToken(), 86400, 31536000);
        $this->em()->flush();
        static::getContainer()->get('tiktok.http')->setResponseFactory(static function (): never { self::fail('Changed account must not call HTTP'); });
        $handler = static::getContainer()->get(PublishTikTokHandler::class);
        $message = new PublishTikTok((int) $job->getId());
        $handler($message);
        self::assertSame('paused', $job->getStatus());
        $connection->reconnect('creator-1', 'QA', $connection->getAccessToken(), $connection->getRefreshToken(), 86400, 31536000);
        $job->attempt();
        $job->started('known-publish-id');
        $job->change('paused');
        $this->em()->flush();
        static::getContainer()->get(TikTokPublishing::class)->resume($job);
        self::assertSame('processing', $job->getStatus());
        static::getContainer()->get('tiktok.http')->setResponseFactory(function (string $method, string $url): MockResponse {
            self::assertStringContainsString('/status/fetch/', $url);

            return $this->response(['status' => 'PUBLISH_COMPLETE', 'publicaly_available_post_id' => ['123456789']]);
        });
        $handler($message);
        self::assertSame('published', $job->getStatus());
        self::assertSame('123456789', $job->getRemoteId());
    }

    public function testRefreshProviderFailureStillPersistsWorkerPause(): void
    {
        $job = $this->job();
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE tiktok_connection SET expires_at = ?', ['2020-01-01']);
        static::getContainer()->get('tiktok.http')->setResponseFactory(static fn (): MockResponse => new MockResponse('private-error', ['http_code' => 503]));
        $handler = static::getContainer()->get(PublishTikTokHandler::class);
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $handler(new PublishTikTok((int) $job->getId()));
        }
        self::assertTrue($this->em()->isOpen());
        self::assertSame('paused', $job->getStatus());
        self::assertFalse($job->isInitAttempted());
        self::assertSame('paused', static::getContainer()->get(Connection::class)->fetchOne('SELECT status FROM tiktok_publication WHERE id = ?', [$job->getId()]));
    }
}
