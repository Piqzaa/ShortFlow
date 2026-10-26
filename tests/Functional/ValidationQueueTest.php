<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\PublicationDraft;
use App\Entity\PublicationDraftItem;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Service\ValidationQueue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

final class ValidationQueueTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private User $other;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $this->em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        $this->clock = new MockClock('2026-10-27T12:00:00Z');
        static::getContainer()->set('app.publication_clock', $this->clock);
        foreach (['youtube', 'instagram', 'tiktok', 'ai'] as $provider) {
            static::getContainer()->set($provider.'.http', new MockHttpClient(static function (): never { throw new \LogicException('No external request on validation queue.'); }));
        }
        foreach (['owner', 'other'] as $name) {
            $user = new User($name.'-validation@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em->persist($user);
            $this->$name = $user;
        }
        $this->em->flush();
        $this->client->loginUser($this->owner);
    }

    private function video(): VideoAsset
    {
        $video = new VideoAsset($this->owner, 'Version finale', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1000, 8, 720, 1280, 'h264');
        $this->em->persist($video);
        $this->em->flush();

        return $video;
    }

    private function draft(string $title = 'Brouillon', ?User $owner = null): PublicationDraft
    {
        $draft = new PublicationDraft($owner ?? $this->owner, $title, $this->clock->now());
        $this->em->persist($draft);

        return $draft;
    }

    public function testQueueSeparatesHumanStepsAndMissingSlotsWithoutWritingOrCallingProviders(): void
    {
        $watch = new AiEpisode($this->owner, 1, '<script>Regarder</script>');
        $watch->flow('ready');
        $series = new AiSeries($this->owner, 'Ma série', 'Thème', 'Style', 'Identité', []);
        $this->em->persist($series);
        $prepare = new AiEpisode($series, 1);
        $prepare->flow('ready');
        $prepare->approve($this->video());
        foreach ([$watch, $prepare] as $episode) {
            $this->em->persist($episode);
        }
        $this->draft();
        $blocked = $this->draft('À corriger');
        $blocked->change('blocked', 'À vérifier', $this->clock->now());
        foreach (['-121 seconds', '-120 seconds', '+1 day'] as $offset) {
            $draft = $this->draft('Créneau '.$offset);
            $draft->plan($this->clock->now()->modify($offset), 'Europe/Paris', '2026-10-27T13:57', $this->clock->now());
            $draft->arm($this->clock->now());
        }
        $this->draft('SECRET AUTRE COMPTE', $this->other);
        foreach (['running', 'assembling', 'stopped', 'draft', 'review'] as $i => $state) {
            $episode = new AiEpisode($this->owner, $i + 10, 'Hors file '.$state);
            $episode->flow($state);
            $this->em->persist($episode);
        }
        $cancelled = $this->draft('Annulation volontaire');
        $cancelled->change('cancelled', null, $this->clock->now());
        $this->em->flush();
        $before = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM publication_draft');
        $queue = static::getContainer()->get(ValidationQueue::class)->page($this->owner);
        self::assertSame(['watch' => 1, 'prepare' => 1, 'confirm' => 1, 'blocked' => 2], $queue['counts']);
        self::assertSame('blocked', $queue['items'][0]['kind']);
        $this->client->request('GET', '/studio/a-valider');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorCount(5, '.validation-card');
        self::assertSelectorTextContains('.validation-list', '27/10/2026 à 12:57 · Europe/Paris');
        self::assertSelectorTextContains('.studio-topbar > span', 'À valider');
        self::assertSelectorNotExists('main script');
        self::assertStringContainsString('&lt;script&gt;Regarder&lt;/script&gt;', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('SECRET AUTRE COMPTE', $this->client->getResponse()->getContent());
        self::assertSelectorExists('a[href="/studio/series/episodes/'.$watch->getId().'"]');
        self::assertSelectorCount(6, '.studio-bottom-nav a');
        self::assertSelectorExists('.studio-sidebar a[aria-current="page"][href="/studio/a-valider"]');
        self::assertSame($before, $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM publication_draft'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testApprovedVersionIsNotDuplicatedButAnOldDraftDoesNotHideANewVersion(): void
    {
        $episode = new AiEpisode($this->owner, 1, 'Vidéo ponctuelle');
        $episode->flow('ready');
        $video = $this->video();
        $episode->approve($video);
        $this->em->persist($episode);
        $draft = $this->draft();
        $draft->addItem(new PublicationDraftItem($draft, $video, 0));
        $draft->attachEpisode($episode);
        $this->em->flush();
        $service = static::getContainer()->get(ValidationQueue::class);
        self::assertSame(0, $service->page($this->owner)['counts']['prepare']);
        self::assertSame(1, $service->page($this->owner)['counts']['confirm']);
        $episode->approve($this->video());
        $this->em->flush();
        self::assertSame(1, $service->page($this->owner)['counts']['prepare']);
        $episode->flow('paused');
        $this->em->flush();
        self::assertSame(1, $service->page($this->owner)['counts']['blocked']);
    }

    public function testPartialPublicationAndOrphanFailuresAreVisibleWithoutDuplicatingDestinations(): void
    {
        $draft = $this->draft('Deux destinations à suivre');
        $draft->edit($draft->getTitle(), ['youtube'], ['youtube' => 'channel'], $this->clock->now());
        $draft->beginImmediate($this->clock->now());
        $draft->dispatched($this->clock->now());
        $connection = new YouTubeConnection($this->owner, 'UC'.str_repeat('a', 22), 'Compte de test', 'synthetic', 'synthetic', 3600);
        $this->em->persist($connection);
        foreach (['published', 'failed'] as $status) {
            $video = $this->video();
            $draft->addItem(new PublicationDraftItem($draft, $video, 0));
            $job = new YouTubePublication($this->owner, $video, $connection, $draft->getSubmissionKey(), 'Envoi lié', '', 'private', false, false);
            $job->change($status);
            $this->em->persist($job);
        }
        $orphan = new YouTubePublication($this->owner, $this->video(), $connection, bin2hex(random_bytes(32)), 'Ancien envoi à vérifier', '', 'private', false, false);
        $orphan->change('review');
        $this->em->persist($orphan);
        $this->em->flush();
        $queue = static::getContainer()->get(ValidationQueue::class)->page($this->owner, 'blocked');
        self::assertSame(2, $queue['total']);
        self::assertEqualsCanonicalizing(['draft', 'job'], array_column($queue['items'], 'resource'));
        $this->client->request('GET', '/studio/a-valider?filter=blocked');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/studio/brouillons/'.$draft->getId().'"]');
        self::assertSelectorExists('a[href="/studio/publications?network=youtube&state=attention"]');
    }

    public function testFilteringPaginationAndEmptyStateStayPrivateAndReadOnly(): void
    {
        for ($i = 1; $i <= 23; ++$i) {
            $this->draft('Publication '.$i);
        }
        $this->em->flush();
        $service = static::getContainer()->get(ValidationQueue::class);
        $page = $service->page($this->owner, 'confirm', 999);
        self::assertSame(23, $page['total']);
        self::assertSame(2, $page['page']);
        self::assertCount(3, $page['items']);
        self::assertSame($page['items'], $service->page($this->owner, 'confirm', 2)['items']);
        self::assertSame('', $service->page($this->owner, 'invalid')['filter']);
        $this->client->request('GET', '/studio/a-valider?filter=watch');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.studio-empty', 'Rien dans cette catégorie');
        $this->client->request('POST', '/studio/a-valider');
        self::assertResponseStatusCodeSame(405);
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/a-valider');
        self::assertSelectorCount(0, '.validation-card');
        self::assertSelectorTextContains('.studio-empty', 'Tout est à jour');
        $this->client->restart();
        $this->client->request('GET', '/studio/a-valider');
        self::assertResponseRedirects('http://localhost/connexion');
    }
}
