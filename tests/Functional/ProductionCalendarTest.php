<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\PublicationDraft;
use App\Entity\PublicationDraftItem;
use App\Entity\SeriesOccurrence;
use App\Entity\SeriesRecurrence;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\ProductionCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

final class ProductionCalendarTest extends WebTestCase
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
            static::getContainer()->set($provider.'.http', new MockHttpClient(static function (): never { throw new \LogicException('Calendar must stay local.'); }));
        }
        foreach (['owner', 'other'] as $name) {
            $user = new User($name.'-production-calendar@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em->persist($user);
            $this->$name = $user;
        }
        $this->em->flush();
        $this->client->loginUser($this->owner);
    }

    private function recurrence(string $title = 'Milo', ?User $owner = null): SeriesRecurrence
    {
        $series = new AiSeries($owner ?? $this->owner, $title, 'Thème', 'Style', 'Identité', []);
        $r = new SeriesRecurrence($series);
        $this->em->persist($series);
        $this->em->persist($r);

        return $r;
    }

    private function episode(SeriesRecurrence $r, int $number, string $state = 'ready', string $at = '2026-10-28T18:00:00Z'): AiEpisode
    {
        $episode = new AiEpisode($r->getSeries(), $number);
        $episode->flow($state);
        $this->em->persist($episode);
        $this->em->persist(new SeriesOccurrence($r, new \DateTimeImmutable($at), 'Europe/Paris', ['youtube'], $this->clock->now(), $episode));
        $this->em->flush();

        return $episode;
    }

    private function video(): VideoAsset
    {
        $v = new VideoAsset($this->owner, 'Version', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1000, 8, 720, 1280, 'h264');
        $this->em->persist($v);
        $this->em->flush();

        return $v;
    }

    /** @return array<string,mixed> */
    private function feed(string $network = '', string $state = '', int $page = 1): array
    {
        return static::getContainer()->get(ProductionCalendar::class)->page($this->owner, new \DateTimeImmutable('2026-09-28T00:00:00Z'), new \DateTimeImmutable('2026-11-02T00:00:00Z'), $network, $state, $page);
    }

    public function testNextAuthorizedSlotIsAnIntentionAndNeverCreatesAnOccurrenceOrMessage(): void
    {
        $r = $this->recurrence();
        $r->activate(['limit' => 2, 'networks' => ['youtube'], 'timezone' => 'Europe/Paris'], new \DateTimeImmutable('2026-10-27T23:30:00Z'), $this->clock->now());
        $foreign = $this->recurrence('SECRET OTHER OWNER', $this->other);
        $foreign->activate(['limit' => 2], new \DateTimeImmutable('2026-10-27T23:30:00Z'), $this->clock->now());
        $this->em->flush();
        $this->client->request('GET', '/studio/calendrier?month=2026-10&zone=Europe/Paris');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.calendar-grid [data-production-state="production_planned"]');
        self::assertSelectorExists('.calendar-grid [data-production-state="production_planned"][aria-label*="Milo"]');
        self::assertSelectorExists('.calendar-day[aria-label="28/10/2026"] [data-production-state="production_planned"]');
        self::assertSelectorTextContains('.calendar-grid .calendar-production time', '00:30');
        self::assertSelectorTextContains('.calendar-mobile-agenda', 'publication à confirmer');
        self::assertStringNotContainsString('SECRET OTHER OWNER', $this->client->getResponse()->getContent());
        self::assertSame(0, $this->em->getRepository(SeriesOccurrence::class)->count([]));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $r = $this->em->find(SeriesRecurrence::class, $r->getId());
        $r->pause('Pause demandée');
        $this->em->flush();
        self::assertSame([], $this->feed()['dated']);
    }

    public function testOccurrenceStageFollowsEpisodeAndPastIntentionsAreNotMissedPublications(): void
    {
        $r = $this->recurrence();
        $this->episode($r, 1, 'running', '2026-10-28T10:00:00Z');
        $this->episode($r, 2, 'ready', '2026-10-20T10:00:00Z');
        $approved = $this->episode($r, 3, 'ready', '2026-10-29T10:00:00Z');
        $approved->approve($this->video());
        $this->episode($r, 4, 'stopped', '2026-10-30T10:00:00Z');
        $this->em->flush();
        self::assertEqualsCanonicalizing(['production_running', 'video_review', 'publication_prepare'], array_column($this->feed()['dated'], 'state'));
        self::assertSame(1, count($this->feed('youtube', 'video_review')['dated']));
        self::assertSame([], $this->feed('instagram')['dated']);
        self::assertSame([], $this->feed('', 'scheduled')['dated']);
        $this->client->request('GET', '/studio/calendrier?month=2026-10&state=video_review');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.calendar-grid .calendar-production');
        self::assertSelectorTextContains('.calendar-grid .calendar-production', 'Vidéo à valider');
        self::assertSelectorNotExists('.calendar-grid .state-missed');
    }

    public function testCurrentDatedDraftReplacesProductionButAnOldVersionDoesNotHideTheNewOne(): void
    {
        $r = $this->recurrence();
        $episode = $this->episode($r, 1);
        $video = $this->video();
        $episode->approve($video);
        $draft = new PublicationDraft($this->owner, 'Publication de Milo', $this->clock->now());
        $draft->addItem(new PublicationDraftItem($draft, $video, 0));
        $draft->attachEpisode($episode);
        $this->em->persist($draft);
        $this->em->flush();
        self::assertSame($draft->getId(), (int) $this->feed()['dated'][0]['draft_id']);
        $draft->plan(new \DateTimeImmutable('2026-10-30T18:00:00Z'), 'Europe/Paris', '2026-10-30T19:00', $this->clock->now());
        $this->em->flush();
        self::assertSame([], $this->feed()['dated']);
        $this->client->request('GET', '/studio/calendrier?month=2026-10');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(0, '.calendar-grid .calendar-production');
        self::assertSelectorCount(1, '.calendar-grid a[href="/studio/brouillons/'.$draft->getId().'"]');
        self::assertSelectorTextContains('.calendar-legend', 'confirmée');
        $episode = $this->em->find(AiEpisode::class, $episode->getId());
        $this->owner = $this->em->find(User::class, $this->owner->getId());
        $episode->approve($this->video());
        $this->em->flush();
        self::assertSame(1, count($this->feed()['dated']));
    }

    public function testExistingOccurrenceWinsOverNextPreviewAndExhaustedAuthorizationHasNoPreview(): void
    {
        $r = $this->recurrence();
        $at = new \DateTimeImmutable('2026-10-28T18:00:00Z');
        $r->activate(['limit' => 1, 'networks' => ['youtube']], $at, $this->clock->now());
        $this->episode($r, 1);
        self::assertSame(['occurrence'], array_column($this->feed()['dated'], 'kind'));
        $r->advanceSchedule(new \DateTimeImmutable('2026-10-29T18:00:00Z'));
        $this->em->flush();
        self::assertSame(['occurrence'], array_column($this->feed()['dated'], 'kind'));
    }

    public function testUndatedProjectsStayOutsideTheGridAndArePaginatedWithoutInventingDates(): void
    {
        for ($i = 0; $i < 13; ++$i) {
            $e = new AiEpisode($this->owner, $i + 1, 'Sans date '.$i);
            $e->flow('ready');
            $this->em->persist($e);
        }
        $this->em->flush();
        $result = $this->feed('', '', 99);
        self::assertSame(13, $result['total']);
        self::assertSame(2, $result['page']);
        self::assertCount(1, $result['undated']);
        self::assertSame([], $result['dated']);
        self::assertNull($result['undated'][0]['at']);
        $this->client->request('GET', '/studio/calendrier?month=2026-10&pendingPage=2');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(0, '.calendar-grid .calendar-production');
        self::assertSelectorCount(1, '.calendar-undated-list a');
        self::assertSelectorTextContains('#calendar-undated-title', 'Sans date');
        self::assertSame([], $this->feed('youtube')['undated']);
    }
}
