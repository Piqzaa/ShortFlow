<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Message\PublishYouTube;
use App\MessageHandler\PublishYouTubeHandler;
use App\Repository\UserRepository;
use App\Service\PrivateVideoStorage;
use App\Service\TokenVault;
use App\Service\YouTubePublishing;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class YouTubePublicationTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $alice;
    private User $bob;
    /** @var list<string> */ private array $files = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        $em = $this->em();
        $this->alice = new User('publisher@example.test');
        $this->alice->verify();
        $this->alice->setPassword('unused');
        $this->bob = new User('other@example.test');
        $this->bob->verify();
        $this->bob->setPassword('unused');
        $em->persist($this->alice);
        $em->persist($this->bob);
        $em->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        } parent::tearDown();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function connect(User $owner): YouTubeConnection
    {
        $vault = static::getContainer()->get(TokenVault::class);
        $connection = new YouTubeConnection($owner, 'UC'.str_repeat('a', 22), 'Ma chaîne <test>', $vault->encrypt('access-test'), $vault->encrypt('refresh-test'), 3600);
        $this->em()->persist($connection);
        $this->em()->flush();

        return $connection;
    }

    private function video(User $owner, string $title = 'Mon clip'): VideoAsset
    {
        $storage = static::getContainer()->get(PrivateVideoStorage::class);
        self::assertStringEndsWith('/var/storage/videos/test', str_replace('\\', '/', $storage->directory()));
        $name = bin2hex(random_bytes(24)).'.mp4';
        $temp = tempnam(sys_get_temp_dir(), 'yt-test-');
        copy(dirname(__DIR__).'/Fixtures/library-valid.mp4', $temp);
        $storage->store(new UploadedFile($temp, 'clip.mp4', 'video/mp4', null, true), $name);
        $path = $storage->path($name);
        $this->files[] = $path;
        $video = new VideoAsset($owner, $title, $name, 'video/mp4', (int) filesize($path), 1, 96, 160, 'h264');
        $this->em()->persist($video);
        $this->em()->flush();

        return $video;
    }

    /** @return array<string,mixed> */
    private function row(): array
    {
        return ['title' => 'Clip choisi', 'description' => 'Ma légende', 'privacy' => 'private', 'audience' => 'no', 'synthetic' => false];
    }

    private function job(VideoAsset $video): YouTubePublication
    {
        $connection = $this->connect($this->alice);
        $job = new YouTubePublication($this->alice, $video, $connection, bin2hex(random_bytes(32)), 'Clip', '', 'private', false, false);
        $this->em()->persist($job);
        $this->em()->flush();

        return $job;
    }

    public function testPublishedBadgeRequiresSuccessAndKeepsOwnerIsolation(): void
    {
        $video = $this->video($this->alice);
        $foreign = $this->video($this->bob);
        $job = $this->job($video);
        $repository = static::getContainer()->get(\App\Repository\VideoAssetRepository::class);
        self::assertSame([], $repository->publishedPlatforms($this->alice, []));
        $this->client->loginUser($this->alice);
        $this->client->request('GET', '/studio/bibliotheque');
        self::assertSelectorNotExists('.video-published');
        $job->uploaded('abcdefghijk');
        $this->em()->flush();
        self::assertSame([], $repository->publishedPlatforms($this->alice, [$video]));
        $job->change('published');
        $connection = $this->em()->getRepository(YouTubeConnection::class)->findOneBy(['owner' => $this->alice]);
        $duplicate = new YouTubePublication($this->alice, $video, $connection, bin2hex(random_bytes(32)), 'Clip bis', '', 'private', false, false);
        $duplicate->uploaded('bcdefghijkl');
        $duplicate->change('published');
        $this->em()->persist($duplicate);
        // Even an inconsistent owner/source record must never leak a badge.
        $inconsistent = new YouTubePublication($this->alice, $foreign, $connection, bin2hex(random_bytes(32)), 'Foreign', '', 'private', false, false);
        $inconsistent->uploaded('cdefghijklm');
        $inconsistent->change('published');
        $this->em()->persist($inconsistent);
        $this->em()->remove($connection);
        $this->em()->flush();
        self::assertSame([$video->getId() => ['youtube']], $repository->publishedPlatforms($this->alice, [$video, $foreign]));
        foreach (['/studio', '/studio/bibliotheque', '/studio/bibliotheque/'.$video->getId()] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, '.video-published');
            self::assertSelectorTextContains('.video-published', 'Déjà publiée');
            self::assertSelectorExists('.video-published svg[role="img"][aria-label="YouTube"]');
        }
        $this->client->loginUser($this->bob);
        $this->client->request('GET', '/studio/bibliotheque');
        self::assertSelectorNotExists('.video-published');
        $this->em()->find(YouTubePublication::class, $job->getId())->change('failed');
        $this->em()->find(YouTubePublication::class, $duplicate->getId())->change('cancelled');
        $this->em()->flush();
        self::assertSame([], $repository->publishedPlatforms($this->alice, [$video]));
    }

    public function testAuthenticationSelectionOwnershipAndCsrf(): void
    {
        $own = $this->video($this->alice);
        $foreign = $this->video($this->bob);
        foreach (['/studio/connexions', '/studio/publications', '/studio/publications/youtube/preparer'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('http://localhost/connexion');
        }
        $this->client->loginUser($this->alice);
        $this->client->request('GET', '/studio/bibliotheque');
        self::assertSelectorExists('input[name="videos[]"][value="'.$own->getId().'"]');
        self::assertSelectorNotExists('input[value="'.$foreign->getId().'"]');
        $this->client->request('GET', '/studio/publications/youtube/preparer', ['videos' => [$foreign->getId()]]);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/studio/connexions/youtube');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/studio/publications/youtube/preparer', ['videos' => []]);
        self::assertResponseRedirects('/studio/bibliotheque', 303);
        $unverified = new User('unverified@example.test');
        $unverified->setPassword('unused');
        $this->em()->persist($unverified);
        $this->em()->flush();
        $this->client->loginUser($unverified);
        $this->client->request('GET', '/studio/publications');
        self::assertResponseStatusCodeSame(403);
    }

    public function testBatchSubmissionOutboxAndDuplicatePostDoNotDuplicateJobs(): void
    {
        $this->connect($this->alice);
        $a = $this->video($this->alice);
        $b = $this->video($this->alice, 'Deuxième');
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', '/studio/publications/youtube/preparer', ['videos' => [$a->getId(), $b->getId()]]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Prêtes à partager');
        self::assertStringNotContainsString('access-test', $this->client->getResponse()->getContent());
        $form = $crawler->selectButton('Publier 2 vidéos sur YouTube')->form();
        foreach ([$a, $b] as $video) {
            $id = $video->getId();
            $form['you_tube_publish[videos]['.$id.'][privacy]'] = 'private';
            $form['you_tube_publish[videos]['.$id.'][audience]'] = 'no';
        }
        $form['you_tube_publish[confirm]'] = true;
        $payload = $form->getPhpValues();
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/publications', 303);
        self::assertSame(2, $this->em()->getRepository(YouTubePublication::class)->count([]));
        self::assertSame(2, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->client->request('POST', '/studio/publications/youtube/preparer', $payload);
        self::assertResponseRedirects('/studio/bibliotheque', 303);
        self::assertSame(2, $this->em()->getRepository(YouTubePublication::class)->count([]));
        $this->client->request('GET', '/studio/publications');
        self::assertSelectorTextContains('.publication-state', 'En attente');
        self::assertStringNotContainsString('<test>', $this->client->getResponse()->getContent());
    }

    public function testFormRefusesMissingConfirmationVisibilityAndCsrf(): void
    {
        $this->connect($this->alice);
        $video = $this->video($this->alice);
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', '/studio/publications/youtube/preparer', ['videos' => [$video->getId()]]);
        $form = $crawler->selectButton('Publier 1 vidéo sur YouTube')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(YouTubePublication::class)->count([]));
        $this->client->request('POST', '/studio/publications/youtube/preparer', ['you_tube_publish' => ['videos' => [$video->getId() => $this->row()], 'confirm' => '1']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(YouTubePublication::class)->count([]));
    }

    public function testOAuthStateIsSingleUseAndCallbackStoresOnlyEncryptedTokens(): void
    {
        static::getContainer()->set('youtube.http', new MockHttpClient([new MockResponse(json_encode(['access_token' => 'access-real-test', 'refresh_token' => 'refresh-real-test', 'scope' => \App\Service\YouTubeApi::SCOPES, 'expires_in' => 3600])), new MockResponse(json_encode(['items' => [['id' => 'UC'.str_repeat('b', 22), 'snippet' => ['title' => 'Ma chaîne']]]]))]));
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', '/studio/connexions');
        $this->client->submit($crawler->selectButton('Connecter YouTube ↗')->form());
        self::assertResponseStatusCodeSame(303);
        parse_str((string) parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->client->request('GET', '/studio/connexions/youtube/retour', ['state' => $query['state'], 'code' => 'one-use-code']);
        self::assertResponseRedirects('/studio/connexions', 303);
        $connection = $this->em()->getRepository(YouTubeConnection::class)->findOneBy(['owner' => $this->alice]);
        self::assertNotNull($connection);
        self::assertStringNotContainsString('access-real-test', $connection->getAccessToken());
        self::assertSame('refresh-real-test', static::getContainer()->get(TokenVault::class)->decrypt($connection->getRefreshToken()));
        $this->client->request('GET', '/studio/connexions/youtube/retour', ['state' => $query['state'], 'code' => 'replayed']);
        self::assertResponseRedirects('/studio/connexions', 303);
        self::assertSame(1, $this->em()->getRepository(YouTubeConnection::class)->count([]));
        $this->client->followRedirect();
        self::assertSelectorTextContains('.account-notice.error', 'expiré');
    }

    public function testHandlerResumesCompletedUploadThenPollsAndIgnoresRedelivery(): void
    {
        $video = $this->video($this->alice);
        $job = $this->job($video);
        $vault = static::getContainer()->get(TokenVault::class);
        $session = 'https://www.googleapis.com/upload/youtube/v3/videos?upload_id=local-test';
        $job->session($vault->encrypt($session));
        $this->em()->flush();
        $calls = 0;
        static::getContainer()->set('youtube.http', new MockHttpClient(function (string $method, string $url) use (&$calls): MockResponse {
            ++$calls;
            if ('PUT' === $method) {
                return new MockResponse('{"id":"abcdefghijk"}');
            } self::assertStringContainsString('/youtube/v3/videos?', $url);

            return new MockResponse('{"items":[{"status":{"uploadStatus":"processed"},"processingDetails":{"processingStatus":"succeeded"}}]}');
        }));
        $handler = static::getContainer()->get(PublishYouTubeHandler::class);
        $message = new PublishYouTube((int) $job->getId());
        $handler($message);
        self::assertSame('processing', $job->getStatus());
        self::assertSame('abcdefghijk', $job->getRemoteId());
        $handler($message);
        self::assertSame('published', $job->getStatus());
        $handler($message);
        self::assertSame(2, $calls);
    }

    public function testHandlerPersistsSessionBeforeBytesAndUploadsOriginalFile(): void
    {
        $video = $this->video($this->alice);
        $job = $this->job($video);
        $steps = 0;
        $db = static::getContainer()->get(Connection::class);
        static::getContainer()->set('youtube.http', new MockHttpClient(function (string $method, string $url, array $options) use (&$steps, $db, $job): MockResponse {
            ++$steps;
            if (1 === $steps) {
                self::assertSame('POST', $method);
                self::assertSame('private', json_decode($options['body'], true)['status']['privacyStatus']);

                return new MockResponse('', ['response_headers' => ['Location: https://www.googleapis.com/upload/youtube/v3/videos?upload_id=first']]);
            }
            self::assertNotNull($db->fetchOne('SELECT upload_session FROM youtube_publication WHERE id = ?', [$job->getId()]));
            if (2 === $steps) {
                return new MockResponse('', ['http_code' => 308]);
            }
            self::assertSame(file_get_contents(dirname(__DIR__).'/Fixtures/library-valid.mp4'), $options['body']);

            return new MockResponse('{"id":"abcdefghijk"}');
        }));
        static::getContainer()->get(PublishYouTubeHandler::class)(new PublishYouTube((int) $job->getId()));
        self::assertSame(3, $steps);
        self::assertSame('processing', $job->getStatus());
        self::assertSame($video->getByteSize(), $job->getSentBytes());
    }

    public function testExpiredSessionRequiresReviewAndNeverStartsAnotherUpload(): void
    {
        $video = $this->video($this->alice);
        $job = $this->job($video);
        $job->session(static::getContainer()->get(TokenVault::class)->encrypt('https://www.googleapis.com/upload/youtube/v3/videos?upload_id=expired'));
        $this->em()->flush();
        $calls = 0;
        static::getContainer()->set('youtube.http', new MockHttpClient(function (string $method) use (&$calls): MockResponse {
            ++$calls;
            self::assertSame('PUT', $method);

            return new MockResponse('', ['http_code' => 404]);
        }));
        $handler = static::getContainer()->get(PublishYouTubeHandler::class);
        $handler(new PublishYouTube((int) $job->getId()));
        self::assertSame('review', $job->getStatus());
        $handler(new PublishYouTube((int) $job->getId()));
        self::assertSame(1, $calls);
    }

    public function testStopOwnershipCsrfAndSourceDeletionProtection(): void
    {
        $video = $this->video($this->alice);
        $job = $this->job($video);
        $this->client->loginUser($this->bob);
        $this->client->request('GET', '/studio/publications/'.$job->getId().'/arreter');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/studio/publications/'.$job->getId().'/reprendre');
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', '/studio/bibliotheque/'.$video->getId().'/supprimer');
        $this->client->submit($crawler->selectButton('Supprimer définitivement')->form());
        self::assertResponseStatusCodeSame(503);
        self::assertFileExists(static::getContainer()->get(PrivateVideoStorage::class)->path($video->getStorageName()));
        // Failed library transaction closes the manager; start a fresh kernel/client.
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $owner = static::getContainer()->get(UserRepository::class)->findByEmail('publisher@example.test');
        $this->client->loginUser($owner);
        $this->client->request('POST', '/studio/publications/'.$job->getId().'/arreter');
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/studio/publications/'.$job->getId().'/arreter');
        $this->client->submit($crawler->selectButton('Arrêter l’envoi et le suivi')->form());
        self::assertResponseRedirects('/studio/publications', 303);
        self::assertSame('cancelled', $this->em()->find(YouTubePublication::class, $job->getId())->getStatus());
    }

    public function testBatchRollsBackBothJobsAndOutboxIfAnotherVideoIsNotOwned(): void
    {
        $connection = $this->connect($this->alice);
        $own = $this->video($this->alice);
        $foreign = $this->video($this->bob);
        try {
            static::getContainer()->get(YouTubePublishing::class)->enqueue($this->alice, [(int) $own->getId(), (int) $foreign->getId()], [$own->getId() => $this->row(), $foreign->getId() => $this->row()], str_repeat('d', 64), $connection->getChannelId());
            self::fail('Foreign video should reject the entire batch');
        } catch (\App\Service\PublicationException) {
            $db = static::getContainer()->get(Connection::class);
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM youtube_publication'));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        }
    }

    public function testInterruptedTransferPausesThenResumesTheSameSession(): void
    {
        $video = $this->video($this->alice);
        $job = $this->job($video);
        $vault = static::getContainer()->get(TokenVault::class);
        $job->session($vault->encrypt('https://www.googleapis.com/upload/youtube/v3/videos?upload_id=resume'));
        $this->em()->flush();
        $calls = 0;
        static::getContainer()->set('youtube.http', new MockHttpClient(function (string $method) use (&$calls): MockResponse {
            self::assertSame('PUT', $method);
            ++$calls;

            return $calls <= 5 ? new MockResponse('', ['http_code' => 503]) : new MockResponse('{"id":"abcdefghijk"}');
        }));
        $handler = static::getContainer()->get(PublishYouTubeHandler::class);
        $message = new PublishYouTube((int) $job->getId());
        for ($i = 0; $i < 5; ++$i) {
            $handler($message);
        }
        self::assertSame('paused', $job->getStatus());
        $encrypted = $job->getUploadSession();
        static::getContainer()->get(YouTubePublishing::class)->resume($job);
        self::assertSame($encrypted, $job->getUploadSession());
        $handler($message);
        self::assertSame('processing', $job->getStatus());
        self::assertSame(6, $calls);
    }

    public function testEffectiveVisibilityAndRejectionComeFromYouTube(): void
    {
        $job = $this->job($this->video($this->alice));
        $job->uploaded('abcdefghijk');
        $this->em()->flush();
        $http = new MockHttpClient(new MockResponse('{"items":[{"status":{"uploadStatus":"processed","privacyStatus":"public"},"processingDetails":{"processingStatus":"succeeded"}}]}'));
        static::getContainer()->set('youtube.http', $http);
        $handler = static::getContainer()->get(PublishYouTubeHandler::class);
        $handler(new PublishYouTube((int) $job->getId()));
        self::assertSame('published', $job->getStatus());
        self::assertSame('public', $job->getEffectivePrivacy());
        self::assertStringContainsString('différente', $job->getNotice());
        $this->client->loginUser($this->alice);
        $this->client->request('GET', '/studio/publications');
        self::assertSelectorTextContains('.publication-state', 'Publique');
        self::assertSelectorTextContains('.publication-item', 'Visibilité demandée : Privée');
        self::assertSelectorTextContains('.publication-item', 'Visibilité confirmée par YouTube : Publique');
        $job->resume();
        $this->em()->flush();
        $http->setResponseFactory(new MockResponse('{"items":[{"status":{"uploadStatus":"rejected"},"processingDetails":{"processingStatus":"succeeded"}}]}'));
        $handler(new PublishYouTube((int) $job->getId()));
        self::assertSame('failed', $job->getStatus());
    }

    public function testHorizontalVideoIsBlockedBeforeUploadOrTokenRefresh(): void
    {
        $video = $this->video($this->alice);
        $video->recordTechnicalMetadata(['rotation' => 90]);
        $job = $this->job($video);
        static::getContainer()->set('youtube.http', new MockHttpClient(static function (): never { self::fail('No remote call for an incompatible video.'); }));
        static::getContainer()->get(PublishYouTubeHandler::class)(new PublishYouTube((int) $job->getId()));
        self::assertSame('failed', $job->getStatus());
        self::assertNull($job->getUploadSession());
        self::assertStringContainsString('horizontale', $job->getNotice());
    }

    public function testUnknownVisibilityIsNeverPresentedAsPublic(): void
    {
        $job = $this->job($this->video($this->alice));
        $job->uploaded('abcdefghijk');
        $this->em()->flush();
        static::getContainer()->set('youtube.http', new MockHttpClient(new MockResponse('{"items":[{"status":{"uploadStatus":"processed"}}]}')));
        static::getContainer()->get(PublishYouTubeHandler::class)(new PublishYouTube((int) $job->getId()));
        self::assertSame('published', $job->getStatus());
        self::assertNull($job->getEffectivePrivacy());
        $this->client->loginUser($this->alice);
        $this->client->request('GET', '/studio/publications');
        self::assertSelectorTextContains('.publication-state', 'visibilité à vérifier');
    }

    public function testWrongOAuthStateNeverCallsGoogleAndDisconnectPausesJobs(): void
    {
        $video = $this->video($this->alice);
        $job = $this->job($video);
        static::getContainer()->set('youtube.http', new MockHttpClient(function (): never { self::fail('No request should reach Google'); }));
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', '/studio/connexions');
        $this->client->submit($crawler->selectButton('Reconnecter YouTube ↗')->form());
        $this->client->request('GET', '/studio/connexions/youtube/retour', ['state' => 'wrong-state', 'code' => 'do-not-exchange']);
        self::assertResponseRedirects('/studio/connexions', 303);
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Déconnecter de VydFlow')->form());
        self::assertResponseRedirects('/studio/connexions', 303);
        self::assertSame(0, $this->em()->getRepository(YouTubeConnection::class)->count([]));
        $fresh = $this->em()->find(YouTubePublication::class, $job->getId());
        static::getContainer()->get(PublishYouTubeHandler::class)(new PublishYouTube((int) $job->getId()));
        self::assertSame('paused', $fresh->getStatus());
    }
}
