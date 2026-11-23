<?php

namespace App\Tests\Functional;

use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\VideoSeries;
use App\Entity\VideoSeriesEpisode;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Service\EpisodePublications;
use App\Service\PublicationDrafts;
use App\Service\TokenVault;
use App\Service\VideoSeriesSchedule;
use App\Service\VideoSeriesStudio;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\MockHttpClient;

final class SeriesScheduleBatchTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    private string $identity;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        foreach (['owner', 'other'] as $name) {
            $user = new User('series-schedule-'.$name.'@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $vault = new TokenVault($_ENV['YOUTUBE_TOKEN_KEY']);
        $this->identity = 'UC'.str_repeat('s', 22);
        $this->em()->persist(new YouTubeConnection($this->owner, $this->identity, 'Ma chaîne', $vault->encrypt('synthetic'), $vault->encrypt('synthetic'), 86400));
        $this->em()->persist(new YouTubeConnection($this->other, 'UC'.str_repeat('f', 22), 'Chaîne étrangère', $vault->encrypt('synthetic'), $vault->encrypt('synthetic'), 86400));
        $this->em()->flush();
        static::getContainer()->set('app.publication_clock', new MockClock('2026-11-07T08:00:00Z'));
        static::getContainer()->get('cache.rate_limiter')->clear();
        $http = new MockHttpClient(static function (): never { self::fail('No external HTTP in series schedule tests.'); });
        foreach (['ai.http', 'story.http', 'youtube.http', 'instagram.http', 'tiktok.http'] as $name) {
            static::getContainer()->set($name, $http);
        }
        $this->client->loginUser($this->owner);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function owner(): User
    {
        return $this->em()->find(User::class, $this->owner->getId());
    }

    private function video(string $title): VideoAsset
    {
        $video = new VideoAsset($this->owner(), $title, bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 100, 5, 720, 1280, 'h264');
        $video->recordTechnicalMetadata(['fps' => 30, 'videoBitrate' => 3000000]);
        $this->em()->persist($video);
        $this->em()->flush();

        return $video;
    }

    /** @return array{series:int,episodes:list<int>} */
    private function seriesWith(int $count): array
    {
        $ids = [];
        for ($i = 1; $i <= $count; ++$i) {
            $ids[] = $this->video('Épisode '.$i)->getId();
        }
        $studio = static::getContainer()->get(VideoSeriesStudio::class);
        $series = $studio->save($this->owner(), 'Série programmée', array_map(static fn (int $id): string => (string) $id, $ids), '', 0);
        $episodes = array_map(static fn ($episode): int => $episode->getId(), $studio->episodes($series));
        $this->em()->clear();

        return ['series' => (int) $series->getId(), 'episodes' => $episodes];
    }

    /** @param list<int> $episodes @return array<int,array{draft:int,revision:int,local:string,zone:string}> */
    private function prepareDrafts(array $episodes, bool $dated = true): array
    {
        $seriesRevision = $this->em()->find(VideoSeriesEpisode::class, $episodes[0])->getSeries()->getRevision();
        $publications = static::getContainer()->get(EpisodePublications::class);
        $picked = [];
        foreach ($episodes as $index => $id) {
            $episode = $this->em()->find(VideoSeriesEpisode::class, $id);
            $video = $episode->getVideo();
            $draft = $publications->prepareVideo($this->owner(), $episode, $seriesRevision);
            static::getContainer()->get(PublicationDrafts::class)->save($this->owner(), [(int) $video->getId()], ['youtube'], ['youtube' => ['videos' => [$video->getId() => ['title' => 'Titre de la vidéo', 'description' => 'Texte de la préparation #VydFlow', 'privacy' => 'private', 'audience' => 'no', 'synthetic' => false]]]], ['youtube' => $this->identity], 'Prête', $draft->getId(), $draft->getRevision());
            $this->em()->clear();
            /** @var PublicationDraft $stored */
            $stored = $this->em()->find(PublicationDraft::class, $draft->getId());
            self::assertSame($id, $stored->getVideoEpisode()?->getId());
            $local = 0 === $index ? '2026-11-10T18:00' : '2026-11-11T09:00';
            $zone = 0 === $index ? 'Europe/Paris' : 'America/Toronto';
            if ($dated) {
                static::getContainer()->get(PublicationDrafts::class)->plan($this->owner(), $stored->getId(), $stored->getRevision(), $local, $zone);
            }
            $picked[$id] = ['draft' => (int) $stored->getId(), 'revision' => $stored->getRevision(), 'local' => $local, 'zone' => $zone];
        }

        return $picked;
    }

    private function breakDraft(array $picked, int $episodeId): void
    {
        $draft = $this->em()->find(PublicationDraft::class, $picked[$episodeId]['draft']);
        $item = $draft->getItems()[0];
        $content = $item->getContent();
        $content['youtube']['videos'][$item->getVideoId()]['title'] = '';
        $item->setContent($content);
        $this->em()->flush();
    }

    private function path(int $seriesId): string
    {
        return '/studio/series/videos/'.$seriesId;
    }

    private function selection(Crawler $crawler, array $episodeIds): array
    {
        $values = $crawler->selectButton('Vérifier la sélection →')->form()->getPhpValues();
        $values['episodes'] = array_map('strval', $episodeIds);

        return $values;
    }

    private function confirmation(Crawler $crawler): array
    {
        return $crawler->selectButton('Confirmer la programmation')->form(['confirm' => '1'])->getPhpValues();
    }

    private function assertUntouched(array $picked): void
    {
        foreach ($picked as $choice) {
            $draft = $this->em()->find(PublicationDraft::class, $choice['draft']);
            self::assertSame('draft', $draft->getStatus());
            self::assertSame($choice['revision'], $draft->getRevision());
        }
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testTheSeriesReviewsAndConfirmsSavedDatesWithoutResubmittingThem(): void
    {
        $setup = $this->seriesWith(3);
        $path = $this->path($setup['series']);
        $picked = $this->prepareDrafts(array_slice($setup['episodes'], 0, 2));
        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#video-episode-'.$setup['episodes'][0], '10/11/2026 à 18:00');
        self::assertSelectorExists('#video-episode-'.$setup['episodes'][0].' a[href^="/studio/composer?draft="]');
        self::assertSelectorExists('#video-episode-'.$setup['episodes'][2].' form[action$="/publication"]');
        self::assertSelectorCount(2, 'input[name="episodes[]"][form="series-batch-preview"]');
        self::assertSelectorNotExists('input[type="datetime-local"]');
        self::assertSelectorNotExists('a[href="'.$path.'/programmer"]');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/video-series-batch-inline.html', (string) $this->client->getResponse()->getContent());
        $values = $this->selection($crawler, array_keys($picked));
        $preview = $this->client->request('POST', $path, $values);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#series-schedule-preview', '10/11/2026 à 18:00 · Europe/Paris');
        self::assertSelectorTextContains('#series-schedule-preview', '11/11/2026 à 09:00 · America/Toronto');
        self::assertSelectorTextContains('#series-schedule-preview', 'Texte de la préparation #VydFlow');
        self::assertSelectorTextContains('#series-schedule-preview', 'Ma chaîne');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/video-series-batch-review-inline.html', (string) $this->client->getResponse()->getContent());
        $this->assertUntouched($picked);
        $confirmation = $this->confirmation($preview);
        self::assertArrayNotHasKey('episodes', $confirmation);
        $this->client->request('POST', $path, $confirmation);
        self::assertResponseRedirects($path.'#video-episode-'.$setup['episodes'][0], 303);
        foreach ($picked as $choice) {
            $draft = $this->em()->find(PublicationDraft::class, $choice['draft']);
            self::assertSame('scheduled', $draft->getStatus());
            self::assertSame($choice['revision'] + 1, $draft->getRevision());
            self::assertSame($choice['local'], $draft->getLocalTime());
            self::assertSame($choice['zone'], $draft->getTimezone());
        }
        $delays = $this->db()->fetchFirstColumn('SELECT EXTRACT(EPOCH FROM (available_at - created_at)) FROM messenger_messages ORDER BY id');
        self::assertCount(2, $delays);
        foreach ([291600, 367200] as $index => $expected) {
            // Doctrine rounds a positive delay up to the next stored second.
            self::assertGreaterThanOrEqual($expected, (int) $delays[$index]);
            self::assertLessThanOrEqual($expected + 1, (int) $delays[$index]);
        }
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM youtube_publication'));
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        file_put_contents(dirname(__DIR__, 2).'/artifacts/video-series-batch-confirmed.html', (string) $this->client->getResponse()->getContent());
        $this->client->request('POST', $path, $confirmation);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(2, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testIncompleteDraftIsExcludedBeforeConfirmation(): void
    {
        $setup = $this->seriesWith(2);
        $path = $this->path($setup['series']);
        $picked = $this->prepareDrafts($setup['episodes']);
        $this->breakDraft($picked, $setup['episodes'][1]);
        $page = $this->client->request('GET', $path);
        $preview = $this->client->request('POST', $path, $this->selection($page, array_keys($picked)));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#series-schedule-preview', 'Ces brouillons demandent une correction.');
        self::assertSelectorTextContains('#series-schedule-preview button', 'Confirmer la programmation de 1 épisode');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/video-series-batch-excluded.html', (string) $this->client->getResponse()->getContent());
        $this->assertUntouched($picked);
        $this->client->request('POST', $path, $this->confirmation($preview));
        self::assertResponseStatusCodeSame(303);
        self::assertSame('scheduled', $this->em()->find(PublicationDraft::class, $picked[$setup['episodes'][0]]['draft'])->getStatus());
        self::assertSame('draft', $this->em()->find(PublicationDraft::class, $picked[$setup['episodes'][1]]['draft'])->getStatus());
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testDraftChangeAfterReviewAndForgedConfirmationCannotScheduleAnything(): void
    {
        $setup = $this->seriesWith(2);
        $path = $this->path($setup['series']);
        $picked = $this->prepareDrafts($setup['episodes']);
        $page = $this->client->request('GET', $path);
        $values = $this->selection($page, array_keys($picked));
        $this->client->request('POST', $path, array_replace($values, ['intent' => 'confirm', 'confirm' => '1', 'snapshot' => 'forged']));
        self::assertResponseStatusCodeSame(422);
        $this->assertUntouched($picked);

        $preview = $this->client->request('POST', $path, $values);
        $confirmation = $this->confirmation($preview);
        $this->client->request('POST', $path, array_diff_key($confirmation, ['confirm' => true]));
        self::assertResponseStatusCodeSame(422);
        $this->assertUntouched($picked);

        $last = $setup['episodes'][1];
        $service = static::getContainer()->get(PublicationDrafts::class);
        $service->plan($this->owner(), $picked[$last]['draft'], $picked[$last]['revision'], '2026-11-12T18:00', $picked[$last]['zone']);
        ++$picked[$last]['revision'];
        $this->client->request('POST', $path, $confirmation);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#series-publication-batch [role=alert]', 'a changé');
        $this->assertUntouched($picked);

        $preview = $this->client->request('POST', $path, $values);
        $confirmation = $this->confirmation($preview);
        $this->em()->find(VideoSeries::class, $setup['series'])->revise('Série renommée');
        $this->em()->flush();
        $this->client->request('POST', $path, $confirmation);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#series-publication-batch [role=alert]', 'La série a changé');
        $this->assertUntouched($picked);
    }

    public function testExpiredSlotAtConfirmationLeavesTheEntireBatchInDraft(): void
    {
        $setup = $this->seriesWith(2);
        $path = $this->path($setup['series']);
        $picked = $this->prepareDrafts($setup['episodes']);
        $last = $setup['episodes'][1];
        static::getContainer()->get(PublicationDrafts::class)->plan($this->owner(), $picked[$last]['draft'], $picked[$last]['revision'], '2026-11-07T09:03', 'Europe/Paris');
        ++$picked[$last]['revision'];
        $page = $this->client->request('GET', $path);
        $preview = $this->client->request('POST', $path, $this->selection($page, array_keys($picked)));
        self::assertSelectorTextContains('#series-schedule-preview button', 'Confirmer la programmation de 2 épisodes');
        static::getContainer()->get('app.publication_clock')->sleep(180);
        $this->client->request('POST', $path, $this->confirmation($preview));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#series-publication-batch [role=alert]', 'deux minutes et 90 jours');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/video-series-batch-expired.html', (string) $this->client->getResponse()->getContent());
        $this->assertUntouched($picked);
    }

    public function testGroupShortcutRequiresSeveralDatedDraftsAndScopesTheSelection(): void
    {
        $setup = $this->seriesWith(2);
        $path = $this->path($setup['series']);
        $picked = $this->prepareDrafts([$setup['episodes'][0]]);
        $this->prepareDrafts([$setup['episodes'][1]], false);
        $this->client->request('GET', $path);
        self::assertSelectorNotExists('#series-publication-batch');
        $this->client->request('GET', $path.'/programmer');
        self::assertResponseRedirects($path.'#series-publication-batch', 303);
        $picked += $this->prepareDrafts([$setup['episodes'][1]]);
        $page = $this->client->request('GET', $path);
        $values = $this->selection($page, array_keys($picked));
        $this->client->request('POST', $path, array_replace($values, ['episodes' => array_fill(0, VideoSeriesSchedule::LIMIT + 1, '1')]));
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $path, array_replace($values, ['episodes' => [$values['episodes'][0], '999999']]));
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $path, array_replace($values, ['_token' => 'forged']));
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->other);
        $this->client->request('POST', $path, $values);
        self::assertResponseStatusCodeSame(404);
        $this->assertUntouched($picked);
    }

    public function testDeletingTheSeriesPreservesVideosDraftsAndPublicationHistory(): void
    {
        $setup = $this->seriesWith(2);
        $path = $this->path($setup['series']).'/supprimer';
        $picked = $this->prepareDrafts($setup['episodes']);
        $service = static::getContainer()->get(PublicationDrafts::class);
        [$first, $second] = array_values($picked);
        $page = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        file_put_contents(dirname(__DIR__, 2).'/artifacts/video-series-delete.html', (string) $this->client->getResponse()->getContent());
        $confirmation = $page->selectButton('Supprimer la série')->form(['confirm' => '1'])->getPhpValues();
        $this->client->request('POST', $path, array_diff_key($confirmation, ['confirm' => true]));
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $path, array_replace($confirmation, ['_token' => 'forged']));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $path, array_replace($confirmation, ['revision' => '0']));
        self::assertResponseStatusCodeSame(422);

        $service->confirm($this->owner(), $first['draft'], $first['revision'], true);
        $this->client->request('POST', $path, $confirmation);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('a[href="/studio/brouillons/'.$first['draft'].'"]');
        self::assertSelectorNotExists('.story-panel button[type="submit"]');
        $service->transition($this->owner(), $first['draft'], $first['revision'] + 1, 'cancel');

        $service->confirm($this->owner(), $second['draft'], $second['revision'], false);
        $job = $this->em()->getRepository(YouTubePublication::class)->findOneBy(['owner' => $this->owner()]);
        self::assertInstanceOf(YouTubePublication::class, $job);
        $this->client->request('POST', $path, $confirmation);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('a[href="/studio/brouillons/'.$second['draft'].'"]');
        $job = $this->em()->find(YouTubePublication::class, $job->getId());
        self::assertInstanceOf(YouTubePublication::class, $job);
        $job->complete('private');
        $this->em()->flush();

        $this->client->loginUser($this->other);
        $this->client->request('POST', $path, $confirmation);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->owner);
        $page = $this->client->request('GET', $path);
        $this->client->submit($page->selectButton('Supprimer la série')->form(['confirm' => '1']));
        self::assertResponseRedirects('/studio/series', 303);
        $this->em()->clear();
        self::assertNull($this->em()->find(VideoSeries::class, $setup['series']));
        self::assertSame(0, $this->em()->getRepository(VideoSeriesEpisode::class)->count([]));
        self::assertSame(2, $this->em()->getRepository(VideoAsset::class)->count([]));
        self::assertSame(1, $this->em()->getRepository(YouTubePublication::class)->count([]));
        foreach ($picked as $choice) {
            $draft = $this->em()->find(PublicationDraft::class, $choice['draft']);
            self::assertNull($draft->getVideoEpisode());
            self::assertNotEmpty($draft->videoIds());
            self::assertSame('Texte de la préparation #VydFlow', $draft->content()['youtube']['videos'][$draft->videoIds()[0]]['description']);
        }
        $this->client->request('GET', '/studio/brouillons/'.$second['draft']);
        self::assertResponseIsSuccessful();
    }
}
