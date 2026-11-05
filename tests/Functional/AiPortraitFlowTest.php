<?php

namespace App\Tests\Functional;

use App\Entity\AiCharacter;
use App\Entity\AiPortraitDraft;
use App\Entity\AiPortraitRequest;
use App\Entity\User;
use App\Service\AiKeys;
use App\Service\AiPortraitApi;
use App\Service\AiPortraitDrafts;
use App\Service\AiPortraits;
use App\Service\AiReferenceMaintenance;
use App\Service\AiReferenceStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Process\Process;

final class AiPortraitFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private int $owner;
    private int $other;
    private array $before;
    private array $calls = [];
    private array $screens = [];
    private string $image;
    private string|false $oldFlag;
    private string $failure = '';
    private string $resultMode = '';
    private bool $pending = false;
    private bool $redeliver = false;

    protected function setUp(): void
    {
        $this->oldFlag = getenv('PORTRAIT_GENERATION_ENABLED');
        putenv('PORTRAIT_GENERATION_ENABLED=1');
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('shortflow_test', $this->em->getConnection()->fetchOne('SELECT current_database()'));
        $this->before = $this->counts();
        $this->em->getConnection()->beginTransaction();
        $this->clock = new MockClock('2026-11-04T23:00:00Z');
        static::getContainer()->set('app.publication_clock', $this->clock);
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['story', 'youtube', 'instagram', 'tiktok'] as $provider) {
            static::getContainer()->set($provider.'.http', new MockHttpClient(fn () => throw new \LogicException('Unexpected provider.')));
        }
        static::getContainer()->set('ai.http', new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->calls[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? null, 'headers' => $options['normalized_headers'] ?? []];
            if ('POST' === $method && 'https://queue.fal.run/fal-ai/flux-2-pro' === $url) {
                if ($this->redeliver) {
                    $current = $this->em->getRepository(AiPortraitRequest::class)->findOneBy(['owner' => $this->user()]);
                    self::assertSame('submitting', $current->getStatus());
                    $this->flow()->process($current->getId());
                }
                if ('submit' === $this->failure) {
                    return new MockResponse('unavailable', ['http_code' => 503]);
                }

                return new MockResponse(json_encode($this->receipt(), JSON_THROW_ON_ERROR));
            }
            if ('GET' === $method && $url === $this->receipt()['status_url']) {
                return new MockResponse(json_encode(['status' => $this->pending ? 'IN_PROGRESS' : 'COMPLETED']), ['http_code' => 'poll' === $this->failure ? 503 : 200]);
            }
            if ('GET' === $method && $url === $this->receipt()['response_url']) {
                $images = [['url' => 'https://v3b.fal.media/files/portrait.jpg']];
                if ('multiple' === $this->resultMode) {
                    $images[] = $images[0];
                }
                if ('unsafe' === $this->resultMode) {
                    $images[0]['url'] = 'https://127.0.0.1/private.jpg';
                }

                return new MockResponse(json_encode(['seed' => 1234, 'images' => $images], JSON_THROW_ON_ERROR));
            }
            if ('GET' === $method && 'https://v3b.fal.media/files/portrait.jpg' === $url) {
                self::assertArrayNotHasKey('authorization', $options['normalized_headers']);
                self::assertSame(0, $options['max_redirects']);
                if ('download' === $this->failure) {
                    return new MockResponse((function (): \Generator {
                        yield 'partial';
                        yield '';
                    })());
                }

                return new MockResponse('invalid' === $this->resultMode ? 'not an image' : file_get_contents($this->image));
            }
            throw new \LogicException('Unexpected HTTP request.');
        }));
        foreach (['owner', 'other'] as $field) {
            $user = new User('portrait-flow-'.$field.'-'.bin2hex(random_bytes(5)).'@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em->persist($user);
            $this->em->flush();
            $this->$field = $user->getId();
        }
        static::getContainer()->get(AiKeys::class)->save($this->user(), 'synthetic-portrait-key-0000000001');
        $this->client->loginUser($this->user());
        $this->image = tempnam(sys_get_temp_dir(), 'portrait-fixture-').'.jpg';
        (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=purple:s=1024x1024', '-frames:v', '1', '-threads', '1', $this->image]))->mustRun();
        @mkdir(dirname(__DIR__, 2).'/artifacts/portrait-flow', 0775, true);
    }

    private function receipt(): array
    {
        return ['request_id' => 'synthetic-portrait-1', 'status_url' => 'https://queue.fal.run/fal-ai/flux-2-pro/requests/synthetic-portrait-1/status', 'response_url' => 'https://queue.fal.run/fal-ai/flux-2-pro/requests/synthetic-portrait-1'];
    }

    private function counts(): array
    {
        $db = $this->em->getConnection();
        $counts = [];
        foreach ($db->fetchFirstColumn("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename") as $table) {
            $counts[$table] = (int) $db->fetchOne('SELECT COUNT(*) FROM '.$db->quoteIdentifier($table));
        }

        return $counts;
    }

    protected function tearDown(): void
    {
        try {
            foreach (glob(dirname(__DIR__, 2).'/var/storage/ai-references/test/'.$this->owner.'/*.jpg') ?: [] as $path) {
                unlink($path);
            }
            @rmdir(dirname(__DIR__, 2).'/var/storage/ai-references/test/'.$this->owner);
            $storageRemoved = !is_dir(dirname(__DIR__, 2).'/var/storage/ai-references/test/'.$this->owner);
            if ('testIdeaToAcceptedPortraitSceneAndFrozenVideoQuoteAcrossReplacement' === $this->name()) {
                self::assertTrue($storageRemoved, 'Synthetic portrait journey references must be removed after the test.');
            }
            @unlink($this->image);
            @unlink(substr($this->image, 0, -4));
            $this->em->getConnection()->rollBack();
            $after = $this->counts();
            self::assertSame($this->before, $after);
            $posts = array_values(array_filter($this->calls, fn (array $call): bool => 'POST' === $call['method']));
            file_put_contents(dirname(__DIR__, 2).'/artifacts/portrait-flow/'.$this->name().'.json', json_encode(['before' => $this->before, 'after' => $after, 'simulatedHttp' => count($this->calls), 'posts' => count($posts), 'storageRemoved' => $storageRemoved, 'screens' => $this->screens], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } finally {
            putenv(false === $this->oldFlag ? 'PORTRAIT_GENERATION_ENABLED' : 'PORTRAIT_GENERATION_ENABLED='.$this->oldFlag);
            parent::tearDown();
        }
    }

    private function user(): User
    {
        return $this->em->find(User::class, $this->owner);
    }

    private function flow(): AiPortraits
    {
        return static::getContainer()->get(AiPortraits::class);
    }

    private function draft(): AiPortraitDraft
    {
        return static::getContainer()->get(AiPortraitDrafts::class)->save($this->user(), null, ['name' => 'Milo', 'idea' => 'Un chat explorateur.', 'appearance' => 'Chat roux.', 'style' => 'Illustration douce.', 'customPrompt' => '', 'revision' => 0]);
    }

    private function start(): AiPortraitRequest
    {
        $draft = $this->draft();

        return $this->flow()->confirm($this->user(), $draft->getId(), $draft->getQuote()['hash']);
    }

    private function snap(string $name): void
    {
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        file_put_contents(dirname(__DIR__, 2).'/artifacts/portrait-flow/'.$name.'.html', $html);
        $this->screens[$name] = hash('sha256', $html);
    }

    private function action(AiPortraitRequest $portrait, string $action): AiPortraitRequest
    {
        $crawler = $this->client->request('GET', '/studio/personnages/idees/demandes/'.$portrait->getId());
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/studio/personnages/idees/demandes/'.$portrait->getId(), ['_token' => $token, 'consent' => '1', 'action' => $action]);

        return $this->em->find(AiPortraitRequest::class, $portrait->getId());
    }

    public function testNativeConfirmationFrozenSnapshotDuplicateDeliveryAndAcceptance(): void
    {
        $draft = $this->draft();
        $crawler = $this->client->request('GET', '/studio/personnages/idees/'.$draft->getId());
        $this->snap('confirmation');
        $form = $crawler->selectButton('Confirmer la demande de portrait')->form(['consent' => '1']);
        $values = $form->getPhpValues();
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseStatusCodeSame(303);
        $location = $this->client->getResponse()->headers->get('Location');
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseRedirects($location, 303);
        $portrait = $this->em->getRepository(AiPortraitRequest::class)->findOneBy(['owner' => $this->user()]);
        self::assertSame(1, $this->em->getRepository(AiPortraitRequest::class)->count(['owner' => $this->user()]));
        self::assertCount(0, $this->calls);
        self::assertSame(0, $this->em->getRepository(AiCharacter::class)->count(['owner' => $this->user()]));
        $this->client->followRedirect();
        $this->snap('queued');
        static::getContainer()->get(AiPortraitDrafts::class)->save($this->user(), $draft->getId(), ['name' => 'Nouveau nom', 'idea' => 'Nouvelle idée', 'appearance' => '', 'style' => '', 'customPrompt' => '', 'revision' => 1]);
        $id = $portrait->getId();
        $this->em->clear();
        $this->redeliver = true;
        $this->flow()->process($id);
        $portrait = $this->em->find(AiPortraitRequest::class, $id);
        self::assertSame('processing', $portrait->getStatus());
        self::assertSame($portrait->getSnapshot()['input'], json_decode($this->calls[0]['body'], true, 32, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('Nouveau nom', $this->calls[0]['body']);
        $this->pending = true;
        $this->flow()->process($id);
        self::assertSame('processing', $portrait->getStatus());
        $this->pending = false;
        $this->flow()->process($id);
        self::assertSame('ready', $portrait->getStatus());
        $this->client->request('GET', '/studio/personnages/idees/demandes/'.$id);
        $this->snap('ready');
        copy($this->image, dirname(__DIR__, 2).'/artifacts/portrait-flow/portrait.jpg');
        $this->client->request('GET', '/studio/personnages/idees/demandes/'.$id.'/image');
        self::assertResponseIsSuccessful();
        $headers = $this->client->getResponse()->headers;
        self::assertTrue($headers->hasCacheControlDirective('no-store'));
        self::assertTrue($headers->hasCacheControlDirective('private'));
        self::assertFalse($headers->hasCacheControlDirective('public'));
        $portrait = $this->action($portrait, 'accept');
        self::assertResponseStatusCodeSame(303);
        $character = $portrait->getAcceptedCharacter();
        self::assertSame('Milo', $character->getName());
        self::assertSame($portrait->getResultReference(), $character->getPortrait());
        $this->flow()->accept($this->user(), $id);
        self::assertSame(1, $this->em->getRepository(AiCharacter::class)->count(['owner' => $this->user()]));
        $this->flow()->process($id);
        self::assertCount(1, array_filter($this->calls, fn (array $c): bool => 'POST' === $c['method']));
        $this->client->request('GET', '/studio/personnages/idees/demandes/'.$id);
        $this->snap('accepted');
    }

    public function testAmbiguousSubmissionAndOrphanedLeaseNeverResubmit(): void
    {
        $portrait = $this->start();
        $this->failure = 'submit';
        $this->flow()->process($portrait->getId());
        self::assertSame('review', $portrait->getStatus());
        self::assertNull($portrait->getRemote());
        $this->flow()->process($portrait->getId());
        $this->client->request('GET', '/studio/personnages/idees/demandes/'.$portrait->getId());
        self::assertSelectorNotExists('button:contains("Reprendre la récupération")');
        try {
            $this->flow()->resume($this->user(), $portrait->getId());
            self::fail('An ambiguous submission must not be retried.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('Reprise indisponible', $error->getMessage());
        }
        $portrait = $this->em->find(AiPortraitRequest::class, $portrait->getId());
        self::assertCount(1, $this->calls);
        $portrait->state('submitting', $this->clock->now()->modify('-6 minutes'), lease: 'orphan');
        $this->em->flush();
        $this->flow()->process($portrait->getId());
        self::assertSame('review', $portrait->getStatus());
        self::assertCount(1, $this->calls);
        $this->client->request('GET', '/studio/personnages/idees/demandes/'.$portrait->getId());
        $this->snap('ambiguous');
    }

    public function testKnownReceiptRecoversWithExplicitNewKeyWithoutPostOrFreshPrice(): void
    {
        $portrait = $this->start();
        $snapshot = $portrait->getSnapshot();
        $this->flow()->process($portrait->getId());
        $this->failure = 'poll';
        $this->flow()->process($portrait->getId());
        self::assertSame('review', $portrait->getStatus());
        $this->client->request('GET', '/studio/personnages/idees/demandes/'.$portrait->getId());
        $this->snap('recoverable');
        static::getContainer()->get(AiKeys::class)->save($this->user(), 'synthetic-portrait-key-replaced-0002');
        $this->clock->sleep(15 * 86400);
        $this->failure = '';
        $portrait = $this->action($portrait, 'resume');
        self::assertResponseStatusCodeSame(303);
        $this->flow()->process($portrait->getId());
        self::assertSame('ready', $portrait->getStatus());
        self::assertSame($snapshot, $portrait->getSnapshot());
        self::assertCount(1, array_filter($this->calls, fn (array $c): bool => 'POST' === $c['method']));
        self::assertStringContainsString('synthetic-portrait-key-replaced-0002', implode('', $this->calls[2]['headers']['authorization']));
    }

    public function testChangedKeyBeforeSubmissionBlocksAndInvalidConsentCannotQueue(): void
    {
        $portrait = $this->start();
        static::getContainer()->get(AiKeys::class)->save($this->user(), 'synthetic-portrait-key-replaced-0002');
        $this->flow()->process($portrait->getId());
        self::assertSame('blocked', $portrait->getStatus());
        self::assertCount(0, $this->calls);
        $this->client->request('GET', '/studio/personnages/idees/demandes/'.$portrait->getId());
        $this->snap('blocked');
        $draft = $this->draft();
        $this->client->request('POST', '/studio/personnages/idees/'.$draft->getId().'/confirmer', ['hash' => $draft->getQuote()['hash'], '_token' => 'invalid', 'consent' => '1']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/studio/personnages/idees/demandes/'.$portrait->getId(), ['_token' => 'invalid', 'action' => 'accept', 'consent' => '1']);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->em->find(User::class, $this->other));
        foreach (['', '/image'] as $suffix) {
            $this->client->request('GET', '/studio/personnages/idees/demandes/'.$portrait->getId().$suffix);
            self::assertResponseStatusCodeSame(404);
        }
    }

    public function testInvalidMultipleAndUnsafeResultsStayPrivateAndRecoverSameReceipt(): void
    {
        $portrait = $this->start();
        $this->flow()->process($portrait->getId());
        foreach (['multiple', 'unsafe', 'invalid'] as $mode) {
            $this->resultMode = $mode;
            $this->flow()->process($portrait->getId());
            self::assertSame('review', $portrait->getStatus());
            self::assertNull($portrait->getResultReference());
            $this->client->request('GET', '/studio/personnages/idees/demandes/'.$portrait->getId());
            self::assertStringNotContainsString('https://127.0.0.1', $this->client->getResponse()->getContent());
            self::assertStringNotContainsString('https://v3b.fal.media', $this->client->getResponse()->getContent());
            $portrait = $this->action($portrait, 'resume');
            self::assertResponseStatusCodeSame(303);
        }
        $this->resultMode = '';
        $this->flow()->process($portrait->getId());
        self::assertSame('ready', $portrait->getStatus());
        self::assertCount(1, array_filter($this->calls, fn (array $c): bool => 'POST' === $c['method']));
    }

    public function testInterruptedDownloadCleansTemporaryFilesAndCanBeDiscarded(): void
    {
        $portrait = $this->start();
        $this->flow()->process($portrait->getId());
        $before = glob(sys_get_temp_dir().'/portrait-*') ?: [];
        $this->failure = 'download';
        $this->flow()->process($portrait->getId());
        self::assertSame('review', $portrait->getStatus());
        self::assertSame($before, glob(sys_get_temp_dir().'/portrait-*') ?: []);
        $this->failure = '';
        $this->flow()->resume($this->user(), $portrait->getId());
        $this->flow()->process($portrait->getId());
        self::assertSame('ready', $portrait->getStatus());
        $reference = $portrait->getResultReference();
        $storage = static::getContainer()->get(AiReferenceStorage::class);
        $path = $storage->path($this->user(), $reference);
        touch($path, time() - 40 * 86400);
        $sessions = sys_get_temp_dir().'/portrait-sessions-'.bin2hex(random_bytes(4));
        mkdir($sessions);
        try {
            $maintenance = new AiReferenceMaintenance($this->em, $storage, $sessions);
            self::assertSame(0, $maintenance->inspect($this->user(), true)['files']);
            self::assertFileExists($path);
            $portrait = $this->action($portrait, 'discard');
            self::assertResponseStatusCodeSame(303);
            $this->flow()->discard($this->user(), $portrait->getId());
            self::assertSame('discarded', $portrait->getStatus());
            self::assertNull($portrait->getResultReference());
            self::assertSame(1, $maintenance->inspect($this->user(), true)['files']);
            self::assertFileDoesNotExist($path);
            $this->client->request('GET', '/studio/personnages/idees/demandes/'.$portrait->getId().'/image');
            self::assertResponseStatusCodeSame(404);
            self::assertSame(0, $this->em->getRepository(AiCharacter::class)->count(['owner' => $this->user()]));
        } finally {
            rmdir($sessions);
        }
    }

    public function testExpiredTariffAndFullStoragePreventAnySubmission(): void
    {
        $portrait = $this->start();
        $this->clock->sleep(14 * 86400);
        $this->flow()->process($portrait->getId());
        self::assertSame('blocked', $portrait->getStatus());
        self::assertCount(0, $this->calls);
        $this->clock->modify('-14 days');
        $other = $this->start();
        $directory = dirname(__DIR__, 2).'/var/storage/ai-references/test/'.$this->owner;
        $path = $directory.'/'.str_repeat('a', 48).'.jpg';
        $handle = fopen($path, 'wb');
        ftruncate($handle, 104857600);
        fclose($handle);
        $this->flow()->process($other->getId());
        self::assertSame('blocked', $other->getStatus());
        self::assertCount(0, $this->calls);
        $draft = $this->draft();
        try {
            $this->flow()->confirm($this->user(), $draft->getId(), $draft->getQuote()['hash']);
            self::fail('Full storage should reject confirmation.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('100 Mo', $error->getMessage());
        }
    }

    public function testDisabledFlagStopsConfirmationAndWorkerEvenForQueuedRequest(): void
    {
        $portrait = $this->start();
        $disabled = new AiPortraits($this->em, static::getContainer()->get(\App\Service\AiPortraitQuotes::class), static::getContainer()->get(AiKeys::class), static::getContainer()->get(AiPortraitApi::class), static::getContainer()->get(AiReferenceStorage::class), static::getContainer()->get('messenger.default_bus'), $this->clock, false);
        $disabled->process($portrait->getId());
        self::assertSame('queued', $portrait->getStatus());
        self::assertCount(0, $this->calls);
        $draft = $this->draft();
        $this->expectException(\RuntimeException::class);
        $disabled->confirm($this->user(), $draft->getId(), $draft->getQuote()['hash']);
    }

    public function testStaleQuoteMissingConsentWrongStateAndNonEmptyRollbackAreRefused(): void
    {
        $draft = $this->draft();
        $old = $draft->getQuote()['hash'];
        $crawler = $this->client->request('GET', '/studio/personnages/idees/'.$draft->getId());
        $form = $crawler->selectButton('Confirmer la demande de portrait')->form();
        $this->client->request('POST', $form->getUri(), $form->getPhpValues());
        self::assertResponseStatusCodeSame(403);
        static::getContainer()->get(AiPortraitDrafts::class)->save($this->user(), $draft->getId(), ['name' => 'Changed', 'idea' => 'Une autre idée', 'appearance' => '', 'style' => '', 'customPrompt' => '', 'revision' => 1]);
        try {
            $this->flow()->confirm($this->user(), $draft->getId(), $old);
            self::fail('Old quote must not be confirmed.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('devis modifié', $error->getMessage());
        }
        $portrait = $this->start();
        foreach (['accept', 'discard'] as $action) {
            try {
                $this->flow()->$action($this->user(), $portrait->getId());
                self::fail('Unfinished portrait must not be used.');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('prêt', $error->getMessage());
            }
        }
        $next = $this->draft();
        try {
            $this->flow()->confirm($this->user(), $next->getId(), $next->getQuote()['hash']);
            self::fail('Only one active request per owner.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('reste à terminer', $error->getMessage());
        }
        require_once dirname(__DIR__, 2).'/migrations/Version20261105020000.php';
        $migration = new \DoctrineMigrations\Version20261105020000($this->em->getConnection(), new \Psr\Log\NullLogger());
        try {
            $migration->down(new \Doctrine\DBAL\Schema\Schema());
            self::fail('Non-empty request table rollback must be refused.');
        } catch (\Doctrine\Migrations\Exception\AbortMigration $error) {
            self::assertStringContainsString('Preserve existing', $error->getMessage());
        }
        self::assertCount(0, $this->calls);
    }

    public function testQueuedOlderTariffVersionCannotUseNewCatalogFreshness(): void
    {
        foreach (['checked' => '2026-11-04', 'model' => 'old-image-model', 'costMicros' => 20000] as $field => $old) {
            $draft = $this->draft();
            $snapshot = $draft->getQuote();
            $snapshot[$field] = $old;
            $portrait = new AiPortraitRequest($this->user(), $draft, $snapshot, $this->clock->now());
            $this->em->persist($portrait);
            $this->em->flush();
            $this->flow()->process($portrait->getId());
            self::assertSame('blocked', $portrait->getStatus());
        }
        self::assertCount(0, $this->calls);
    }

    public function testIdeaToAcceptedPortraitSceneAndFrozenVideoQuoteAcrossReplacement(): void
    {
        $crawler = $this->client->request('GET', '/studio/personnages/idees/nouveau');
        $form = $crawler->selectButton('Enregistrer le brouillon')->form();
        $values = $form->getPhpValues();
        $values['ai_portrait_draft'] = array_replace($values['ai_portrait_draft'], ['name' => 'Milo', 'idea' => 'Un chat explorateur dans un jardin.', 'appearance' => 'Orange cat wearing a blue jacket.', 'style' => 'Soft illustration.']);
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        $this->snap('journey-image-estimate');
        $form = $crawler->selectButton('Confirmer la demande de portrait')->form(['consent' => '1']);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        $portrait = $this->em->getRepository(AiPortraitRequest::class)->findOneBy(['owner' => $this->user()]);
        $portraitId = $portrait->getId();
        $this->flow()->process($portraitId);
        $this->flow()->process($portraitId);
        self::assertSame('ready', $portrait->getStatus());
        self::assertSame(0, $this->em->getRepository(AiCharacter::class)->count(['owner' => $this->user()]));
        $portrait = $this->action($portrait, 'accept');
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        $this->snap('journey-accepted-profile');
        $portrait = $this->em->find(AiPortraitRequest::class, $portraitId);
        $character = $portrait->getAcceptedCharacter();
        $characterId = $character->getId();
        $original = $character->getPortrait();
        $storage = static::getContainer()->get(AiReferenceStorage::class);
        $originalPath = $storage->path($this->user(), $original);
        $originalHash = hash_file('sha256', $originalPath);
        copy($originalPath, dirname(__DIR__, 2).'/artifacts/portrait-flow/journey-original.jpg');

        $prepare = function (int $revision, string $capture) use ($characterId): int {
            $crawler = $this->client->request('GET', '/studio/creation');
            self::assertSelectorExists('select[name="ai_idea[characterSelection][]"] option[value="'.$characterId.':'.$revision.'"]');
            $form = $crawler->selectButton('Voir le récapitulatif')->form();
            $values = $form->getPhpValues();
            $values['intent'] = 'compose';
            $values['ai_idea'] = array_replace($values['ai_idea'], ['prompt' => 'A cat explores a garden.', 'model' => 'vidu-q1-subject', 'source' => 'subject', 'seconds' => '5', 'target' => '10', 'planCount' => '2', 'ratio' => '9:16', 'accessMode' => 'api', 'characterSelectionManaged' => '1', 'characterSelection' => [$characterId.':'.$revision], 'shots' => [['prompt' => 'A cat walks slowly across the garden.', 'seconds' => '5', 'promptOnly' => 'manual', 'characterIdsManaged' => '1', 'characterIds' => [(string) $characterId]]]]);
            $values['ai_idea']['shots'][] = array_replace($values['ai_idea']['shots'][0], ['prompt' => 'The same cat looks up at a flower.']);
            $this->client->request('POST', $form->getUri(), $values);
            self::assertResponseStatusCodeSame(303);
            $this->client->followRedirect();
            $this->snap($capture);

            return $this->em->getRepository(\App\Entity\AiEpisode::class)->findOneBy(['owner' => $this->user()], ['id' => 'DESC'])->getId();
        };
        $firstId = $prepare(1, 'journey-original-video-estimate');
        $this->em->clear();
        $flow = static::getContainer()->get(\App\Service\AiEpisodeFlow::class);
        $first = $this->em->find(\App\Entity\AiEpisode::class, $firstId);
        $firstQuote = $flow->quote($first);
        self::assertSame(2, $firstQuote['count']);
        self::assertSame(10, $firstQuote['seconds']);
        self::assertSame([$original, $original], array_column($firstQuote['references'], 'start'));
        self::assertSame($original, $firstQuote['references'][0]['start']);
        self::assertSame(['Milo'], array_column($firstQuote['references'][0]['subjectDetails'], 'name'));
        self::assertSame([(string) $characterId], $flow->shots($first)[0]->getInputs()['characterIds']);
        self::assertSame('draft', $first->getFlowState());
        self::assertNull($first->getConsentHash());
        self::assertSame(0, $this->em->getRepository(\App\Entity\AiGeneration::class)->count(['owner' => $this->user()]));

        // The accepted file is retained while a deliberate profile update gets a new file.
        (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=blue:s=1024x1024', '-frames:v', '1', '-threads', '1', $this->image]))->mustRun();
        $characters = static::getContainer()->get(\App\Service\AiCharacters::class);
        $changed = $characters->save($this->user(), $characterId, 1, 'Milo updated', 'Blue cat wearing a green jacket.', new \Symfony\Component\HttpFoundation\File\UploadedFile($this->image, 'replacement.jpg', test: true), []);
        $replacement = $changed->getPortrait();
        copy($storage->path($this->user(), $replacement), dirname(__DIR__, 2).'/artifacts/portrait-flow/journey-replacement.jpg');
        self::assertNotSame($original, $replacement);
        self::assertNotSame($originalHash, hash_file('sha256', $storage->path($this->user(), $replacement)));
        self::assertSame($firstQuote, $flow->quote($first));
        $secondId = $prepare(2, 'journey-updated-video-estimate');
        $this->em->clear();
        $first = $this->em->find(\App\Entity\AiEpisode::class, $firstId);
        $second = $this->em->find(\App\Entity\AiEpisode::class, $secondId);
        $secondQuote = $flow->quote($second);
        self::assertSame([$replacement, $replacement], array_column($secondQuote['references'], 'start'));
        self::assertSame($replacement, $secondQuote['references'][0]['start']);
        self::assertSame(['Milo updated'], array_column($secondQuote['references'][0]['subjectDetails'], 'name'));
        self::assertNotSame($firstQuote['hash'], $secondQuote['hash']);
        self::assertSame($firstQuote, $flow->quote($first));

        $characters->archive($this->user(), $characterId, 2, true);
        $this->em->clear();
        foreach ([$firstId => $firstQuote, $secondId => $secondQuote] as $id => $quote) {
            $episode = $this->em->find(\App\Entity\AiEpisode::class, $id);
            self::assertSame($quote, $flow->quote($episode));
            self::assertSame('draft', $episode->getFlowState());
            self::assertNull($episode->getConsentHash());
        }
        $acceptedAgain = $this->flow()->accept($this->user(), $portraitId);
        self::assertSame($characterId, $acceptedAgain->getId());
        self::assertTrue($acceptedAgain->isArchived());
        self::assertSame($replacement, $acceptedAgain->getPortrait());
        self::assertSame(1, $this->em->getRepository(AiCharacter::class)->count(['owner' => $this->user()]));
        self::assertSame($originalHash, hash_file('sha256', $originalPath));

        $sessions = sys_get_temp_dir().'/portrait-journey-'.bin2hex(random_bytes(4));
        mkdir($sessions);
        try {
            touch($originalPath, time() - 40 * 86400);
            touch($storage->path($this->user(), $replacement), time() - 40 * 86400);
            self::assertSame(0, (new AiReferenceMaintenance($this->em, $storage, $sessions))->inspect($this->user(), true)['files']);
            self::assertFileExists($originalPath);
            self::assertFileExists($storage->path($this->user(), $replacement));
        } finally {
            rmdir($sessions);
        }
        self::assertSame(0, $this->em->getRepository(\App\Entity\AiGeneration::class)->count(['owner' => $this->user()]));
        self::assertCount(1, array_filter($this->calls, fn (array $call): bool => 'POST' === $call['method']));
        self::assertCount(4, $this->calls);
        file_put_contents(dirname(__DIR__, 2).'/artifacts/portrait-flow/journey-quotes.json', json_encode(['characterId' => $characterId, 'portraitRequest' => $portraitId, 'originalSha256' => $originalHash, 'firstQuote' => $firstQuote, 'secondQuote' => $secondQuote, 'acceptedSnapshot' => $this->em->find(AiPortraitRequest::class, $portraitId)->getSnapshot()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}
