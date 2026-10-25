<?php

namespace App\Tests\Functional;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\AiCatalog;
use App\Service\AiGenerations;
use App\Service\AiKeys;
use App\Service\AiReferenceStorage;
use App\Service\FalMcpVideoApi;
use App\Service\PrivateVideoStorage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

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

    /** @param array<string,mixed> $values */
    private function legacyForm(\Symfony\Component\DomCrawler\Crawler $crawler, array $values = []): \Symfony\Component\DomCrawler\Form
    {
        $form = $crawler->selectButton('Voir le récapitulatif →')->form($values);
        $form['intent'] = 'prepare';

        return $form;
    }

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

    private function imageUpload(string $slot = 'start'): string
    {
        $file = tempnam(sys_get_temp_dir(), 'ai-image-');
        $this->files[] = $file;
        (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=purple:s=360x640', '-frames:v', '1', '-c:v', 'png', '-f', 'image2', $file]))->mustRun();
        $crawler = $this->client->request('GET', '/studio/creation');
        $root = 'end' === $slot ? 'ai_end_reference' : 'ai_reference';
        $token = $crawler->filter('input[name="'.$root.'[_token]"]')->attr('value');
        $this->client->request('POST', '/studio/creation/images'.('end' === $slot ? '?slot=end' : ''), [$root => ['_token' => $token]], [$root => ['image' => new UploadedFile($file, 'test.png', 'image/png', null, true)]], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->files[] = static::getContainer()->get(AiReferenceStorage::class)->path($this->owner, $data['reference']);

        return $data['reference'];
    }

    public function testImageReferenceIsPrivateAndPreparationRetainsTheImageWithoutSubmitting(): void
    {
        $name = $this->imageUpload();
        self::assertSame(0, $this->submissions);
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        $this->client->request('GET', '/studio/creation/images/'.$name);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/creation/images/'.$name);
        self::assertResponseStatusCodeSame(404);
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[source]' => 'image', 'ai_idea[reference]' => $name, 'ai_idea[model]' => 'kling-25-image', 'ai_idea[prompt]' => 'Animate the trees', 'ai_idea[seconds]' => 5, 'ai_idea[ratio]' => 'auto']));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[source]' => 'image', 'ai_idea[reference]' => $name, 'ai_idea[model]' => 'kling-25-image', 'ai_idea[prompt]' => 'Animate the trees', 'ai_idea[seconds]' => 10, 'ai_idea[ratio]' => 'auto']));
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorExists('img[alt="Référence visuelle de cette génération"]');
        self::assertSelectorTextContains('.profile-facts', '0,70');
        $job = $this->em()->getRepository(AiGeneration::class)->findOneBy(['owner' => $this->owner]);
        self::assertSame($name, $job->getReferenceImage());
        self::assertSame('auto', $job->getRatio());
        $reuse = $this->client->getCrawler()->selectButton('Réutiliser cette idée ↗')->form();
        $this->client->submit($reuse);
        $this->client->followRedirect();
        self::assertSelectorExists('input[name="ai_idea[reference]"][value="'.$name.'"]');
        self::assertSelectorExists('#ai_idea_source option[value="image"][selected]');
        self::assertSame(0, $this->submissions);
    }

    public function testLegacyPlansKeepNamedSubjectReferencesWithoutSubmitting(): void
    {
        $name = $this->imageUpload();
        $crawler = $this->client->request('GET', '/studio/creation');
        $values = $this->legacyForm($crawler, ['ai_idea[source]' => 'image', 'ai_idea[reference]' => $name, 'ai_idea[model]' => 'kling-3-image', 'ai_idea[prompt]' => 'Milo enters the room.', 'ai_idea[seconds]' => 5, 'ai_idea[ratio]' => '9:16'])->getPhpValues();
        $values['intent'] = 'plans';
        $values['ai_idea']['subjects'] = [$name];
        $values['ai_idea']['subjectDetails'] = [['reference' => $name, 'name' => 'Milo', 'role' => 'character', 'presence' => 'enters', 'views' => []]];
        $this->client->request('POST', '/studio/creation', $values);
        self::assertResponseStatusCodeSame(303);
        $episode = $this->em()->getRepository(\App\Entity\AiEpisode::class)->findOneBy(['owner' => $this->owner]);
        self::assertNotNull($episode);
        self::assertSame([$name], $episode->getPreparationRecipe()['subjects']);
        self::assertSame('Milo', $episode->getPreparationRecipe()['subjectDetails'][0]['name']);
        self::assertSame(0, $this->submissions);
    }

    public function testImageWorkerUsesOnlyTheConfirmedOwnedImageAndNeverSubmitsTwice(): void
    {
        $name = $this->imageUpload();
        $this->key();
        $job = new AiGeneration($this->owner, 'Image animée', 'A slow camera movement', 'kling-25-image', 5, 'auto', 35);
        $job->attachReference($name);
        $this->em()->persist($job);
        $this->em()->flush();
        $this->httpScenario = function (string $method, string $url, array $options): MockResponse {
            if ('POST' === $method) {
                ++$this->submissions;
                self::assertSame('https://queue.fal.run/fal-ai/kling-video/v2.5-turbo/pro/image-to-video', $url);
                $input = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
                self::assertSame(['prompt', 'duration', 'enable_safety_checker', 'image_url'], array_keys($input));
                self::assertSame('5', $input['duration']);
                self::assertTrue($input['enable_safety_checker']);
                self::assertStringStartsWith('data:image/jpeg;base64,', $input['image_url']);
                $image = getimagesizefromstring(base64_decode(substr($input['image_url'], strlen('data:image/jpeg;base64,')), true));
                self::assertSame(360, $image[0]);
                self::assertSame(640, $image[1]);

                return new MockResponse('{"status_url":"https://queue.fal.run/fal-ai/kling-video/requests/image-test/status","response_url":"https://queue.fal.run/fal-ai/kling-video/requests/image-test"}');
            }
            if (str_ends_with($url, '/status')) {
                return new MockResponse('{"status":"IN_PROGRESS"}');
            }
            throw new \LogicException('Unexpected request.');
        };
        $this->service()->confirm($this->owner, $job->getId());
        self::assertSame(0, $this->submissions);
        $this->service()->process($job->getId());
        self::assertSame(1, $this->submissions);
        self::assertSame('processing', $job->getStatus());
        $this->service()->process($job->getId());
        self::assertSame(1, $this->submissions);
        $missing = new AiGeneration($this->owner, 'Missing', 'A camera move', 'kling-25-image', 5, 'auto', 35);
        $missing->attachReference(str_repeat('b', 48).'.jpg');
        $missing->progress('queued');
        $this->em()->persist($missing);
        $this->em()->flush();
        $this->service()->process($missing->getId());
        self::assertSame('review', $missing->getStatus());
        self::assertSame(1, $this->submissions);
    }

    public function testImageUploadRejectsCsrfMalformedFilesAndModelsMissingTheirReference(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->request('POST', '/studio/creation/images', ['ai_reference' => ['_token' => 'forged']], [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        $file = tempnam(sys_get_temp_dir(), 'bad-image-');
        file_put_contents($file, '<svg onload="evil()"></svg>');
        $this->files[] = $file;
        $token = $crawler->filter('input[name="ai_reference[_token]"]')->attr('value');
        $this->client->request('POST', '/studio/creation/images', ['ai_reference' => ['_token' => $token]], ['ai_reference' => ['image' => new UploadedFile($file, 'fake.jpg', 'image/jpeg', null, true)]], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[source]' => 'image', 'ai_idea[model]' => 'kling-25-image', 'ai_idea[prompt]' => 'A camera move', 'ai_idea[ratio]' => 'auto']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'Importe une image');
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[source]' => 'image', 'ai_idea[reference]' => '../../secret.jpg', 'ai_idea[model]' => 'kling-25-image', 'ai_idea[prompt]' => 'A camera move', 'ai_idea[ratio]' => 'auto']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorNotExists('img.creation-reference-preview[src]');

        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[source]' => 'image', 'ai_idea[model]' => 'kling-25', 'ai_idea[prompt]' => 'A camera move']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'compatible avec ton point de départ');
        self::assertSame(0, $this->submissions);
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
    }

    public function testFramesPreparationRetainsBothImagesAndRejectsUnsupportedModels(): void
    {
        $first = $this->imageUpload();
        $last = $this->imageUpload('end');
        $this->client->request('GET', '/studio/creation/images/'.$last.'?ratio=16:9&fit=contain');
        self::assertResponseIsSuccessful();
        self::assertSame([1920, 1080], array_slice(getimagesizefromstring($this->client->getResponse()->getContent()), 0, 2));
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[source]' => 'image', 'ai_idea[useEnd]' => '1', 'ai_idea[reference]' => $first, 'ai_idea[endReference]' => $last, 'ai_idea[imageFit]' => 'contain', 'ai_idea[model]' => 'kling-25-image', 'ai_idea[prompt]' => 'Transition gently', 'ai_idea[seconds]' => 5, 'ai_idea[ratio]' => '16:9']));
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorExists('img[alt="Image de fin de cette génération"]');
        $job = $this->em()->getRepository(AiGeneration::class)->findOneBy(['owner' => $this->owner]);
        self::assertSame($last, $job->getEndReferenceImage());
        self::assertSame('contain', $job->getImageFit());
        self::assertSame('16:9', $job->getRatio());
        $this->client->submit($this->client->getCrawler()->selectButton('Réutiliser cette idée ↗')->form());
        $this->client->followRedirect();
        self::assertSelectorExists('input[name="ai_idea[endReference]"][value="'.$last.'"]');
        self::assertSelectorExists('input[name="ai_idea[useEnd]"][checked]');
        $crawler = $this->client->getCrawler();
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[model]' => 'wan-25-image-720p']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'n’accepte pas d’image de fin');
        self::assertSame(1, $this->em()->getRepository(AiGeneration::class)->count([]));
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/creation/images/'.$last.'?ratio=9:16&fit=crop');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/creation/images/'.$last.'?match='.$first);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->submissions);
    }

    public function testFramesModelsRequireTheirEndingImageBeforePreparation(): void
    {
        $first = $this->imageUpload();
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[source]' => 'image', 'ai_idea[useEnd]' => '1', 'ai_idea[reference]' => $first, 'ai_idea[endReference]' => '', 'ai_idea[model]' => 'vidu-q1-frames', 'ai_idea[prompt]' => 'Transition gently', 'ai_idea[seconds]' => 4, 'ai_idea[ratio]' => 'auto']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'Importe ton image de fin');
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[source]' => 'image', 'ai_idea[reference]' => $first, 'ai_idea[endReference]' => '../../secret.jpg', 'ai_idea[model]' => 'kling-25-image', 'ai_idea[prompt]' => 'Transition gently']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorNotExists('img[alt="Image de fin"][src]');
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->submissions);
    }

    public function testMcpImagePayloadKeepsBothConfirmedFramesAndCropping(): void
    {
        $name = $this->imageUpload();
        $job = new AiGeneration($this->owner, 'MCP frames', 'A gentle transition', 'kling-25-image', 5, '1:1', 35);
        $job->attachReference($name);
        $job->attachEndReference($name);
        $job->frameImages('contain');
        $job->useMcp();
        $job->bindAccessAccount('synthetic-account');
        $this->httpScenario = function (string $method, string $url, array $options): MockResponse {
            self::assertSame('https://mcp.fal.ai/mcp-relay', $url);
            $body = json_decode($options['body'], true, 64, JSON_THROW_ON_ERROR);
            if ('initialize' === $body['method']) {
                return new MockResponse('{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-03-26"}}');
            }
            if ('notifications/initialized' === $body['method']) {
                return new MockResponse('', ['http_code' => 202]);
            }
            self::assertSame('submit_job', $body['params']['name']);
            ++$this->submissions;
            $args = $body['params']['arguments'];
            self::assertFalse($args['store_payload']);
            self::assertSame('fal-ai/kling-video/v2.5-turbo/pro/image-to-video', $args['endpoint_id']);
            self::assertArrayNotHasKey('aspect_ratio', $args['input']);
            foreach (['image_url', 'tail_image_url'] as $key) {
                $bytes = base64_decode(substr($args['input'][$key], strlen('data:image/jpeg;base64,')), true);
                self::assertSame([1080, 1080], array_slice(getimagesizefromstring($bytes), 0, 2));
            }

            return new MockResponse('{"jsonrpc":"2.0","id":2,"result":{"structuredContent":{"request_id":"synthetic-frames"}}}');
        };
        $receipt = static::getContainer()->get(FalMcpVideoApi::class)->submit($job, json_encode(['access_token' => 'synthetic-not-a-real-token', 'account_id' => 'synthetic-account'], JSON_THROW_ON_ERROR));
        self::assertArrayHasKey('mcp_receipt', $receipt);
        $job->progress('processing', remote: $receipt);
        foreach (['IN_QUEUE' => 'queued', 'IN_PROGRESS' => 'running'] as $remoteState => $stage) {
            $this->httpScenario = function (string $method, string $url, array $options) use ($remoteState): MockResponse {
                $body = json_decode($options['body'], true, 64, JSON_THROW_ON_ERROR);
                if ('initialize' === $body['method']) {
                    return new MockResponse('{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-03-26"}}');
                }
                if ('notifications/initialized' === $body['method']) {
                    return new MockResponse('', ['http_code' => 202]);
                }
                self::assertSame('check_job', $body['params']['name']);
                self::assertSame(0, $body['params']['arguments']['wait_seconds']);

                return new MockResponse(json_encode(['jsonrpc' => '2.0', 'id' => 2, 'result' => ['structuredContent' => ['status' => $remoteState, 'logs' => 'never-visible']]], JSON_THROW_ON_ERROR));
            };
            self::assertSame(['ready' => false, 'stage' => $stage], static::getContainer()->get(FalMcpVideoApi::class)->poll($job, json_encode(['access_token' => 'synthetic-not-a-real-token', 'account_id' => 'synthetic-account'], JSON_THROW_ON_ERROR)));
        }
        self::assertSame(1, $this->submissions);
    }

    public function testIdeaAutosaveKeepsIncompleteInputsWithoutCreatingGeneration(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        $before = $this->em()->getRepository(AiGeneration::class)->count([]);
        $revision = $crawler->filter('#ai-idea-form')->attr('data-idea-save-revision-value');
        $token = $crawler->filter('input[name="ai_idea[_token]"]')->attr('value');
        $payload = ['revision' => $revision, 'editor' => str_repeat('a', 32), 'sequence' => 2, 'ai_idea' => ['_token' => $token, 'prompt' => 'Une idée privée <test>', 'title' => '', 'model' => '', 'seconds' => '', 'ratio' => '9:16', 'accessMode' => 'api']];
        $this->client->request('POST', '/studio/creation/idee', $payload);
        self::assertResponseIsSuccessful();
        self::assertSame($before, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->submissions);
        $payload['sequence'] = 1;
        $payload['ai_idea']['prompt'] = 'Ancienne idée';
        $this->client->request('POST', '/studio/creation/idee', $payload);
        self::assertResponseStatusCodeSame(409);
        $this->client->request('GET', '/studio/creation');
        self::assertSelectorTextContains('#ai_idea_prompt', 'Une idée privée <test>');
        self::assertSelectorNotExists('[data-controller="unsaved"]');
        $payload['sequence'] = 3;
        $payload['ai_idea']['_token'] = 'forged';
        $this->client->request('POST', '/studio/creation/idee', $payload);
        self::assertResponseStatusCodeSame(422);
        $payload['ai_idea']['_token'] = $token;
        $payload['ai_idea']['model'] = 'forged-model';
        $this->client->request('POST', '/studio/creation/idee', $payload);
        self::assertResponseStatusCodeSame(422);
        $payload['ai_idea']['model'] = '';
        $payload['ai_idea']['prompt'] = str_repeat('x', 1501);
        $this->client->request('POST', '/studio/creation/idee', $payload);
        self::assertResponseStatusCodeSame(422);
        $payload['ai_idea']['prompt'] = 'Stale';
        $payload['revision'] = 'old-page';
        $this->client->request('POST', '/studio/creation/idee', $payload);
        self::assertResponseStatusCodeSame(409);
    }

    public function testCatalogShowsResolutionAndMissingAccessAndConnectionPreservesAnIncompleteIdea(): void
    {
        $this->key();
        $crawler = $this->client->request('GET', '/studio/creation');
        self::assertSelectorTextContains('#ai_idea_model option[value="kling-3"]', '1080p');
        self::assertSelectorTextContains('#ai_idea_model option[value="ltx-23-fast-4k"]', 'à connecter');
        self::assertSelectorExists('article.is-disconnected[data-group-id="ltx-23-fast-720"] option[value="ltx-23-fast-4k"]');
        self::assertSelectorNotExists('article.is-disconnected[data-model-id="kling-3"]');
        $form = $crawler->selectButton('Connecter mon fournisseur')->form(['ai_idea[accessMode]' => 'api', 'ai_idea[model]' => 'ltx-23-fast-4k', 'ai_idea[prompt]' => '', 'ai_idea[seconds]' => '', 'ai_idea[title]' => 'Mon futur plan']);
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
        self::assertSelectorTextContains('.ai-key-mask', '•••• 7890');
        self::assertSelectorNotExists('details .ai-key-removal');
        self::assertSelectorNotExists('details.ai-other-providers');
        self::assertSelectorNotExists('input[name="apiKey"][value]');
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
        $values = $form->getPhpValues();
        unset($values['confirm']);
        $this->client->request('POST', '/studio/connexions/ia', $values);
        self::assertResponseStatusCodeSame(303);
        self::assertNotNull($keys->connection($this->owner));
        $values = $form->getPhpValues();
        unset($values['_token']);
        $this->client->request('POST', '/studio/connexions/ia', $values);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($keys->connection($this->owner));
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertNull($keys->connection($this->owner));
    }

    public function testUnreadableKeyStillHasADirectRemovalAction(): void
    {
        $this->key();
        $keys = static::getContainer()->get(AiKeys::class);
        $keys->connection($this->owner)->replace('unreadable-ciphertext');
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $crawler = $this->client->request('GET', '/studio/connexions/ia');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ai-key-mask', '•••• ••••');
        self::assertSelectorNotExists('details .ai-key-removal');
        $this->client->submit($crawler->selectButton('Retirer ma clé')->form(['confirm' => '1']));
        self::assertResponseStatusCodeSame(303);
        self::assertNull($keys->connection($this->owner));
        self::assertSame(0, $this->submissions);
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
        $form = $crawler->selectButton('Connecter mon fournisseur')->form(['ai_idea[accessMode]' => 'api', 'ai_idea[title]' => 'Mon idée', 'ai_idea[prompt]' => 'Un paysage <privé>', 'ai_idea[model]' => 'wan-25']);
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/connexions/ia?next=idea&provider=fal', 303);
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Enregistrer ma clé')->form(['apiKey' => self::KEY]));
        self::assertResponseRedirects('/studio/creation', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('input[name="ai_idea[title]"][value="Mon idée"]');
        self::assertSelectorTextContains('textarea', 'Un paysage <privé>');
        $form = $this->legacyForm($crawler);
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
        $token = $crawler->filter('.ai-status form.account-form input[name="_token"]')->first()->attr('value');
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
        $deadline = $this->reload($id)->getUpdatedAt()->format('U.u');
        $this->waiting = true;
        $this->service()->process($id);
        self::assertSame('processing', $this->reload($id)->getStatus());
        self::assertSame('running', $this->reload($id)->getProviderStage());
        self::assertNotNull($this->reload($id)->getProviderCheckedAt());
        self::assertSame($deadline, $this->reload($id)->getUpdatedAt()->format('U.u'));
        $this->waiting = false;
        $this->badFile = true;
        $this->service()->process($id);
        self::assertSame('review', $this->reload($id)->getStatus());
        self::assertSame('importing', $this->reload($id)->getProviderStage());
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
        $this->service()->confirm($this->owner, $id);
        $this->service()->process($id);
        $full = new VideoAsset($this->owner, 'Synthetic quota', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1073741824, 4, 720, 1280, 'h264');
        $this->em()->persist($full);
        $this->em()->flush();
        $fullId = $full->getId();
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
        self::assertSelectorTextSame('h1', 'Créer une vidéo.');
        self::assertSelectorExists('.studio-sidebar nav a[href="/studio/creation"][aria-current="page"]');
        self::assertSelectorExists('.studio-bottom-nav a[href="/studio/creation"][aria-current="page"]');
        self::assertSelectorNotExists('.studio-sidebar nav a[href="/studio"][aria-current="page"]');
        self::assertSelectorExists('#ai-idea-form');
        self::assertSelectorNotExists('.creation-tool-soon[aria-disabled="true"]');
        self::assertSelectorNotExists('input[name="ai_idea[title]"][required]');
        $this->client->request('GET', '/studio');
        self::assertSelectorTextSame('h1', 'Tes vidéos.');
        self::assertSelectorExists('.studio-sidebar nav a[href="/studio"][aria-current="page"]');
        self::assertSelectorNotExists('.studio-sidebar nav a[href="/studio/creation"][aria-current="page"]');
        $this->client->request('GET', '/studio/ia');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Créer une vidéo.');
        $this->client->request('GET', '/studio/connexions/ia');
        self::assertSelectorExists('.studio-sidebar nav a[href="/studio/connexions"][aria-current="page"]');
        self::assertSelectorNotExists('.studio-sidebar nav a[href="/studio/creation"][aria-current="page"]');
    }

    public function testIdeaCanBePreparedWithoutNamingItAndStillRequiresCostConfirmation(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[prompt]' => "Une scène\n  au soleil <privée>", 'ai_idea[model]' => 'kling-25', 'ai_idea[seconds]' => 10]));
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
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[prompt]' => " \n ", 'ai_idea[model]' => 'kling-25']));
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
        self::assertSelectorTextContains('.ai-api-alternative .ai-key-mask', '•••• 7890');
        self::assertSelectorTextContains('.ai-other-providers .ai-key-mask', '•••• 3456');
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
        $this->client->submit($crawler->selectButton('Connecter mon fournisseur')->form(['ai_idea[prompt]' => 'Une forêt sous la pluie', 'ai_idea[model]' => 'wan-25-replicate', 'ai_idea[seconds]' => 10]));
        self::assertResponseRedirects('/studio/connexions/ia?next=idea&provider=replicate', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextSame('#ai-replicate-title', 'Replicate');
        $this->client->submit($crawler->selectButton('Enregistrer ma clé Replicate')->form(['apiKey' => 'synthetic-replicate-only-key-123456']));
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('option[value="wan-25-replicate"][selected]');
        $this->client->submit($this->legacyForm($crawler));
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
            $this->client->submit($this->legacyForm($crawler, ['ai_idea[prompt]' => 'Un plan contrôlé', 'ai_idea[model]' => $model, 'ai_idea[seconds]' => $seconds, 'ai_idea[ratio]' => $ratio]));
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
        $deadline = $this->reload($id)->getUpdatedAt()->format('U.u');
        $this->waiting = true;
        $this->service()->process($id);
        self::assertSame('processing', $this->reload($id)->getStatus());
        self::assertSame('running', $this->reload($id)->getProviderStage());
        self::assertNotNull($this->reload($id)->getProviderCheckedAt());
        self::assertSame($deadline, $this->reload($id)->getUpdatedAt()->format('U.u'));
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
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[prompt]' => 'Une histoire de trente secondes', 'ai_idea[model]' => 'seedance-25', 'ai_idea[seconds]' => 30, 'ai_idea[ratio]' => '9:16']));
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.studio-heading', '30 secondes');
        self::assertSelectorTextContains('.ai-review', '14,19 $ US');
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[prompt]' => str_repeat('🌲', 600), 'ai_idea[model]' => 'pixverse-6', 'ai_idea[seconds]' => 5, 'ai_idea[ratio]' => '9:16']));
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
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[prompt]' => 'A forest', 'ai_idea[model]' => 'ltx-25-fast-4k', 'ai_idea[seconds]' => 6, 'ai_idea[ratio]' => '9:16', 'ai_idea[accessMode]' => 'mcp']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'Ce fournisseur utilise une clé API');
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($this->legacyForm($crawler, ['ai_idea[prompt]' => 'A forest', 'ai_idea[model]' => 'ltx-25-fast-4k', 'ai_idea[seconds]' => 20, 'ai_idea[ratio]' => '9:16', 'ai_idea[accessMode]' => 'api']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'Cette durée n’est pas disponible');
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->submissions);
    }

    public function testGuideHistorySearchAndPaginationArePrivate(): void
    {
        for ($i = 0; $i < 14; ++$i) {
            $job = new AiGeneration($this->owner, 13 === $i ? '100% privé' : 'Plan '.$i, 'Prompt privé', 'kling-25', 5, '9:16', 35);
            $this->em()->persist($job);
        }
        $other = new AiGeneration($this->other, '100% autre propriétaire', 'Secret', 'kling-25', 5, '9:16', 35);
        $this->em()->persist($other);
        $this->em()->flush();
        $this->client->request('GET', '/studio/creation/historique');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(12, '.ai-history-row');
        self::assertSelectorTextContains('.library-pagination', '1 / 2');
        self::assertSelectorTextNotContains('.ai-history-list', 'autre propriétaire');
        $this->client->request('GET', '/studio/creation/historique?page=2');
        self::assertSelectorCount(2, '.ai-history-row');
        $this->client->request('GET', '/studio/creation/historique?q=100%25&status=prepared');
        self::assertSelectorCount(1, '.ai-history-row');
        self::assertSelectorTextContains('.ai-history-row', '100% privé');
        $this->client->request('GET', '/studio/creation/historique?status=forged');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->submissions);
    }

    public function testGuideStatusAndReuseNeverSendARequestAndRequireOwnershipAndCsrf(): void
    {
        $job = $this->job();
        $job->useMcp();
        $job->progress('processing', remote: ['secret' => 'receipt-must-stay-private']);
        $job->observeProvider('running');
        $this->em()->flush();
        $id = $job->getId();
        $this->client->request('GET', '/studio/ia/generations/'.$id.'/etat');
        self::assertResponseIsSuccessful();
        $state = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['status', 'updatedAt', 'stage', 'checkedAt'], array_keys($state));
        self::assertSame('running', $state['stage']);
        self::assertNotNull($state['checkedAt']);
        self::assertSame('processing', $state['status']);
        self::assertStringNotContainsString('receipt-must-stay-private', $this->client->getResponse()->getContent());
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $crawler = $this->client->request('GET', '/studio/ia/generations/'.$id);
        self::assertSelectorExists('[data-controller="generation-watch"]');
        self::assertSelectorExists('[role="progressbar"]:not([aria-valuenow])');
        self::assertSelectorCount(4, '.generation-progress-steps li');
        $form = $crawler->selectButton('Réutiliser cette idée ↗')->form();
        $this->client->request('POST', '/studio/ia/generations/'.$id.'/reutiliser');
        self::assertResponseStatusCodeSame(403);
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/creation', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('textarea[name="ai_idea[prompt]"]', 'Un oiseau au soleil <script>');
        self::assertSelectorExists('select[name="ai_idea[accessMode]"] option[value="mcp"][selected]');
        self::assertSame(1, $this->em()->getRepository(AiGeneration::class)->count([]));
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/ia/generations/'.$id.'/etat');
        self::assertResponseStatusCodeSame(404);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->submissions);
    }

    public function testGuideCapacityIncludesOtherActiveGenerationsBeforeConfirmation(): void
    {
        $this->key();
        $job = $this->job();
        $active = $this->job();
        $active->progress('queued');
        $used = new VideoAsset($this->owner, 'Synthetic occupancy', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1073741824 - 150000000, 4, 720, 1280, 'h264');
        $this->em()->persist($used);
        $this->em()->flush();
        $crawler = $this->client->request('GET', '/studio/ia/generations/'.$job->getId());
        $this->client->submit($crawler->selectButton('Confirmer et générer →')->form(['confirm' => '1']));
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Place insuffisante');
        self::assertSame('prepared', static::getContainer()->get(Connection::class)->fetchOne('SELECT status FROM ai_generation WHERE id = ?', [$job->getId()]));
        self::assertSame(0, $this->submissions);
    }

    public function testGuideWorkerChecksCapacityAgainBeforeAnyBillablePost(): void
    {
        $this->key();
        $job = $this->job();
        $this->service()->confirm($this->owner, $job->getId());
        $used = new VideoAsset($this->owner, 'Synthetic full storage', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1073741824, 4, 720, 1280, 'h264');
        $this->em()->persist($used);
        $this->em()->flush();
        $this->service()->process($job->getId());
        self::assertSame('review', $job->getStatus());
        self::assertStringContainsString('Place insuffisante', $job->getIssue());
        self::assertSame([], $job->getRemote());
        self::assertSame(0, $this->submissions);
    }

    public function testSimpleStudioRecommendsExistingModelsAndDefaultsOnlyNewIdeasToBrowserAccess(): void
    {
        $this->client->request('GET', '/studio/creation');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '.creation-recommendation');
        self::assertSelectorExists('select[name="ai_idea[accessMode]"] option[value="mcp"][selected]');
        self::assertSelectorExists('details[data-creation-target="modelDetails"][open]');
        self::assertSelectorExists('select[name="ai_idea[model]"] option[value="ltx-25-fast-4k-image"]');
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        $this->client->request('GET', '/studio/connexions/ia');
        self::assertSelectorTextContains('.ai-fal-primary', 'Connecter mon compte Fal');
        self::assertSelectorTextContains('.ai-billing-guide', 'Aucun abonnement VydFlow');
        self::assertSelectorCount(1, '.ai-api-alternative input[name="apiKey"]');
        self::assertSelectorCount(2, '.ai-other-providers input[name="apiKey"]');
        $this->key();
        $this->client->request('GET', '/studio/creation');
        self::assertSelectorExists('select[name="ai_idea[accessMode]"] option[value="api"][selected]');
        self::assertSame(0, $this->submissions);
    }
}
