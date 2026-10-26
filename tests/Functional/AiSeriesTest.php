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
        $this->client->submit($crawler->selectButton('Créer ma série →')->form($overrides + ['ai_series[episodeCount]' => 3, 'ai_series[name]' => 'Les aventures de Milo <script>', 'ai_series[theme]' => 'Un chat explore la lune.', 'ai_series[style]' => 'Cinéma doux', 'ai_series[characters]' => 'Milo, chat roux en combinaison.']));
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

    public function testBrowserConnectionIsSuggestedForNewSeriesWithoutSwitchingExistingBilling(): void
    {
        $series = $this->createSeries();
        $this->em()->persist(new \App\Entity\AiCredential($this->em()->find(User::class, $this->owner->getId()), 'synthetic-unread-credential', 'fal-mcp'));
        $this->em()->persist(new \App\Entity\AiCredential($this->em()->find(User::class, $this->other->getId()), 'synthetic-other-key', 'fal'));
        $this->em()->flush();

        $this->client->request('GET', '/studio/series/nouvelle');
        self::assertSelectorExists('select[name="ai_series[accessMode]"] option[value="mcp"][selected]');
        $this->client->request('GET', '/studio/series/'.$series->getId().'/configuration');
        self::assertSelectorExists('select[name="ai_series[accessMode]"] option[value="api"][selected]');
        $this->open($series);
        self::assertSelectorTextContains('.series-recurrence', 'Fal est déjà connecté par navigateur');
        self::assertSelectorTextContains('.series-recurrence', 'Mon compte Fal connecté par navigateur');
        self::assertSame('api', $series->getRecipe()['accessMode']);
        $this->em()->persist(new \App\Entity\AiCredential($this->em()->find(User::class, $this->owner->getId()), 'synthetic-own-key', 'fal'));
        $this->em()->flush();
        $this->client->request('GET', '/studio/series/nouvelle');
        self::assertSelectorExists('select[name="ai_series[accessMode]"] option[value="api"][selected]');
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testDeletionRequiresOwnershipConsentAndFreshCsrfAndKeepsVideosAndScheduledDrafts(): void
    {
        $series = $this->createSeries();
        $id = $series->getId();
        $episode = $this->episodes($series)[0];
        $owner = $this->em()->find(User::class, $this->owner->getId());
        $video = new \App\Entity\VideoAsset($owner, 'Keep this video', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 100, 5, 720, 1280, 'h264');
        $this->em()->persist($video);
        $episode->flow('ready');
        $episode->approve($video);
        $this->em()->flush();
        $draft = static::getContainer()->get(\App\Service\EpisodePublications::class)->prepare($owner, $episode, $episode->getRevision());
        $draft->plan(new \DateTimeImmutable('+1 day'), 'Europe/Paris', (new \DateTimeImmutable('+1 day'))->format('Y-m-d\\TH:i'), new \DateTimeImmutable());
        $draft->arm(new \DateTimeImmutable());
        $this->em()->flush();
        $videoId = $video->getId();
        $draftId = $draft->getId();
        // Editorial scopes identify publication drafts, not episodes with the same numeric id.
        $editorial = new \App\Entity\StoryProposal($owner, $episode->getId(), 'unrelated-editorial', [], 'editorial');
        $this->em()->persist($editorial);
        $this->em()->flush();
        $url = '/studio/series/'.$id.'/supprimer';
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $values = $crawler->selectButton('Supprimer la série et ses épisodes')->form(['form[confirm]' => '1'])->getPhpValues();
        $bad = $values;
        $bad['form']['_token'] = 'invalid';
        $this->client->request('POST', $url, $bad);
        self::assertResponseStatusCodeSame(422);
        $bad = $values;
        unset($bad['form']['confirm']);
        $this->client->request('POST', $url, $bad);
        self::assertResponseStatusCodeSame(422);
        $bad = $values;
        $bad['form']['revision'] = '0';
        $this->client->request('POST', $url, $bad);
        self::assertResponseStatusCodeSame(422);
        $this->client->loginUser($this->other);
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Supprimer la série et ses épisodes')->form(['form[confirm]' => '1']));
        self::assertResponseRedirects('/studio/series', 303);
        $this->em()->clear();
        self::assertNull($this->em()->find(AiSeries::class, $id));
        self::assertNotNull($this->em()->find(\App\Entity\VideoAsset::class, $videoId));
        $kept = $this->em()->find(\App\Entity\PublicationDraft::class, $draftId);
        self::assertSame('scheduled', $kept->getStatus());
        self::assertSame([$videoId], $kept->videoIds());
        self::assertNull($kept->getEpisode());
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testDeletionCannotOrphanAnActiveGenerationOrLocalWriting(): void
    {
        $series = $this->createSeries();
        $job = $this->legacyPrepare($series);
        $job->progress('queued');
        $this->em()->flush();
        $url = '/studio/series/'.$series->getId().'/supprimer';
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Supprimer la série et ses épisodes')->form(['form[confirm]' => '1']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-errors, .account-form', 'encore en rédaction');
        self::assertNotNull($this->em()->find(AiSeries::class, $series->getId()));
        $job = $this->em()->find(AiGeneration::class, $job->getId());
        $job->progress('prepared');
        $writing = new \App\Entity\StoryProposal($this->em()->find(User::class, $this->owner->getId()), $this->episodes($series)[0]->getId(), 'fingerprint', []);
        $this->em()->persist($writing);
        $this->em()->flush();
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Supprimer la série et ses épisodes')->form(['form[confirm]' => '1']));
        self::assertResponseStatusCodeSame(422);
        $writing = $this->em()->find(\App\Entity\StoryProposal::class, $writing->getId());
        $writing->fail('Synthetic failure');
        $recurrence = new \App\Entity\SeriesRecurrence($this->em()->find(AiSeries::class, $series->getId()));
        $recurrence->activate([], new \DateTimeImmutable('+1 day'), new \DateTimeImmutable());
        $this->em()->persist($recurrence);
        $this->em()->flush();
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Supprimer la série et ses épisodes')->form(['form[confirm]' => '1']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.account-form', 'Mets la récurrence en pause');
    }

    public function testReferenceChoiceIsExplicitEvenWithATextModel(): void
    {
        $this->client->request('GET', '/studio/series/nouvelle');
        self::assertSelectorExists('select[name="ai_series[source]"] option[value="image"]');
        self::assertSelectorExists('[data-series-settings-target="images"] input[type="file"]');
        self::assertSelectorTextContains('label[for="ai_series_characters"]', 'Description textuelle');
    }

    public function testSubjectReferenceIsDistinctAndRemovingImagesKeepsPreparedSnapshots(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'series-subject-');
        copy(dirname(__DIR__).'/Fixtures/reference.jpg', $file);
        $this->files[] = $file;
        $series = $this->createSeries(['ai_series[model]' => 'vidu-q1-subject', 'ai_series[image]' => new UploadedFile($file, 'character.jpg', 'image/jpeg', null, true)]);
        $image = $series->getRecipe()['reference'];
        $this->files[] = static::getContainer()->get(AiReferenceStorage::class)->path($this->owner, $image);
        $recipe = $series->getRecipe();
        $recipe['subjects'] = [$image];
        $recipe['subjectDetails'] = [['reference' => $image, 'name' => 'Milo', 'role' => 'character', 'presence' => 'enters', 'views' => []]];
        $series->configure($series->getName(), $series->getTheme(), $series->getStyle(), $series->getCharacters(), $recipe);
        $this->em()->flush();
        $job = $this->legacyPrepare($series);
        self::assertSame($image, $job->getReferenceImage());
        self::assertNull($job->getEndReferenceImage());
        self::assertSame('vidu-q1-subject', $job->getModel());
        try {
            static::getContainer()->get(\App\Service\AiEpisodeFlow::class)->prepare($this->em()->find(User::class, $this->owner->getId()), ['model' => 'vidu-q1-subject', 'source' => 'image', 'target' => 10, 'shots' => [['prompt' => 'The character waves.', 'seconds' => 5], ['prompt' => 'The character smiles.', 'seconds' => 5]], 'ratio' => '9:16', 'reference' => $image, 'continuity' => 'linked']);
            self::fail('Une référence de personnage ne doit pas être remplacée par la dernière image du plan précédent.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('raccords', $error->getMessage());
        }
        $url = '/studio/series/'.$series->getId().'/configuration';
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Enregistrer la configuration')->form(['ai_series[removeSubjects]' => [$image]]));
        self::assertResponseStatusCodeSame(303);
        $series = $this->em()->find(AiSeries::class, $series->getId());
        self::assertSame($image, $series->getRecipe()['reference']);
        self::assertSame([], $series->getRecipe()['subjects']);
        self::assertSame('Milo', $series->getRecipe()['subjectDetails'][0]['name']);
        $crawler = $this->client->request('GET', $url);
        self::assertSelectorExists('select[name="ai_series[source]"] option[value="subject"][selected]');
        self::assertSelectorExists('[data-series-reference-original-value] img');
        $this->client->submit($crawler->selectButton('Enregistrer la configuration')->form(['ai_series[removeImage]' => '1']));
        self::assertResponseStatusCodeSame(303);
        $cleared = $this->em()->find(AiSeries::class, $series->getId());
        self::assertNull($cleared->getRecipe()['reference']);
        try {
            $this->legacyPrepare($cleared, 1);
            self::fail('Une image retirée doit bloquer une nouvelle préparation image.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('Ajoute une image', $error->getMessage());
        }
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Enregistrer la configuration')->form(['ai_series[removeImage]' => '1', 'ai_series[removeEndImage]' => '1', 'ai_series[model]' => 'kling-25']));
        self::assertResponseStatusCodeSame(303);
        $series = $this->em()->find(AiSeries::class, $series->getId());
        self::assertNull($series->getRecipe()['reference']);
        $job = $this->em()->find(AiGeneration::class, $job->getId());
        self::assertSame($image, $job->getReferenceImage());
        self::assertFileExists(static::getContainer()->get(AiReferenceStorage::class)->path($this->owner, $image));
    }

    public function testCreatesThreeDurablePrivateEpisodesWithoutGenerationOrMessages(): void
    {
        $this->client->request('GET', '/studio/series');
        self::assertSelectorTextContains('h2', 'Pose le décor');
        $series = $this->createSeries();
        $episodes = $this->episodes($series);
        self::assertCount(3, $episodes);
        self::assertSame([1, 2, 3], array_map(static fn (AiEpisode $e): int => $e->getNumber(), $episodes));
        self::assertSame('', $episodes[0]->getPrompt());
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
        self::assertSelectorTextContains('.series-item small', '30 s par épisode');
    }

    public function testChosenEpisodeCountAndAddingEpisodesArePrivateAndIdempotent(): void
    {
        $series = $this->createSeries(['ai_series[episodeCount]' => 0]);
        self::assertCount(0, $this->episodes($series));
        $crawler = $this->open($series);
        self::assertSelectorTextContains('.series-episodes', 'Ajoute un épisode');
        $form = $crawler->selectButton('Ajouter les épisodes →')->form(['ai_episode_batch[count]' => 2]);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertSame([1, 2], array_map(static fn (AiEpisode $e): int => $e->getNumber(), $this->episodes($series)));
        foreach ($this->episodes($series) as $episode) {
            self::assertSame('', $episode->getPrompt());
        }
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertCount(2, $this->episodes($series));
        $crawler = $this->open($series);
        $values = $crawler->selectButton('Ajouter les épisodes →')->form()->getPhpValues();
        $bad = $values;
        $bad['ai_episode_batch']['_token'] = 'bad';
        $this->client->request('POST', '/studio/series/'.$series->getId().'/episodes', $bad);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->other);
        $this->client->request('POST', '/studio/series/'.$series->getId().'/episodes', $values);
        self::assertResponseStatusCodeSame(404);
        self::assertCount(2, $this->episodes($series));
    }

    /** Legacy preparation remains callable by existing tabs, but is no longer a second visible journey. */
    private function legacyPrepare(AiSeries $series, int $number = 0, ?string $prompt = null, bool $prepare = true): ?AiGeneration
    {
        $episode = $this->episodes($series)[$number];
        $owner = $this->em()->find(User::class, $this->owner->getId());

        return static::getContainer()->get(\App\Service\AiSeriesStudio::class)->saveEpisode($owner, $episode, ['title' => $episode->getTitle(), 'prompt' => $prompt ?? ($episode->getPrompt() ?: $series->initialPrompt()), 'revision' => $episode->getRevision(), 'seriesRevision' => $this->em()->find(AiSeries::class, $series->getId())->getRevision()], $prepare);
    }

    public function testSavePrepareReviseAndRepeatedPreparationReuseTheSameGeneration(): void
    {
        $series = $this->createSeries();
        $this->legacyPrepare($series, prompt: 'Milo steps onto the moon.', prepare: false);
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        $job = $this->legacyPrepare($series);
        self::assertSame('prepared', $job->getStatus());
        self::assertSame(35, $job->getEstimatedCents());
        $episode = $this->episodes($series)[0];
        $this->open($series);
        self::assertSelectorNotExists('#episode-'.$episode->getId().' form');
        self::assertSelectorExists('a[href="/studio/series/episodes/'.$episode->getId().'"]');
        $this->legacyPrepare($series, prompt: 'Milo waves at Earth.', prepare: false);
        $job = $this->em()->find(AiGeneration::class, $job->getId());
        self::assertSame('Milo waves at Earth.', $job->getPrompt());
        self::assertSame(1, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame($job->getId(), $this->legacyPrepare($series)->getId());
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testOwnershipCsrfAndUnknownEpisodesCannotBeBypassed(): void
    {
        $series = $this->createSeries();
        $episode = $this->episodes($series)[0];
        $url = '/studio/series/episodes/'.$episode->getId();
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Voir le récapitulatif →')->form()->getPhpValues();
        $values['ai_idea']['_token'] = 'invalid';
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        $this->client->loginUser($this->other);
        foreach ([$url, '/studio/series/'.$series->getId(), '/studio/series/'.$series->getId().'/configuration'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('POST', $url, $values);
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
            $this->client->submit($crawler->selectButton('Créer ma série →')->form($values));
            self::assertResponseStatusCodeSame(422);
            self::assertSame(0, $this->em()->getRepository(AiSeries::class)->count([]));
            self::assertSame(0, $this->em()->getRepository(AiEpisode::class)->count([]));
        }
    }

    public function testConfigurationChangesKeepPreparedSnapshotAndDetectStaleForms(): void
    {
        $series = $this->createSeries();
        $episode = $this->episodes($series)[0];
        $crawler = $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        $stale = $crawler->selectButton('Voir le récapitulatif →')->form();
        $job = $this->legacyPrepare($series);
        $crawler = $this->client->request('GET', '/studio/series/'.$series->getId().'/configuration');
        $this->client->submit($crawler->selectButton('Enregistrer la configuration')->form(['ai_series[theme]' => 'Un voyage sur Mars', 'ai_series[seconds]' => 10, 'ai_series[totalSeconds]' => 45]));
        self::assertResponseStatusCodeSame(303);
        $series = $this->em()->find(AiSeries::class, $series->getId());
        self::assertSame(5, $job->getSeconds());
        self::assertSame(10, $series->getRecipe()['seconds']);
        self::assertSame(45, $series->getRecipe()['totalSeconds']);
        $stale['ai_idea[shots][0][prompt]'] = 'Scene first';
        $stale['ai_idea[shots][1][prompt]'] = 'Scene second';
        $stale['ai_idea[shots][2][prompt]'] = 'Scene third';
        $this->client->submit($stale);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'changé');
        $next = $this->episodes($series)[1];
        $this->client->request('GET', '/studio/series/episodes/'.$next->getId());
        self::assertSelectorExists('input[name="ai_idea[target]"][value="45"]');
        $nextJob = $this->legacyPrepare($series, 1);
        self::assertSame(10, $nextJob->getSeconds());
        self::assertSame(70, $nextJob->getEstimatedCents());
    }

    public function testListReflectsRealStatusAndConfirmedPromptCannotBeChanged(): void
    {
        $series = $this->createSeries();
        $job = $this->legacyPrepare($series);
        $episode = $this->episodes($series)[0];
        $job->progress('processing');
        $this->em()->flush();
        $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        self::assertResponseStatusCodeSame(404);
        $this->open($series);
        self::assertSelectorTextContains('#episode-'.$episode->getId().' .series-status', 'Génération en cours');
        self::assertSelectorNotExists('#episode-'.$episode->getId().' textarea');
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
        $job = $this->legacyPrepare($series);
        self::assertSame($image, $job->getReferenceImage());
        self::assertSame('mcp', $job->getAccessMode());
        $episode = $this->episodes($series)[0];
        $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        self::assertSelectorExists('input[name="ai_idea[reference]"][value="'.$image.'"]');
        self::assertSelectorExists('select[name="ai_idea[model]"]');
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/creation/images/'.$image);
        self::assertResponseStatusCodeSame(404);
    }

    public function testOldCostConfirmationCannotGenerateAnEditedPrompt(): void
    {
        $series = $this->createSeries();
        static::getContainer()->get(AiKeys::class)->save($this->em()->find(User::class, $this->owner->getId()), 'synthetic-series-key-never-real-1234567890');
        $this->em()->flush();
        $job = $this->legacyPrepare($series);
        $jobUrl = '/studio/ia/generations/'.$job->getId();
        $crawler = $this->client->request('GET', $jobUrl);
        $confirmation = $crawler->selectButton('Confirmer et générer →')->form(['confirm' => '1']);
        $this->legacyPrepare($series, prompt: 'A new scene with Milo.', prepare: false);
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
        $crawler = $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[prompt]' => str_repeat('🌙', 600)]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'octets');
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
    }
}
