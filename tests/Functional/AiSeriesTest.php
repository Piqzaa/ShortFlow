<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\AiSeries;
use App\Entity\User;
use App\Service\AiKeys;
use App\Service\AiReferenceStorage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class AiSeriesTest extends WebTestCase
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
            $user = new User('series-'.$name.'@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $this->em()->flush();
        static::getContainer()->set('ai.http', new MockHttpClient(static function (): never {
            self::fail('La préparation d’une série ne doit appeler aucun fournisseur.');
        }));
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

    /** @param array<string,mixed> $overrides */
    private function createSeries(array $overrides = []): AiSeries
    {
        $crawler = $this->client->request('GET', '/studio/series/nouvelle');
        $this->client->submit($crawler->selectButton('Créer la série et ses 3 épisodes →')->form($overrides + ['ai_series[name]' => 'Les aventures de Milo <script>', 'ai_series[theme]' => 'Un chat explore la lune.', 'ai_series[style]' => 'Cinéma doux', 'ai_series[characters]' => 'Milo, chat roux en combinaison.']));
        self::assertResponseStatusCodeSame(303);

        return $this->em()->getRepository(AiSeries::class)->findOneBy(['owner' => $this->owner], ['id' => 'DESC']);
    }

    /** @return list<AiEpisode> */
    private function episodes(AiSeries $series): array
    {
        return $this->em()->getRepository(AiEpisode::class)->findBy(['series' => $series], ['number' => 'ASC']);
    }

    private function open(AiSeries $series): Crawler
    {
        return $this->client->request('GET', '/studio/series/'.$series->getId());
    }

    public function testCreatesThreeDurablePrivateEpisodesWithoutGenerationOrMessages(): void
    {
        $this->client->request('GET', '/studio/series');
        self::assertSelectorTextContains('h2', 'Pose le décor');
        $series = $this->createSeries();
        $episodes = $this->episodes($series);
        self::assertCount(3, $episodes);
        self::assertSame([1, 2, 3], array_map(static fn (AiEpisode $e): int => $e->getNumber(), $episodes));
        self::assertStringContainsString('Milo, chat roux', $episodes[0]->getPrompt());
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->open($series);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSelectorCount(3, '.series-episode');
        self::assertSelectorCount(3, '.series-status');
        self::assertSelectorExists('a[aria-current="page"][href="/studio/series"]');
        self::assertStringContainsString('&lt;script&gt;', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/studio/series');
        self::assertSelectorTextContains('.series-item', 'Les aventures de Milo');
    }

    public function testSavePrepareReviseAndRepeatedPreparationReuseTheSameGeneration(): void
    {
        $series = $this->createSeries();
        $episode = $this->episodes($series)[0];
        $root = 'episode_'.$episode->getId();
        $crawler = $this->open($series);
        $this->client->submit($crawler->filter('#episode-'.$episode->getId())->selectButton('Enregistrer le prompt')->form([$root.'[title]' => 'Premier pas', $root.'[prompt]' => 'Milo steps onto the moon.']));
        self::assertResponseStatusCodeSame(303);
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        $crawler = $this->open($series);
        $form = $crawler->filter('#episode-'.$episode->getId())->selectButton('Vérifier l’estimation →')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertMatchesRegularExpression('~^/studio/ia/generations/\d+$~', $this->client->getResponse()->headers->get('Location'));
        $episode = $this->em()->find(AiEpisode::class, $episode->getId());
        $job = $episode->getGeneration();
        self::assertNotNull($job);
        self::assertSame('prepared', $job->getStatus());
        self::assertSame(35, $job->getEstimatedCents());
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/ia/generations/'.$job->getId(), 303);
        self::assertSame(1, $this->em()->getRepository(AiGeneration::class)->count([]));
        $this->client->followRedirect();
        self::assertSelectorExists('a[href="/studio/series/'.$series->getId().'#episode-'.$episode->getId().'"]');
        $crawler = $this->open($series);
        $this->client->submit($crawler->filter('#episode-'.$episode->getId())->selectButton('Enregistrer le prompt')->form([$root.'[prompt]' => 'Milo waves at Earth.']));
        self::assertResponseStatusCodeSame(303);
        $job = $this->em()->find(AiGeneration::class, $job->getId());
        self::assertSame('Milo waves at Earth.', $job->getPrompt());
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testOwnershipCsrfAndUnknownEpisodesCannotBeBypassed(): void
    {
        $series = $this->createSeries();
        $crawler = $this->open($series);
        $episode = $this->episodes($series)[0];
        $root = 'episode_'.$episode->getId();
        $form = $crawler->filter('#episode-'.$episode->getId())->selectButton('Vérifier l’estimation →')->form();
        $values = $form->getPhpValues();
        $values[$root]['_token'] = 'invalid';
        $this->client->request('POST', '/studio/series/'.$series->getId(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        $this->client->loginUser($this->other);
        foreach (['/studio/series/'.$series->getId(), '/studio/series/'.$series->getId().'/configuration'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('POST', '/studio/series/'.$series->getId(), $form->getPhpValues());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/series');
        self::assertSelectorNotExists('.series-item');
    }

    public function testModelValidationAndMissingImagesRejectCreationWithoutPartialEpisodes(): void
    {
        foreach ([['model' => 'kling-25', 'seconds' => 1, 'ratio' => '9:16', 'accessMode' => 'api'], ['model' => 'kling-25-image', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api'], ['model' => 'wan-25-replicate', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'mcp']] as $settings) {
            $crawler = $this->client->request('GET', '/studio/series/nouvelle');
            $values = ['ai_series[name]' => 'Essai', 'ai_series[theme]' => 'Un paysage'];
            foreach ($settings as $key => $value) {
                $values['ai_series['.$key.']'] = $value;
            }
            $this->client->submit($crawler->selectButton('Créer la série et ses 3 épisodes →')->form($values));
            self::assertResponseStatusCodeSame(422);
            self::assertSame(0, $this->em()->getRepository(AiSeries::class)->count([]));
            self::assertSame(0, $this->em()->getRepository(AiEpisode::class)->count([]));
        }
    }

    public function testConfigurationChangesKeepPreparedSnapshotAndDetectStaleForms(): void
    {
        $series = $this->createSeries();
        $episode = $this->episodes($series)[0];
        $crawler = $this->open($series);
        $stale = $crawler->filter('#episode-'.$episode->getId())->selectButton('Enregistrer le prompt')->form();
        $this->client->submit($crawler->filter('#episode-'.$episode->getId())->selectButton('Vérifier l’estimation →')->form());
        $episode = $this->em()->find(AiEpisode::class, $episode->getId());
        $job = $episode->getGeneration();
        $crawler = $this->client->request('GET', '/studio/series/'.$series->getId().'/configuration');
        $this->client->submit($crawler->selectButton('Enregistrer la configuration')->form(['ai_series[theme]' => 'Un voyage sur Mars', 'ai_series[seconds]' => 10]));
        self::assertResponseStatusCodeSame(303);
        $series = $this->em()->find(AiSeries::class, $series->getId());
        self::assertSame(5, $job->getSeconds());
        self::assertSame(10, $series->getRecipe()['seconds']);
        self::assertStringContainsString('la lune', $episode->getPrompt());
        $this->client->submit($stale);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#episode-'.$episode->getId().' form', 'autre onglet');
        $next = $this->episodes($series)[1];
        $crawler = $this->open($series);
        $this->client->submit($crawler->filter('#episode-'.$next->getId())->selectButton('Vérifier l’estimation →')->form());
        $next = $this->em()->find(AiEpisode::class, $next->getId());
        self::assertSame(10, $next->getGeneration()->getSeconds());
        self::assertSame(70, $next->getGeneration()->getEstimatedCents());
    }

    public function testListReflectsRealStatusAndConfirmedPromptCannotBeChanged(): void
    {
        $series = $this->createSeries();
        $episode = $this->episodes($series)[0];
        $crawler = $this->open($series);
        $this->client->submit($crawler->filter('#episode-'.$episode->getId())->selectButton('Vérifier l’estimation →')->form());
        $crawler = $this->open($series);
        $stale = $crawler->filter('#episode-'.$episode->getId())->selectButton('Enregistrer le prompt')->form(['episode_'.$episode->getId().'[prompt]' => 'Changed while submitting']);
        $episode = $this->em()->find(AiEpisode::class, $episode->getId());
        $episode->getGeneration()->progress('processing');
        $this->em()->flush();
        $this->client->submit($stale);
        self::assertResponseStatusCodeSame(403);
        $this->open($series);
        self::assertSelectorTextContains('#episode-'.$episode->getId().' .series-status', 'Génération en cours');
        self::assertSelectorNotExists('#episode-'.$episode->getId().' textarea');
        self::assertStringNotContainsString('Changed while submitting', $episode->getPrompt());
    }

    public function testImageRecipeReusesPrivateStorageAndCopiesReferencesToPreparation(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'series-image-');
        $this->files[] = $file;
        (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=purple:s=360x640', '-frames:v', '1', '-c:v', 'png', '-f', 'image2', $file]))->mustRun();
        $series = $this->createSeries(['ai_series[model]' => 'kling-25-image', 'ai_series[image]' => new UploadedFile($file, 'milo.png', 'image/png', null, true), 'ai_series[accessMode]' => 'mcp']);
        $image = $series->getRecipe()['reference'];
        self::assertNotNull($image);
        $this->files[] = static::getContainer()->get(AiReferenceStorage::class)->path($this->owner, $image);
        $crawler = $this->open($series);
        $episode = $this->episodes($series)[0];
        $this->client->submit($crawler->filter('#episode-'.$episode->getId())->selectButton('Vérifier l’estimation →')->form());
        self::assertResponseStatusCodeSame(303);
        $episode = $this->em()->find(AiEpisode::class, $episode->getId());
        self::assertSame($image, $episode->getGeneration()->getReferenceImage());
        self::assertSame('mcp', $episode->getGeneration()->getAccessMode());
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/creation/images/'.$image);
        self::assertResponseStatusCodeSame(404);
    }

    public function testOldCostConfirmationCannotGenerateAnEditedPrompt(): void
    {
        $series = $this->createSeries();
        static::getContainer()->get(AiKeys::class)->save($this->em()->find(User::class, $this->owner->getId()), 'synthetic-series-key-never-real-1234567890');
        $this->em()->flush();
        $episode = $this->episodes($series)[0];
        $crawler = $this->open($series);
        $this->client->submit($crawler->filter('#episode-'.$episode->getId())->selectButton('Vérifier l’estimation →')->form());
        $jobUrl = $this->client->getResponse()->headers->get('Location');
        $crawler = $this->client->followRedirect();
        $confirmation = $crawler->selectButton('Confirmer et générer →')->form(['confirm' => '1']);
        $crawler = $this->open($series);
        $this->client->submit($crawler->filter('#episode-'.$episode->getId())->selectButton('Enregistrer le prompt')->form(['episode_'.$episode->getId().'[prompt]' => 'A new scene with Milo.']));
        $this->client->submit($confirmation);
        self::assertResponseRedirects($jobUrl, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.account-notice.error', 'prompt a changé');
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testProviderByteLimitIsCheckedBeforePreparingAnEpisode(): void
    {
        $series = $this->createSeries(['ai_series[model]' => 'pixverse-6']);
        $episode = $this->episodes($series)[0];
        $crawler = $this->open($series);
        $this->client->submit($crawler->filter('#episode-'.$episode->getId())->selectButton('Vérifier l’estimation →')->form(['episode_'.$episode->getId().'[prompt]' => str_repeat('🌙', 600)]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#episode-'.$episode->getId().' form', 'octets');
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
    }
}
