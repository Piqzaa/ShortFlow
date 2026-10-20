<?php

namespace App\Tests\Functional;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\AiCatalog;
use App\Service\AiGenerations;
use App\Service\AiKeys;
use App\Service\PrivateVideoStorage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AiStudioTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    private int $submissions = 0;
    private ?\Closure $httpScenario = null;
    private bool $timeout = false;
    private bool $badUrl = false;
    private bool $badFile = false;
    private bool $waiting = false;
    /** @var list<string> */ private array $files = [];
    private const KEY = 'synthetic-fal-key-never-real-1234567890';

    protected function setUp(): void
    {
        $_ENV['AI_GENERATION_ENABLED'] = '1';
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        foreach (['owner', 'other'] as $name) {
            $user = new User($name.'-ai@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $this->em()->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
        static::getContainer()->set('ai.http', new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            if ($this->httpScenario) {
                return ($this->httpScenario)($method, $url, $options);
            }
            if ('POST' === $method) {
                ++$this->submissions;
                self::assertStringStartsWith('https://queue.fal.run/fal-ai/', $url);
                self::assertContains('Authorization: Key '.self::KEY, $options['headers']);
                $body = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
                self::assertSame('9:16', $body['aspect_ratio']);
                self::assertSame('5', $body['duration']);
                self::assertTrue($body['enable_safety_checker']);
                if (str_contains($url, 'wan-25')) {
                    self::assertSame('720p', $body['resolution']);
                    self::assertFalse($body['enable_prompt_expansion']);
                }
                if ($this->timeout) {
                    throw new \RuntimeException('Synthetic network timeout');
                }

                $application = str_contains($url, 'wan-25') ? 'wan-25-preview' : 'kling-video';

                return new MockResponse(json_encode(['status_url' => 'https://queue.fal.run/fal-ai/'.$application.'/requests/synthetic-id/status', 'response_url' => 'https://queue.fal.run/fal-ai/'.$application.'/requests/synthetic-id'], JSON_THROW_ON_ERROR));
            }
            if (str_ends_with($url, '/status')) {
                return new MockResponse($this->waiting ? '{"status":"IN_PROGRESS"}' : '{"status":"COMPLETED"}');
            }
            if (str_contains($url, '/requests/')) {
                return new MockResponse(json_encode(['video' => ['url' => $this->badUrl ? 'http://127.0.0.1/private' : 'https://v3.fal.media/files/synthetic.mp4']], JSON_THROW_ON_ERROR));
            }
            self::assertSame('https://v3.fal.media/files/synthetic.mp4', $url);
            self::assertNotContains('Authorization: Key '.self::KEY, $options['headers']);

            return new MockResponse($this->badFile ? 'Not a video' : file_get_contents(dirname(__DIR__).'/Fixtures/library-valid.mp4'));
        }));
        $this->client->loginUser($this->owner);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function service(): AiGenerations
    {
        return static::getContainer()->get(AiGenerations::class);
    }

    private function job(string $model = 'kling-25'): AiGeneration
    {
        $job = new AiGeneration($this->owner, 'Création privée', 'Un oiseau au soleil <script>', $model, 5, '9:16', 'wan-25' === $model ? 50 : 35);
        $this->em()->persist($job);
        $this->em()->flush();

        return $job;
    }

    private function key(): void
    {
        $this->owner = $this->em()->find(User::class, $this->owner->getId());
        static::getContainer()->get(AiKeys::class)->save($this->owner, self::KEY);
    }

    private function reload(int $id): AiGeneration
    {
        $job = $this->em()->find(AiGeneration::class, $id);
        $this->em()->refresh($job);

        return $job;
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $_ENV['AI_GENERATION_ENABLED'] = '1';
        parent::tearDown();
    }

    public function testCatalogShowsResolutionAndMissingAccessAndConnectionPreservesAnIncompleteIdea(): void
    {
        $this->key();
        $crawler = $this->client->request('GET', '/studio/creation');
        self::assertSelectorTextContains('option[value="kling-3"]', '1080p');
        self::assertSelectorTextContains('#ai_idea_model option[value="ltx-23-fast-4k"]', 'à connecter');
        self::assertSelectorExists('article.is-disconnected[data-group-id="ltx-23-fast-720"] option[value="ltx-23-fast-4k"]');
        self::assertSelectorNotExists('article.is-disconnected[data-model-id="kling-3"]');
        $form = $crawler->selectButton('Ajouter ma clé en gardant l’idée')->form(['ai_idea[model]' => 'ltx-23-fast-4k', 'ai_idea[prompt]' => '', 'ai_idea[seconds]' => '', 'ai_idea[title]' => 'Mon futur plan']);
        $values = $form->getPhpValues();
        unset($values['ai_idea']['_token']);
        $this->client->request('POST', '/studio/creation', $values);
        self::assertResponseStatusCodeSame(422);
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/connexions/ia?next=idea&provider=ltx', 303);
        $this->client->request('GET', '/studio/creation');
        self::assertSelectorExists('input[name="ai_idea[title]"][value="Mon futur plan"]');
        self::assertSelectorExists('option[value="ltx-23-fast-4k"][selected]');
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->submissions);
    }

    public function testKeyIsEncryptedNeverRedisplayedAndRemovalIsOwnerScoped(): void
    {
        $crawler = $this->client->request('GET', '/studio/connexions/ia');
        $form = $crawler->selectButton('Enregistrer ma clé')->form(['apiKey' => self::KEY]);
        $data = $form->getPhpValues();
        unset($data['_token']);
        $this->client->request('POST', '/studio/connexions/ia', $data);
        self::assertResponseStatusCodeSame(403);
        self::assertStringNotContainsString(self::KEY, $this->client->getRequest()->getContent());
        self::assertFalse($this->client->getRequest()->request->has('apiKey'));
        self::assertNull(static::getContainer()->get(AiKeys::class)->connection($this->owner));
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/connexions/ia', 303);
        $crawler = $this->client->followRedirect();
        self::assertStringNotContainsString(self::KEY, $this->client->getResponse()->getContent());
        $keys = static::getContainer()->get(AiKeys::class);
        self::assertNotSame(self::KEY, $keys->connection($this->owner)->getEncryptedKey());
        self::assertSame(self::KEY, $keys->plain($this->owner));
        $this->client->loginUser($this->other);
        $crawler = $this->client->request('GET', '/studio/connexions/ia');
        self::assertSelectorNotExists('.ai-key-status');
        self::assertNotNull($keys->connection($this->owner));
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/studio/connexions/ia');
        $form = $crawler->selectButton('Retirer ma clé')->form(['confirm' => '1']);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertNull($keys->connection($this->owner));
    }

    public function testModelFavoritesRequireCsrfAndBelongToTheSignedInOwner(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        self::assertSelectorCount(1, 'article[data-group-id="pika-22"]');
        self::assertSelectorCount(2, 'article[data-group-id="pika-22"] .catalog-variant option');
        $form = $crawler->filter('article[data-group-id="pika-22"] .catalog-favorite')->form();
        $data = $form->getPhpValues();
        $this->client->request('POST', '/studio/creation/favoris/pika-22', ['favorite' => '1']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/studio/creation/favoris/pika-22', $data, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertSame(['favorite' => true], json_decode($this->client->getResponse()->getContent(), true));
        $this->client->request('GET', '/studio/creation');
        self::assertSelectorExists('article[data-group-id="pika-22"][data-favorite="true"]');
        self::assertSelectorTextContains('select[name="ai_idea[model]"] option[value="pika-22"]', '★ Pika');
        self::assertSelectorTextContains('select[name="ai_idea[model]"] option[value="pika-22-1080"]', '★ Pika');
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/creation');
        self::assertSelectorExists('article[data-group-id="pika-22"][data-favorite="false"]');
        self::assertSelectorTextNotContains('select[name="ai_idea[model]"] option[value="pika-22"]', '★');
        self::assertSame(0, $this->submissions);
    }

    public function testIdeaSurvivesKeySetupAndInvalidModelCannotBePrepared(): void
    {
        $crawler = $this->client->request('GET', '/studio/ia');
        $form = $crawler->selectButton('Ajouter ma clé en gardant l’idée')->form(['ai_idea[title]' => 'Mon idée', 'ai_idea[prompt]' => 'Un paysage <privé>', 'ai_idea[model]' => 'wan-25']);
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/connexions/ia?next=idea&provider=fal', 303);
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Enregistrer ma clé')->form(['apiKey' => self::KEY]));
        self::assertResponseRedirects('/studio/creation', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('input[name="ai_idea[title]"][value="Mon idée"]');
        self::assertSelectorTextContains('textarea', 'Un paysage <privé>');
        $form = $crawler->selectButton('Voir le récapitulatif →')->form();
        $data = $form->getPhpValues();
        $data['ai_idea']['model'] = 'arbitrary-endpoint';
        $this->client->request('POST', '/studio/ia', $data);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.ai-review', '0,50 $ US');
        self::assertSame(0, $this->submissions);
    }

    public function testConfirmationRequiresCsrfConsentKeyAndIsIdempotent(): void
    {
        $job = $this->job();
        $id = $job->getId();
        $crawler = $this->client->request('GET', '/studio/ia/generations/'.$id);
        $token = $crawler->filter('input[name="_token"]')->last()->attr('value');
        $this->client->request('POST', '/studio/ia/generations/'.$id, ['confirm' => '1']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/studio/ia/generations/'.$id, ['_token' => $token]);
        self::assertSame('prepared', $this->reload($id)->getStatus());
        $this->client->request('POST', '/studio/ia/generations/'.$id, ['_token' => $token, 'confirm' => '1']);
        self::assertSame('prepared', $this->reload($id)->getStatus());
        $this->key();
        for ($i = 0; $i < 2; ++$i) {
            $this->client->request('POST', '/studio/ia/generations/'.$id, ['_token' => $token, 'confirm' => '1']);
        }
        self::assertSame('queued', $this->reload($id)->getStatus());
        self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/ia/generations/'.$id);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/ia');
        self::assertSelectorNotExists('.ai-history-item');
    }

    public function testBothModelsGeneratePrivatelyAndRedeliveryDoesNotDuplicateAssets(): void
    {
        $this->key();
        foreach (['kling-25', 'wan-25'] as $model) {
            $job = $this->job($model);
            $id = $job->getId();
            $this->service()->confirm($this->owner, $id);
            $this->service()->process($id);
            self::assertSame('processing', $this->reload($id)->getStatus());
            $this->service()->process($id);
            self::assertSame('available', $this->reload($id)->getStatus());
            $video = $this->reload($id)->getVideo();
            $this->files[] = static::getContainer()->get(PrivateVideoStorage::class)->path($video->getStorageName());
            self::assertFileExists(end($this->files));
            self::assertSame($this->owner->getId(), $video->getOwner()->getId());
            $this->service()->process($id);
        }
        self::assertSame(2, $this->submissions);
        self::assertSame(2, $this->em()->getRepository(VideoAsset::class)->count([]));
        foreach (['youtube_publication', 'instagram_publication', 'tiktok_publication'] as $table) {
            self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM '.$table));
        }
    }

    public function testAmbiguousSubmissionNeverAutomaticallySubmitsAgain(): void
    {
        $this->key();
        $job = $this->job();
        $id = $job->getId();
        $this->timeout = true;
        $this->service()->confirm($this->owner, $id);
        $this->service()->process($id);
        $this->service()->process($id);
        $this->service()->retry($this->owner, $id);
        $this->service()->process($id);
        self::assertSame('review', $this->reload($id)->getStatus());
        self::assertSame([], $this->reload($id)->getRemote());
        self::assertSame(1, $this->submissions);
    }

    public function testResultDomainIsCheckedBeforeDownloadAndRecoveryDoesNotRegenerate(): void
    {
        $this->key();
        $job = $this->job();
        $id = $job->getId();
        $this->service()->confirm($this->owner, $id);
        $this->service()->process($id);
        $this->badUrl = true;
        $this->service()->process($id);
        self::assertSame('review', $this->reload($id)->getStatus());
        self::assertSame(0, $this->em()->getRepository(VideoAsset::class)->count([]));
        $this->badUrl = false;
        $this->service()->retry($this->owner, $id);
        $this->service()->process($id);
        self::assertSame('available', $this->reload($id)->getStatus());
        $this->files[] = static::getContainer()->get(PrivateVideoStorage::class)->path($this->reload($id)->getVideo()->getStorageName());
        self::assertSame(1, $this->submissions);
    }

    public function testWaitingAndInvalidVideoRemainRecoverableWithoutAnotherBillableRequest(): void
    {
        $this->key();
        $job = $this->job();
        $id = $job->getId();
        $this->service()->confirm($this->owner, $id);
        $this->service()->process($id);
        $this->waiting = true;
        $this->service()->process($id);
        self::assertSame('processing', $this->reload($id)->getStatus());
        $this->waiting = false;
        $this->badFile = true;
        $this->service()->process($id);
        self::assertSame('review', $this->reload($id)->getStatus());
        self::assertSame(0, $this->em()->getRepository(VideoAsset::class)->count([]));
        $this->badFile = false;
        $this->owner = $this->em()->find(User::class, $this->owner->getId());
        $this->service()->retry($this->owner, $id);
        $this->service()->process($id);
        self::assertSame('available', $this->reload($id)->getStatus());
        $this->files[] = static::getContainer()->get(PrivateVideoStorage::class)->path($this->reload($id)->getVideo()->getStorageName());
        self::assertSame(1, $this->submissions);
    }

    public function testQuotaRollbackExposesAnIssueAndCanResumeAfterSpaceIsFreed(): void
    {
        $this->key();
        $job = $this->job();
        $id = $job->getId();
        $full = new VideoAsset($this->owner, 'Synthetic quota', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1073741824, 4, 720, 1280, 'h264');
        $this->em()->persist($full);
        $this->em()->flush();
        $fullId = $full->getId();
        $this->service()->confirm($this->owner, $id);
        $this->service()->process($id);
        $this->service()->process($id);
        self::assertSame('review', $this->reload($id)->getStatus());
        self::assertSame(1, $this->em()->getRepository(VideoAsset::class)->count([]));
        $this->em()->remove($this->em()->find(VideoAsset::class, $fullId));
        $this->em()->flush();
        $this->owner = $this->em()->find(User::class, $this->owner->getId());
        $this->service()->retry($this->owner, $id);
        $this->service()->process($id);
        self::assertSame('available', $this->reload($id)->getStatus());
        $this->files[] = static::getContainer()->get(PrivateVideoStorage::class)->path($this->reload($id)->getVideo()->getStorageName());
        self::assertSame(1, $this->submissions);
    }

    public function testOldPricesAndTooManyActiveJobsBlockBeforeSubmission(): void
    {
        $this->key();
        $stale = $this->job();
        (new \ReflectionProperty(AiGeneration::class, 'priceChecked'))->setValue($stale, '2000-01-01');
        $this->em()->flush();
        try {
            $this->service()->confirm($this->owner, $stale->getId());
            self::fail('Stale price accepted.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('revérifié', $error->getMessage());
        }
        self::assertSame(0, $this->submissions);
    }

    public function testTooManyActiveJobsBlockBeforeSubmission(): void
    {
        $this->key();
        for ($i = 0; $i < 3; ++$i) {
            $job = $this->job();
            $this->service()->confirm($this->owner, $job->getId());
        }
        $extra = $this->job();
        try {
            $this->service()->confirm($this->owner, $extra->getId());
            self::fail('Fourth concurrent job accepted.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('Trois générations', $error->getMessage());
        }
        self::assertSame(0, $this->submissions);
    }

    public function testRemovedKeyBeforeDepartureRequiresConsentAgainAndCanResume(): void
    {
        $this->key();
        $job = $this->job();
        $id = $job->getId();
        $this->service()->confirm($this->owner, $id);
        static::getContainer()->get(AiKeys::class)->remove($this->owner);
        $this->service()->process($id);
        self::assertSame('waiting_key', $this->reload($id)->getStatus());
        self::assertSame(0, $this->submissions);
        $this->key();
        $this->service()->retry($this->owner, $id, true);
        $this->service()->process($id);
        self::assertSame('processing', $this->reload($id)->getStatus());
        self::assertSame(1, $this->submissions);
    }

    public function testKeySetupReturnsToSavedIdeaAndRejectsExternalReturnUrl(): void
    {
        $job = $this->job();
        $id = $job->getId();
        $crawler = $this->client->request('GET', '/studio/connexions/ia?next='.$id);
        self::assertSelectorExists('a[href="/studio/ia/generations/'.$id.'"]');
        $this->client->submit($crawler->selectButton('Enregistrer ma clé')->form(['apiKey' => self::KEY]));
        self::assertResponseRedirects('/studio/ia/generations/'.$id, 303);
        $crawler = $this->client->request('GET', '/studio/connexions/ia?next=https://external.example/');
        $this->client->submit($crawler->selectButton('Remplacer ma clé')->form(['apiKey' => self::KEY]));
        self::assertResponseRedirects('/studio/connexions/ia', 303);
        self::assertSame(0, $this->submissions);
    }

    public function testStudioAndVideosHaveDistinctNavigationAndLegacyLinksStillWork(): void
    {
        $this->client->request('GET', '/studio/creation');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Studio.');
        self::assertSelectorExists('.studio-sidebar nav a[href="/studio/creation"][aria-current="page"]');
        self::assertSelectorExists('.studio-bottom-nav a[href="/studio/creation"][aria-current="page"]');
        self::assertSelectorNotExists('.studio-sidebar nav a[href="/studio"][aria-current="page"]');
        self::assertSelectorExists('.creation-tool-soon[aria-disabled="true"]');
        self::assertSelectorNotExists('input[name="ai_idea[title]"][required]');
        $this->client->request('GET', '/studio');
        self::assertSelectorTextSame('h1', 'Tes vidéos.');
        self::assertSelectorExists('.studio-sidebar nav a[href="/studio"][aria-current="page"]');
        self::assertSelectorNotExists('.studio-sidebar nav a[href="/studio/creation"][aria-current="page"]');
        $this->client->request('GET', '/studio/ia');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Studio.');
        $this->client->request('GET', '/studio/connexions/ia');
        self::assertSelectorExists('.studio-sidebar nav a[href="/studio/connexions"][aria-current="page"]');
        self::assertSelectorNotExists('.studio-sidebar nav a[href="/studio/creation"][aria-current="page"]');
    }

    public function testIdeaCanBePreparedWithoutNamingItAndStillRequiresCostConfirmation(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[prompt]' => "Une scène\n  au soleil <privée>", 'ai_idea[model]' => 'kling-25', 'ai_idea[seconds]' => 10]));
        self::assertResponseStatusCodeSame(303);
        $job = $this->em()->getRepository(AiGeneration::class)->findOneBy(['owner' => $this->owner]);
        self::assertSame('Une scène au soleil <privée>', $job->getTitle());
        self::assertSame(70, $job->getEstimatedCents());
        self::assertSame('prepared', $job->getStatus());
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Une scène au soleil <privée>');
        self::assertStringNotContainsString('<privée>', $this->client->getResponse()->getContent());
        self::assertSame(0, $this->submissions);
    }

    public function testWhitespaceIdeaCannotCreateAnEmptyPreparation(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[prompt]' => " \n ", 'ai_idea[model]' => 'kling-25']));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->submissions);
    }

    public function testRuntimeFlagBlocksEvenForgedConfirmation(): void
    {
        $this->key();
        $job = $this->job();
        (new \ReflectionProperty(AiGenerations::class, 'enabled'))->setValue($this->service(), false);
        try {
            $this->service()->confirm($this->owner, $job->getId());
            self::fail('Disabled generation accepted.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('pas encore activés', $error->getMessage());
        }
        self::assertSame('prepared', $job->getStatus());
        self::assertSame(0, $this->submissions);
    }

    public function testProviderKeysCoexistAndRemovalNeverTouchesTheOtherProvider(): void
    {
        $this->key();
        $keys = static::getContainer()->get(AiKeys::class);
        $crawler = $this->client->request('GET', '/studio/connexions/ia?provider=replicate');
        $this->client->submit($crawler->selectButton('Enregistrer ma clé Replicate')->form(['apiKey' => 'synthetic-replicate-only-key-123456']));
        self::assertResponseStatusCodeSame(303);
        self::assertSame(self::KEY, $keys->plain($this->owner));
        self::assertSame('synthetic-replicate-only-key-123456', $keys->plain($this->owner, 'replicate'));
        self::assertNotSame('synthetic-replicate-only-key-123456', $keys->connection($this->owner, 'replicate')->getEncryptedKey());
        $crawler = $this->client->followRedirect();
        self::assertStringNotContainsString('synthetic-replicate-only-key-123456', $this->client->getResponse()->getContent());
        $this->client->submit($crawler->selectButton('Retirer ma clé Replicate')->form(['confirm' => '1']));
        self::assertNull($keys->connection($this->owner, 'replicate'));
        self::assertSame(self::KEY, $keys->plain($this->owner));
        $this->client->loginUser($this->other);
        $crawler = $this->client->request('GET', '/studio/connexions/ia');
        self::assertSelectorNotExists('.ai-key-status');
        $values = $crawler->selectButton('Enregistrer ma clé Replicate')->form(['apiKey' => 'synthetic-replicate-only-key-123456'])->getPhpValues();
        $values['provider'] = 'https://untrusted.example';
        $this->client->request('POST', '/studio/connexions/ia', $values);
        self::assertResponseStatusCodeSame(303);
        self::assertNull($keys->connection($this->other, 'replicate'));
        self::assertSame(0, $this->submissions);
    }

    public function testReplicateIdeaSurvivesConnectionAndCannotUseTheFalKey(): void
    {
        $this->key();
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($crawler->selectButton('Ajouter ma clé en gardant l’idée')->form(['ai_idea[prompt]' => 'Une forêt sous la pluie', 'ai_idea[model]' => 'wan-25-replicate', 'ai_idea[seconds]' => 10]));
        self::assertResponseRedirects('/studio/connexions/ia?next=idea&provider=replicate', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextSame('.ai-key-panel h2', 'Replicate');
        $this->client->submit($crawler->selectButton('Enregistrer ma clé Replicate')->form(['apiKey' => 'synthetic-replicate-only-key-123456']));
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('option[value="wan-25-replicate"][selected]');
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.studio-heading', 'Wan 2.5 · Replicate · 720p · 10 secondes');
        self::assertSelectorTextContains('.ai-review', '1,00 $ US');
        self::assertSelectorTextContains('.ai-confirm', 'chez Replicate');
        $job = $this->em()->getRepository(AiGeneration::class)->findOneBy(['owner' => $this->owner]);
        $this->owner = $this->em()->find(User::class, $this->owner->getId());
        static::getContainer()->get(AiKeys::class)->remove($this->owner, 'replicate');
        try {
            $this->service()->confirm($this->owner, $job->getId());
            self::fail('Fal key accepted for Replicate.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('Replicate', $error->getMessage());
        }
        self::assertSame('prepared', $job->getStatus());
        self::assertSame(0, $this->submissions);
    }

    public function testModelSpecificDurationAndRatioAreCheckedWithoutJavascript(): void
    {
        foreach ([['kling-25', 15, '9:16'], ['wan-25-replicate', 5, '1:1'], ['kling-3', 3, '9:16']] as [$model, $seconds, $ratio]) {
            $crawler = $this->client->request('GET', '/studio/creation');
            $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[prompt]' => 'Un plan contrôlé', 'ai_idea[model]' => $model, 'ai_idea[seconds]' => $seconds, 'ai_idea[ratio]' => $ratio]));
            self::assertResponseStatusCodeSame('kling-3' === $model ? 303 : 422);
        }
        $job = $this->em()->getRepository(AiGeneration::class)->findOneBy(['owner' => $this->owner]);
        self::assertSame('kling-3', $job->getModel());
        self::assertSame(51, $job->getEstimatedCents());
        self::assertSame(1, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->submissions);
    }

    public function testReplicatePollingAndSafeDownloadResumeNeverResubmit(): void
    {
        static::getContainer()->get(AiKeys::class)->save($this->owner, 'synthetic-replicate-only-key-123456', 'replicate');
        $this->httpScenario = function (string $method, string $url, array $options): MockResponse {
            if ('POST' === $method) {
                ++$this->submissions;
                self::assertSame('https://api.replicate.com/v1/models/wan-video/wan-2.5-t2v/predictions', $url);
                self::assertContains('Authorization: Bearer synthetic-replicate-only-key-123456', $options['headers']);
                $body = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
                self::assertSame(['prompt' => 'Un paysage', 'duration' => 10, 'size' => '720*1280', 'enable_prompt_expansion' => false], $body['input']);

                return new MockResponse('{"id":"synthetic-prediction","urls":{"get":"https://untrusted.example/credential-theft"}}');
            }
            if (str_starts_with($url, 'https://api.replicate.com/')) {
                self::assertSame('https://api.replicate.com/v1/predictions/synthetic-prediction', $url);
                self::assertContains('Authorization: Bearer synthetic-replicate-only-key-123456', $options['headers']);

                return new MockResponse(json_encode(['status' => $this->waiting ? 'processing' : 'succeeded', 'output' => $this->badUrl ? 'https://replicate.delivery.evil.example/result.mp4' : 'https://tjzk.replicate.delivery/synthetic.mp4'], JSON_THROW_ON_ERROR));
            }
            self::assertSame('https://tjzk.replicate.delivery/synthetic.mp4', $url);
            self::assertFalse(isset($options['normalized_headers']['authorization']));
            self::assertSame(0, $options['max_redirects']);

            return new MockResponse(file_get_contents(dirname(__DIR__).'/Fixtures/library-valid.mp4'));
        };
        $job = new AiGeneration($this->owner, 'Replicate', 'Un paysage', 'wan-25-replicate', 10, '9:16', 100);
        $this->em()->persist($job);
        $this->em()->flush();
        $id = $job->getId();
        $this->service()->confirm($this->owner, $id);
        $this->service()->process($id);
        self::assertSame(['prediction_id' => 'synthetic-prediction'], $this->reload($id)->getRemote());
        $this->waiting = true;
        $this->service()->process($id);
        self::assertSame('processing', $this->reload($id)->getStatus());
        $this->waiting = false;
        $this->badUrl = true;
        $this->service()->process($id);
        self::assertSame('review', $this->reload($id)->getStatus());
        $this->badUrl = false;
        $this->service()->retry($this->owner, $id);
        $this->service()->process($id);
        self::assertSame('available', $this->reload($id)->getStatus());
        $this->files[] = static::getContainer()->get(PrivateVideoStorage::class)->path($job->getVideo()->getStorageName());
        $this->service()->process($id);
        self::assertSame(1, $this->submissions);
        self::assertSame(1, $this->em()->getRepository(VideoAsset::class)->count([]));
    }

    public function testKlingThreeUsesFifteenSecondsAndAnExplicitAudioSetting(): void
    {
        $this->key();
        $this->httpScenario = function (string $method, string $url, array $options): MockResponse {
            ++$this->submissions;
            self::assertSame('POST', $method);
            self::assertSame('https://queue.fal.run/fal-ai/kling-video/v3/pro/text-to-video', $url);
            $input = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
            self::assertSame('15', $input['duration']);
            self::assertTrue($input['generate_audio']);
            self::assertSame('customize', $input['shot_type']);

            return new MockResponse('{"status_url":"https://queue.fal.run/fal-ai/kling-video/requests/kling3/status","response_url":"https://queue.fal.run/fal-ai/kling-video/requests/kling3"}');
        };
        $job = new AiGeneration($this->owner, 'Plan long', 'Un paysage', 'kling-3', 15, '9:16', 252);
        $this->em()->persist($job);
        $this->em()->flush();
        $this->service()->confirm($this->owner, $job->getId());
        $this->service()->process($job->getId());
        self::assertSame('processing', $job->getStatus());
        self::assertSame(1, $this->submissions);
    }

    public function testVeoUsesItsOwnSchemaAndTerminalFailureNeverResubmits(): void
    {
        static::getContainer()->get(AiKeys::class)->save($this->owner, 'synthetic-replicate-only-key-123456', 'replicate');
        $this->httpScenario = function (string $method, string $url, array $options): MockResponse {
            if ('POST' === $method) {
                ++$this->submissions;
                self::assertSame('https://api.replicate.com/v1/models/google/veo-3.1-fast/predictions', $url);
                $input = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR)['input'];
                self::assertSame(['prompt' => 'Un paysage', 'duration' => 8, 'aspect_ratio' => '9:16', 'resolution' => '720p', 'generate_audio' => true], $input);

                return new MockResponse('{"id":"synthetic-veo"}');
            }

            return new MockResponse('{"status":"failed","error":"synthetic provider moderation"}');
        };
        $job = new AiGeneration($this->owner, 'Veo', 'Un paysage', 'veo-31-fast-replicate', 8, '9:16', 120);
        $this->em()->persist($job);
        $this->em()->flush();
        $id = $job->getId();
        $this->service()->confirm($this->owner, $id);
        $this->service()->process($id);
        $this->service()->process($id);
        self::assertSame('review', $job->getStatus());
        $this->service()->retry($this->owner, $id);
        $this->service()->process($id);
        self::assertSame('review', $job->getStatus());
        self::assertSame(1, $this->submissions);
        self::assertSame(0, $this->em()->getRepository(VideoAsset::class)->count([]));
    }

    public function testExtendedFalFamiliesReachThePrivateLibraryWithoutDuplicateSubmissions(): void
    {
        $this->key();
        $catalog = new AiCatalog();
        $cases = [
            ['pika-22', 5, '9:16', 20],
            ['vidu-q1', 5, '1:1', 40],
            ['grok-video', 1, '9:16', 7],
            ['hailuo-02', 6, '16:9', 27],
            ['pixverse-56', 8, '9:16', 90],
            ['pixverse-6', 15, '9:16', 90],
            ['seedance-25', 30, '9:16', 1419],
        ];
        $this->httpScenario = function (string $method, string $url, array $options): MockResponse {
            if ('POST' === $method) {
                ++$this->submissions;
                self::assertContains('Authorization: Key '.self::KEY, $options['headers']);
                $path = parse_url($url, PHP_URL_PATH);
                $application = implode('/', array_slice(explode('/', trim($path, '/')), 0, 2));
                $base = 'https://queue.fal.run/'.$application.'/requests/extended';

                return new MockResponse(json_encode(['status_url' => $base.'/status', 'response_url' => $base], JSON_THROW_ON_ERROR));
            }
            if (str_ends_with($url, '/status')) {
                return new MockResponse('{"status":"COMPLETED"}');
            }
            if (str_contains($url, '/requests/')) {
                return new MockResponse('{"video":{"url":"https://v3.fal.media/extended.mp4"}}');
            }
            self::assertSame('https://v3.fal.media/extended.mp4', $url);
            self::assertFalse(isset($options['normalized_headers']['authorization']));

            return new MockResponse(file_get_contents(dirname(__DIR__).'/Fixtures/library-valid.mp4'));
        };
        foreach ($cases as [$model, $seconds, $ratio, $cents]) {
            self::assertSame($cents, $catalog->estimate($model, $seconds));
            $job = new AiGeneration($this->owner, 'Création '.$model, 'Un paysage', $model, $seconds, $ratio, $cents);
            $this->em()->persist($job);
            $this->em()->flush();
            $id = $job->getId();
            $this->service()->confirm($this->owner, $id);
            $this->service()->process($id);
            self::assertSame('processing', $job->getStatus());
            $this->service()->process($id);
            self::assertSame('available', $job->getStatus());
            self::assertSame($this->owner->getId(), $job->getVideo()->getOwner()->getId());
            $this->files[] = static::getContainer()->get(PrivateVideoStorage::class)->path($job->getVideo()->getStorageName());
            $this->service()->process($id);
        }
        self::assertSame(7, $this->submissions);
        self::assertSame(7, $this->em()->getRepository(VideoAsset::class)->count([]));
    }

    public function testThirtySecondIdeaAndMultibytePromptLimitsAreValidatedBeforeAnySpend(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[prompt]' => 'Une histoire de trente secondes', 'ai_idea[model]' => 'seedance-25', 'ai_idea[seconds]' => 30, 'ai_idea[ratio]' => '9:16']));
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.studio-heading', '30 secondes');
        self::assertSelectorTextContains('.ai-review', '14,19 $ US');
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[prompt]' => str_repeat('🌲', 600), 'ai_idea[model]' => 'pixverse-6', 'ai_idea[seconds]' => 5, 'ai_idea[ratio]' => '9:16']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', '2048 octets');
        self::assertSame(1, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->submissions);
    }

    public function testLtxFourKUsesItsOwnKeyAndReachesThePrivateLibrary(): void
    {
        static::getContainer()->get(AiKeys::class)->save($this->owner, 'synthetic-ltx-only-key-1234567890', 'ltx');
        $this->httpScenario = function (string $method, string $url, array $options): MockResponse {
            if ('POST' === $method) {
                ++$this->submissions;
                self::assertSame('https://api.ltx.io/v2/text-to-video', $url);
                self::assertContains('Authorization: Bearer synthetic-ltx-only-key-1234567890', $options['headers']);
                $input = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
                self::assertSame('2160x3840', $input['resolution']);

                return new MockResponse('{"id":"ltx-test"}', ['http_code' => 202]);
            }
            if (str_starts_with($url, 'https://api.ltx.io/')) {
                return new MockResponse('{"status":"completed","result":{"video_url":"https://storage.googleapis.com/ltx-result/test.mp4"}}');
            }
            self::assertSame('https://storage.googleapis.com/ltx-result/test.mp4', $url);
            self::assertFalse(isset($options['normalized_headers']['authorization']));

            return new MockResponse(file_get_contents(dirname(__DIR__).'/Fixtures/library-valid.mp4'));
        };
        $job = new AiGeneration($this->owner, 'LTX 4K', 'A forest', 'ltx-25-fast-4k', 6, '9:16', 180);
        $this->em()->persist($job);
        $this->em()->flush();
        $this->service()->confirm($this->owner, $job->getId());
        $this->service()->process($job->getId());
        $this->service()->process($job->getId());
        self::assertSame('available', $job->getStatus());
        $this->files[] = static::getContainer()->get(PrivateVideoStorage::class)->path($job->getVideo()->getStorageName());
        $this->service()->process($job->getId());
        self::assertSame(1, $this->submissions);
    }

    public function testMcpCannotBeForgedForAnotherProviderAndFourKBlocksLongDurations(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[prompt]' => 'A forest', 'ai_idea[model]' => 'ltx-25-fast-4k', 'ai_idea[seconds]' => 6, 'ai_idea[ratio]' => '9:16', 'ai_idea[accessMode]' => 'mcp']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'Ce fournisseur utilise une clé API');
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[prompt]' => 'A forest', 'ai_idea[model]' => 'ltx-25-fast-4k', 'ai_idea[seconds]' => 20, 'ai_idea[ratio]' => '9:16', 'ai_idea[accessMode]' => 'api']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'Cette durée n’est pas disponible');
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->submissions);
    }
}
