<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\InstagramConnection;
use App\Entity\InstagramPublication;
use App\Entity\PublicationStatistic;
use App\Entity\TikTokConnection;
use App\Entity\TikTokPublication;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Message\RefreshPublicationStatistics;
use App\MessageHandler\RefreshPublicationStatisticsHandler;
use App\Service\PublicationException;
use App\Service\PublicationStatistics;
use App\Service\PublicationStatisticsAccess;
use App\Service\TokenVault;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PublicationStatisticsTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    private const IDS = ['youtube' => 'abcdefghijk', 'instagram' => '179000000000001', 'tiktok' => '7123456789012345678'];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        self::assertSame('shortflow_test', $this->db()->fetchOne('SELECT current_database()'));
        $this->db()->executeStatement('DELETE FROM messenger_messages');
        $this->db()->executeStatement('DELETE FROM app_user');
        foreach (['owner', 'other'] as $name) {
            $user = new User($name.'-statistics@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $this->em()->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['youtube', 'instagram', 'tiktok'] as $network) {
            static::getContainer()->set($network.'.http', new MockHttpClient(static function (): never { throw new \LogicException('Unexpected provider request'); }));
        }
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function stats(): PublicationStatistics
    {
        return static::getContainer()->get(PublicationStatistics::class);
    }

    private function mock(string $network, callable $factory): void
    {
        $client = static::getContainer()->get($network.'.http');
        self::assertInstanceOf(MockHttpClient::class, $client);
        $client->setResponseFactory($factory);
    }

    private function owner(): User
    {
        return $this->em()->find(User::class, $this->owner->getId());
    }

    private function job(string $network, ?User $owner = null, bool $published = true): YouTubePublication|InstagramPublication|TikTokPublication
    {
        $owner = $this->em()->find(User::class, ($owner ?? $this->owner)->getId());
        $video = new VideoAsset($owner, 'Vidéo <privée> '.$network, bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 3, 96, 160, 'h264');
        $this->em()->persist($video);
        $key = bin2hex(random_bytes(32));
        $vault = new TokenVault($_ENV[strtoupper($network).'_TOKEN_KEY']);
        $access = $vault->encrypt('statistics-private-token');
        if ('youtube' === $network) {
            $connection = new YouTubeConnection($owner, 'UC'.str_repeat('a', 22), 'Chaîne test', $access, $access, 3600);
            $job = new YouTubePublication($owner, $video, $connection, $key, $video->getTitle(), '', 'private', false, false);
            if ($published) {
                $job->uploaded(self::IDS[$network]);
                $job->change('published');
            }
        } elseif ('instagram' === $network) {
            $connection = new InstagramConnection($owner, '17841400000000001', 'statistics_test', 'MEDIA_CREATOR', $access, 3600);
            $job = new InstagramPublication($owner, $video, $connection, $key, '', false, false);
            if ($published) {
                $job->published(self::IDS[$network]);
            }
        } else {
            $connection = new TikTokConnection($owner, 'statistics-creator', 'Créateur test', $access, $access, 3600, 86400);
            $job = new TikTokPublication($owner, $video, $connection, $key, 'Créateur test', ['privacy_level' => 'SELF_ONLY']);
            if ($published) {
                $job->published(self::IDS[$network]);
            }
        }
        $this->em()->persist($connection);
        $this->em()->persist($job);
        $this->em()->flush();

        return $job;
    }

    private function snapshot(string $network, int $id): PublicationStatistic
    {
        return $this->em()->getRepository(PublicationStatistic::class)->findOneBy(['network' => $network, 'publicationId' => $id]);
    }

    /** @return array<string, mixed> */
    private function row(int $id): array
    {
        $row = $this->db()->fetchAssociative('SELECT * FROM publication_statistic WHERE id = ?', [$id]);
        $row['counts'] = $row['counts'] ? json_decode($row['counts'], true, flags: JSON_THROW_ON_ERROR) : null;

        return $row;
    }

    /** @return iterable<string, array{string}> */
    public static function networks(): iterable
    {
        foreach (['youtube', 'instagram', 'tiktok'] as $network) {
            yield $network => [$network];
        }
    }

    #[DataProvider('networks')]
    public function testRefreshUsesOfficialShapeKeepsZeroAndUnknownAndIsIdempotent(string $network): void
    {
        $job = $this->job($network);
        $calls = 0;
        $this->mock($network, function (string $method, string $url, array $options) use ($network, &$calls): MockResponse {
            ++$calls;
            if ('youtube' === $network) {
                self::assertSame('GET', $method);
                self::assertStringContainsString('/youtube/v3/videos?', $url);

                return new MockResponse(json_encode(['items' => [['id' => self::IDS[$network], 'snippet' => ['channelId' => 'UC'.str_repeat('a', 22)], 'statistics' => ['viewCount' => '123', 'likeCount' => '0']]]], JSON_THROW_ON_ERROR));
            }
            if ('instagram' === $network) {
                self::assertSame('GET', $method);

                return new MockResponse(json_encode(str_contains($url, '/insights') ? ['data' => [['name' => 'views', 'values' => [['value' => 123]]], ['name' => 'likes', 'values' => [['value' => 0]]]]] : ['id' => self::IDS[$network], 'username' => 'statistics_test'], JSON_THROW_ON_ERROR));
            }
            self::assertSame('POST', $method);
            self::assertStringContainsString('/v2/video/query/?', $url);
            self::assertSame([self::IDS[$network]], json_decode($options['body'], true)['filters']['video_ids']);

            return new MockResponse(json_encode(['data' => ['videos' => [['id' => self::IDS[$network], 'view_count' => 123, 'like_count' => 0]]], 'error' => ['code' => 'ok']], JSON_THROW_ON_ERROR));
        });
        self::assertTrue($this->stats()->queue($this->owner(), $network, (int) $job->getId()));
        self::assertFalse($this->stats()->queue($this->owner(), $network, (int) $job->getId()));
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $snapshot = $this->snapshot($network, (int) $job->getId());
        $message = new RefreshPublicationStatistics((int) $snapshot->getId(), $snapshot->getRequestKey());
        $handler = static::getContainer()->get(RefreshPublicationStatisticsHandler::class);
        $handler($message);
        $handler($message);
        self::assertSame('ready', $this->row((int) $snapshot->getId())['status']);
        self::assertSame(['views' => 123, 'likes' => 0, 'comments' => null], $this->row((int) $snapshot->getId())['counts']);
        self::assertSame('instagram' === $network ? 2 : 1, $calls);
        $this->client->loginUser($this->owner());
        $crawler = $this->client->request('GET', '/studio/publications');
        self::assertResponseIsSuccessful();
        self::assertSame(['123', '0', '—'], $crawler->filter('.publication-statistics dd')->each(static fn ($node): string => $node->text()));
        self::assertSelectorExists('.publication-statistics button[disabled]');
        self::assertSelectorExists('.publication-preview video[preload="metadata"][controls]');
        self::assertStringNotContainsString('statistics-private-token', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('<privée>', $this->client->getResponse()->getContent());
    }

    public function testRefreshBecomesAvailableAfterOneMinute(): void
    {
        $job = $this->job('youtube');
        self::assertTrue($this->stats()->queue($this->owner(), 'youtube', (int) $job->getId()));
        $snapshot = $this->snapshot('youtube', (int) $job->getId());
        $this->db()->executeStatement("UPDATE publication_statistic SET requested_at = CURRENT_TIMESTAMP - INTERVAL '30 seconds' WHERE id = ?", [$snapshot->getId()]);
        $this->em()->refresh($snapshot);
        self::assertFalse($this->stats()->queue($this->owner(), 'youtube', (int) $job->getId()));
        $this->client->loginUser($this->owner());
        $this->client->request('GET', '/studio/publications');
        self::assertSelectorExists('.publication-statistics button[disabled]');
        self::assertSelectorTextContains('.publication-statistics', 'après une minute');
        $this->db()->executeStatement("UPDATE publication_statistic SET requested_at = CURRENT_TIMESTAMP - INTERVAL '61 seconds' WHERE id = ?", [$snapshot->getId()]);
        $this->em()->refresh($snapshot);
        $this->client->request('GET', '/studio/publications');
        self::assertSelectorExists('.publication-statistics button:not([disabled])');
        self::assertTrue($this->stats()->queue($this->owner(), 'youtube', (int) $job->getId()));
        self::assertSame(2, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testYouTubeActivationDoesNotEnableOtherNetworks(): void
    {
        static::getContainer()->set(PublicationStatisticsAccess::class, new PublicationStatisticsAccess(true, false, false));
        $youtube = $this->job('youtube');
        $instagram = $this->job('instagram');
        $tiktok = $this->job('tiktok');
        $this->client->loginUser($this->owner());
        $crawler = $this->client->request('GET', '/studio/publications');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.publication-statistics form');
        self::assertSelectorCount(3, '.publication-statistics');
        self::assertSame(['—', '—', '—'], $crawler->filter('.publication-item[data-network="instagram"] .publication-statistics dd')->each(static fn ($node): string => $node->text()));
        self::assertSame(1, $crawler->filter('.publication-item[data-network="youtube"] .publication-statistics form')->count());
        self::assertTrue($this->stats()->queue($this->owner(), 'youtube', (int) $youtube->getId()));
        foreach (['instagram' => $instagram, 'tiktok' => $tiktok] as $network => $job) {
            try {
                $this->stats()->queue($this->owner(), $network, (int) $job->getId());
                self::fail('Disabled network accepted statistics request.');
            } catch (PublicationException) {
                self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM publication_statistic'));
            }
        }
        $snapshot = new PublicationStatistic($this->owner(), 'instagram', (int) $instagram->getId());
        $this->em()->persist($snapshot);
        $this->em()->flush();
        $this->stats()->refresh((int) $snapshot->getId(), $snapshot->getRequestKey());
        self::assertSame('unavailable', $this->row((int) $snapshot->getId())['status']);
        self::assertNull($this->row((int) $snapshot->getId())['counts']);
    }

    public function testInstagramVideoLinkUsesProviderPermalinkAndChecksOwnership(): void
    {
        $job = $this->job('instagram');
        $this->mock('instagram', static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('GET', $method);
            self::assertStringContainsString('fields=id%2Cusername%2Cpermalink', $url);

            return new MockResponse(json_encode(['id' => self::IDS['instagram'], 'username' => 'statistics_test', 'permalink' => 'https://www.instagram.com/reel/Test_clip123/'], JSON_THROW_ON_ERROR));
        });
        $this->client->loginUser($this->owner());
        $this->client->request('GET', '/studio/publications/instagram/'.$job->getId().'/voir');
        self::assertResponseRedirects('https://www.instagram.com/reel/Test_clip123/', 303);
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'));
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/publications/instagram/'.$job->getId().'/voir');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testInstagramProviderCannotRedirectToAnUntrustedWebsite(): void
    {
        $job = $this->job('instagram');
        $this->mock('instagram', static fn (): MockResponse => new MockResponse(json_encode(['id' => self::IDS['instagram'], 'username' => 'statistics_test', 'permalink' => 'https://www.instagram.com.evil.test/reel/Test/'], JSON_THROW_ON_ERROR)));
        $this->client->loginUser($this->owner());
        $this->client->request('GET', '/studio/publications/instagram/'.$job->getId().'/voir');
        self::assertResponseRedirects('/studio/publications', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.account-notice.error', 'lien Instagram est indisponible');
    }

    public function testReadingIsLocalAndQueueRequiresOwnerAndCsrf(): void
    {
        $job = $this->job('youtube');
        $foreign = $this->job('instagram', $this->other);
        $this->client->loginUser($this->owner());
        $crawler = $this->client->request('GET', '/studio/publications');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.publication-item');
        self::assertSelectorTextContains('.publication-statistics', 'Aucun relevé');
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->client->request('POST', '/studio/publications/youtube/'.$job->getId().'/statistiques', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/studio/publications/instagram/'.$foreign->getId().'/statistiques', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(404);
        $this->client->submit($crawler->selectButton('Actualiser les statistiques')->form());
        self::assertResponseRedirects('/studio/publications?network=youtube&state=published', 303);
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM publication_statistic'));
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testProviderFailureKeepsLastSnapshotAndStaleMessagesCannotOverwriteIt(): void
    {
        $job = $this->job('youtube');
        $failing = false;
        $this->mock('youtube', function () use (&$failing): MockResponse {
            if ($failing) {
                return new MockResponse('private-provider-token-error', ['http_code' => 403]);
            }

            return new MockResponse(json_encode(['items' => [['id' => self::IDS['youtube'], 'snippet' => ['channelId' => 'UC'.str_repeat('a', 22)], 'statistics' => ['viewCount' => '99']]]], JSON_THROW_ON_ERROR));
        });
        $this->stats()->queue($this->owner(), 'youtube', (int) $job->getId());
        $snapshot = $this->snapshot('youtube', (int) $job->getId());
        $id = (int) $snapshot->getId();
        $oldKey = $snapshot->getRequestKey();
        $this->stats()->refresh($id, $oldKey);
        $before = $this->row($id);
        $this->db()->executeStatement("UPDATE publication_statistic SET requested_at = requested_at - INTERVAL '6 minutes' WHERE id = ?", [$id]);
        $this->em()->clear();
        self::assertTrue($this->stats()->queue($this->owner(), 'youtube', (int) $job->getId()));
        $snapshot = $this->snapshot('youtube', (int) $job->getId());
        $this->stats()->refresh($id, $oldKey);
        self::assertSame('queued', $this->row($id)['status']);
        $failing = true;
        $this->stats()->refresh($id, $snapshot->getRequestKey());
        $after = $this->row($id);
        self::assertSame('unavailable', $after['status']);
        self::assertSame($before['counts'], $after['counts']);
        self::assertSame($before['updated_at'], $after['updated_at']);
        self::assertStringNotContainsString('private-provider-token-error', $after['notice']);
    }

    public function testAccountSwitchStopsRefreshBeforeProviderRequest(): void
    {
        $job = $this->job('youtube');
        $calls = 0;
        $this->mock('youtube', static function () use (&$calls): never {
            ++$calls;
            throw new \LogicException('Unexpected provider request');
        });
        $this->stats()->queue($this->owner(), 'youtube', (int) $job->getId());
        $snapshot = $this->snapshot('youtube', (int) $job->getId());
        $this->db()->executeStatement('UPDATE youtube_connection SET channel_id = ?', ['UC'.str_repeat('b', 22)]);
        $this->em()->clear();
        $this->stats()->refresh((int) $snapshot->getId(), $snapshot->getRequestKey());
        self::assertSame('unavailable', $this->row((int) $snapshot->getId())['status']);
        self::assertNull($this->row((int) $snapshot->getId())['counts']);
        self::assertSame(0, $calls);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCounts(): iterable
    {
        yield 'negative' => ['-1'];
        yield 'fraction' => ['1.5'];
        yield 'overflow' => ['999999999999999999999999999'];
    }

    #[DataProvider('invalidCounts')]
    public function testInvalidCountersNeverBecomeRealNumbers(string $value): void
    {
        $job = $this->job('youtube');
        $this->mock('youtube', static fn (): MockResponse => new MockResponse(json_encode(['items' => [['id' => self::IDS['youtube'], 'snippet' => ['channelId' => 'UC'.str_repeat('a', 22)], 'statistics' => ['viewCount' => $value]]]], JSON_THROW_ON_ERROR)));
        $this->stats()->queue($this->owner(), 'youtube', (int) $job->getId());
        $snapshot = $this->snapshot('youtube', (int) $job->getId());
        $this->stats()->refresh((int) $snapshot->getId(), $snapshot->getRequestKey());
        self::assertSame('unavailable', $this->row((int) $snapshot->getId())['status']);
        self::assertNull($this->row((int) $snapshot->getId())['counts']);
    }

    public function testWrongRemoteVideoIsRejected(): void
    {
        $job = $this->job('youtube');
        $this->mock('youtube', static fn (): MockResponse => new MockResponse(json_encode(['items' => [['id' => 'other-video', 'snippet' => ['channelId' => 'UC'.str_repeat('a', 22)], 'statistics' => ['viewCount' => '900']]]], JSON_THROW_ON_ERROR)));
        $this->stats()->queue($this->owner(), 'youtube', (int) $job->getId());
        $snapshot = $this->snapshot('youtube', (int) $job->getId());
        $this->stats()->refresh((int) $snapshot->getId(), $snapshot->getRequestKey());
        self::assertNull($this->row((int) $snapshot->getId())['counts']);
    }

    public function testUnpublishedVideoCannotQueueStatistics(): void
    {
        $job = $this->job('youtube', published: false);
        $this->expectException(PublicationException::class);
        $this->stats()->queue($this->owner(), 'youtube', (int) $job->getId());
    }

    public function testMissingRemoteIdCannotQueueAndDeletedSourceHasNoPreview(): void
    {
        $job = $this->job('tiktok');
        $job->published(null);
        $this->em()->flush();
        $this->client->loginUser($this->owner());
        $this->client->request('GET', '/studio/publications');
        self::assertSelectorTextContains('.publication-statistics', 'identifiant vidéo');
        self::assertSelectorNotExists('.publication-statistics form');
        $this->db()->executeStatement('DELETE FROM video_asset WHERE owner_id = ?', [$this->owner->getId()]);
        $this->client->request('GET', '/studio/publications');
        self::assertSelectorNotExists('.publication-preview');
        self::assertSelectorTextContains('.publication-item', 'Source supprimée');
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM publication_statistic'));
    }
}
