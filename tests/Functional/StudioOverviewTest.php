<?php

namespace App\Tests\Functional;

use App\Entity\AccountToken;
use App\Entity\InstagramConnection;
use App\Entity\InstagramPublication;
use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Service\PublicationOverview;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
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
        self::assertSelectorCount(2, '.publication-preview video[controls][preload="metadata"]');
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
        self::assertSelectorNotExists('.studio-footer');
        self::assertSelectorNotExists('.studio-sidebar a[href="/informations-legales"]');
        self::assertSelectorNotExists('.studio-topbar a[href="/informations-legales"]');
        self::assertSelectorExists('.dashboard-actions a[href="/studio/bibliotheque#library-selection"]');
        self::assertSelectorExists('.studio-profile[href="/studio/mon-compte"]');
        $owner = $this->em()->find(User::class, $this->alice->getId());
        $overview = static::getContainer()->get(PublicationOverview::class);
        $first = $overview->page($owner, limit: 1);
        $second = $overview->page($owner, 2, limit: 1);
        self::assertSame(2, $first['pages']);
        self::assertSame('instagram', $first['items'][0]['network']);
        self::assertSame('youtube', $second['items'][0]['network']);
        self::assertSame(2, $overview->page($owner, 999, limit: 1)['page']);
        $this->publication($owner, 'youtube', 'queued', 'Waiting video');
        $this->client->request('GET', '/studio');
        self::assertSelectorTextContains('#active-title', 'En cours');
        self::assertSelectorTextContains('.dashboard-activity[aria-labelledby="active-title"]', 'Waiting video');
        self::assertSelectorTextContains('.dashboard-summary a[href="/studio/publications?state=active"] strong', '1');
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
        $this->client->request('GET', '/studio');
        self::assertSelectorExists('.dashboard-actions a[href="/studio/bibliotheque/importer"]');
        self::assertSelectorNotExists('#active-title');
        self::assertSelectorNotExists('#attention-title');
        $crawler = $this->client->request('GET', '/studio/mon-compte');
        self::assertSelectorTextContains('.profile-panel', $this->alice->getEmail());
        self::assertSelectorTextNotContains('.profile-panel', $this->bob->getEmail());
        self::assertSelectorExists('.profile-legal a[href="/confidentialite"]');
        self::assertSelectorExists('.profile-legal a[href="/informations-legales"]');
        self::assertSelectorExists('.profile-legal a[href="/suppression-donnees"]');
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

    private function preparation(User $owner, string $title, string $status, ?string $date = null): PublicationDraft
    {
        $now = new \DateTimeImmutable('2026-10-18T08:00:00+00:00');
        $draft = new PublicationDraft($owner, $title, $now);
        $draft->edit($title, ['youtube'], [], $now);
        if ($date) {
            $draft->plan(new \DateTimeImmutable($date), 'Europe/Paris', null, $now);
        }
        if ('draft' !== $status) {
            $draft->arm($now);
            if ('scheduled' !== $status) {
                $draft->change($status, null, $now);
            }
        }
        $this->em()->persist($draft);
        $this->em()->flush();

        return $draft;
    }

    public function testDashboardShowsOwnedDraftsAndSchedulesWithoutApiCallsOrWrites(): void
    {
        static::getContainer()->set('app.publication_clock', new MockClock('2026-10-18T08:00:00+00:00'));
        foreach (['youtube.http', 'instagram.http', 'tiktok.http'] as $service) {
            static::getContainer()->set($service, new MockHttpClient(static function (): never {
                throw new \LogicException('Dashboard consultation must never call a social API.');
            }));
        }
        $this->preparation($this->alice, 'Créneau du lendemain', 'scheduled', '2026-10-19T09:00:00+00:00');
        $due = $this->preparation($this->alice, 'Fenêtre encore valide', 'scheduled', '2026-10-18T07:58:00+00:00');
        $late = $this->preparation($this->alice, 'Départ manqué', 'scheduled', '2026-10-18T07:57:59+00:00');
        $this->preparation($this->alice, 'Compte à revérifier', 'blocked', '2026-10-18T10:00:00+00:00');
        $this->preparation($this->alice, 'Ancien brouillon', 'draft');
        $second = $this->preparation($this->alice, 'Brouillon récent', 'draft');
        $this->preparation($this->alice, 'À exclure car annulée', 'cancelled');
        $this->preparation($this->bob, 'Préparation autre propriétaire', 'scheduled', '2026-10-18T09:00:00+00:00');
        $this->preparation($this->bob, 'Blocage autre propriétaire', 'blocked');
        $this->preparation($this->bob, 'Brouillon autre propriétaire', 'draft');
        $this->client->loginUser($this->alice);
        $crawler = $this->client->request('GET', '/studio');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#upcoming-title .dashboard-count', '2');
        self::assertSelectorTextContains('#drafts-title .dashboard-count', '2');
        self::assertSelectorTextContains('.dashboard-schedule-alert h3', '2 préparations');
        self::assertSelectorTextContains('.dashboard-schedule-alert', 'À reprogrammer');
        self::assertSelectorTextContains('.dashboard-planning time', '18/10 à 09:58');
        self::assertSelectorTextContains('.dashboard-planning', 'Europe/Paris');
        self::assertSame('/studio/brouillons/'.$due->getId(), $crawler->filter('[aria-labelledby="upcoming-title"] > .dashboard-preparation')->first()->attr('href'));
        self::assertSame('/studio/brouillons/'.$second->getId(), $crawler->filter('[aria-labelledby="drafts-title"] .dashboard-preparation')->first()->attr('href'));
        self::assertStringNotContainsString('autre propriétaire', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('À exclure car annulée', $this->client->getResponse()->getContent());
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('scheduled', $db->fetchOne('SELECT status FROM publication_draft WHERE id = ?', [$late->getId()]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM youtube_publication'));
        self::assertSelectorExists('.studio-mobile-menu summary');
        self::assertSelectorExists('.studio-mobile-menu a[href="/studio/mon-compte"]');
    }

    public function testDashboardEmptyAndBoundedPreparationLists(): void
    {
        static::getContainer()->set('app.publication_clock', new MockClock('2026-10-18T08:00:00+00:00'));
        $this->client->loginUser($this->alice);
        $this->client->request('GET', '/studio');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#upcoming-title .dashboard-count', '0');
        self::assertSelectorTextContains('#drafts-title .dashboard-count', '0');
        self::assertSelectorNotExists('.dashboard-schedule-alert');
        self::assertSelectorNotExists('.dashboard-preparation');
        self::assertSelectorExists('.dashboard-guide[open]');
        $owner = $this->em()->find(User::class, $this->alice->getId());
        for ($i = 0; $i < 5; ++$i) {
            $this->preparation($owner, 'Départ '.$i, 'scheduled', '2026-10-19T'.(10 + $i).':00:00+00:00');
            $this->preparation($owner, 'Brouillon '.$i, 'draft');
        }
        $this->client->request('GET', '/studio');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#upcoming-title .dashboard-count', '5');
        self::assertSelectorTextContains('#drafts-title .dashboard-count', '5');
        self::assertSelectorCount(3, '[aria-labelledby="upcoming-title"] .dashboard-preparation');
        self::assertSelectorCount(2, '[aria-labelledby="drafts-title"] .dashboard-preparation');
    }
}
