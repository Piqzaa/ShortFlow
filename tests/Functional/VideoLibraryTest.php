<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\VideoAsset;
use App\Repository\UserRepository;
use App\Repository\VideoAssetRepository;
use App\Service\PrivateVideoStorage;
use App\Service\VideoLibrary;
use App\Service\VideoLibraryException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class VideoLibraryTest extends WebTestCase
{
    private KernelBrowser $client;
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Keep one kernel per test; Symfony still resets services between HTTP requests.
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $root = static::getContainer()->get(PrivateVideoStorage::class)->directory();
        self::assertStringEndsWith('/var/storage/videos/test', str_replace('\\', '/', (string) realpath($root)));
        foreach (glob($root.'/*') ?: [] as $path) {
            if (preg_match('/^[a-f0-9]{48}\.(mp4|webm)(\.deleted-[a-f0-9]{16})?$/D', basename($path)) && !is_link($path)) {
                unlink($path);
            }
        }
        $db->executeStatement('DELETE FROM video_asset');
        $db->executeStatement('DELETE FROM account_token');
        $db->executeStatement('DELETE FROM app_user');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        foreach (['alice@example.test', 'bob@example.test'] as $email) {
            $user = new User($email);
            $user->setPassword('unused-by-loginUser');
            $user->verify();
            $em->persist($user);
        }
        $em->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    public function testBetaLandingRoutesAnonymousAndConfirmedAccounts(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.nav .button', 'Créer mon compte');
        self::assertSelectorExists('.nav .button[href="/inscription"]');
        self::assertSelectorNotExists('input[name="waitlist[email]"]');
        $this->asUser('alice@example.test');
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/studio');
        $this->client->followRedirect();
        self::assertSelectorExists('.studio-sidebar .wordmark[href="/studio"]');
        self::assertSelectorNotExists('.studio-footer a[href="/"]');
        $this->client->request('GET', '/studio/bibliotheque');
        self::assertSelectorTextContains('h2', 'Ton prochain flow');
        self::assertSelectorExists('a[href="/studio/bibliotheque/importer"]');
    }

    public function testAnonymousAndUnverifiedCannotReachLibrary(): void
    {
        foreach (['/studio/bibliotheque', '/studio/bibliotheque/importer', '/studio/bibliotheque/1/lecture'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseRedirects('http://localhost/connexion');
        }
        $user = static::getContainer()->get(UserRepository::class)->findByEmail('alice@example.test');
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE app_user SET verified = false WHERE id = ?', [$user->getId()]);
        static::getContainer()->get(EntityManagerInterface::class)->refresh($user);
        $this->client->loginUser($user);
        $this->client->request('GET', '/studio/bibliotheque');
        self::assertResponseStatusCodeSame(403);
    }

    public function testMp4UploadPersistsMetadataAndSupportsRanges(): void
    {
        $this->asUser('alice@example.test');
        $video = $this->upload('tests/Fixtures/library-valid.mp4', 'Mon premier clip');
        self::assertSame('video/mp4', $video->getMimeType());
        self::assertSame('h264', $video->getCodec());
        self::assertSame(96, $video->getWidth());
        self::assertSame(160, $video->getHeight());
        self::assertGreaterThan(0, $video->getDuration());
        self::assertMatchesRegularExpression('/^[a-f0-9]{48}\.mp4$/D', $video->getStorageName());
        $path = static::getContainer()->get(PrivateVideoStorage::class)->path($video->getStorageName());
        self::assertFileExists($path);
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Mon premier clip');
        self::assertSelectorExists('video[controls]');
        $id = $video->getId();
        $this->client->request('GET', '/studio/bibliotheque/'.$id.'/lecture', server: ['HTTP_RANGE' => 'bytes=0-15']);
        self::assertResponseStatusCodeSame(206);
        self::assertResponseHeaderSame('Content-Range', 'bytes 0-15/'.$video->getByteSize());
        self::assertResponseHeaderSame('Content-Length', '16');
        self::assertResponseHeaderSame('Content-Type', 'video/mp4');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSame(substr(file_get_contents($path), 0, 16), $this->client->getInternalResponse()->getContent());
        $this->client->request('HEAD', '/studio/bibliotheque/'.$id.'/lecture');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Length', (string) $video->getByteSize());
        self::assertSame('', $this->client->getInternalResponse()->getContent());
        $this->client->request('GET', '/studio/bibliotheque/'.$id.'/lecture', server: ['HTTP_RANGE' => 'bytes=9999999-10000000']);
        self::assertResponseStatusCodeSame(416);
        $this->client->request('GET', '/var/storage/videos/test/'.$video->getStorageName());
        self::assertResponseStatusCodeSame(404);
    }

    public function testWebmUploadAndDefaultName(): void
    {
        $this->asUser('alice@example.test');
        $video = $this->upload('public/media/films/tiktok.webm', '', '../../mon clip.webm');
        self::assertSame('mon clip', $video->getTitle());
        self::assertSame('video/webm', $video->getMimeType());
        self::assertSame('vp9', $video->getCodec());
    }

    public function testOwnerIsolationForListReadRenameAndDelete(): void
    {
        $this->asUser('alice@example.test');
        $video = $this->upload('tests/Fixtures/library-valid.mp4', 'Confidentiel Alice');
        $id = $video->getId();
        $this->asUser('bob@example.test');
        $this->client->request('GET', '/studio/bibliotheque');
        self::assertSelectorTextNotContains('main', 'Confidentiel Alice');
        $this->client->request('GET', '/studio');
        self::assertSelectorTextNotContains('main', 'Confidentiel Alice');
        foreach (['', '/lecture', '/supprimer'] as $suffix) {
            $this->client->request('GET', '/studio/bibliotheque/'.$id.$suffix);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('POST', '/studio/bibliotheque/'.$id, ['video_rename' => ['title' => 'Intrusion']]);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/studio/bibliotheque/'.$id.'/supprimer', ['_token' => 'fake']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('Confidentiel Alice', static::getContainer()->get(VideoAssetRepository::class)->find($id)->getTitle());
    }

    public function testRenameEscapesHtmlAndDeleteRequiresConfirmationCsrf(): void
    {
        $this->asUser('alice@example.test');
        $video = $this->upload('tests/Fixtures/library-valid.mp4');
        $id = $video->getId();
        $name = $video->getStorageName();
        $this->client->request('POST', '/studio/bibliotheque/'.$id, ['video_rename' => ['title' => 'Sans CSRF']]);
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->request('GET', '/studio/bibliotheque/'.$id);
        $title = '<script>alert(1)</script>';
        $this->client->submit($crawler->selectButton('Enregistrer le nom')->form(['video_rename[title]' => $title]));
        self::assertResponseRedirects('/studio/bibliotheque/'.$id, 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', $title);
        self::assertSelectorNotExists('h1 script');
        $this->client->request('POST', '/studio/bibliotheque/'.$id.'/supprimer');
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/studio/bibliotheque/'.$id.'/supprimer');
        self::assertResponseIsSuccessful();
        self::assertNotNull(static::getContainer()->get(VideoAssetRepository::class)->find($id));
        $this->client->submit($crawler->selectButton('Supprimer définitivement')->form());
        self::assertResponseRedirects('/studio/bibliotheque', 303);
        self::assertNull(static::getContainer()->get(VideoAssetRepository::class)->find($id));
        self::assertFileDoesNotExist(static::getContainer()->get(PrivateVideoStorage::class)->path($name));
        $this->client->request('GET', '/studio/bibliotheque/'.$id.'/lecture');
        self::assertResponseStatusCodeSame(404);
    }

    public function testFakeExtensionEmptyCorruptAndOversizedFilesAreRejected(): void
    {
        $this->asUser('alice@example.test');
        $path = $this->temporaryPath();
        file_put_contents($path, '<?php echo "not a video";');
        foreach ([$this->fixture('tests/Fixtures/library-valid.mp4', 'fake.webm'), new UploadedFile($path, 'fake.mp4', 'video/mp4', null, true)] as $file) {
            $this->submitUpload($file);
            self::assertResponseStatusCodeSame(422);
        }
        $empty = $this->temporaryPath();
        touch($empty);
        $this->submitUpload(new UploadedFile($empty, 'empty.mp4', 'video/mp4', null, true));
        self::assertResponseStatusCodeSame(422);
        $corrupt = $this->temporaryPath();
        file_put_contents($corrupt, substr(file_get_contents(__DIR__.'/../Fixtures/library-valid.mp4'), 0, 40));
        $this->submitUpload(new UploadedFile($corrupt, 'truncated.mp4', 'video/mp4', null, true));
        self::assertResponseStatusCodeSame(422);
        $large = $this->temporaryPath();
        $handle = fopen($large, 'w');
        ftruncate($handle, VideoLibrary::MAX_BYTES + 1);
        fclose($handle);
        $this->submitUpload(new UploadedFile($large, 'too-big.mp4', 'video/mp4', null, true));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, static::getContainer()->get(VideoAssetRepository::class)->count([]));
        self::assertSame([], glob(static::getContainer()->get(PrivateVideoStorage::class)->directory().'/*'));
    }

    public function testUploadCsrfAndStorageQuota(): void
    {
        $this->asUser('alice@example.test');
        $this->client->request('POST', '/studio/bibliotheque/importer', ['video_upload' => ['title' => 'No CSRF']], ['video_upload' => ['file' => $this->fixture('tests/Fixtures/library-valid.mp4')]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, static::getContainer()->get(VideoAssetRepository::class)->count([]));
        $video = $this->upload('tests/Fixtures/library-valid.mp4');
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE video_asset SET byte_size = ? WHERE id = ?', [VideoLibrary::MAX_STORAGE, $video->getId()]);
        $this->submitUpload($this->fixture('tests/Fixtures/library-valid.mp4'));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.upload-form', 'a atteint sa limite');
        self::assertSame(1, static::getContainer()->get(VideoAssetRepository::class)->count([]));
        self::assertCount(1, glob(static::getContainer()->get(PrivateVideoStorage::class)->directory().'/*'));
    }

    public function testTraversalStorageNameAndMissingFilesAreUnavailable(): void
    {
        $this->asUser('alice@example.test');
        $video = $this->upload('tests/Fixtures/library-valid.mp4');
        $id = $video->getId();
        $storage = static::getContainer()->get(PrivateVideoStorage::class);
        unlink($storage->path($video->getStorageName()));
        $this->client->request('GET', '/studio/bibliotheque/'.$id.'/lecture');
        self::assertResponseStatusCodeSame(404);
        static::getContainer()->get(Connection::class)->executeStatement('UPDATE video_asset SET storage_name = ? WHERE id = ?', ['../../.env.local', $id]);
        $this->client->request('GET', '/studio/bibliotheque/'.$id.'/lecture');
        self::assertResponseStatusCodeSame(404);
        $this->expectException(VideoLibraryException::class);
        static::getContainer()->get(PrivateVideoStorage::class)->path('../../.env.local');
    }

    public function testPaginationAndUploadRateLimit(): void
    {
        $this->asUser('alice@example.test');
        $user = static::getContainer()->get(UserRepository::class)->findByEmail('alice@example.test');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        for ($i = 0; $i < 13; ++$i) {
            $em->persist(new VideoAsset($user, 'Vidéo '.$i, bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 100, 1.0, 96, 160, 'h264'));
        }
        $em->flush();
        $this->client->request('GET', '/studio/bibliotheque');
        self::assertSelectorCount(12, '.video-card');
        $this->client->request('GET', '/studio/bibliotheque?page=2');
        self::assertSelectorCount(1, '.video-card');
        for ($i = 0; $i < 11; ++$i) {
            $this->client->request('POST', '/studio/bibliotheque/importer', ['video_upload' => ['title' => 'invalid']]);
            self::assertResponseStatusCodeSame(10 === $i ? 429 : 422);
        }
    }

    public function testProgressiveUploadReturnsOnlyAnAuthorizedRedirect(): void
    {
        $this->asUser('alice@example.test');
        $crawler = $this->client->request('GET', '/studio/bibliotheque/importer');
        $values = $crawler->selectButton('Importer ma vidéo')->form()->getPhpValues();
        $this->client->request('POST', '/studio/bibliotheque/importer', $values, ['video_upload' => ['file' => $this->fixture('tests/Fixtures/library-valid.mp4')]], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true, 8, JSON_THROW_ON_ERROR);
        self::assertSame(['redirect'], array_keys($data));
        self::assertMatchesRegularExpression('#^/studio/bibliotheque/\d+$#D', $data['redirect']);
        $this->client->request('GET', $data['redirect']);
        self::assertResponseIsSuccessful();
    }

    public function testVideoCountQuotaAndCleanupNeverPurgesReferencedFiles(): void
    {
        $this->asUser('alice@example.test');
        $user = static::getContainer()->get(UserRepository::class)->findByEmail('alice@example.test');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $liveName = bin2hex(random_bytes(24)).'.mp4';
        for ($i = 0; $i < VideoLibrary::MAX_VIDEOS; ++$i) {
            $em->persist(new VideoAsset($user, 'Clip '.$i, 0 === $i ? $liveName : bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 100, 1.0, 96, 160, 'h264'));
        }
        $em->flush();
        $this->submitUpload($this->fixture('tests/Fixtures/library-valid.mp4'));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.upload-form', 'a atteint sa limite');
        $storage = static::getContainer()->get(PrivateVideoStorage::class);
        $active = $storage->directory().'/'.$liveName.'.deleted-'.bin2hex(random_bytes(8));
        $orphan = $storage->directory().'/'.bin2hex(random_bytes(24)).'.mp4.deleted-'.bin2hex(random_bytes(8));
        foreach ([$active, $orphan] as $path) {
            file_put_contents($path, 'test');
            touch($path, time() - 90000);
        }
        self::assertSame(1, $storage->cleanupTrash(static::getContainer()->get(VideoAssetRepository::class)));
        self::assertFileExists($active);
        self::assertFileDoesNotExist($orphan);
    }

    private function asUser(string $email): void
    {
        $this->client->loginUser(static::getContainer()->get(UserRepository::class)->findByEmail($email));
    }

    private function upload(string $source, string $title = 'Ma vidéo', ?string $name = null): VideoAsset
    {
        $this->submitUpload($this->fixture($source, $name), $title);
        self::assertResponseStatusCodeSame(303);
        $video = static::getContainer()->get(VideoAssetRepository::class)->findOneBy([], ['id' => 'DESC']);
        self::assertNotNull($video);

        return $video;
    }

    private function submitUpload(UploadedFile $file, string $title = 'Ma vidéo'): void
    {
        $crawler = $this->client->request('GET', '/studio/bibliotheque/importer');
        $form = $crawler->selectButton('Importer ma vidéo')->form(['video_upload[title]' => $title]);
        $this->client->request('POST', '/studio/bibliotheque/importer', $form->getPhpValues(), ['video_upload' => ['file' => $file]]);
    }

    private function fixture(string $source, ?string $name = null): UploadedFile
    {
        $path = $this->temporaryPath();
        copy(dirname(__DIR__, 2).'/'.$source, $path);

        return new UploadedFile($path, $name ?? basename($source), null, null, true);
    }

    private function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vydflow-test-');
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
