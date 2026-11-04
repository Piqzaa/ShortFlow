<?php

namespace App\Tests\Functional;

use App\Entity\AiCharacter;
use App\Entity\AiPortraitDraft;
use App\Entity\User;
use App\Service\AiKeys;
use App\Service\AiPortraitDrafts;
use App\Service\AiPortraitQuotes;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

final class AiPortraitDraftsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private int $owner;
    private int $other;
    private int $http = 0;
    private array $before;
    private array $screens = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('shortflow_test', $this->em->getConnection()->fetchOne('SELECT current_database()'));
        $this->before = $this->counts();
        $this->em->getConnection()->beginTransaction();
        $this->clock = new MockClock('2026-11-04T23:00:00Z');
        static::getContainer()->set('app.publication_clock', $this->clock);
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['ai', 'story', 'youtube', 'instagram', 'tiktok'] as $provider) {
            static::getContainer()->set($provider.'.http', new MockHttpClient(function (): never {
                ++$this->http;
                throw new \LogicException('Portrait drafts cannot call providers.');
            }));
        }
        foreach (['owner', 'other'] as $field) {
            $user = new User('portrait-'.$field.'-'.bin2hex(random_bytes(6)).'@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em->persist($user);
            $this->em->flush();
            $this->$field = $user->getId();
        }
        $this->client->loginUser($this->user());
        if (!is_dir(dirname(__DIR__, 2).'/artifacts/portrait-drafts')) {
            mkdir(dirname(__DIR__, 2).'/artifacts/portrait-drafts', 0775, true);
        }
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
            $this->em->getConnection()->rollBack();
            $this->em->clear();
            $after = $this->counts();
            self::assertSame($this->before, $after);
            self::assertSame(0, $this->http);
            file_put_contents(dirname(__DIR__, 2).'/artifacts/portrait-drafts/'.$this->name().'.json', json_encode(['before' => $this->before, 'after' => $after, 'http' => $this->http, 'screens' => $this->screens], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } finally {
            parent::tearDown();
        }
    }

    private function user(): User
    {
        return $this->em->find(User::class, $this->owner);
    }

    private function values(): array
    {
        return ['name' => 'Milo', 'idea' => 'Un chat explorateur dans un jardin.', 'appearance' => 'Chat roux, veste bleue.', 'style' => 'Illustration douce.', 'customPrompt' => '', 'revision' => '0'];
    }

    private function save(): AiPortraitDraft
    {
        return static::getContainer()->get(AiPortraitDrafts::class)->save($this->user(), null, $this->values());
    }

    private function snap(string $name): void
    {
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        file_put_contents(dirname(__DIR__, 2).'/artifacts/portrait-drafts/'.$name.'.html', $html);
        $this->screens[$name] = hash('sha256', $html);
    }

    public function testNativeDraftFormCreatesSeparateEstimateWithoutCharacterOrProviderJob(): void
    {
        $this->client->request('GET', '/studio/personnages/idees');
        $this->snap('empty');
        $crawler = $this->client->request('GET', '/studio/personnages/idees/nouveau');
        $this->snap('new');
        $form = $crawler->selectButton('Enregistrer le brouillon')->form();
        $values = $form->getPhpValues();
        $values['ai_portrait_draft'] = array_replace($values['ai_portrait_draft'], $this->values());
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        $this->snap('saved-missing-key');
        self::assertSelectorTextContains('.portrait-estimate', '0,03 $ US');
        self::assertSelectorTextContains('.portrait-estimate', 'Aucune clé API');
        self::assertSelectorCount(6, '.studio-bottom-nav > a');
        self::assertSelectorExists('.studio-bottom-nav a[href="/studio/creation"][aria-current="page"]');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSelectorNotExists('button[name=intent][value=confirm]');
        self::assertSelectorTextContains('.portrait-prompt', 'Chat roux, veste bleue.');
        $draft = $this->em->getRepository(AiPortraitDraft::class)->findOneBy(['owner' => $this->owner]);
        $quote = $draft->getQuote();
        self::assertSame(AiPortraitQuotes::MODEL, $quote['model']);
        self::assertSame($draft->getId(), $quote['draftId']);
        self::assertSame($this->owner, $quote['ownerId']);
        self::assertSame(1, $quote['quantity']);
        self::assertSame(30000, $quote['costMicros']);
        self::assertSame(['width' => 1024, 'height' => 1024], $quote['input']['image_size']);
        self::assertTrue($quote['input']['enable_safety_checker']);
        self::assertFalse($quote['input']['sync_mode']);
        self::assertArrayNotHasKey('num_images', $quote['input']);
        self::assertNull($quote['connection']);
        self::assertSame(0, $this->em->getRepository(AiCharacter::class)->count(['owner' => $this->owner]));
        self::assertSame($this->before['ai_generation'], $this->counts()['ai_generation']);
        self::assertSame($this->before['messenger_messages'], $this->counts()['messenger_messages']);
        $this->client->request('GET', '/studio/personnages/idees');
        $this->snap('list');
    }

    public function testForeignCsrfStaleAndExtraFieldsAreRejectedWithoutLosingText(): void
    {
        $draft = $this->save();
        $url = '/studio/personnages/idees/'.$draft->getId();
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $stale = $values;
        $values['ai_portrait_draft']['name'] = 'Milo corrigé';
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(303);
        $stale['ai_portrait_draft']['idea'] = 'Ma saisie à récupérer.';
        $this->client->request('POST', $url, $stale);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('textarea[name="ai_portrait_draft[idea]"]');
        self::assertSelectorTextContains('textarea[name="ai_portrait_draft[idea]"]', 'Ma saisie à récupérer.');
        self::assertSelectorTextContains('.portrait-estimate', 'dernière version sauvegardée');
        $html = $this->client->getResponse()->getContent();
        file_put_contents(dirname(__DIR__, 2).'/artifacts/portrait-drafts/stale.html', $html);
        $this->screens['stale'] = hash('sha256', $html);
        $crawler = $this->client->request('GET', $url);
        $good = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $bad = $good;
        $bad['ai_portrait_draft']['_token'] = 'invalid';
        $this->client->request('POST', $url, $bad);
        self::assertResponseStatusCodeSame(422);
        $bad = $good;
        $bad['ai_portrait_draft']['costMicros'] = '1';
        $this->client->request('POST', $url, $bad);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/studio/personnages/idees/0', $good);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->em->find(User::class, $this->other));
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, $url, $good);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('GET', '/studio/personnages/idees');
        self::assertSelectorNotExists('.character-card');
    }

    public function testQuoteTracksKeyReplacementRemovalAndTextButGetDoesNotRewriteIt(): void
    {
        $draft = $this->save();
        $id = $draft->getId();
        $quotes = static::getContainer()->get(AiPortraitQuotes::class);
        self::assertSame('connection_missing', $quotes->status($draft));
        $keys = static::getContainer()->get(AiKeys::class);
        $keys->save($this->user(), 'portrait-synthetic-key-one-never-real-123456');
        self::assertSame('changed', $quotes->status($draft));
        $service = static::getContainer()->get(AiPortraitDrafts::class);
        $draft = $service->save($this->user(), $id, array_replace($this->values(), ['revision' => $draft->getRevision()]));
        $hash = $draft->getQuote()['hash'];
        self::assertSame('current', $quotes->status($draft));
        $this->client->request('GET', '/studio/personnages/idees/'.$id);
        $this->snap('saved-key');
        $draft = $this->em->find(AiPortraitDraft::class, $id);
        $keys->save($this->user(), 'portrait-synthetic-key-two-never-real-123456');
        self::assertSame('changed', $quotes->status($draft));
        $this->client->request('GET', '/studio/personnages/idees/'.$id);
        $this->snap('key-changed');
        $draft = $this->em->find(AiPortraitDraft::class, $id);
        self::assertSame($hash, $draft->getQuote()['hash']);
        $draft = $service->save($this->user(), $id, array_replace($this->values(), ['revision' => $draft->getRevision(), 'customPrompt' => 'One red cat on a plain background.']));
        self::assertNotSame($hash, $draft->getQuote()['hash']);
        self::assertSame('One red cat on a plain background.', $draft->getQuote()['input']['prompt']);
        $keys->remove($this->user());
        self::assertSame('changed', $quotes->status($draft));
    }

    public function testPriceBoundaryPreservesDraftAndNeverReissuesAnExpiredEstimate(): void
    {
        $quotes = static::getContainer()->get(AiPortraitQuotes::class);
        self::assertTrue($quotes->fresh());
        $draft = $this->save();
        $id = $draft->getId();
        $stored = $draft->getQuote();
        $this->clock->sleep(14 * 86400 - 1);
        self::assertTrue($quotes->fresh());
        $this->clock->sleep(1);
        self::assertFalse($quotes->fresh());
        self::assertSame('expired', $quotes->status($draft));
        $this->client->request('GET', '/studio/personnages/idees/'.$id);
        $this->snap('expired');
        $draft = $this->em->find(AiPortraitDraft::class, $id);
        self::assertSame($stored, $draft->getQuote());
        $saved = static::getContainer()->get(AiPortraitDrafts::class)->save($this->user(), $id, array_replace($this->values(), ['revision' => $draft->getRevision(), 'idea' => 'Description conservée malgré tarif périmé.']));
        self::assertNull($saved->getQuote());
        self::assertSame('Description conservée malgré tarif périmé.', $saved->getIdea());
        $this->client->request('GET', '/studio/personnages/idees/'.$id);
        $this->snap('expired-saved');
        self::assertSelectorTextContains('.portrait-prompt', 'Description conservée malgré tarif périmé.');
        $beforeDate = new AiPortraitQuotes(new MockClock('2026-11-04T22:59:59Z'), static::getContainer()->get(AiKeys::class));
        self::assertFalse($beforeDate->fresh());
        self::assertNull($beforeDate->build($this->em->find(AiPortraitDraft::class, $id)));
    }

    public function testMigrationRollbackRefusesNonEmptyDraftTable(): void
    {
        $this->save();
        require_once dirname(__DIR__, 2).'/migrations/Version20261105010000.php';
        $migration = new \DoctrineMigrations\Version20261105010000($this->em->getConnection(), new \Psr\Log\NullLogger());
        try {
            $migration->down(new \Doctrine\DBAL\Schema\Schema());
            self::fail('Non-empty draft table rollback must be refused.');
        } catch (\Doctrine\Migrations\Exception\AbortMigration $error) {
            self::assertStringContainsString('Preserve existing', $error->getMessage());
        }
    }

    public function testPaginationEscapingDraftIdentityAndAnonymousAccess(): void
    {
        $service = static::getContainer()->get(AiPortraitDrafts::class);
        $first = $this->save();
        $second = $this->save();
        self::assertNotSame($first->getQuote()['hash'], $second->getQuote()['hash']);
        for ($i = 0; $i < 11; ++$i) {
            $service->save($this->user(), null, array_replace($this->values(), ['name' => '<script>alert(1)</script>']));
        }
        $this->client->request('GET', '/studio/personnages/idees');
        self::assertSelectorCount(12, '.character-card');
        self::assertStringNotContainsString('<h2><script>', $this->client->getResponse()->getContent());
        $this->client->request('GET', '/studio/personnages/idees?page=999');
        self::assertSelectorCount(1, '.character-card');
        self::assertSelectorTextContains('nav[aria-label="Pages des idées"]', 'Page 2 sur 2');
        $this->client->restart();
        $this->client->request('GET', '/studio/personnages/idees');
        self::assertResponseRedirects('http://localhost/connexion');
    }
}
