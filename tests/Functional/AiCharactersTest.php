<?php

namespace App\Tests\Functional;

use App\Entity\AiCharacter;
use App\Entity\User;
use App\Service\AiCharacters;
use App\Service\AiReferenceMaintenance;
use App\Service\AiReferenceStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class AiCharactersTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $owner;
    private int $other;
    private array $temps = [];
    private array $rows = [];
    private array $counts;
    private int $http = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $this->em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $this->counts = $this->counts();
        $db->beginTransaction();
        foreach (['youtube', 'instagram', 'tiktok', 'ai', 'story'] as $provider) {
            static::getContainer()->set($provider.'.http', new MockHttpClient(function (): never {
                ++$this->http;
                throw new \LogicException('No HTTP allowed.');
            }));
        }
        foreach (['owner', 'other'] as $key) {
            $user = new User('character-'.$key.'-'.bin2hex(random_bytes(5)).'@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em->persist($user);
            $this->em->flush();
            $this->$key = $user->getId();
        }
        $this->login($this->owner);
        if (!is_dir(dirname(__DIR__, 2).'/artifacts/characters')) {
            mkdir(dirname(__DIR__, 2).'/artifacts/characters', 0775, true);
        }
    }

    private function counts(): array
    {
        $db = $this->em->getConnection();
        $rows = [];
        foreach ($db->fetchFirstColumn("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename") as $table) {
            $rows[$table] = (int) $db->fetchOne('SELECT COUNT(*) FROM '.$db->quoteIdentifier($table));
        }

        return $rows;
    }

    protected function tearDown(): void
    {
        try {
            $this->em->getConnection()->rollBack();
            $this->em->clear();
            self::assertSame($this->counts, $this->counts());
            self::assertSame(0, $this->http);
        } finally {
            $root = dirname(__DIR__, 2).'/var/storage/ai-references/test';
            foreach ([$this->owner, $this->other] as $id) {
                foreach (glob($root.'/'.$id.'/*.jpg') ?: [] as $path) {
                    if (is_file($path) && !is_link($path)) {
                        unlink($path);
                    }
                }if (is_dir($root.'/'.$id)) {
                    rmdir($root.'/'.$id);
                }
            }
            foreach ($this->temps as $path) {
                if (is_file($path)) {
                    unlink($path);
                } elseif (is_dir($path)) {
                    rmdir($path);
                }
            }
            file_put_contents(dirname(__DIR__, 2).'/artifacts/characters/'.$this->name().'.json', json_encode(['before' => $this->counts, 'after' => $this->counts(), 'http' => $this->http, 'screens' => $this->rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            parent::tearDown();
        }
    }

    private function login(int $id): void
    {
        $this->client->loginUser($this->em->find(User::class, $id));
    }

    private function image(bool $valid = true): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'character-');
        $this->temps[] = $path;
        if ($valid) {
            (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=purple:s=360x640', '-frames:v', '1', '-c:v', 'mjpeg', '-f', 'image2', $path]))->mustRun();
        } else {
            file_put_contents($path, 'not an image');
        }

        return new UploadedFile($path, 'portrait.jpg', 'image/jpeg', null, true);
    }

    private function snap(string $name): void
    {
        $html = $this->client->getResponse()->getContent();
        file_put_contents(dirname(__DIR__, 2).'/artifacts/characters/'.$name.'.html', $html);
        $this->rows[$name] = hash('sha256', $html);
    }

    private function create(): AiCharacter
    {
        $crawler = $this->client->request('GET', '/studio/personnages/nouveau');
        $form = $crawler->selectButton('Enregistrer le personnage')->form(['ai_character[name]' => 'Milo', 'ai_character[description]' => 'Chat roux en veste bleue.']);
        $this->client->request('POST', '/studio/personnages/nouveau', $form->getPhpValues(), ['ai_character' => ['portrait' => $this->image()]]);
        self::assertResponseStatusCodeSame(303);

        return $this->em->getRepository(AiCharacter::class)->findOneBy(['owner' => $this->owner]);
    }

    public function testCrudStaleFormsArchiveAndPrivateImages(): void
    {
        $this->client->request('GET', '/studio/personnages');
        self::assertResponseIsSuccessful();
        $this->snap('empty');
        $this->client->request('GET', '/studio/personnages/nouveau');
        $this->snap('new');
        $character = $this->create();
        $id = $character->getId();
        $old = $character->getPortrait();
        $this->client->request('GET', '/studio/personnages');
        $this->snap('list');
        self::assertSelectorTextContains('h2', 'Milo');
        self::assertSelectorCount(6, '.studio-bottom-nav > a');
        self::assertSelectorExists('.studio-bottom-nav a[href="/studio/creation"][aria-current="page"]');
        $this->client->request('GET', '/studio/personnages/'.$id.'/image/portrait');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $crawler = $this->client->request('GET', '/studio/personnages/'.$id);
        $this->snap('edit');
        $stale = $crawler->selectButton('Enregistrer le personnage')->form()->getPhpValues();
        $values = $stale;
        $values['ai_character']['name'] = 'Milo corrigé';
        $this->client->request('POST', '/studio/personnages/'.$id, $values, ['ai_character' => ['portrait' => $this->image(), 'views' => [$this->image()]]]);
        self::assertResponseStatusCodeSame(303);
        $character = $this->em->find(AiCharacter::class, $id);
        self::assertNotSame($old, $character->getPortrait());
        self::assertCount(1, $character->getViews());
        $storage = static::getContainer()->get(AiReferenceStorage::class);
        $owner = $this->em->find(User::class, $this->owner);
        self::assertFileExists($storage->path($owner, $old));
        copy($storage->path($owner, $character->getPortrait()), dirname(__DIR__, 2).'/artifacts/characters/portrait.jpg');
        $this->client->request('POST', '/studio/personnages/'.$id, $stale);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-error-message, .form-errors', 'changé');
        $this->client->request('GET', '/studio/personnages/'.$id.'/image/0');
        self::assertResponseIsSuccessful();
        $crawler = $this->client->request('GET', '/studio/personnages');
        $this->client->submit($crawler->selectButton('Archiver')->form());
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorNotExists('.character-card');
        $crawler = $this->client->request('GET', '/studio/personnages?archives=1');
        $this->snap('archives');
        $restore = $crawler->selectButton('Restaurer')->form();
        $this->client->request('GET', '/studio/personnages/'.$id);
        self::assertSelectorNotExists('input[type=file]');
        $this->client->request('GET', '/studio/personnages/'.$id.'/image/portrait');
        self::assertResponseIsSuccessful();
        $this->client->submit($restore);
        self::assertResponseStatusCodeSame(303);
        $this->login($this->other);
        foreach (['/studio/personnages/'.$id, '/studio/personnages/'.$id.'/image/portrait'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('POST', '/studio/personnages/'.$id, $stale);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/studio/personnages/'.$id.'/archiver');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/personnages');
        self::assertSelectorNotExists('.character-card');
    }

    public function testInvalidFilesKeepTextAndRollbackEveryNewImage(): void
    {
        $crawler = $this->client->request('GET', '/studio/personnages/nouveau');
        $values = $crawler->selectButton('Enregistrer le personnage')->form(['ai_character[name]' => 'À conserver'])->getPhpValues();
        $this->client->request('POST', '/studio/personnages/nouveau', $values, ['ai_character' => ['portrait' => $this->image(), 'views' => [$this->image(false)]]]);
        self::assertResponseStatusCodeSame(422);
        self::assertInputValueSame('ai_character[name]', 'À conserver');
        $this->snap('invalid');
        self::assertSame(0, $this->em->getRepository(AiCharacter::class)->count(['owner' => $this->owner]));
        self::assertSame([], glob(dirname(__DIR__, 2).'/var/storage/ai-references/test/'.$this->owner.'/*.jpg') ?: []);
        $values['ai_character']['_token'] = 'invalid';
        $this->client->request('POST', '/studio/personnages/nouveau', $values);
        self::assertResponseStatusCodeSame(422);
        $values['ai_character']['_token'] = $crawler->selectButton('Enregistrer le personnage')->form()->getPhpValues()['ai_character']['_token'];
        $this->client->request('POST', '/studio/personnages/nouveau', $values);
        self::assertResponseStatusCodeSame(422);
    }

    public function testMaintenancePinsArchivedAndReplacedImages(): void
    {
        $character = $this->create();
        $id = $character->getId();
        $old = $character->getPortrait();
        $owner = $this->em->find(User::class, $this->owner);
        $service = static::getContainer()->get(AiCharacters::class);
        $service->save($owner, $id, 1, 'Milo', 'Description', $this->image(), []);
        $service->archive($owner, $id, 2, true);
        $storage = static::getContainer()->get(AiReferenceStorage::class);
        $oldPath = $storage->path($owner, $old);
        touch($oldPath, time() - 40 * 86400);
        $orphan = $storage->store($owner, $this->image());
        $orphanPath = $storage->path($owner, $orphan);
        touch($orphanPath, time() - 40 * 86400);
        $sessions = sys_get_temp_dir().'/characters-'.bin2hex(random_bytes(6));
        mkdir($sessions);
        $this->temps[] = $sessions;
        $result = (new AiReferenceMaintenance($this->em, $storage, $sessions))->inspect($owner, true);
        self::assertSame(1, $result['files']);
        self::assertFileExists($oldPath);
        self::assertFileDoesNotExist($orphanPath);
        $this->client->request('POST', '/studio/personnages/'.$id.'/archiver', ['revision' => 3, 'archive' => 0, '_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testPaginationStaleArchiveAndFailedReplacementPreserveImages(): void
    {
        $character = $this->create();
        $id = $character->getId();
        $old = $character->getPortrait();
        $owner = $this->em->find(User::class, $this->owner);
        $service = static::getContainer()->get(AiCharacters::class);
        $storage = static::getContainer()->get(AiReferenceStorage::class);
        $folder = dirname($storage->path($owner, $old));
        $before = glob($folder.'/*.jpg');
        try {
            $service->save($owner, $id, 1, 'Remplacement refusé', '', $this->image(), [$this->image(), $this->image(false)]);
            self::fail('The last invalid view must refuse the entire replacement.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('JPG', $error->getMessage());
        }
        self::assertSame($before, glob($folder.'/*.jpg'));
        $this->em->refresh($character);
        self::assertSame($old, $character->getPortrait());
        self::assertSame(1, $character->getRevision());
        $character = $service->save($owner, $id, 1, 'Milo', '', null, [$this->image()]);
        $view = $character->getViews()[0];
        $service->save($owner, $id, 2, 'Milo', '', null, [], true);
        self::assertSame([], $character->getViews());
        self::assertFileExists($storage->path($owner, $view));
        for ($i = 0; $i < 12; ++$i) {
            $this->em->persist(new AiCharacter($owner, 'Fiche '.$i, '<script>texte</script>', $old));
        }
        $this->em->flush();
        $crawler = $this->client->request('GET', '/studio/personnages');
        self::assertSelectorCount(12, '.character-card');
        $this->client->request('GET', '/studio/personnages?page=999');
        self::assertSelectorCount(1, '.character-card');
        self::assertSelectorTextContains('nav[aria-label="Pages des personnages"]', 'Page 2 sur 2');
        $this->client->request('GET', '/studio/personnages/'.$id);
        $crawler = $this->client->request('GET', '/studio/personnages?page=2');
        $form = $crawler->selectButton('Archiver')->form();
        $values = $form->getPhpValues();
        $values['revision'] = 1;
        $this->client->request('POST', $form->getUri(), $values);
        self::assertResponseStatusCodeSame(303);
        self::assertFalse($this->em->find(AiCharacter::class, $id)->isArchived());
        $this->client->restart();
        $this->client->request('GET', '/studio/personnages');
        self::assertResponseRedirects('http://localhost/connexion');
    }
}
