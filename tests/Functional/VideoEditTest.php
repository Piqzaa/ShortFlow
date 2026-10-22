<?php

namespace App\Tests\Functional;

use App\Entity\AiGeneration;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\VideoEdit;
use App\Service\PrivateVideoStorage;
use App\Service\VideoEdits;
use App\Service\VideoLibrary;
use App\Service\VideoLibraryException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class VideoEditTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    private VideoAsset $imported;
    private VideoAsset $generated;
    /** @var list<string> */ private array $before = [];

    protected function setUp(): void
    {
        $_ENV['LOCAL_CAPTIONS_ENABLED'] = '1';
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        $storage = static::getContainer()->get(PrivateVideoStorage::class);
        self::assertStringEndsWith('/videos/test', $storage->directory());
        $this->before = glob($storage->directory().'/*') ?: [];
        foreach (['owner', 'other'] as $name) {
            $user = new User($name.'-edit@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $this->em()->flush();
        $this->imported = $this->source('red', false, 'Un clip importé');
        $this->generated = $this->source('blue', true, 'Un clip IA');
        $job = new AiGeneration($this->owner, 'Essai fictif', 'Une scène bleue', 'kling-25', 5, '9:16', 35);
        $job->complete($this->generated);
        $this->em()->persist($job);
        $this->em()->flush();
        $this->client->loginUser($this->owner);
    }

    protected function tearDown(): void
    {
        if (static::$booted) {
            $root = static::getContainer()->get(PrivateVideoStorage::class)->directory();
            foreach (array_diff(glob($root.'/*') ?: [], $this->before) as $path) {
                if (is_file($path) && !is_link($path)) {
                    unlink($path);
                }
            }
        }
        parent::tearDown();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function edits(): VideoEdits
    {
        return static::getContainer()->get(VideoEdits::class);
    }

    private function source(string $color, bool $audio, string $title): VideoAsset
    {
        $path = tempnam(sys_get_temp_dir(), 'edit-test-').'.mp4';
        $command = ['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c='.$color.':s=96x64:r=30:d=1'];
        if ($audio) {
            array_push($command, '-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=48000:duration=1');
        }
        array_push($command, '-c:v', 'libx264', '-threads', '1', '-pix_fmt', 'yuv420p');
        if ($audio) {
            array_push($command, '-c:a', 'aac');
        }
        $command[] = $path;
        (new Process($command))->mustRun();
        try {
            return static::getContainer()->get(VideoLibrary::class)->import($this->owner, new UploadedFile($path, 'source.mp4', null, null, true), $title);
        } finally {
            if (is_file($path)) {
                unlink($path);
            } @unlink(substr($path, 0, -4));
        }
    }

    private function project(): VideoEdit
    {
        return $this->edits()->create($this->owner, [$this->imported->getId(), $this->generated->getId()]);
    }

    /** @return list<array{id:int,start:float,end:float,position:int}> */
    private function cuts(): array
    {
        return [['id' => $this->imported->getId(), 'start' => 0.2, 'end' => 0.7, 'position' => 2], ['id' => $this->generated->getId(), 'start' => 0.1, 'end' => 0.6, 'position' => 1]];
    }

    public function testStudioIsFocusedAndBothSourcesShareTheMontageEntry(): void
    {
        $this->client->request('GET', '/studio/creation');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.ai-history');
        self::assertSelectorExists('a[href="/studio/creation/historique"]');
        self::assertSelectorNotExists('.creation-tools a[href="/studio/montage"]');
        foreach ([$this->imported, $this->generated] as $video) {
            $this->client->request('GET', '/studio/bibliotheque/'.$video->getId());
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('form[action="/studio/montage/video/'.$video->getId().'"] button');
            $form = $this->client->getCrawler()->selectButton('Éditer la vidéo')->form();
            $this->client->submit($form);
            self::assertResponseStatusCodeSame(303);
            $this->client->followRedirect();
            self::assertSelectorCount(1, '[data-edit-target=list] > .edit-clip');
        }
        $crawler = $this->client->request('GET', '/studio/montage');
        self::assertSelectorCount(2, '.edit-pick');
        $values = $crawler->selectButton('Continuer le montage →')->form()->getPhpValues();
        $values['videos'] = [$this->imported->getId(), $this->generated->getId()];
        $this->client->request('POST', '/studio/montage', $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorCount(2, '[data-edit-target=list] > .edit-clip');
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testSplittingAnUnchangedMovingShotPreservesEveryDecodedFrameAndAudio(): void
    {
        $base = tempnam(sys_get_temp_dir(), 'continuous-');
        $file = $base.'.mp4';
        (new Process(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'testsrc2=s=96x64:r=30:d=1', '-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=48000:duration=1', '-c:v', 'libx264', '-threads', '1', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-shortest', $file]))->mustRun();
        try {
            $source = static::getContainer()->get(VideoLibrary::class)->import($this->owner, new UploadedFile($file, 'motion.mp4', null, null, true), 'Plan en mouvement');
            $decoded = [];
            foreach ([false, true] as $split) {
                $project = $this->edits()->create($this->owner, [$source->getId()]);
                $clips = $split ? [['id' => $source->getId(), 'start' => 0, 'end' => 0.4, 'at' => 0], ['id' => $source->getId(), 'start' => 0.4, 'end' => 1, 'at' => 0.4]] : [['id' => $source->getId(), 'start' => 0, 'end' => 1, 'at' => 0]];
                $this->edits()->save($project, 'Continuité', '1:1', $clips, true);
                $this->edits()->process($project->getId());
                self::assertSame('ready', $project->getStatus(), (string) $project->getIssue());
                $path = static::getContainer()->get(PrivateVideoStorage::class)->path($project->getOutput()->getStorageName());
                $frames = new Process(['ffmpeg', '-v', 'error', '-i', $path, '-map', '0:v:0', '-f', 'framemd5', '-']);
                $frames->mustRun();
                $audio = new Process(['ffmpeg', '-v', 'error', '-i', $path, '-map', '0:a:0', '-f', 's16le', '-']);
                $audio->mustRun();
                $decoded[] = [$frames->getOutput(), hash('sha256', $audio->getOutput())];
            }
            self::assertSame($decoded[0], $decoded[1], 'Une simple coupe doit préserver toutes les images et les échantillons audio.');
        } finally {
            @unlink($file);
            @unlink($base);
        }
    }

    public function testFreeTimelineExportsGapsSpeedAndPerClipFilter(): void
    {
        $project = $this->project();
        $clips = [['id' => $this->imported->getId(), 'start' => 0.1, 'end' => 0.9, 'at' => 0.4, 'rate' => 2, 'look' => ['filter' => 'bw']], ['id' => $this->generated->getId(), 'start' => 0.1, 'end' => 0.6, 'at' => 1.3, 'rate' => 1]];
        $this->edits()->save($project, 'Espaces et vitesse', '1:1', $clips, true, array_replace($project->getSettings(), ['background' => '#204060']), texts: [['id' => 'headline', 'text' => 'Un titre', 'start' => 0.1, 'end' => 0.3, 'x' => 0.5, 'y' => 0.1, 'size' => 5, 'font' => 'anton']]);
        self::assertEqualsWithDelta(1.8, $project->getDuration(), 0.001);
        $this->edits()->process($project->getId());
        self::assertSame('ready', $project->getStatus(), (string) $project->getIssue());
        $output = static::getContainer()->get(PrivateVideoStorage::class)->path($project->getOutput()->getStorageName());
        foreach ([[0.1, 'gap'], [0.55, 'gray'], [1.05, 'gap'], [1.5, 'blue']] as [$time,$kind]) {
            $pixel = new Process(['ffmpeg', '-v', 'error', '-ss', (string) $time, '-i', $output, '-vf', 'crop=2:2:540:540,format=rgb24', '-frames:v', '1', '-f', 'rawvideo', '-']);
            $pixel->mustRun();
            $rgb = array_values(unpack('C3', $pixel->getOutput()));
            if ('gap' === $kind) {
                self::assertEqualsWithDelta(32, $rgb[0], 10);
                self::assertEqualsWithDelta(64, $rgb[1], 10);
                self::assertEqualsWithDelta(96, $rgb[2], 10);
            } elseif ('gray' === $kind) {
                self::assertLessThan(12, abs($rgb[0] - $rgb[1]));
                self::assertLessThan(12, abs($rgb[1] - $rgb[2]));
            } else {
                self::assertGreaterThan(140, $rgb[2]);
                self::assertLessThan(50, $rgb[0]);
            }
        }
    }

    public function testPersonalStylesMultipleMusicAndPrivateWaveforms(): void
    {
        $project = $this->project();
        $url = '/studio/montage/'.$project->getId();
        $crawler = $this->client->request('GET', $url);
        $token = $crawler->filter('[data-edit-styles-token-value]')->attr('data-edit-styles-token-value');
        $this->client->request('POST', '/studio/montage/styles/enregistrer', ['_token' => $token, 'name' => 'Mon habillage', 'style' => ['font' => 'serif-bold', 'color' => '#ab1234', 'align' => 'left', 'box' => '1']]);
        self::assertResponseIsSuccessful();
        $styles = json_decode($this->client->getResponse()->getContent(), true)['styles'];
        self::assertCount(1, $styles);
        self::assertSame('#ab1234', array_values($styles)[0]['style']['color']);
        $project = $this->edits()->owned($project->getId(), $this->owner);
        foreach ([1, 2] as $i) {
            $file = $this->musicFile();
            $this->edits()->attachMusic($project, $file, true);
            @unlink($file->getPathname());
        }
        self::assertCount(2, $project->getMusicFiles());
        self::assertCount(2, $project->getAudioClips());
        $first = array_key_first($project->getMusicFiles());
        $stored = $project->getMusicFiles()[$first];
        $path = static::getContainer()->get(\App\Service\EditMusic::class)->path($stored['name']);
        $waveforms = static::getContainer()->get(\App\Service\EditWaveforms::class);
        self::assertSame('pending', $waveforms->read($path)['status']);
        $waveforms->build($path);
        $wave = $waveforms->read($path);
        self::assertSame('ready', $wave['status']);
        self::assertCount(256, $wave['peaks']);
        self::assertGreaterThan(0.01, max($wave['peaks']));
        $this->client->request('GET', $url.'/onde?track='.$first);
        self::assertResponseIsSuccessful();
        $this->client->loginUser($this->other);
        $this->client->request('GET', $url.'/onde?track='.$first);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->owner);
        $project = $this->edits()->owned($project->getId(), $this->owner);
        $this->edits()->attachMusic($project, null, false, $first);
        self::assertCount(1, $project->getMusicFiles());
        self::assertCount(1, $project->getAudioClips());
    }

    public function testStillImageImportAddsPrivateClipAndRejectsFakeImage(): void
    {
        $project = $this->project();
        $url = '/studio/montage/'.$project->getId();
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Ajouter l’image')->form()->getPhpValues();
        $values['seconds'] = '1.2';
        $path = tempnam(sys_get_temp_dir(), 'still-').'.png';
        (new Process(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=green:s=64x64', '-frames:v', '1', '-threads', '1', $path]))->mustRun();
        try {
            $this->client->request('POST', $url.'/image', $values, ['image' => new UploadedFile($path, 'Carte.png', null, null, true)]);
            self::assertResponseStatusCodeSame(303);
            $project = $this->edits()->owned($project->getId(), $this->owner);
            self::assertCount(3, $project->getClips());
            self::assertEqualsWithDelta(3.2, $project->getDuration(), 0.1);
        } finally {
            @unlink($path);
            @unlink(substr($path, 0, -4));
        }
        $this->client->request('POST', $url.'/image', ['_token' => $values['_token'], 'solid' => '1', 'color' => '#123456', 'seconds' => '1']);
        self::assertResponseStatusCodeSame(303);
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertCount(4, $project->getClips());
        self::assertSame('Ton texte', $project->getTexts()[0]['text']);
        $fake = tempnam(sys_get_temp_dir(), 'fake-still-');
        file_put_contents($fake, 'not an image');
        try {
            $this->client->request('POST', $url.'/image', $values, ['image' => new UploadedFile($fake, 'fake.png', null, null, true)]);
            self::assertResponseStatusCodeSame(303);
            self::assertCount(4, $this->edits()->owned($project->getId(), $this->owner)->getClips());
        } finally {
            @unlink($fake);
        }
    }

    public function testThirtyTwoShortFragmentsCanActuallyRender(): void
    {
        $project = $this->project();
        $clips = [];
        for ($i = 0; $i < 32; ++$i) {
            $clips[] = ['id' => $this->imported->getId(), 'start' => 0.0, 'end' => 0.1, 'origin' => 'part-'.$i];
        }
        $this->edits()->save($project, '32 fragments', '1:1', $clips, true);
        $this->edits()->process($project->getId());
        self::assertSame('ready', $project->getStatus(), (string) $project->getIssue());
        self::assertEqualsWithDelta(3.2, $project->getOutput()->getDuration(), 0.2);
    }

    public function testAccessCsrfAndSourceIsolation(): void
    {
        $project = $this->project();
        $this->client->request('POST', '/studio/montage/video/'.$this->imported->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/studio/montage', ['videos' => [$this->imported->getId()]]);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->other);
        foreach (['', '/etat'] as $suffix) {
            $this->client->request('GET', '/studio/montage/'.$project->getId().$suffix);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('POST', '/studio/montage/video/'.$this->imported->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/bibliotheque/importer?montage='.$project->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/studio/montage/'.$project->getId().'/retirer');
        self::assertResponseStatusCodeSame(404);
        $crawler = $this->client->request('GET', '/studio/montage');
        self::assertSelectorNotExists('.edit-pick');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/studio/montage', ['_token' => $token, 'videos' => [$this->imported->getId()]]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->em()->getRepository(VideoEdit::class)->count([]));
        $this->client->getCookieJar()->clear();
        $this->client->restart();
        $this->client->request('GET', '/studio/montage');
        self::assertResponseRedirects('http://localhost/connexion');
    }

    public function testImportFromEditorReturnsToTheSameProject(): void
    {
        $project = $this->project();
        $id = $project->getId();
        $url = '/studio/bibliotheque/importer?next=edit&montage='.$id;
        $crawler = $this->client->request('GET', $url);
        self::assertSelectorExists('a[href="/studio/montage/'.$id.'"]');
        $form = $crawler->selectButton('Importer ma vidéo')->form(['video_upload[title]' => 'Un nouveau plan']);
        $path = tempnam(sys_get_temp_dir(), 'edit-upload-');
        copy(static::getContainer()->get(PrivateVideoStorage::class)->path($this->imported->getStorageName()), $path);
        try {
            $this->client->request('POST', $url, $form->getPhpValues(), ['video_upload' => ['file' => new UploadedFile($path, 'clip.mp4', null, null, true)]], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
            self::assertResponseStatusCodeSame(201);
            self::assertSame('/studio/montage/'.$id, json_decode($this->client->getResponse()->getContent(), true)['redirect']);
            $project = $this->edits()->owned($id, $this->owner);
            self::assertCount(3, $project->getClips());
            self::assertSame($this->imported->getId(), $project->getClips()[0]['id']);
            self::assertSame(1, $this->em()->getRepository(VideoEdit::class)->count([]));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testLongImportFromEditorExplainsRefusalWithoutChangingTheProject(): void
    {
        $project = $this->project();
        $url = '/studio/bibliotheque/importer?montage='.$project->getId();
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Importer ma vidéo')->form(['video_upload[title]' => 'Trop longue'])->getPhpValues();
        $path = tempnam(sys_get_temp_dir(), 'long-edit-').'.mp4';
        (new Process(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=red:s=96x64:r=1:d=181', '-c:v', 'libx264', '-threads', '1', '-pix_fmt', 'yuv420p', $path]))->mustRun();
        try {
            $this->client->request('POST', $url, $values, ['video_upload' => ['file' => new UploadedFile($path, 'long.mp4', null, null, true)]], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
            self::assertResponseStatusCodeSame(422);
            self::assertStringContainsString('3 minutes', json_decode($this->client->getResponse()->getContent(), true)['message']);
            self::assertSame(2, $this->em()->getRepository(VideoAsset::class)->count([]));
            self::assertCount(2, $this->edits()->owned($project->getId(), $this->owner)->getClips());
        } finally {
            @unlink($path);
            @unlink(substr($path, 0, -4));
        }
    }

    public function testFreeTextsAndIndividualCaptionPositionsAreSavedAndExported(): void
    {
        $project = $this->project();
        $url = '/studio/montage/'.$project->getId();
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Enregistrer le montage')->form()->getPhpValues();
        $values['intent'] = 'save';
        $values['texts'] = [['id' => 'heading', 'start' => 0, 'end' => 1, 'text' => '<Mon texte>', 'x' => 0.5, 'y' => 0.15, 'size' => 4, 'color' => '#ffffff']];
        $values['captions'] = [['start' => 0.1, 'end' => 0.9, 'text' => 'Paroles', 'x' => 0.5, 'y' => 0.85]];
        $values['captions_current'] = 1;
        $values['video_edit']['captionStyle'] = 'clean';
        $this->client->request('POST', $url, $values, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertSame('<Mon texte>', $project->getTexts()[0]['text']);
        self::assertEquals(0.85, $project->getCaptions()[0]['y']);
        $this->edits()->save($project, $project->getTitle(), $project->getFormat(), $project->getClips(), true);
        $this->edits()->process($project->getId());
        self::assertSame('ready', $project->getStatus());
        self::assertNotNull($project->getOutput());
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $crawler = $this->client->getCrawler();
        $this->client->submit($crawler->selectButton('Modifier une copie')->form());
        self::assertResponseStatusCodeSame(303);
        $copy = $this->em()->getRepository(VideoEdit::class)->findOneBy(['owner' => $project->getOwner(), 'status' => 'draft']);
        self::assertSame($project->getTexts(), $copy->getTexts());
    }

    public function testCutsOrderDraftAndExportConfirmation(): void
    {
        $project = $this->project();
        $id = $project->getId();
        $crawler = $this->client->request('GET', '/studio/montage/'.$id);
        $form = $crawler->selectButton('Enregistrer le montage')->form();
        $values = $form->getPhpValues();
        $values['clips'] = $this->cuts();
        $values['video_edit']['title'] = 'Mon histoire';
        $values['video_edit']['format'] = '16:9';
        $this->client->request('POST', '/studio/montage/'.$id, $values);
        self::assertResponseStatusCodeSame(303);
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertSame('draft', $project->getStatus());
        self::assertSame('16:9', $project->getFormat());
        self::assertSame($this->generated->getId(), $project->getClips()[0]['id']);
        $values['intent'] = 'export';
        unset($values['confirm']);
        $this->client->request('POST', '/studio/montage/'.$id, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.form-errors, .form-error-message, .account-form', 'Confirme l’export');
        $values['confirm'] = '1';
        $values['clips'][0]['start'] = -2;
        $this->client->request('POST', '/studio/montage/'.$id, $values);
        self::assertResponseStatusCodeSame(422);
        $values['clips'] = $this->cuts();
        unset($values['video_edit']['_token']);
        $this->client->request('POST', '/studio/montage/'.$id, $values);
        self::assertResponseStatusCodeSame(422);
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertSame('draft', $project->getStatus());
    }

    public function testRealLocalExportJoinsImportedAndAiClipsWithAudioAndIsIdempotent(): void
    {
        $project = $this->project();
        $this->edits()->save($project, 'Notre montage', '9:16', $this->cuts(), true);
        self::assertSame('queued', $project->getStatus());
        self::assertSame('video_edits', static::getContainer()->get(Connection::class)->fetchOne('SELECT queue_name FROM messenger_messages LIMIT 1'));
        $this->edits()->save($project, 'Notre montage', '9:16', $this->cuts(), true);
        self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->client->request('GET', '/studio/montage/'.$project->getId().'/etat');
        self::assertResponseIsSuccessful();
        self::assertSame('queued', json_decode($this->client->getResponse()->getContent(), true)['status']);
        $this->edits()->process($project->getId());
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertSame('ready', $project->getStatus(), (string) $project->getIssue());
        $output = $project->getOutput();
        self::assertNotNull($output);
        self::assertSame(1080, $output->getWidth());
        self::assertSame(1920, $output->getHeight());
        self::assertEqualsWithDelta(1.0, $output->getDuration(), 0.15);
        $storage = static::getContainer()->get(PrivateVideoStorage::class);
        $path = $storage->path($output->getStorageName());
        $probe = new Process(['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_type,codec_name', '-of', 'json', $path]);
        $probe->mustRun();
        self::assertStringContainsString('aac', $probe->getOutput());
        self::assertStringContainsString('h264', $probe->getOutput());
        foreach ([['0.2', true], ['0.8', false]] as [$time, $blue]) {
            $pixel = new Process(['ffmpeg', '-v', 'error', '-ss', $time, '-i', $path, '-vf', 'crop=2:2:540:960,format=rgb24', '-frames:v', '1', '-f', 'rawvideo', '-']);
            $pixel->mustRun();
            $bytes = $pixel->getOutput();
            self::assertGreaterThanOrEqual(3, strlen($bytes));
            self::assertTrue($blue ? ord($bytes[2]) > ord($bytes[0]) : ord($bytes[0]) > ord($bytes[2]), 'The exported clips keep their selected order.');
        }
        self::assertFileExists($storage->path($this->imported->getStorageName()));
        self::assertFileExists($storage->path($this->generated->getStorageName()));
        $this->edits()->process($project->getId());
        self::assertSame(3, $this->em()->getRepository(VideoAsset::class)->count([]));
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        self::assertSelectorExists('.ai-result-player');
        $this->client->submit($crawler->selectButton('Modifier une copie')->form());
        self::assertResponseStatusCodeSame(303);
        self::assertSame(2, $this->em()->getRepository(VideoEdit::class)->count([]));
        $this->client->request('GET', '/studio/bibliotheque/'.$output->getId().'/telecharger');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'video/mp4');
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        $this->client->submit($crawler->selectButton('Retirer le projet')->form(['confirm' => '1']));
        self::assertResponseStatusCodeSame(303);
        self::assertSame(3, $this->em()->getRepository(VideoAsset::class)->count([]));
        self::assertFileExists($path);
    }

    public function testActiveExportProtectsSourcesAndCannotBeQueuedTwice(): void
    {
        $project = $this->project();
        $this->edits()->save($project, 'Export', '1:1', $this->cuts(), true);
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        self::assertSelectorExists('[data-controller="edit-watch"]');
        self::assertSelectorNotExists('button[name="intent"]');
        try {
            static::getContainer()->get(VideoLibrary::class)->delete($this->imported);
            self::fail('Active sources must be protected.');
        } catch (VideoLibraryException $error) {
            self::assertStringContainsString('export de montage', $error->getMessage());
        }
        self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testUnavailableSourceFailsWithoutAddingAnAssetAndCanBeRemoved(): void
    {
        $project = $this->project();
        $this->edits()->save($project, 'Export', '1:1', $this->cuts(), true);
        static::getContainer()->get(PrivateVideoStorage::class)->discard($this->imported->getStorageName());
        $this->edits()->process($project->getId());
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertSame('failed', $project->getStatus());
        self::assertSame(2, $this->em()->getRepository(VideoAsset::class)->count([]));
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        self::assertSelectorTextContains('[role="alert"]', 'clip source');
        $this->client->submit($crawler->selectButton('Retirer le projet')->form(['confirm' => '1']));
        self::assertResponseStatusCodeSame(303);
        self::assertSame(0, $this->em()->getRepository(VideoEdit::class)->count([]));
        self::assertSame(2, $this->em()->getRepository(VideoAsset::class)->count([]));
    }

    public function testInvalidRangesForeignSourcesAndOversizedSelectionsNeverQueue(): void
    {
        $project = $this->project();
        $foreign = new VideoAsset($this->other, 'Privé', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1, 1, 96, 64, 'h264');
        $this->em()->persist($foreign);
        $this->em()->flush();
        $invalid = [
            [['id' => $this->imported->getId(), 'start' => 0, 'end' => 2]],
            [['id' => $this->imported->getId(), 'start' => 'INF', 'end' => 1]],
            [['id' => $this->imported->getId(), 'start' => 0.5, 'end' => 0.5]],
            [['id' => $foreign->getId(), 'start' => 0, 'end' => 1]],
            [['id' => $this->imported->getId(), 'start' => 0, 'end' => 1, 'omit' => '1']],
            array_fill(0, 33, $this->cuts()[0]),
        ];
        foreach ($invalid as $clips) {
            try {
                $this->edits()->save($project, 'Vérification', '9:16', $clips, true);
                self::fail('Invalid cuts must be refused.');
            } catch (VideoLibraryException) {
                self::assertSame('draft', $project->getStatus());
            }
        }
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->edits()->save($project, 'Un seul clip', '1:1', [$this->cuts()[0], $this->cuts()[1] + ['omit' => '1']], false);
        self::assertCount(1, $project->getClips());
    }

    public function testExportRequiresAvailableStorage(): void
    {
        $project = $this->project();
        $full = new VideoAsset($this->owner, 'Occupation fictive', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', VideoLibrary::MAX_STORAGE, 1, 96, 64, 'h264');
        $this->em()->persist($full);
        $this->em()->flush();
        try {
            $this->edits()->save($project, 'Export', '9:16', $this->cuts(), true);
            self::fail('Quota must be checked before export.');
        } catch (VideoLibraryException $error) {
            self::assertStringContainsString('100 Mo', $error->getMessage());
        }
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testLocalCaptionsQueueProducesEditableTextWithoutCreatingAVideo(): void
    {
        $speech = new class implements \App\Service\SpeechTranscriber {
            public int $calls = 0;

            public function transcribe(string $audio, string $language = 'fr'): array
            {
                ++$this->calls;
                if (!is_file($audio) || 'fr' !== $language) {
                    throw new \RuntimeException('Bad local input.');
                }

                return [['start' => 0.0, 'end' => 0.5, 'text' => 'Bonjour VydFlow'], ['start' => 0.5, 'end' => 1.0, 'text' => 'Une vidéo courte.']];
            }
        };
        static::getContainer()->set(\App\Service\SpeechTranscriber::class, $speech);
        $project = $this->project();
        $this->edits()->save($project, 'La voix', '9:16', $this->cuts(), false);
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        $this->client->submit($crawler->selectButton('Générer les sous-titres')->form());
        self::assertResponseStatusCodeSame(303);
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertSame('transcribing', $project->getStatus());
        $service = static::getContainer()->get(\App\Service\VideoTranscriptions::class);
        $service->process($project->getId());
        self::assertSame('draft', $project->getStatus());
        self::assertCount(2, $project->getCaptions());
        self::assertSame(2, $this->em()->getRepository(VideoAsset::class)->count([]));
        $service->process($project->getId());
        self::assertSame(1, $speech->calls);
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        self::assertSelectorTextContains('[data-caption-text]', 'Bonjour VydFlow');
        $this->client->request('GET', '/studio/montage/'.$project->getId().'/sous-titres.srt');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('00:00:00,000 --> 00:00:00,500', $this->client->getResponse()->getContent());
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/montage/'.$project->getId().'/sous-titres.srt');
        self::assertResponseStatusCodeSame(404);
    }

    public function testInlineTranscriptionKeepsEditorAndFailureKeepsCorrections(): void
    {
        static::getContainer()->set(\App\Service\SpeechTranscriber::class, new class implements \App\Service\SpeechTranscriber {
            public function transcribe(string $audio, string $language = 'fr'): array
            {
                throw new \RuntimeException('Synthetic failure');
            }
        });
        $project = $this->project();
        $this->edits()->save($project, 'Ma voix', '9:16', $project->getClips(), false, null, [['start' => 0.1, 'end' => 0.6, 'text' => 'Correction conservée']]);
        $url = '/studio/montage/'.$project->getId();
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Régénérer les sous-titres')->form()->getPhpValues();
        $values['intent'] = 'captions';
        $this->client->request('POST', $url, $values, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(202);
        $state = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('transcribing', $state['status']);
        self::assertSame('Correction conservée', $state['captions'][0]['text']);
        $this->client->request('GET', $url);
        self::assertSelectorExists('.editor-desk[data-edit-transcribing-value="true"]');
        self::assertSelectorExists('[data-edit-target="timeline"]');
        self::assertSelectorExists('[data-edit-target="stage"]');
        self::assertSelectorNotExists('[data-controller="edit-watch"]');
        static::getContainer()->get(\App\Service\VideoTranscriptions::class)->process($project->getId());
        $this->client->request('GET', $url.'/etat');
        $state = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('failed', $state['status']);
        self::assertSame('Correction conservée', $state['captions'][0]['text']);
        $values['intent'] = 'save';
        $this->client->request('POST', $url, $values, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('autre onglet', json_decode($this->client->getResponse()->getContent(), true)['message']);
        $this->client->loginUser($this->other);
        $this->client->request('GET', $url.'/etat');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRealOfflineTranscriberReturnsAnEditableDraftForSourcesWithoutSpeech(): void
    {
        $project = $this->project();
        $this->edits()->save($project, 'Sans paroles', '9:16', $this->cuts(), false);
        $service = static::getContainer()->get(\App\Service\VideoTranscriptions::class);
        $service->queue($project);
        $service->process($project->getId());
        self::assertSame('draft', $project->getStatus(), (string) $project->getIssue());
        self::assertSame([], $project->getCaptions());
        self::assertStringContainsString('Aucune parole', $project->getIssue());
        self::assertSame(2, $this->em()->getRepository(VideoAsset::class)->count([]));
    }

    public function testAutomaticRecipePreparesCaptionsAndFramingWithoutExporting(): void
    {
        $project = $this->project();
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        $form = $crawler->selectButton('Préparer l’assemblage ✦')->form();
        $values = $form->getPhpValues();
        $values['autoCaptions'] = '1';
        $this->client->request('POST', '/studio/montage/'.$project->getId(), $values);
        self::assertResponseStatusCodeSame(303);
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertSame('transcribing', $project->getStatus());
        self::assertSame('9:16', $project->getFormat());
        self::assertSame('contain', $project->getSettings()['fit']);
        self::assertFalse($project->getSettings()['intro']);
        self::assertNull($project->getOutput());
    }

    public function testAutomaticAssemblyWithoutCaptionsDoesNotRunTranscriptionOrChangeFraming(): void
    {
        $project = $this->project();
        $this->edits()->save($project, 'Mes plans', '16:9', $project->getClips(), false);
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        $this->client->submit($crawler->selectButton('Préparer l’assemblage ✦')->form());
        self::assertResponseStatusCodeSame(303);
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertSame('draft', $project->getStatus());
        self::assertSame('16:9', $project->getFormat());
        self::assertSame('off', $project->getSettings()['captionStyle']);
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testSplitPiecesOfTheSameSourceCanBeReorderedAndExported(): void
    {
        $project = $this->project();
        $id = $this->imported->getId();
        $pieces = [['id' => $id, 'start' => 0.6, 'end' => 1.0], ['id' => $id, 'start' => 0.0, 'end' => 0.4]];
        $this->edits()->save($project, 'Deux morceaux', '1:1', $pieces, true);
        $this->edits()->process($project->getId());
        self::assertSame('ready', $project->getStatus(), (string) $project->getIssue());
        self::assertEquals($pieces, array_map(static fn (array $clip): array => array_intersect_key($clip, array_flip(['id', 'start', 'end'])), $project->getClips()));
        self::assertEqualsWithDelta(0.8, $project->getOutput()->getDuration(), 0.1);
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        $this->client->submit($crawler->selectButton('Modifier une copie')->form());
        self::assertResponseStatusCodeSame(303);
        $copy = $this->em()->getRepository(VideoEdit::class)->findOneBy(['title' => 'Deux morceaux · copie']);
        self::assertEquals($pieces, array_map(static fn (array $clip): array => array_intersect_key($clip, array_flip(['id', 'start', 'end'])), $copy->getClips()));
    }

    private function musicFile(): UploadedFile
    {
        $path = sys_get_temp_dir().'/edit-music-'.bin2hex(random_bytes(8)).'.wav';
        (new Process(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'sine=frequency=880:sample_rate=48000:duration=0.4', $path]))->mustRun();

        return new UploadedFile($path, 'Ma musique.wav', 'audio/wav', null, true);
    }

    public function testPrivateMusicUploadMixesLoopsAndPreservesTheSources(): void
    {
        $project = $this->project();
        $file = $this->musicFile();
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        $form = $crawler->selectButton('Importer la piste')->form();
        $values = $form->getPhpValues();
        $values['rights'] = '1';
        $this->client->request('POST', '/studio/montage/'.$project->getId().'/musique', $values, ['music' => $file]);
        @unlink($file->getPathname());
        self::assertResponseStatusCodeSame(303);
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertSame('Ma musique.wav', $project->getMusicTitle());
        $name = $project->getMusicName();
        $music = static::getContainer()->get(\App\Service\EditMusic::class);
        self::assertFileExists($music->path($name));
        $this->client->request('GET', '/studio/montage/'.$project->getId().'/musique/lecture');
        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'), (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/montage/'.$project->getId().'/musique/lecture');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/studio/montage/'.$project->getId().'/musique', ['remove' => '1']);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->owner);
        $this->client->request('POST', '/studio/montage/'.$project->getId().'/musique', ['remove' => '1']);
        self::assertResponseStatusCodeSame(403);
        $cuts = [['id' => $this->imported->getId(), 'start' => 0.0, 'end' => 0.9]];
        $project = $this->edits()->owned($project->getId(), $this->owner);
        $this->edits()->save($project, 'Avec musique', '1:1', $cuts, true, array_replace($project->getSettings(), ['sourceVolume' => 0, 'musicVolume' => 100, 'musicLoop' => true]));
        $this->edits()->process($project->getId());
        self::assertSame('ready', $project->getStatus(), (string) $project->getIssue());
        $output = static::getContainer()->get(PrivateVideoStorage::class)->path($project->getOutput()->getStorageName());
        $audio = new Process(['ffmpeg', '-v', 'error', '-ss', '0.6', '-i', $output, '-t', '0.1', '-vn', '-ac', '1', '-f', 's16le', '-']);
        $audio->mustRun();
        $samples = unpack('v*', $audio->getOutput());
        self::assertGreaterThan(500, max(array_map(static fn (int $sample): int => abs($sample > 32767 ? $sample - 65536 : $sample), $samples)));
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        $this->client->submit($crawler->selectButton('Modifier une copie')->form());
        $copy = $this->em()->getRepository(VideoEdit::class)->findOneBy(['title' => 'Avec musique · copie']);
        self::assertNotSame($name, $copy->getMusicName());
        $copyMusic = $copy->getMusicName();
        $this->edits()->attachMusic($copy, null, false);
        self::assertFileDoesNotExist($music->path($copyMusic));
        self::assertFileExists($music->path($name));
        $project = $this->edits()->owned($project->getId(), $this->owner);
        $this->edits()->remove($project);
        self::assertFileDoesNotExist($music->path($name));
        self::assertFileExists($output);
    }

    public function testIndependentAudioFragmentsKeepTheirTimingAndRejectForeignSources(): void
    {
        $project = $this->project();
        $file = $this->musicFile();
        $this->edits()->attachMusic($project, $file, true);
        @unlink($file->getPathname());
        $url = '/studio/montage/'.$project->getId();
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Enregistrer le montage')->form()->getPhpValues();
        $values['intent'] = 'save';
        $values['audio_current'] = '1';
        $values['video_edit']['sourceVolume'] = 0;
        $rows = [
            ['id' => 'musical', 'kind' => 'music', 'source' => 0, 'start' => 0.2, 'end' => 0.6, 'offset' => 0.05, 'volume' => 80, 'loop' => '1'],
            ['id' => 'detached', 'kind' => 'video', 'source' => $this->generated->getId(), 'start' => 1, 'end' => 1.5, 'offset' => 0.1, 'volume' => 70, 'loop' => '0'],
        ];
        $values['audio'] = $rows;
        $this->client->request('POST', $url, $values, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertCount(2, $project->getAudioClips());
        self::assertEquals(1, $project->getAudioClips()[1]['start']);
        $foreign = new VideoAsset($this->em()->find(User::class, $this->other->getId()), 'Son étranger', str_repeat('a', 48).'.mp4', 'video/mp4', 100, 5, 96, 64, 'h264');
        $this->em()->persist($foreign);
        $this->em()->flush();
        $bad = $values;
        $bad['edit_revision'] = $project->getRevision();
        $bad['audio'][1]['source'] = $foreign->getId();
        $this->client->request('POST', $url, $bad, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('indisponible', $this->client->getResponse()->getContent());
        $bad['audio'] = $rows;
        $bad['audio'][1]['offset'] = 0.9;
        $this->client->request('POST', $url, $bad, server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('dépasse', json_decode($this->client->getResponse()->getContent(), true)['message']);
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertEquals(0.1, $project->getAudioClips()[1]['offset']);
        $extractor = static::getContainer()->get(\App\Service\EditAudio::class);
        $speechFile = $extractor->extract($project);
        try {
            foreach ([[0.3, false], [1.15, true]] as [$time, $audible]) {
                $sample = new Process(['ffmpeg', '-v', 'error', '-ss', (string) $time, '-i', $speechFile, '-t', '0.05', '-f', 's16le', '-']);
                $sample->mustRun();
                $peak = max(array_map(static fn (int $value): int => abs($value > 32767 ? $value - 65536 : $value), unpack('v*', $sample->getOutput())));
                if ($audible) {
                    self::assertGreaterThan(400, $peak);
                } else {
                    self::assertLessThan(150, $peak, 'La musique est exclue de la transcription.');
                }
            }
        } finally {
            $extractor->discard($speechFile);
        }
        $this->edits()->save($project, 'Son indépendant', '1:1', $project->getClips(), true);
        $this->edits()->process($project->getId());
        self::assertSame('ready', $project->getStatus(), (string) $project->getIssue());
        $output = static::getContainer()->get(PrivateVideoStorage::class)->path($project->getOutput()->getStorageName());
        foreach ([[0.05, false], [0.3, true], [0.8, false], [1.15, true], [1.8, false]] as [$time, $audible]) {
            $audio = new Process(['ffmpeg', '-v', 'error', '-ss', (string) $time, '-i', $output, '-t', '0.05', '-vn', '-ac', '1', '-f', 's16le', '-']);
            $audio->mustRun();
            $samples = unpack('v*', $audio->getOutput());
            $peak = max(array_map(static fn (int $value): int => abs($value > 32767 ? $value - 65536 : $value), $samples));
            if ($audible) {
                self::assertGreaterThan(400, $peak, 'Son attendu à '.$time);
            } else {
                self::assertLessThan(150, $peak, 'Silence attendu à '.$time);
            }
        }
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Modifier une copie')->form());
        $copy = $this->em()->getRepository(VideoEdit::class)->findOneBy(['title' => 'Son indépendant · copie']);
        self::assertSame($project->getAudioClips(), $copy->getAudioClips());
        $this->edits()->attachMusic($copy, null, false);
        self::assertCount(1, $copy->getAudioClips());
        self::assertSame('video', $copy->getAudioClips()[0]['kind']);
    }

    public function testMusicRejectsFakeAudioAndRequiresRightsAndValidOffsets(): void
    {
        $project = $this->project();
        $file = $this->musicFile();
        try {
            $this->edits()->attachMusic($project, $file, false);
            self::fail('Rights must be confirmed.');
        } catch (VideoLibraryException $error) {
            self::assertStringContainsString('droits', $error->getMessage());
        }
        $this->edits()->attachMusic($project, $file, true);
        @unlink($file->getPathname());
        $name = $project->getMusicName();
        try {
            $this->edits()->save($project, 'Audio', '9:16', $project->getClips(), false, array_replace($project->getSettings(), ['musicOffset' => 500]));
            self::fail('Offset beyond duration.');
        } catch (VideoLibraryException $error) {
            self::assertStringContainsString('intérieur', $error->getMessage());
        }
        $bad = tempnam(sys_get_temp_dir(), 'bad-audio');
        file_put_contents($bad, 'not audio');
        try {
            $this->edits()->attachMusic($project, new UploadedFile($bad, 'fake.mp3', null, null, true), true);
            self::fail('Fake audio accepted.');
        } catch (VideoLibraryException $error) {
            self::assertStringContainsString('uniquement du son', $error->getMessage());
        } finally {
            @unlink($bad);
        }
        self::assertSame($name, $project->getMusicName());
        $this->edits()->attachMusic($project, null, false);
        self::assertNull($project->getMusicName());
    }

    public function testCaptionTimingAndCorrectedTextFollowCuts(): void
    {
        $project = $this->project();
        $rows = [['start' => 0.0, 'end' => 0.7, 'text' => '<script>Texte privé</script>']];
        $settings = ['fit' => 'cover', 'captionStyle' => 'clean', 'intro' => false, 'language' => 'fr'];
        $this->edits()->save($project, 'Un titre', '9:16', $project->getClips(), false, $settings, $rows);
        $this->em()->clear();
        $project = $this->edits()->owned($project->getId(), $this->owner);
        $this->edits()->save($project, 'Un autre titre', '16:9', $project->getClips(), false);
        self::assertCount(1, $project->getCaptions());
        $this->client->request('GET', '/studio/montage/'.$project->getId());
        self::assertStringNotContainsString('<script>Texte privé</script>', $this->client->getResponse()->getContent());
        $project = $this->edits()->owned($project->getId(), $this->owner);
        $this->edits()->save($project, 'Des coupes', '16:9', $this->cuts(), false);
        self::assertEquals([['start' => 0.5, 'end' => 1.0, 'text' => '<script>Texte privé</script>']], $project->getCaptions());
        $project->caption([]);
        $this->em()->flush();
        try {
            $this->edits()->save($project, 'Des coupes', '16:9', $project->getClips(), true);
            self::fail('Missing captions must block styled export.');
        } catch (VideoLibraryException $error) {
            self::assertStringContainsString('sous-titres', $error->getMessage());
        }
    }

    public function testStyledExportBurnsCaptionsAndAnOpeningTitleWithoutTouchingSources(): void
    {
        $project = $this->project();
        $this->edits()->save($project, 'Le titre', '9:16', $this->cuts(), false);
        $this->edits()->save($project, 'Le titre', '9:16', $project->getClips(), true, ['fit' => 'cover', 'captionStyle' => 'clean', 'intro' => true, 'language' => 'fr'], [['start' => 0.0, 'end' => 0.9, 'text' => 'Bonjour VydFlow']]);
        $this->edits()->process($project->getId());
        self::assertSame('ready', $project->getStatus(), (string) $project->getIssue());
        self::assertNotNull($project->getOutput());
        self::assertSame(1080, $project->getOutput()->getWidth());
        self::assertSame(3, $this->em()->getRepository(VideoAsset::class)->count([]));
        $path = static::getContainer()->get(PrivateVideoStorage::class)->path($project->getOutput()->getStorageName());
        $frame = new Process(['ffmpeg', '-v', 'error', '-ss', '0.2', '-i', $path, '-vf', 'crop=1080:400:0:1300,format=rgb24', '-frames:v', '1', '-f', 'rawvideo', '-']);
        $frame->mustRun();
        $pixels = $frame->getOutput();
        $white = false;
        for ($i = 0; $i + 2 < strlen($pixels); $i += 3) {
            if (ord($pixels[$i]) > 210 && ord($pixels[$i + 1]) > 210 && ord($pixels[$i + 2]) > 210) {
                $white = true;
                break;
            }
        }
        self::assertTrue($white, 'Caption glyphs must be visible in the exported lower band.');
    }

    public function testSearchAndAddAnotherOwnedClipWithoutLosingSavedCuts(): void
    {
        $project = $this->edits()->create($this->owner, [$this->imported->getId()]);
        $this->edits()->save($project, 'À compléter', '16:9', [['id' => $this->imported->getId(), 'start' => 0.2, 'end' => 0.8]], false);
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId().'?q=IA');
        self::assertSelectorTextContains('.edit-add-list', 'Un clip IA');
        self::assertSelectorCount(1, '.edit-add-list form');
        $this->client->submit($crawler->filter('.edit-add-list form')->last()->selectButton('Ajouter')->form());
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.account-notice', 'Clip ajouté');
        $project = $this->edits()->owned($project->getId(), $this->owner);
        self::assertCount(2, $project->getClips());
        self::assertEquals(0.2, $project->getClips()[0]['start']);
        self::assertSame('16:9', $project->getFormat());
    }
}
