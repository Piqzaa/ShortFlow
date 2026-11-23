<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\AiSeries;
use App\Entity\SeriesOccurrence;
use App\Entity\SeriesRecurrence;
use App\Entity\User;
use App\Message\PrepareSeriesEpisode;
use App\Service\SeriesRecurrences;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

final class SeriesRecurrencesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private AiSeries $series;
    private MockClock $clock;
    private int $paidCalls = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $this->em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        static::getContainer()->get('cache.rate_limiter')->clear();
        $this->clock = new MockClock('2026-10-24T08:00:00Z');
        static::getContainer()->set('app.publication_clock', $this->clock);
        foreach (['youtube.http', 'instagram.http', 'tiktok.http'] as $id) {
            static::getContainer()->set($id, new MockHttpClient(static function (): never { throw new \LogicException('No social calls'); }));
        }
        static::getContainer()->set('ai.http', new MockHttpClient(function (): never {
            ++$this->paidCalls;
            throw new \LogicException('No billable provider request expected');
        }));
        static::getContainer()->set('story.http', new MockHttpClient(static function (): never { throw new \LogicException('No scenario request expected'); }));
        $this->owner = new User('recurrences@example.test');
        $this->owner->verify();
        $this->owner->setPassword('unused');
        $this->em->persist($this->owner);
        $this->em->flush();
        $this->series = new AiSeries($this->owner, 'Milo', 'Milo découvre les objets du quotidien', 'réaliste', 'Milo, chat blanc joueur', ['model' => 'kling-25', 'totalSeconds' => 30, 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $this->em->persist($this->series);
        $this->em->flush();
        $this->client->loginUser($this->owner);
    }

    private function service(): SeriesRecurrences
    {
        return static::getContainer()->get(SeriesRecurrences::class);
    }

    public function testOccurrenceWithoutPublicationShowsItsOwnSavedDestination(): void
    {
        $id = 'UC'.str_repeat('a', 22);
        $this->em->persist(new \App\Entity\YouTubeConnection($this->owner, $id, 'Original destination', 'synthetic', 'synthetic', 86400));
        $this->em->persist(new \App\Entity\YouTubeConnection($this->owner, 'UC'.str_repeat('b', 22), 'Other destination', 'synthetic', 'synthetic', 86400));
        $this->em->flush();
        $recurrence = $this->recurrence();
        $episode = new AiEpisode($this->series, 1);
        $occurrence = new SeriesOccurrence($recurrence, $recurrence->getNextAt(), 'Europe/Paris', ['youtube'], $this->clock->now(), $episode, ['youtube' => $id]);
        $this->em->persist($episode);
        $this->em->persist($occurrence);
        $this->em->flush();
        $this->client->request('GET', '/studio/series/'.$this->series->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#episode-'.$episode->getId(), 'Original destination · '.$id);
        self::assertSelectorTextNotContains('#episode-'.$episode->getId(), 'Other destination');
        self::assertSame(0, $this->paidCalls);
    }

    public function testRetiringTheRecurrenceKeepsTheSavedEpisodeAndBlocksReactivation(): void
    {
        $recurrence = $this->recurrence();
        $episode = new AiEpisode($this->series, 1);
        $occurrence = new SeriesOccurrence($recurrence, $recurrence->getNextAt(), 'Europe/Paris', ['youtube'], $this->clock->now(), $episode);
        $this->em->persist($episode);
        $this->em->persist($occurrence);
        $this->em->flush();
        $message = new PrepareSeriesEpisode($recurrence->getId(), $recurrence->getRevision(), $recurrence->getNextAt()->format(DATE_ATOM));

        $path = '/studio/series/'.$this->series->getId();
        $this->client->request('POST', $path, ['series_recurrence' => ['consent' => '1']]);
        self::assertResponseStatusCodeSame(410);
        $this->service()->produce($message);
        $this->service()->produce($message);
        self::assertSame('paused', $recurrence->getStatus());
        self::assertSame(1, $this->em->getRepository(AiEpisode::class)->count([]));
        self::assertSame(1, $this->em->getRepository(SeriesOccurrence::class)->count([]));
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));

        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.series-recurrence', 'La création automatique de scénarios est arrêtée.');
        self::assertSelectorExists('#episode-'.$episode->getId());
        self::assertSelectorNotExists('input[name^="series_recurrence"]');
    }

    private function recurrence(): SeriesRecurrence
    {
        $recurrence = new SeriesRecurrence($this->series);
        $recurrence->activate(['mode' => 'preparation', 'limit' => 2], $this->clock->now()->modify('+3 days'), $this->clock->now());
        $this->em->persist($recurrence);
        $this->em->flush();

        return $recurrence;
    }
}
