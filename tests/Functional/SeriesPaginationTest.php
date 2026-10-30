<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\AiSeries;
use App\Entity\SeriesOccurrence;
use App\Entity\SeriesRecurrence;
use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class SeriesPaginationTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        foreach (['owner', 'other'] as $name) {
            $user = new User('pagination-'.$name.'@example.test');
            $user->verify();
            $user->setPassword('synthetic');
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $this->em()->flush();
        $http = new MockHttpClient(static function (): never { self::fail('No external HTTP on paginated reads or legacy saves.'); });
        foreach (['ai.http', 'story.http', 'youtube.http', 'instagram.http', 'tiktok.http'] as $service) {
            static::getContainer()->set($service, $http);
        }
        static::getContainer()->get('cache.rate_limiter')->clear();
        $this->client->loginUser($this->owner);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /** @return array{AiSeries,list<AiEpisode>} */
    private function seed(int $count, ?User $owner = null): array
    {
        $owner = $this->em()->find(User::class, ($owner ?? $this->owner)->getId()) ?? throw new \LogicException('Synthetic owner unavailable.');
        $series = new AiSeries($owner, 'Pagination synthetic', 'A white cat opens a door.', '', '', ['model' => 'kling-25', 'seconds' => 5, 'totalSeconds' => 10, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $this->em()->persist($series);
        $episodes = [];
        for ($i = 1; $i <= $count; ++$i) {
            $episode = new AiEpisode($series, $i);
            $episode->edit('Épisode '.$i, 'A white cat walks across the room.');
            $episodes[] = $episode;
            $this->em()->persist($episode);
        }
        $this->em()->flush();

        return [$series, $episodes];
    }

    public static function volumes(): iterable
    {
        yield [0, 0, 1];
        yield [12, 12, 1];
        yield [13, 12, 2];
        yield [25, 12, 3];
    }

    #[DataProvider('volumes')]
    public function testReadIsBoundedAndKeepsTheTotal(int $total, int $visible, int $pages): void
    {
        [$series] = $this->seed($total);
        $this->client->request('GET', '/studio/series/'.$series->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount($visible, '.series-episode');
        self::assertSelectorTextContains('.series-overview', $total.' épisode');
        self::assertSelectorNotExists('.series-episode form');
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        if ($pages > 1) {
            self::assertSelectorTextContains('nav[aria-label="Pages des épisodes (haut)"]', 'Page 1 / '.$pages);
        } else {
            self::assertSelectorNotExists('nav[aria-label="Pages des épisodes (haut)"]');
        }
    }

    public function testPagesFocusAndForeignIdsCannotChangeTheSeriesScope(): void
    {
        [$series, $episodes] = $this->seed(25);
        [, $sameOwner] = $this->seed(1);
        [$foreignSeries, $foreign] = $this->seed(1, $this->other);
        $url = '/studio/series/'.$series->getId();
        foreach (['-1', 'abc', str_repeat('9', 30), ['2']] as $invalid) {
            $this->client->request('GET', $url, ['page' => $invalid]);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(12, '.series-episode');
            self::assertSelectorExists('#episode-'.$episodes[0]->getId());
        }
        $this->client->request('GET', $url.'?page=999999');
        self::assertSelectorCount(1, '.series-episode');
        self::assertSelectorExists('#episode-'.$episodes[24]->getId());
        foreach ([$sameOwner[0]->getId(), $foreign[0]->getId()] as $focus) {
            $this->client->request('GET', $url.'?page=2&episode='.$focus);
            self::assertSelectorExists('#episode-'.$episodes[12]->getId());
            self::assertSelectorNotExists('#episode-'.$focus);
        }
        $crawler = $this->client->request('GET', $url.'?episode='.$episodes[24]->getId());
        self::assertSelectorCount(1, '.series-episode');
        $previous = $crawler->filter('nav[aria-label="Pages des épisodes (haut)"] a[rel="prev"]')->link();
        self::assertStringNotContainsString('episode=', $previous->getUri());
        $this->client->click($previous);
        self::assertSelectorCount(12, '.series-episode');
        self::assertSelectorExists('#episode-'.$episodes[12]->getId());
        $this->client->request('GET', '/studio/series/episodes/'.$episodes[24]->getId());
        self::assertSelectorExists('a[href*="episode='.$episodes[24]->getId().'"]');
        $this->client->request('GET', '/studio/series/'.$foreignSeries->getId().'?page=2');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT count(*) FROM messenger_messages'));
    }

    public function testOccurrenceHistoryPagesIndependentlyAndFindsOldEpisodes(): void
    {
        [$series, $episodes] = $this->seed(25);
        $now = new \DateTimeImmutable('2026-01-01T00:00:00Z');
        $recurrence = new SeriesRecurrence($series);
        $recurrence->activate(['mode' => 'preparation', 'weekday' => 2, 'hour' => '18:00', 'timezone' => 'Europe/Paris', 'networks' => ['youtube'], 'accounts' => ['youtube' => null], 'limit' => 12, 'tone' => 'auto', 'continuity' => 'independent'], $now->modify('+7 days'), $now);
        $recurrence->pause('Synthetic history');
        $this->em()->persist($recurrence);
        foreach ($episodes as $index => $episode) {
            $occurrence = new SeriesOccurrence($recurrence, $now->modify('+'.($index + 1).' days'), 'Europe/Paris', ['youtube'], $now, $episode, ['youtube' => null]);
            $occurrence->fail('Attention synthétique '.$episode->getNumber());
            $this->em()->persist($occurrence);
        }
        $this->em()->flush();
        $crawler = $this->client->request('GET', '/studio/series/'.$series->getId().'?page=2&occurrencePage=3');
        self::assertSelectorCount(12, '.series-episode');
        self::assertSelectorCount(1, '#occurrence-history-content li');
        self::assertSelectorExists('#occurrence-history[open]');
        self::assertSelectorExists('nav[aria-label="Pages des épisodes (haut)"] a[href*="occurrencePage=3"]');
        self::assertSelectorExists('nav[aria-label="Pages des créneaux"] a[href*="page=2"]');
        $recurrenceFields = $crawler->selectButton('Activer la préparation des scénarios →')->form()->getPhpValues();
        $recurrenceFields['series_recurrence']['_token'] = 'invalid';
        $this->client->request('POST', '/studio/series/'.$series->getId().'?page=2&occurrencePage=3', $recurrenceFields);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('#episode-'.$episodes[12]->getId());
        self::assertSelectorExists('[data-series-navigation-blocked="true"]');
        self::assertSelectorCount(1, '#occurrence-history-content li');
        self::assertSame('paused', $recurrence->getStatus());
        $this->client->click($crawler->selectLink('Retrouver l’épisode 1 ↗')->link());
        self::assertSelectorExists('#episode-'.$episodes[0]->getId());
        self::assertSelectorCount(1, '#occurrence-history-content li');
        self::assertSelectorTextContains('#episode-'.$episodes[0]->getId(), 'Publication visée');
        self::assertSame(25, $this->em()->getRepository(SeriesOccurrence::class)->count(['recurrence' => $recurrence]));
    }

    public function testLegacyPostsKeepErrorsAndInputsOutsideTheFirstPage(): void
    {
        [$series, $episodes] = $this->seed(25);
        $episode = $episodes[24];
        $url = '/studio/series/'.$series->getId();
        $this->client->request('GET', $url);
        $fields = ['title' => 'Mon texte <script> conservé', 'prompt' => 'My human prompt kept after an old tab submission.', 'revision' => (string) $episode->getRevision(), 'seriesRevision' => (string) $series->getRevision(), '_token' => 'invalid'];
        $this->client->request('POST', $url, ['intent' => 'save', 'episode_'.$episode->getId() => $fields]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorCount(1, '.legacy-episode-correction form');
        self::assertSelectorTextContains('.legacy-episode-correction', 'La validation de la session a échoué.');
        self::assertSelectorExists('#episode-'.$episode->getId().' textarea');
        self::assertSelectorTextContains('.legacy-episode-correction textarea', $fields['prompt']);
        self::assertStringNotContainsString('<script> conservé', $this->client->getResponse()->getContent());
        self::assertSame('A white cat walks across the room.', $this->em()->find(AiEpisode::class, $episode->getId())->getPrompt());
        $fields['_token'] = $this->client->getCrawler()->selectButton('Enregistrer cette saisie')->form()->getPhpValues()['episode_'.$episode->getId()]['_token'];
        $this->client->request('POST', $url, ['intent' => 'save', 'episode_'.$episode->getId() => $fields]);
        self::assertResponseRedirects($url.'?episode='.$episode->getId().'#episode-'.$episode->getId(), 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('#episode-'.$episode->getId(), $fields['prompt']);
        self::assertSelectorNotExists('.legacy-episode-correction');
        $fields['revision'] = (string) $this->em()->find(AiEpisode::class, $episode->getId())->getRevision();
        $this->client->request('POST', $url, ['intent' => 'prepare', 'episode_'.$episode->getId() => $fields]);
        self::assertResponseStatusCodeSame(303);
        $location = $this->client->getResponse()->headers->get('Location');
        $this->client->request('POST', $url, ['intent' => 'prepare', 'episode_'.$episode->getId() => $fields]);
        self::assertResponseRedirects($location, 303);
        self::assertSame(1, $this->em()->getRepository(AiGeneration::class)->count([]));
        $this->client->request('POST', $url, ['intent' => 'save', 'episode_'.$episode->getId() => $fields, 'episode_'.$episodes[0]->getId() => $fields]);
        self::assertResponseStatusCodeSame(403);
        [, $anotherSeriesEpisodes] = $this->seed(1);
        $this->client->request('POST', $url, ['intent' => 'save', 'episode_'.$anotherSeriesEpisodes[0]->getId() => $fields]);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->other);
        $this->client->request('POST', $url, ['intent' => 'save', 'episode_'.$episode->getId() => $fields]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT count(*) FROM messenger_messages'));
    }
}
