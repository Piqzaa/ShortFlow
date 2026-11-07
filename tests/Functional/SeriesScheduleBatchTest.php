<?php

namespace App\Tests\Functional;

use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\VideoSeries;
use App\Entity\VideoSeriesEpisode;
use App\Entity\YouTubeConnection;
use App\Service\EpisodePublications;
use App\Service\PublicationDrafts;
use App\Service\PublicationException;
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
    private function prepareDrafts(array $episodes): array
    {
        $seriesRevision = $this->em()->find(VideoSeriesEpisode::class, $episodes[0])->getSeries()->getRevision();
        $publications = static::getContainer()->get(EpisodePublications::class);
        $picked = [];
        foreach ($episodes as $id) {
            $episode = $this->em()->find(VideoSeriesEpisode::class, $id);
            $video = $episode->getVideo();
            $draft = $publications->prepareVideo($this->owner(), $episode, $seriesRevision);
            static::getContainer()->get(PublicationDrafts::class)->save($this->owner(), [(int) $video->getId()], ['youtube'], ['youtube' => ['videos' => [$video->getId() => ['title' => 'Titre de la vidéo', 'description' => 'Texte de la préparation #VydFlow', 'privacy' => 'private', 'audience' => 'no', 'synthetic' => false]]]], ['youtube' => $this->identity], 'Prête', $draft->getId(), $draft->getRevision());
            $this->em()->clear();
            /** @var PublicationDraft $stored */
            $stored = $this->em()->find(PublicationDraft::class, $draft->getId());
            self::assertSame($id, $stored->getVideoEpisode()?->getId());
            $picked[$id] = ['draft' => (int) $stored->getId(), 'revision' => $stored->getRevision(), 'local' => '2026-11-10T18:00', 'zone' => 'Europe/Paris'];
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
        return '/studio/series/videos/'.$seriesId.'/programmer';
    }

    private function pickedValues(Crawler $crawler, array $picked): array
    {
        $form = $crawler->selectButton('Vérifier l’aperçu ↗')->form();
        $values = $form->getPhpValues();
        foreach ($picked as $id => $choice) {
            $values['video_series_schedule']['selection'][$id]['picked'] = '1';
            $values['video_series_schedule']['selection'][$id]['local'] = $choice['local'];
            $values['video_series_schedule']['selection'][$id]['zone'] = $choice['zone'];
        }

        return $values;
    }

    private function post(array $values): Crawler
    {
        $this->client->request('POST', $this->path($this->seriesId()), $values);

        return $this->client->getCrawler();
    }

    private int $setupId = 0;

    private function seriesId(): int
    {
        return $this->setupId;
    }

    private function confirm(Crawler $crawler): void
    {
        $form = $crawler->selectButton('Confirmer la programmation')->form(['video_series_schedule[confirm]' => '1']);
        $this->client->request('POST', $form->getUri(), $form->getPhpValues());
    }

    /** @param array<int,array{draft:int,revision:int,local:string,zone:string}> $picked */
    private function assertUntouched(array $picked): void
    {
        foreach ($picked as $choice) {
            $draft = $this->em()->find(PublicationDraft::class, $choice['draft']);
            self::assertSame('draft', $draft->getStatus());
            self::assertSame($choice['revision'], $draft->getRevision());
        }
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testPreviewThenConfirmProgramsEachChosenEpisodeOnce(): void
    {
        $setup = $this->seriesWith(2);
        $this->setupId = $setup['series'];
        $picked = $this->prepareDrafts($setup['episodes']);
        $picked[$setup['episodes'][1]]['local'] = '2026-11-11T09:00';
        $crawler = $this->client->request('GET', $this->path($setup['series']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.workspace-heading', 'Un créneau par épisode');
        self::assertSelectorTextSame('#schedule-episode-'.$setup['episodes'][0].' .eyebrow', 'ÉPISODE 1');
        self::assertSelectorExists('input[name="video_series_schedule[selection]['.$setup['episodes'][0].'][draft]"][value="'.$picked[$setup['episodes'][0]]['draft'].'"]');
        self::assertSelectorExists('input[name="video_series_schedule[selection]['.$setup['episodes'][0].'][revision]"][value="'.$picked[$setup['episodes'][0]]['revision'].'"]');
        self::assertSelectorExists('input[name="video_series_schedule[selection]['.$setup['episodes'][0].'][picked]"]');
        self::assertSelectorExists('input[name="video_series_schedule[selection]['.$setup['episodes'][0].'][local]"]');
        self::assertSelectorExists('select[name="video_series_schedule[selection]['.$setup['episodes'][0].'][zone]"]');
        self::assertSelectorExists('input[name="video_series_schedule[revision]"]');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/series-schedule-batch.html', $this->client->getResponse()->getContent());

        $values = $this->pickedValues($crawler, $picked);
        $post = $this->post($values);
        self::assertResponseStatusCodeSame(200);
        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('Récapitulatif de la programmation', $html);
        self::assertStringContainsString('2 épisodes prêts', $html);
        self::assertSelectorTextContains('#series-schedule-preview article h3', 'Épisode 1 ·');
        self::assertStringContainsString('départ le 10/11/2026 à 18:00 · Europe/Paris', $html);
        self::assertStringContainsString('départ le 11/11/2026 à 09:00 · Europe/Paris', $html);
        self::assertStringContainsString('Titre de la vidéo', $html);
        self::assertStringNotContainsString('batch-issues', $html);
        file_put_contents(dirname(__DIR__, 2).'/artifacts/series-schedule-preview.html', $html);
        $this->assertUntouched($picked);

        $replay = $post->selectButton('Confirmer la programmation')->form(['video_series_schedule[confirm]' => '1'])->getPhpValues();
        $this->confirm($post);
        self::assertResponseStatusCodeSame(303);
        self::assertResponseRedirects('/studio/series/videos/'.$setup['series'].'#video-episode-'.$setup['episodes'][0]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.account-notice.success', '2 épisodes programmés : les envois démarreront aux créneaux choisis.');
        $revisions = [];
        foreach ($picked as $choice) {
            $draft = $this->em()->find(PublicationDraft::class, $choice['draft']);
            self::assertSame('scheduled', $draft->getStatus());
            self::assertSame($choice['revision'] + 2, $draft->getRevision());
            self::assertSame($choice['local'], $draft->getLocalTime());
            self::assertSame($choice['zone'], $draft->getTimezone());
            self::assertNotNull($draft->getScheduledAt());
            $revisions[$choice['draft']] = $draft->getRevision();
        }
        $delays = $this->db()->fetchFirstColumn('SELECT EXTRACT(EPOCH FROM (available_at - created_at)) FROM messenger_messages ORDER BY id');
        self::assertCount(2, $delays);
        foreach ($delays as $delay) {
            self::assertGreaterThanOrEqual(273600, (int) $delay);
        }
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM youtube_publication'));

        $crawler = $this->client->request('GET', $this->path($setup['series']));
        self::assertSelectorTextContains('#schedule-episode-'.$setup['episodes'][0], 'Programmé');
        self::assertSelectorTextContains('#schedule-episode-'.$setup['episodes'][0], 'Départ prévu le 10/11/2026 à 18:00');
        self::assertSelectorTextContains('#schedule-episode-'.$setup['episodes'][1], 'Départ prévu le 11/11/2026 à 09:00');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/series-schedule-scheduled.html', $this->client->getResponse()->getContent());

        $this->post($replay);
        self::assertResponseStatusCodeSame(422);
        foreach ($picked as $choice) {
            self::assertSame($revisions[$choice['draft']], $this->em()->find(PublicationDraft::class, $choice['draft'])->getRevision());
        }
        self::assertCount(2, $this->db()->fetchAllAssociative('SELECT id FROM messenger_messages'));
    }

    public function testIssuesKeepEpisodesOutAndSubsetConfirmArmsOthersOnly(): void
    {
        $setup = $this->seriesWith(2);
        $this->setupId = $setup['series'];
        $picked = $this->prepareDrafts($setup['episodes']);
        $this->breakDraft($picked, $setup['episodes'][1]);
        $crawler = $this->client->request('GET', $this->path($setup['series']));
        $values = $this->pickedValues($crawler, $picked);
        $post = $this->post($values);
        self::assertResponseStatusCodeSame(200);
        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('1 épisode prêt', $html);
        self::assertStringContainsString('Ces épisodes ne passent pas à la confirmation', $html);
        self::assertStringContainsString('invalide ou incomplet', $html);
        self::assertStringContainsString('Confirmer la programmation de 1 épisode ↗', $html);
        file_put_contents(dirname(__DIR__, 2).'/artifacts/series-schedule-preview-issues.html', $html);
        $this->assertUntouched($picked);

        $replay = $post->selectButton('Confirmer la programmation')->form(['video_series_schedule[confirm]' => '1'])->getPhpValues();
        $this->confirm($post);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.account-notice.success', '1 épisode programmé : les envois démarreront aux créneaux choisis.');
        self::assertSame('scheduled', $this->em()->find(PublicationDraft::class, $picked[$setup['episodes'][0]]['draft'])->getStatus());
        $broken = $this->em()->find(PublicationDraft::class, $picked[$setup['episodes'][1]]['draft']);
        self::assertSame('draft', $broken->getStatus());
        self::assertSame($picked[$setup['episodes'][1]]['revision'], $broken->getRevision());
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));

        $this->post($replay);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame('draft', $this->em()->find(PublicationDraft::class, $picked[$setup['episodes'][1]]['draft'])->getStatus());
    }

    public function testDraftOrSeriesChangeBetweenPreviewAndConfirmRefusesTheWholeBatch(): void
    {
        $setup = $this->seriesWith(2);
        $this->setupId = $setup['series'];
        $picked = $this->prepareDrafts($setup['episodes']);
        $crawler = $this->client->request('GET', $this->path($setup['series']));
        $values = $this->pickedValues($crawler, $picked);
        $post = $this->post($values);
        self::assertResponseStatusCodeSame(200);
        $confirmValues = $post->selectButton('Confirmer la programmation')->form(['video_series_schedule[confirm]' => '1'])->getPhpValues();

        $broken = $this->em()->find(PublicationDraft::class, $picked[$setup['episodes'][1]]['draft']);
        $videoId = (int) $broken->getItems()[0]->getVideoId();
        static::getContainer()->get(PublicationDrafts::class)->save($this->owner(), [$videoId], ['youtube'], ['youtube' => ['videos' => [$videoId => ['title' => 'Titre changé', 'description' => 'Texte changé #VydFlow', 'privacy' => 'private', 'audience' => 'no', 'synthetic' => false]]]], ['youtube' => $this->identity], 'Prête changée', $broken->getId(), $broken->getRevision());
        $this->em()->clear();

        $this->post($confirmValues);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Un épisode a changé depuis l’aperçu. Refais l’aperçu avant de confirmer.', $this->client->getResponse()->getContent());
        foreach ($picked as $choice) {
            self::assertSame('draft', $this->em()->find(PublicationDraft::class, $choice['draft'])->getStatus());
        }
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));

        $crawler = $this->client->request('GET', $this->path($setup['series']));
        $post = $this->post($this->pickedValues($crawler, $picked));
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('#series-schedule-preview-title');
        $confirmValues = $post->selectButton('Confirmer la programmation')->form(['video_series_schedule[confirm]' => '1'])->getPhpValues();
        $series = $this->em()->find(VideoSeries::class, $setup['series']);
        $studio = static::getContainer()->get(VideoSeriesStudio::class);
        $studio->save($this->owner(), 'Série renommée', array_map(static fn (VideoSeriesEpisode $episode): string => (string) $episode->getVideo()->getId(), $studio->episodes($series)), '', $series->getRevision(), $series);
        $this->em()->clear();
        $this->post($confirmValues);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('La série a changé. Recharge ses épisodes avant de confirmer.', $this->client->getResponse()->getContent());
        foreach ($picked as $choice) {
            self::assertSame('draft', $this->em()->find(PublicationDraft::class, $choice['draft'])->getStatus());
        }
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testGuardRailRejectionsStayScopedWithoutProgrammingAnything(): void
    {
        $setup = $this->seriesWith(2);
        $this->setupId = $setup['series'];
        $picked = $this->prepareDrafts($setup['episodes']);
        $service = static::getContainer()->get(VideoSeriesSchedule::class);
        $series = $this->em()->find(VideoSeries::class, $setup['series']);
        $junk = [];
        for ($i = 0; $i < VideoSeriesSchedule::LIMIT + 1; ++$i) {
            $junk[] = ['draft' => 900000 + $i, 'revision' => 1, 'local' => '2026-11-10T18:00', 'zone' => 'Europe/Paris'];
        }
        try {
            $service->preview($series, $series->getRevision(), $junk);
            self::fail('Over-limit batch must be refused.');
        } catch (PublicationException $error) {
            self::assertSame('Choisis de 1 à '.VideoSeriesSchedule::LIMIT.' épisodes par préparation groupée.', $error->getMessage());
        }
        try {
            $service->confirm($this->owner(), $series, $series->getRevision(), $junk);
            self::fail('Over-limit batch must be refused.');
        } catch (PublicationException $error) {
            self::assertSame('Choisis de 1 à '.VideoSeriesSchedule::LIMIT.' épisodes par préparation groupée.', $error->getMessage());
        }
        try {
            $service->preview($series, $series->getRevision(), []);
            self::fail('Empty batch must be refused.');
        } catch (PublicationException $error) {
            self::assertSame('Coche au moins un épisode programmable.', $error->getMessage());
        }
        $this->assertUntouched($picked);

        $this->client->loginUser($this->other);
        $this->client->request('GET', $this->path($setup['series']));
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', $this->path($setup['series']), ['intent' => 'preview']);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->owner);

        $crawler = $this->client->request('GET', $this->path($setup['series']));
        $form = $crawler->selectButton('Vérifier l’aperçu ↗')->form();
        $values = $form->getPhpValues();
        $id = (string) $setup['episodes'][0];
        $values['video_series_schedule']['selection'][$id]['picked'] = '1';
        $values['video_series_schedule']['selection'][$id]['draft'] = '999999';
        $values['video_series_schedule']['selection'][$id]['revision'] = '1';
        $values['video_series_schedule']['selection'][$id]['local'] = '2026-11-10T18:00';
        $values['video_series_schedule']['selection'][$id]['zone'] = 'Europe/Paris';
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Cet épisode n’a plus de brouillon. Recharge ses épisodes.', $this->client->getResponse()->getContent());
        $this->assertUntouched($picked);

        $crawler = $this->client->request('GET', $this->path($setup['series']));
        $values = $this->pickedValues($crawler, [$setup['episodes'][0] => ['local' => '2020-01-01T00:00', 'zone' => 'Europe/Paris', 'draft' => $picked[$setup['episodes'][0]]['draft'], 'revision' => $picked[$setup['episodes'][0]]['revision']]]);
        $this->post($values);
        self::assertResponseStatusCodeSame(200);
        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('Choisis un créneau entre deux minutes et 90 jours dans le futur.', $html);
        $replay = $values;
        $replay['intent'] = 'confirm';
        $replay['video_series_schedule']['confirm'] = '1';
        $this->post($replay);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.series-schedule-form', 'Vérifie un nouvel aperçu');
        $this->assertUntouched($picked);
        $crawler = $this->client->request('GET', $this->path($setup['series']));
        self::assertSelectorCount(1, 'form button[type="submit"][name="intent"][value="preview"]');
        self::assertSelectorCount(1, 'form button[type="submit"][name="intent"][value="confirm"]');
        self::assertSelectorExists('input[name="video_series_schedule[_token]"]');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/series-schedule-guardrails.html', $this->client->getResponse()->getContent());
    }

    public function testConfirmationRequiresTheReviewedSelectionAndExplicitConsent(): void
    {
        $setup = $this->seriesWith(2);
        $this->setupId = $setup['series'];
        $episodeId = $setup['episodes'][1];
        $picked = $this->prepareDrafts([$episodeId]);
        $crawler = $this->client->request('GET', $this->path($setup['series']));
        self::assertResponseIsSuccessful();
        self::assertDoesNotMatchRegularExpression('~<form[^>]+series-schedule-form[^>]*>(?:(?!</form>).)*<form~s', (string) $this->client->getResponse()->getContent());
        self::assertSelectorExists('.series-schedule-form a[href$="#video-episode-'.$setup['episodes'][0].'"]');
        self::assertSelectorExists('.series-schedule-form input[name="video_series_schedule[_token]"]');
        self::assertSelectorExists('.series-schedule-form button[value="preview"]');
        $values = $this->pickedValues($crawler, $picked);
        $this->post(array_replace($values, ['intent' => 'confirm']));
        self::assertResponseStatusCodeSame(422);
        $this->assertUntouched($picked);

        $preview = $this->post($values);
        self::assertResponseIsSuccessful();
        $form = $preview->selectButton('Confirmer la programmation')->form();
        $this->post($form->getPhpValues());
        self::assertResponseStatusCodeSame(422);
        $this->assertUntouched($picked);

        $form['video_series_schedule[confirm]']->tick();
        $values = $form->getPhpValues();
        $values['video_series_schedule']['selection'][$episodeId]['local'] = '2026-11-12T18:00';
        $this->post($values);
        self::assertResponseStatusCodeSame(422);
        $this->assertUntouched($picked);
    }

    public function testAnExpiredReviewedSlotLeavesTheEntireBatchUntouched(): void
    {
        $setup = $this->seriesWith(2);
        $this->setupId = $setup['series'];
        $picked = $this->prepareDrafts($setup['episodes']);
        $picked[$setup['episodes'][1]]['local'] = '2026-11-07T09:03';
        $crawler = $this->client->request('GET', $this->path($setup['series']));
        $preview = $this->post($this->pickedValues($crawler, $picked));
        self::assertSelectorTextContains('#series-schedule-preview', '2 épisodes prêts');
        static::getContainer()->get('app.publication_clock')->sleep(180);
        $this->confirm($preview);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.series-schedule-form', 'Choisis un créneau entre deux minutes et 90 jours dans le futur.');
        $this->assertUntouched($picked);
    }
}
