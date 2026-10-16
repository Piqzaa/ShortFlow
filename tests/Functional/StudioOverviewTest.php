<?php

namespace App\Tests\Functional;

use App\Entity\AccountToken;
use App\Entity\InstagramConnection;
use App\Entity\InstagramPublication;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Service\PublicationOverview;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class StudioOverviewTest extends WebTestCase
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
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        foreach (['alice', 'bob'] as $name) {
            $user = new User($name.'-overview@example.test');
            $user->verify();
            $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'original password for studio'));
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $this->em()->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function publication(User $owner, string $network, string $status, string $title): YouTubePublication|InstagramPublication
    {
        $video = new VideoAsset($owner, $title, bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 4, 96, 160, 'h264');
        $this->em()->persist($video);
        if ('youtube' === $network) {
            $connection = $this->em()->getRepository(YouTubeConnection::class)->findOneBy(['owner' => $owner]);
            if (!$connection) {
                $connection = new YouTubeConnection($owner, 'UC'.str_repeat('a', 22), 'Test channel', 'private-encrypted-access', 'private-encrypted-refresh', 3600);
                $this->em()->persist($connection);
            }
            $job = new YouTubePublication($owner, $video, $connection, bin2hex(random_bytes(32)), $title, '', 'private', false, false);
        } else {
            $connection = $this->em()->getRepository(InstagramConnection::class)->findOneBy(['owner' => $owner]);
            if (!$connection) {
                $connection = new InstagramConnection($owner, '17841400000000001', 'studio_test', 'MEDIA_CREATOR', 'private-encrypted-instagram', 3600);
                $this->em()->persist($connection);
            }
            $job = new InstagramPublication($owner, $video, $connection, bin2hex(random_bytes(32)), '', false, false);
        }
        $job->change($status);
        $this->em()->persist($job);
        $this->em()->flush();

        return $job;
    }

    public function testCommonFeedFiltersPaginatesAndNeverShowsAnotherOwner(): void
    {
        $this->publication($this->alice, 'youtube', 'paused', 'Older YouTube');
        $this->publication($this->bob, 'youtube', 'failed', 'Other owner private');
        $this->publication($this->alice, 'instagram', 'review', '<script>Latest Instagram</script>');
        $db = static::getContainer()->get(Connection::class);
        $db->executeStatement("UPDATE youtube_publication SET created_at = '2026-10-14 12:00:00'");
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', '/studio/publications');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.publication-item');
        self::assertStringContainsString('Latest Instagram', $crawler->filter('.publication-item')->first()->text());
        self::assertStringNotContainsString('Other owner private', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('<script>Latest', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('private-encrypted', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/studio/publications?network=youtube&state=attention');
        self::assertSelectorCount(1, '.publication-item');
        self::assertSelectorTextContains('.publication-item', 'Older YouTube');
        $this->client->request('GET', '/studio/publications?state=published');
        self::assertSelectorNotExists('.publication-item');
        $this->client->request('GET', '/studio');
        self::assertSelectorTextContains('.dashboard-summary a.needs-attention strong', '2');
        self::assertSelectorTextNotContains('.dashboard-attention', 'Other owner private');
        self::assertSelectorCount(1, '.studio-footer .legal-links a');
        self::assertSelectorExists('.studio-profile[href="/studio/mon-compte"]');
        $owner = $this->em()->find(User::class, $this->alice->getId());
        $overview = static::getContainer()->get(PublicationOverview::class);
        $first = $overview->page($owner, limit: 1);
        $second = $overview->page($owner, 2, limit: 1);
        self::assertSame(2, $first['pages']);
        self::assertSame('instagram', $first['items'][0]['network']);
        self::assertSame('youtube', $second['items'][0]['network']);
        self::assertSame(2, $overview->page($owner, 999, limit: 1)['page']);
    }

    public function testProfileRequiresVerifiedSessionAndRejectsWrongPasswordAndCsrf(): void
    {
        $this->client->request('GET', '/studio/mon-compte');
        self::assertResponseRedirects('http://localhost/connexion');
        $unverified = new User('unverified-overview@example.test');
        $unverified->setPassword('unused');
        $this->em()->persist($unverified);
        $this->em()->flush();
        $this->client->loginUser($unverified);
        $this->client->request('GET', '/studio/mon-compte');
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', '/studio/mon-compte');
        self::assertSelectorTextContains('.profile-panel', $this->alice->getEmail());
        self::assertSelectorTextNotContains('.profile-panel', $this->bob->getEmail());
        $this->client->submit($crawler->selectButton('Modifier mon mot de passe ↗')->form($this->passwordData('wrong password')));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.account-form', 'actuel est incorrect');
        $this->client->request('POST', '/studio/mon-compte', ['change_password' => ['currentPassword' => 'original password for studio', 'newPassword' => ['first' => 'a new password for studio', 'second' => 'a new password for studio']]]);
        self::assertResponseStatusCodeSame(422);
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($this->em()->find(User::class, $this->alice->getId()), 'original password for studio'));
    }

    /** @return array<string, string> */
    private function passwordData(string $current): array
    {
        return ['change_password[currentPassword]' => $current, 'change_password[newPassword][first]' => 'a new password for studio', 'change_password[newPassword][second]' => 'a new password for studio'];
    }

    public function testPasswordChangeRevokesResetLinksAndCurrentSessionOnlyChangesOwnPassword(): void
    {
        $this->em()->persist(new AccountToken($this->alice, hash('sha256', 'old reset'), AccountToken::RESET));
        $this->em()->persist(new AccountToken($this->bob, hash('sha256', 'other reset'), AccountToken::RESET));
        $this->em()->flush();
        $oldSession = new KernelBrowser(self::$kernel);
        $oldSession->disableReboot();
        $oldSession->loginUser($this->alice);
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', '/studio/mon-compte');
        $this->client->submit($crawler->selectButton('Modifier mon mot de passe ↗')->form($this->passwordData('original password for studio')));
        self::assertResponseRedirects('/connexion', 303);
        $db = static::getContainer()->get(Connection::class);
        self::assertNotNull($db->fetchOne('SELECT consumed_at FROM account_token WHERE owner_id = ?', [$this->alice->getId()]));
        self::assertNull($db->fetchOne('SELECT consumed_at FROM account_token WHERE owner_id = ?', [$this->bob->getId()]));
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->em()->clear();
        self::assertTrue($hasher->isPasswordValid($this->em()->find(User::class, $this->alice->getId()), 'a new password for studio'));
        self::assertTrue($hasher->isPasswordValid($this->em()->find(User::class, $this->bob->getId()), 'original password for studio'));
        $this->client->request('GET', '/studio');
        self::assertResponseRedirects('http://localhost/connexion');
        $oldSession->request('GET', '/studio');
        self::assertSame(302, $oldSession->getResponse()->getStatusCode());
        self::assertSame('http://localhost/connexion', $oldSession->getResponse()->headers->get('Location'));
    }

    public function testPasswordAttemptsAreRateLimited(): void
    {
        $this->client->loginUser($this->alice);
        for ($attempt = 0; $attempt < 6; ++$attempt) {
            $this->client->request('POST', '/studio/mon-compte', ['change_password' => ['_token' => 'invalid']]);
        }
        self::assertResponseStatusCodeSame(429);
    }
}
