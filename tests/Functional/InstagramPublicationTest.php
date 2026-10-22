<?php

namespace App\Tests\Functional;

use App\Entity\InstagramConnection;
use App\Entity\InstagramPublication;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Message\PublishInstagram;
use App\MessageHandler\PublishInstagramHandler;
use App\Repository\VideoAssetRepository;
use App\Service\InstagramMediaLink;
use App\Service\InstagramPublishing;
use App\Service\PrivateVideoStorage;
use App\Service\PublicationException;
use App\Service\TokenVault;
use App\Service\VideoLibrary;
use App\Service\VideoLibraryException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class InstagramPublicationTest extends WebTestCase
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
        $this->owner = new User('reels@example.test');
        $this->owner->verify();
        $this->owner->setPassword('unused');
        $this->other = new User('other@example.test');
        $this->other->verify();
        $this->other->setPassword('unused');
        $this->em()->persist($this->owner);
        $this->em()->persist($this->other);
        $this->em()->flush();
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

    private function connection(): InstagramConnection
    {
        $connection = new InstagramConnection($this->owner, '17841400000000001', 'vydflow.test', 'MEDIA_CREATOR', (new TokenVault($_ENV['INSTAGRAM_TOKEN_KEY']))->encrypt('private-access'), 5184000);
        $this->em()->persist($connection);
        $this->em()->flush();

        return $connection;
    }

    private function video(?User $owner = null, float $duration = 3): VideoAsset
    {
        $storage = static::getContainer()->get(PrivateVideoStorage::class);
        self::assertStringEndsWith('/var/storage/videos/test', str_replace('\\', '/', $storage->directory()));
        $name = bin2hex(random_bytes(24)).'.mp4';
        $temp = tempnam(sys_get_temp_dir(), 'reels-');
        copy(dirname(__DIR__).'/Fixtures/reels-valid.mp4', $temp);
        $storage->store(new UploadedFile($temp, 'clip.mp4', 'video/mp4', null, true), $name);
        $path = $storage->path($name);
        $this->files[] = $path;
        $video = new VideoAsset($owner ?? $this->owner, 'Mon Reel <test>', $name, 'video/mp4', (int) filesize($path), $duration, 96, 160, 'h264');
        $this->em()->persist($video);
        $this->em()->flush();

        return $video;
    }

    private function job(): InstagramPublication
    {
        $connection = $this->connection();
        $video = $this->video();
        $job = new InstagramPublication($this->owner, $video, $connection, bin2hex(random_bytes(32)), 'Ma légende #video', true, false);
        $this->em()->persist($job);
        $this->em()->flush();

        return $job;
    }

    public function testPreparationQueuesBatchWithConfirmationAndOwnerIsolation(): void
    {
        $this->connection();
        $a = $this->video();
        $b = $this->video();
        $foreign = $this->video($this->other);
        $this->client->request('GET', '/studio/publications/instagram/preparer', ['videos' => [$a->getId()]]);
        self::assertResponseRedirects('http://localhost/connexion');
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/publications/instagram/preparer', ['videos' => [$foreign->getId()]]);
        self::assertResponseStatusCodeSame(404);
        $crawler = $this->client->request('GET', '/studio/publications/instagram/preparer', ['videos' => [$a->getId(), $b->getId()]]);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('private-access', $this->client->getResponse()->getContent());
        $form = $crawler->selectButton('Publier 2 Reels sur Instagram ↗')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $form['instagram_publish[confirm]'] = true;
        $payload = $form->getPhpValues();
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/publications', 303);
        self::assertSame(2, $this->em()->getRepository(InstagramPublication::class)->count([]));
        self::assertSame(2, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->client->request('POST', '/studio/publications/instagram/preparer', $payload);
        self::assertResponseRedirects('/studio/bibliotheque', 303);
        self::assertSame(2, $this->em()->getRepository(InstagramPublication::class)->count([]));
        $this->client->request('GET', '/studio/publications');
        self::assertSelectorTextContains('.publication-state', 'En attente');
        self::assertStringNotContainsString('<test>', $this->client->getResponse()->getContent());
    }

    public function testInvalidFormatCsrfCaptionAndChangedAccountNeverQueue(): void
    {
        $this->connection();
        $video = $this->video(duration: 1);
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/studio/publications/instagram/preparer', ['videos' => [$video->getId()]]);
        self::assertSelectorExists('button[type="submit"][disabled]');
        self::assertSelectorTextContains('.publication-notice', '3 secondes');
        $this->client->request('POST', '/studio/publications/instagram/preparer', ['instagram_publish' => ['confirm' => '1']]);
        self::assertResponseStatusCodeSame(422);
        $valid = $this->video($this->em()->find(User::class, $this->owner->getId()));
        $crawler = $this->client->request('GET', '/studio/publications/instagram/preparer', ['videos' => [$valid->getId()]]);
        $form = $crawler->selectButton('Publier 1 Reel sur Instagram ↗')->form();
        $form['instagram_publish[confirm]'] = true;
        $form['instagram_publish[videos]['.$valid->getId().'][caption]'] = str_repeat('#x ', 31);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $form['instagram_publish[videos]['.$valid->getId().'][caption]'] = 'Valide';
        $connection = $this->em()->getRepository(InstagramConnection::class)->findOneBy(['owner' => $this->owner]);
        $connection->reconnect('17841400000000002', 'autre.compte', 'BUSINESS', $connection->getAccessToken(), 5184000);
        $this->em()->flush();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(InstagramPublication::class)->count([]));
    }

    public function testHandlerPersistsContainerAndPublishIntentThenIgnoresRedelivery(): void
    {
        $job = $this->job();
        $calls = 0;
        $db = static::getContainer()->get(Connection::class);
        static::getContainer()->set('instagram.http', new MockHttpClient(function (string $method, string $url, array $options) use (&$calls, $job, $db): MockResponse {
            ++$calls;
            if (1 === $calls) {
                self::assertSame('POST', $method);
                self::assertStringContainsString('/media', $url);
                parse_str($options['body'], $body);
                self::assertSame('REELS', $body['media_type']);
                self::assertStringContainsString('/media/instagram/'.$job->getId().'/', $body['video_url']);

                return new MockResponse('{"id":"111"}');
            }
            self::assertSame('111', $db->fetchOne('SELECT container_id FROM instagram_publication WHERE id = ?', [$job->getId()]));
            if (2 === $calls) {
                self::assertSame('GET', $method);

                return new MockResponse('{"status_code":"FINISHED"}');
            }
            self::assertTrue((bool) $db->fetchOne('SELECT publish_attempted FROM instagram_publication WHERE id = ?', [$job->getId()]));
            self::assertStringContainsString('/media_publish', $url);

            return new MockResponse('{"id":"222"}');
        }));
        $handler = static::getContainer()->get(PublishInstagramHandler::class);
        $message = new PublishInstagram((int) $job->getId());
        $handler($message);
        self::assertSame('processing', $job->getStatus());
        $handler($message);
        self::assertSame('published', $job->getStatus());
        self::assertSame('222', $job->getRemoteId());
        $handler($message);
        self::assertSame(3, $calls);
        self::assertSame([$job->getSource()->getId() => ['instagram']], static::getContainer()->get(VideoAssetRepository::class)->publishedPlatforms($this->owner, [$job->getSource()]));
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/bibliotheque');
        self::assertSelectorExists('svg[aria-label="Instagram"]');
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/publications');
        self::assertSelectorNotExists('.publication-item');
    }

    public function testManualReviewCanResumeOnlyTheExistingContainerWithoutAnotherPost(): void
    {
        $job = $this->job();
        $job->container('111');
        $job->attempt();
        $job->change('review', 'Réponse incertaine.');
        $this->em()->flush();
        $calls = 0;
        static::getContainer()->set('instagram.http', new MockHttpClient(function (string $method) use (&$calls): MockResponse {
            self::assertSame('GET', $method);
            ++$calls;

            return new MockResponse('{"status_code":"PUBLISHED"}');
        }));
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/studio/publications');
        self::assertSelectorNotExists('a[href*="retryNetwork"]');
        $form = $crawler->selectButton('Reprendre le suivi ↻')->form();
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/publications', 303);
        static::getContainer()->get(PublishInstagramHandler::class)(new PublishInstagram((int) $job->getId()));
        self::assertSame('published', $this->em()->find(InstagramPublication::class, $job->getId())->getStatus());
        self::assertSame(1, $calls);
    }

    public function testLostPublishResponseIsRecoveredWithoutRepeatingPost(): void
    {
        $job = $this->job();
        $job->container('111');
        $this->em()->flush();
        $posts = 0;
        $gets = 0;
        static::getContainer()->set('instagram.http', new MockHttpClient(function (string $method) use (&$posts, &$gets): MockResponse {
            if ('POST' === $method) {
                ++$posts;

                return new MockResponse('secret-provider-error', ['http_code' => 503]);
            } ++$gets;

            return new MockResponse($gets > 1 ? '{"status_code":"PUBLISHED"}' : '{"status_code":"FINISHED"}');
        }));
        $handler = static::getContainer()->get(PublishInstagramHandler::class);
        $message = new PublishInstagram((int) $job->getId());
        $handler($message);
        self::assertSame('publishing', $job->getStatus());
        $handler($message);
        self::assertSame('published', $job->getStatus());
        self::assertNull($job->getRemoteId());
        $handler($message);
        self::assertSame(1, $posts);
    }

    public function testUncertainResultRequiresReviewAndNeverRepublishes(): void
    {
        $job = $this->job();
        $job->container('111');
        $job->attempt();
        $this->em()->flush();
        $calls = 0;
        static::getContainer()->set('instagram.http', new MockHttpClient(function (string $method) use (&$calls): MockResponse {
            self::assertSame('GET', $method);
            ++$calls;

            return new MockResponse('{"status_code":"FINISHED"}');
        }));
        $handler = static::getContainer()->get(PublishInstagramHandler::class);
        $message = new PublishInstagram((int) $job->getId());
        for ($i = 0; $i < 6; ++$i) {
            $handler($message);
        }
        self::assertSame('review', $job->getStatus());
        self::assertSame(5, $calls);
        self::assertStringContainsString('incertain', $job->getNotice());
    }

    public function testSignedMediaOnlyServesItsFileAndRevokesAfterStop(): void
    {
        $job = $this->job();
        $url = static::getContainer()->get(InstagramMediaLink::class)->url($job);
        $path = parse_url($url, PHP_URL_PATH);
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'video/mp4');
        $this->client->request('GET', substr($path, 0, -1).('a' === substr($path, -1) ? 'b' : 'a'));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', str_replace('/'.($job->getCreatedAt()->getTimestamp() + 86400).'/', '/1/', $path));
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->other);
        $this->client->request('POST', '/studio/publications/instagram/'.$job->getId().'/arreter');
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->owner);
        $this->client->request('POST', '/studio/publications/instagram/'.$job->getId().'/arreter');
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/studio/publications');
        $this->client->submit($crawler->selectButton('Arrêter le suivi')->form());
        self::assertResponseRedirects('/studio/publications', 303);
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDisconnectedAccountPausesWithoutCallingMeta(): void
    {
        $job = $this->job();
        $connection = $this->em()->getRepository(InstagramConnection::class)->findOneBy(['owner' => $this->owner]);
        $this->em()->remove($connection);
        $this->em()->flush();
        static::getContainer()->set('instagram.http', new MockHttpClient(function (): never { self::fail('No HTTP without account'); }));
        static::getContainer()->get(PublishInstagramHandler::class)(new PublishInstagram((int) $job->getId()));
        self::assertSame('paused', $job->getStatus());
    }

    public function testActiveSourceDeletionProtection(): void
    {
        $job = $this->job();
        try {
            static::getContainer()->get(VideoLibrary::class)->delete($job->getSource());
            self::fail('Active source must stay');
        } catch (VideoLibraryException $error) {
            self::assertStringContainsString('envoi en cours', $error->getMessage());
        }
        self::assertFileExists($this->files[0]);
    }

    public function testBatchRollsBackJobsAndMessagesForForeignVideo(): void
    {
        $connection = $this->connection();
        $own = $this->video();
        $foreign = $this->video($this->other);
        $row = ['caption' => 'Clip', 'shareToFeed' => true, 'synthetic' => false];
        try {
            static::getContainer()->get(InstagramPublishing::class)->enqueue($this->owner, [(int) $own->getId(), (int) $foreign->getId()], [$own->getId() => $row, $foreign->getId() => $row], str_repeat('b', 64), $connection->getInstagramId());
            self::fail('Foreign video must reject the batch');
        } catch (PublicationException) {
            $db = static::getContainer()->get(Connection::class);
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM instagram_publication'));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        }
    }
}
