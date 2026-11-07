<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\VideoSeries;
use App\Entity\YouTubeConnection;
use App\Service\EpisodePublications;
use App\Service\PublicationDrafts;
use App\Service\SeriesPublicationSettings;
use App\Service\TokenVault;
use App\Service\VideoSeriesStudio;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

final class SeriesPublicationJourneyTest extends WebTestCase
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
            $user = new User('publication-series-'.$name.'@example.test');
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
        $http = new MockHttpClient(static function (): never { self::fail('No external HTTP in series publication tests.'); });
        foreach (['ai.http', 'story.http', 'youtube.http', 'instagram.http', 'tiktok.http'] as $name) {
            static::getContainer()->set($name, $http);
        }
        $this->client->loginUser($this->owner);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
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

    /** @return array{networks:list<string>,accounts:array<string,?string>} */
    private function settings(): array
    {
        return ['networks' => ['youtube'], 'accounts' => ['youtube' => $this->identity]];
    }

    private function noPosts(): void
    {
        foreach (['youtube_publication', 'instagram_publication', 'tiktok_publication', 'ai_generation'] as $table) {
            self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM '.$table));
        }
    }

    public function testBothCreationFormsSaveOwnedDestinationsWithoutAutomaticPreparation(): void
    {
        $video = $this->video('Mon premier épisode');
        foreach (['ai', 'videos'] as $kind) {
            $path = 'ai' === $kind ? '/studio/series/nouvelle' : '/studio/series/videos/nouvelle';
            $prefix = 'ai' === $kind ? 'ai_series' : 'video_series';
            $crawler = $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.series-publication-settings', 'Où publier');
            self::assertStringNotContainsString('Chaîne étrangère', $this->client->getResponse()->getContent());
            $values = $crawler->selectButton('Créer ma série →')->form()->getPhpValues();
            $values[$prefix]['name'] = 'Série '.$kind;
            $values[$prefix]['publication'] = $this->settings();
            if ('ai' === $kind) {
                $values[$prefix]['theme'] = 'Mon univers';
            } else {
                $values[$prefix]['videos'] = [(string) $video->getId()];
            }
            $this->client->request('POST', $path, $values);
            self::assertResponseStatusCodeSame(303);
            $series = $this->em()->getRepository('ai' === $kind ? AiSeries::class : VideoSeries::class)->findOneBy(['owner' => $this->owner()]);
            self::assertSame($this->settings(), $series->getPublicationSettings());
            $edit = 'ai' === $kind ? '/studio/series/'.$series->getId().'/configuration' : '/studio/series/videos/'.$series->getId().'/modifier';
            $this->client->request('GET', $edit);
            self::assertSelectorExists('select[name="'.$prefix.'[publication][accounts][youtube]"] option[selected][value="'.$this->identity.'"]');
            file_put_contents(dirname(__DIR__, 2).'/artifacts/series-publication-'.$kind.'-settings.html', $this->client->getResponse()->getContent());
        }
        self::assertSame(0, $this->em()->getRepository(PublicationDraft::class)->count([]));
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->noPosts();
    }

    public function testImportedEpisodeKeepsTextsDateAndIdentityAcrossReorderAndDefaultsChange(): void
    {
        $a = $this->video('Épisode A');
        $b = $this->video('Épisode B');
        $studio = static::getContainer()->get(VideoSeriesStudio::class);
        $series = $studio->save($this->owner(), 'Série importée', [(string) $a->getId(), (string) $b->getId()], '', 0, null, $this->settings());
        $episodes = $studio->episodes($series);
        $episodeId = $episodes[0]->getId();
        $seriesId = $series->getId();
        $crawler = $this->client->request('GET', '/studio/series/videos/'.$seriesId);
        $form = $crawler->filter('#video-episode-'.$episodeId)->selectButton('Préparer la publication →')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $draft = $this->em()->getRepository(PublicationDraft::class)->findOneBy(['videoEpisode' => $episodeId]);
        self::assertInstanceOf(PublicationDraft::class, $draft);
        $draftId = $draft->getId();
        self::assertSame($this->settings()['networks'], $draft->getNetworks());
        self::assertSame([$a->getId()], $draft->videoIds());
        $this->client->submit($form);
        self::assertSame(1, $this->em()->getRepository(PublicationDraft::class)->count([]));
        $this->client->request('GET', '/studio/composer?draft='.$draftId);
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href^="/studio/series/videos/'.$seriesId.'"]');
        self::assertSelectorExists('textarea[name$="[description]"]');
        self::assertSelectorNotExists('button[value^="edit_"]');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/series-publication-videos-composer.html', $this->client->getResponse()->getContent());
        $values = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $attack = $values;
        $attack['intent'] = 'edit_'.$a->getId();
        $this->client->request('POST', $crawler->getUri(), $attack);
        self::assertResponseStatusCodeSame(403);
        $values['publication_composer']['settings']['youtube']['videos'][$a->getId()] = ['title' => 'Titre de A', 'description' => 'Texte propre à A #VydFlow', 'privacy' => 'private', 'audience' => 'no', 'synthetic' => false];
        $values['publication_composer']['mode'] = 'scheduled';
        $values['publication_composer']['local'] = '2026-11-10T18:00';
        $values['publication_composer']['timezone'] = 'Europe/Paris';
        $values['intent'] = 'review';
        $this->client->request('POST', $crawler->getUri(), $values);
        self::assertResponseRedirects('/studio/brouillons/'.$draftId.'/confirmer');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href^="/studio/series/videos/'.$seriesId.'"]');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/series-publication-confirm.html', $this->client->getResponse()->getContent());
        $this->client->submit($crawler->selectButton('Confirmer la programmation ↗')->form(['form[confirm]' => '1']));
        self::assertResponseRedirects('/studio/series/videos/'.$seriesId.'#video-episode-'.$episodeId);
        $series = $studio->owned($seriesId, $this->owner());
        $studio->save($this->owner(), 'Série renommée', [(string) $a->getId(), (string) $b->getId()], $b->getId().','.$a->getId(), $series->getRevision(), $series, ['networks' => ['instagram'], 'accounts' => ['instagram' => null]]);
        $episodes = $studio->episodes($series);
        self::assertSame($episodeId, $episodes[1]->getId());
        $draft = $this->em()->find(PublicationDraft::class, $draftId);
        self::assertSame('scheduled', $draft->getStatus());
        self::assertSame('Texte propre à A #VydFlow', $draft->content()['youtube']['videos'][$a->getId()]['description']);
        self::assertSame(['youtube'], $draft->getNetworks());
        self::assertSame('2026-11-10T18:00', $draft->getLocalTime());
        $this->client->request('GET', '/studio/series/videos/'.$seriesId);
        self::assertSelectorTextContains('#video-episode-'.$episodeId, 'Programmée');
        self::assertSelectorTextContains('#video-episode-'.$episodeId, '10/11/2026 à 18:00');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/series-publication-videos-show.html', $this->client->getResponse()->getContent());
        $series = $studio->owned($seriesId, $this->owner());
        try {
            $studio->save($this->owner(), 'Retrait refusé', [(string) $b->getId()], '', $series->getRevision(), $series);
            self::fail('Cannot remove a scheduled episode.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('publication', $error->getMessage());
        }
        self::assertCount(2, $studio->episodes($series));
        $this->noPosts();
    }

    public function testAiDefaultsReachPublicationAndExplicitVideoChoiceRejectsAnotherVersion(): void
    {
        $video = $this->video('Vidéo IA');
        $series = new AiSeries($this->owner(), 'Série IA', 'Mon histoire', '', '', ['model' => 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $series->setPublicationSettings($this->settings());
        $episode = new AiEpisode($series, 1);
        $episode->flow('ready');
        $episode->approve($video);
        $this->em()->persist($series);
        $this->em()->persist($episode);
        $this->em()->flush();
        $episodeId = $episode->getId();
        $crawler = $this->client->request('GET', '/studio/series/'.$series->getId());
        self::assertSelectorExists('#episode-'.$episodeId.' form[action$="/publication"]');
        $form = $crawler->filter('#episode-'.$episodeId)->selectButton('Préparer la publication →')->form();
        $values = $form->getPhpValues();
        $values['video'] = $this->video('Autre vidéo')->getId();
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(0, $this->em()->getRepository(PublicationDraft::class)->count([]));
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $draft = $this->em()->getRepository(PublicationDraft::class)->findOneBy(['episode' => $episodeId]);
        self::assertInstanceOf(PublicationDraft::class, $draft);
        self::assertSame($this->settings()['accounts'], $draft->getAccounts());
        self::assertTrue($draft->content()['youtube']['videos'][$video->getId()]['synthetic']);
        $this->client->request('GET', '/studio/composer?draft='.$draft->getId());
        $this->client->followRedirect();
        self::assertSelectorExists('input[name$="[title]"]');
        self::assertSelectorExists('textarea[name$="[description]"]');
        self::assertSelectorExists('input[name="publication_composer[mode]"][value="immediate"][checked]');
        file_put_contents(dirname(__DIR__, 2).'/artifacts/series-publication-ai-composer.html', $this->client->getResponse()->getContent());
        $this->noPosts();
    }

    public function testMissingAndForeignAccountsAndEpisodeCsrfDoNotCreatePreparations(): void
    {
        $settings = static::getContainer()->get(SeriesPublicationSettings::class);
        try {
            $settings->normalize($this->owner(), ['networks' => ['youtube'], 'accounts' => ['youtube' => 'UC'.str_repeat('f', 22)]]);
            self::fail('Foreign account must be rejected.');
        } catch (\App\Service\PublicationException $error) {
            self::assertStringContainsString('compte', $error->getMessage());
        }
        self::assertSame(['youtube' => null], $settings->normalize($this->owner(), ['networks' => ['youtube'], 'accounts' => []])['accounts']);
        $video = $this->video('Mon épisode');
        $studio = static::getContainer()->get(VideoSeriesStudio::class);
        $series = $studio->save($this->owner(), 'Ma série', [(string) $video->getId()], '', 0);
        $episode = $studio->episodes($series)[0];
        $this->client->request('POST', '/studio/series/videos/episodes/'.$episode->getId().'/publication', ['_token' => 'invalid', 'revision' => $series->getRevision()]);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->other);
        $this->client->request('POST', '/studio/series/videos/episodes/'.$episode->getId().'/publication');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->em()->getRepository(PublicationDraft::class)->count([]));
        $this->noPosts();
    }

    public function testDisplayedAssemblyCanBeChosenAndPreparedInOneExplicitAction(): void
    {
        $video = $this->video('Résultat assemblé');
        $series = new AiSeries($this->owner(), 'Série prête', 'Mon histoire', '', '', ['model' => 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $edit = new \App\Entity\VideoEdit($this->owner(), 'Montage', []);
        $edit->complete($video);
        $episode = new AiEpisode($series, 1);
        $episode->assembleWith($edit);
        $episode->flow('ready');
        $this->em()->persist($series);
        $this->em()->persist($edit);
        $this->em()->persist($episode);
        $this->em()->flush();
        $id = $episode->getId();
        $crawler = $this->client->request('GET', '/studio/series/'.$series->getId());
        $this->client->submit($crawler->filter('#episode-'.$id)->selectButton('Préparer la publication →')->form());
        self::assertResponseStatusCodeSame(303);
        $episode = $this->em()->find(AiEpisode::class, $id);
        self::assertSame($video->getId(), $episode->getApprovedVideo()->getId());
        $draft = $this->em()->getRepository(PublicationDraft::class)->findOneBy(['episode' => $episode]);
        self::assertInstanceOf(PublicationDraft::class, $draft);
        self::assertSame([$video->getId()], $draft->videoIds());
        $this->noPosts();
    }

    public function testImmediatePublicationRequiresConfirmationAndCannotEnqueueTwice(): void
    {
        $video = $this->video('Publication immédiate');
        $studio = static::getContainer()->get(VideoSeriesStudio::class);
        $series = $studio->save($this->owner(), 'Série immédiate', [(string) $video->getId()], '', 0, null, $this->settings());
        $episode = $studio->episodes($series)[0];
        $draft = static::getContainer()->get(EpisodePublications::class)->prepareVideo($this->owner(), $episode, $series->getRevision());
        $drafts = static::getContainer()->get(PublicationDrafts::class);
        $drafts->save($this->owner(), [(int) $video->getId()], ['youtube'], ['youtube' => ['videos' => [$video->getId() => ['title' => 'Mon titre', 'description' => 'Ma description #VydFlow', 'privacy' => 'private', 'audience' => 'no', 'synthetic' => false]]]], ['youtube' => $this->identity], 'Prête', $draft->getId(), $draft->getRevision());
        $url = '/studio/brouillons/'.$draft->getId().'/confirmer';
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.batch-total', 'Départ immédiat après confirmation');
        $this->noPosts();
        $form = $crawler->selectButton('Confirmer et publier ↗')->form(['form[confirm]' => '1']);
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/series/videos/'.$series->getId().'#video-episode-'.$episode->getId());
        self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM youtube_publication'));
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/series/videos/'.$series->getId().'#video-episode-'.$episode->getId());
        self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM youtube_publication'));
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM ai_generation'));
    }
}
