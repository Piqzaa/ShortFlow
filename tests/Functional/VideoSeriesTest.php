<?php

namespace App\Tests\Functional;

use App\Entity\AiGeneration;
use App\Entity\AiSeries;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\VideoSeries;
use App\Entity\VideoSeriesEpisode;
use App\Service\PrivateVideoStorage;
use App\Service\VideoSeriesStudio;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class VideoSeriesTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        foreach (['owner', 'other'] as $name) {
            $user = new User('video-series-'.$name.'@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $this->em()->flush();
        $http = new MockHttpClient(static function (): never { self::fail('No external HTTP for video series.'); });
        foreach (['ai.http', 'story.http', 'youtube.http', 'instagram.http', 'tiktok.http'] as $id) {
            static::getContainer()->set($id, $http);
        }
        static::getContainer()->get('cache.rate_limiter')->clear();
        $this->client->loginUser($this->owner);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function video(string $title, ?User $owner = null): VideoAsset
    {
        $video = new VideoAsset($owner ?? $this->owner, $title, bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 100, 5, 96, 160, 'h264');
        $this->em()->persist($video);
        $this->em()->flush();

        return $video;
    }

    public function testChoiceCreationOrderingAndEditingKeepVideosAndNeverGenerate(): void
    {
        $a = $this->video('Premier <script>');
        $b = $this->video('Deuxième');
        $foreign = $this->video('Secret étranger', $this->other);
        $this->client->request('GET', '/studio/series/creer');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/studio/series/nouvelle"]');
        self::assertSelectorExists('a[href="/studio/series/videos/nouvelle"]');
        $crawler = $this->client->request('GET', '/studio/series/videos/nouvelle');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('select[name="ai_series[model]"]');
        self::assertSelectorExists('a[aria-current="page"][href="/studio/series"]');
        self::assertStringNotContainsString('Secret étranger', $this->client->getResponse()->getContent());
        file_put_contents(dirname(__DIR__, 2).'/artifacts/video-series-new.html', $this->client->getResponse()->getContent());
        $values = $crawler->selectButton('Créer ma série →')->form(['video_series[name]' => 'Mes aventures', 'video_series[order]' => $b->getId().','.$a->getId()])->getPhpValues();
        $values['video_series']['videos'] = [(string) $a->getId(), (string) $b->getId()];
        $this->client->request('POST', '/studio/series/videos/nouvelle', $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Mes aventures');
        self::assertSame(['Deuxième', 'Premier <script>'], $this->client->getCrawler()->filter('.series-video-episode h2')->each(static fn ($row) => $row->text()));
        file_put_contents(dirname(__DIR__, 2).'/artifacts/video-series-show.html', $this->client->getResponse()->getContent());
        $series = $this->em()->getRepository(VideoSeries::class)->findOneBy(['owner' => $this->owner]);
        self::assertInstanceOf(VideoSeries::class, $series);
        $crawler = $this->client->request('GET', '/studio/series/videos/'.$series->getId().'/modifier');
        self::assertSelectorExists('input[name="video_series[order]"][value="'.$b->getId().','.$a->getId().'"]');
        $values = $crawler->selectButton('Enregistrer les épisodes')->form(['video_series[name]' => 'Série renommée', 'video_series[order]' => (string) $a->getId()])->getPhpValues();
        $values['video_series']['videos'] = [(string) $a->getId()];
        $this->client->request('POST', '/studio/series/videos/'.$series->getId().'/modifier', $values);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(3, $this->em()->getRepository(VideoAsset::class)->count([]));
        self::assertSame(1, $this->em()->getRepository(VideoSeriesEpisode::class)->count(['active' => true]));
        self::assertSame(2, $this->em()->getRepository(VideoSeriesEpisode::class)->count([]));
        $this->client->request('GET', '/studio/series');
        self::assertSelectorTextContains('.series-item', 'Série renommée');
        self::assertSelectorTextContains('.series-item', 'Avec mes vidéos');
        self::assertSelectorExists('a[href="/studio/series/creer"]');
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/series/videos/'.$series->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/series/videos/'.$series->getId().'/modifier');
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidSelectionOrderAndCsrfCannotCreatePartialSeries(): void
    {
        $a = $this->video('Mon clip');
        $foreign = $this->video('Privé', $this->other);
        $crawler = $this->client->request('GET', '/studio/series/videos/nouvelle');
        $values = $crawler->selectButton('Créer ma série →')->form(['video_series[name]' => 'Conserver mon nom', 'video_series[videos]' => [(string) $a->getId()]])->getPhpValues();
        foreach (['foreign', 'order', 'csrf', 'empty'] as $invalid) {
            $submitted = $values;
            if ('foreign' === $invalid) {
                $submitted['video_series']['videos'] = [(string) $foreign->getId()];
            }
            if ('order' === $invalid) {
                $submitted['video_series']['order'] = $a->getId().','.$a->getId();
            }
            if ('csrf' === $invalid) {
                $submitted['video_series']['_token'] = 'bad';
            }
            if ('empty' === $invalid) {
                $submitted['video_series']['videos'] = [];
            }
            $this->client->request('POST', '/studio/series/videos/nouvelle', $submitted);
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorExists('input[name="video_series[name]"][value="Conserver mon nom"]');
            self::assertSame(0, $this->em()->getRepository(VideoSeries::class)->count([]));
            self::assertSame(0, $this->em()->getRepository(VideoSeriesEpisode::class)->count([]));
        }
    }

    public function testStaleEditCannotOverwriteTheOrder(): void
    {
        $a = $this->video('A');
        $b = $this->video('B');
        $studio = static::getContainer()->get(VideoSeriesStudio::class);
        $series = $studio->save($this->owner, 'Série', [(string) $a->getId(), (string) $b->getId()], '', 0);
        $crawler = $this->client->request('GET', '/studio/series/videos/'.$series->getId().'/modifier');
        $values = $crawler->selectButton('Enregistrer les épisodes')->form()->getPhpValues();
        $series->revise('Changement récent');
        $this->em()->flush();
        $this->client->request('POST', '/studio/series/videos/'.$series->getId().'/modifier', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[data-series-videos-target="form"]', 'Cette série a changé');
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('Changement récent', $db->fetchOne('SELECT name FROM video_series WHERE id = ?', [$series->getId()]));
        self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM video_series_episode'));
    }

    public function testImportUsesThePrivateLibraryAndRequiresAnOwnedContext(): void
    {
        $foreign = new VideoSeries($this->other, 'Privée');
        $this->em()->persist($foreign);
        $this->em()->flush();
        $this->client->request('GET', '/studio/bibliotheque/importer?next=series&series='.$foreign->getId());
        self::assertResponseStatusCodeSame(404);
        $crawler = $this->client->request('GET', '/studio/series/videos/nouvelle');
        $values = $crawler->selectButton('Importer et sélectionner')->form(['video_upload[title]' => 'Nouveau clip'])->getPhpValues();
        $path = tempnam(sys_get_temp_dir(), 'series-video-');
        copy(dirname(__DIR__).'/Fixtures/library-valid.mp4', $path);
        $this->files[] = $path;
        $this->client->request('POST', '/studio/bibliotheque/importer?next=series', $values, ['video_upload' => ['file' => new UploadedFile($path, 'clip.mp4', 'video/mp4', null, true)]], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(201);
        $result = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Nouveau clip', $result['video']['title']);
        $video = $this->em()->find(VideoAsset::class, $result['video']['id']);
        self::assertInstanceOf(VideoAsset::class, $video);
        self::assertSame($this->owner->getId(), $video->getOwner()->getId());
        $this->files[] = static::getContainer()->get(PrivateVideoStorage::class)->path($video->getStorageName());
        $crawler = $this->client->request('GET', $result['redirect']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="video_series[videos][]"][value="'.$video->getId().'"][checked]');
        $this->client->submit($crawler->selectButton('Créer ma série →')->form(['video_series[name]' => 'Import réussi']));
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->em()->getRepository(VideoSeriesEpisode::class)->count([]));
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
    }

    public function testMixedPaginationAndVideoDeletionKeepTheSeriesPrivate(): void
    {
        $video = $this->video('Mon clip');
        for ($i = 0; $i < 13; ++$i) {
            $series = new VideoSeries($this->owner, 'Vidéos '.$i);
            $this->em()->persist($series);
        }
        $ai = new AiSeries($this->owner, 'Série IA', 'Mon thème', '', '', ['model' => 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $this->em()->persist($ai);
        $private = new VideoSeries($this->other, 'Secret étranger');
        $this->em()->persist($private);
        $this->em()->persist(new VideoSeriesEpisode($series, $video, 1));
        $this->em()->flush();
        $this->client->request('GET', '/studio/series');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(12, '.series-item');
        self::assertStringNotContainsString('Secret étranger', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/studio/series?page=2');
        self::assertSelectorCount(2, '.series-item');
        self::assertSelectorTextContains('nav[aria-label="Pagination des séries"]', '2 / 2');
        $id = $series->getId();
        $this->em()->remove($this->em()->find(VideoAsset::class, $video->getId()));
        $this->em()->flush();
        $this->em()->clear();
        $this->client->request('GET', '/studio/series/videos/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.series-video-episode');
        self::assertSelectorTextContains('.series-episodes', 'Aucun épisode disponible');
        self::assertSame(14, $this->em()->getRepository(VideoSeries::class)->count([]));
    }
}
