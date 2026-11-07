<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\EpisodePublications;
use App\Service\SeriesCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

final class SeriesCalendarJourneyTest extends WebTestCase
{
    private KernelBrowser $client;
    private AiSeries $series;
    private User $owner;
    private string $url;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = $this->em()->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        static::getContainer()->set('app.publication_clock', new MockClock('2026-11-07T08:00:00Z'));
        foreach (['ai.http', 'story.http', 'youtube.http', 'instagram.http', 'tiktok.http'] as $name) {
            static::getContainer()->set($name, new MockHttpClient(static function (): never { throw new \LogicException('No external HTTP in calendar tests.'); }));
        }
        $this->owner = new User('series-calendar@example.test');
        $this->owner->verify();
        $this->owner->setPassword('unused');
        $this->em()->persist($this->owner);
        $this->series = new AiSeries($this->owner, 'Mes rendez-vous', 'Un chat découvre le jardin.', 'Réaliste', 'Chat blanc', ['model' => 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $this->em()->persist($this->series);
        foreach ([1, 2, 3] as $number) {
            $this->em()->persist(new AiEpisode($this->series, $number));
        }
        $this->em()->flush();
        $this->url = '/studio/series/'.$this->series->getId().'/calendrier';
        $this->client->loginUser($this->owner);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /** @return array<string,mixed> */
    private function input(): array
    {
        $crawler = $this->client->request('GET', $this->url);
        self::assertResponseIsSuccessful();
        file_put_contents('/app/artifacts/series-calendar-initial.html', $this->client->getResponse()->getContent());
        $values = $crawler->selectButton('Voir les dates proposées →')->form()->getPhpValues();
        $values['series_calendar']['start'] = '2026-11-09';
        $values['series_calendar']['end'] = '2026-11-20';
        $values['series_calendar']['hour'] = '18:00';
        $values['series_calendar']['timezone'] = 'Europe/Paris';
        $values['series_calendar']['weekdays'] = ['1', '3'];
        $values['series_calendar']['count'] = '4';
        $values['series_calendar']['dates'] = ['2026-11-09T18:00', '2026-11-11T18:00', '2026-11-16T18:00', '2026-11-18T18:00'];
        $values['intent'] = 'preview';

        return $values;
    }

    public function testPreviewSaveReopenKeepsDatesWithoutCreatingAnyWork(): void
    {
        $values = $this->input();
        $crawler = $this->client->request('POST', $this->url, $values);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#series-dates-title', 'Aperçu');
        self::assertSelectorTextContains('.story-context', '09/11/2026 à 18:00');
        self::assertSelectorTextContains('.story-context', 'Épisode à créer');
        self::assertSame([], $this->em()->find(AiSeries::class, $this->series->getId())->getCalendarPlan());
        file_put_contents('/app/artifacts/series-calendar-preview.html', $this->client->getResponse()->getContent());
        $save = $crawler->selectButton('Enregistrer ces dates prévues')->form()->getPhpValues();
        $save['intent'] = 'save';
        $this->client->request('POST', $this->url, $save);
        self::assertResponseRedirects($this->url, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('#series-dates-title', 'Dates prévues enregistrées');
        file_put_contents('/app/artifacts/series-calendar-saved.html', $this->client->getResponse()->getContent());
        $stored = $this->em()->find(AiSeries::class, $this->series->getId());
        self::assertSame('2026-11-09T17:00:00+00:00', $stored->getCalendarPlan()['slots'][0]['at']);
        self::assertNull($stored->getCalendarPlan()['slots'][3]['episode']);
        self::assertSame(4, $stored->getCalendarPlan()['slots'][3]['number']);
        foreach (['publication_draft', 'ai_generation', 'story_proposal', 'messenger_messages', 'series_recurrence', 'series_occurrence'] as $table) {
            self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table));
        }
        self::assertSame(3, $this->em()->getRepository(AiEpisode::class)->count(['series' => $stored]));
        $before = $stored->getCalendarPlan();
        $invalid = $this->input();
        $invalid['series_calendar']['dates'][1] = '2026-11-08T18:00';
        $this->client->request('POST', $this->url, $invalid);
        self::assertResponseStatusCodeSame(422);
        self::assertSame($before, $this->em()->find(AiSeries::class, $this->series->getId())->getCalendarPlan());
    }

    public function testCsrfOwnershipStaleRevisionAndChangedPreviewAreRejected(): void
    {
        $input = $this->input();
        $invalid = $input;
        $invalid['series_calendar']['_token'] = 'invalid';
        $this->client->request('POST', $this->url, $invalid);
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->request('POST', $this->url, $input);
        $save = $crawler->selectButton('Enregistrer ces dates prévues')->form()->getPhpValues();
        $save['intent'] = 'save';
        $changed = $save;
        $changed['series_calendar']['dates'][0] = '2026-11-09T19:00';
        $this->client->request('POST', $this->url, $changed);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="series_calendar"]', 'nouvel aperçu');
        self::assertSame([], $this->em()->find(AiSeries::class, $this->series->getId())->getCalendarPlan());
        $series = $this->em()->find(AiSeries::class, $this->series->getId());
        $series->episodesAdded();
        $this->em()->flush();
        $this->client->request('POST', $this->url, $save);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="series_calendar"]', 'La série a changé');
        $other = new User('series-calendar-other@example.test');
        $other->verify();
        $other->setPassword('unused');
        $this->em()->persist($other);
        $this->em()->flush();
        $this->client->loginUser($other);
        $this->client->request('GET', $this->url);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', $this->url, $save);
        self::assertResponseStatusCodeSame(404);
    }

    public function testConfirmedEpisodeIsExcludedAndExistingDraftIsNeverModified(): void
    {
        $episode = $this->em()->getRepository(AiEpisode::class)->findOneBy(['series' => $this->series, 'number' => 1]);
        $video = new VideoAsset($this->owner, 'Version prête', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 100, 5, 720, 1280, 'h264');
        $this->em()->persist($video);
        $episode->flow('ready');
        $episode->approve($video);
        $this->em()->flush();
        $draft = static::getContainer()->get(EpisodePublications::class)->prepare($this->owner, $episode, $episode->getRevision());
        $now = new \DateTimeImmutable('2026-11-07T08:00:00Z');
        $draft->plan(new \DateTimeImmutable('2026-11-12T17:00:00Z'), 'Europe/Paris', '2026-11-12T18:00', $now);
        $draft->arm($now);
        $this->em()->flush();
        $rules = ['start' => '2026-11-09', 'end' => null, 'weekdays' => [1], 'hour' => '18:00', 'timezone' => 'Europe/Paris', 'count' => 3];
        $calendar = static::getContainer()->get(SeriesCalendar::class);
        $preview = $calendar->preview($this->series, $rules);
        self::assertSame([2, 3, 4], array_column($preview['slots'], 'number'));
        $calendar->save($this->owner, $this->series, $rules + ['snapshot' => $preview['hash'], 'revision' => $this->series->getRevision()]);
        $this->em()->refresh($draft);
        self::assertSame('scheduled', $draft->getStatus());
        self::assertSame('2026-11-12T17:00:00+00:00', $draft->getScheduledAt()->format(DATE_ATOM));
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testRhythmFillsEditableDatesAndCustomTimesSurviveSave(): void
    {
        $input = $this->input();
        $input['intent'] = 'fill';
        $crawler = $this->client->request('POST', $this->url, $input);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="series_calendar[dates][1]"][type="datetime-local"][value="2026-11-11T18:00"]');
        $custom = $crawler->selectButton('Voir les dates proposées →')->form()->getPhpValues();
        $custom['series_calendar']['dates'][1] = '2026-11-12T10:15';
        $custom['series_calendar']['start'] = '';
        $custom['series_calendar']['hour'] = '';
        $custom['series_calendar']['weekdays'] = [];
        $custom['intent'] = 'preview';
        $crawler = $this->client->request('POST', $this->url, $custom);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.story-context', '12/11/2026 à 10:15');
        file_put_contents('/app/artifacts/series-calendar-custom.html', $this->client->getResponse()->getContent());
        $save = $crawler->selectButton('Enregistrer ces dates prévues')->form()->getPhpValues();
        $save['intent'] = 'save';
        $this->client->request('POST', $this->url, $save);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorExists('input[name="series_calendar[dates][1]"][value="2026-11-12T10:15"]');
        $stored = $this->em()->find(AiSeries::class, $this->series->getId());
        self::assertSame('manual', $stored->getCalendarPlan()['rules']['mode']);
        self::assertSame('2026-11-12T09:15:00+00:00', $stored->getCalendarPlan()['slots'][1]['at']);
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testResizePreservesEnteredDatesWithoutCreatingEpisodes(): void
    {
        $input = $this->input();
        $input['series_calendar']['count'] = '6';
        $input['intent'] = 'resize';
        $this->client->request('POST', $this->url, $input);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(6, '.series-calendar-date');
        self::assertSelectorExists('input[name="series_calendar[dates][0]"][value="2026-11-09T18:00"]');
        self::assertSelectorExists('input[name="series_calendar[dates][5]"]:not([value])');
        self::assertSame(3, $this->em()->getRepository(AiEpisode::class)->count([]));
        self::assertSame([], $this->em()->find(AiSeries::class, $this->series->getId())->getCalendarPlan());
    }
}
