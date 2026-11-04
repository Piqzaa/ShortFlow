<?php

namespace App\Tests\Functional;

use App\Entity\AiCharacter;
use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\User;
use App\Service\AiCharacters;
use App\Service\AiEpisodeFlow;
use App\Service\AiGenerations;
use App\Service\AiKeys;
use App\Service\AiReferenceStorage;
use App\Service\AiSeriesStudio;
use App\Service\PrivateVideoStorage;
use App\Service\VideoEdits;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class CharacterJourneyTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private int $ownerId;
    private array $oldEnv = [];
    private array $before;
    private array $filesBefore;
    private string $videoDirectory;
    private string $out;
    private string $clip;
    private array $temporary = [];
    private array $calls = [];
    private array $posts = [];
    private array $routes = [];
    private array $snapshots = [];
    private array $images = [];
    private array $media = [];
    private int $unexpected = 0;
    private bool $failPoll = false;
    private bool $uncertain = false;
    private bool $completed = false;

    protected function setUp(): void
    {
        foreach (['_ENV', '_SERVER'] as $source) {
            $this->oldEnv[$source] = $GLOBALS[$source]['AI_GENERATION_ENABLED'] ?? null;
            $GLOBALS[$source]['AI_GENERATION_ENABLED'] = '1';
        }
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('shortflow_test', $this->em->getConnection()->fetchOne('SELECT current_database()'));
        $this->before = $this->counts();
        $this->em->getConnection()->beginTransaction();
        $this->videoDirectory = static::getContainer()->get(PrivateVideoStorage::class)->directory();
        self::assertStringEndsWith('/var/storage/videos/test', $this->videoDirectory);
        $this->filesBefore = glob($this->videoDirectory.'/*') ?: [];
        foreach (['youtube', 'instagram', 'tiktok', 'story'] as $provider) {
            static::getContainer()->set($provider.'.http', new MockHttpClient(function (): never {
                ++$this->unexpected;
                throw new \LogicException('Unexpected HTTP in character journey.');
            }));
        }
        static::getContainer()->set('ai.http', new MockHttpClient($this->respond(...)));
        static::getContainer()->get('cache.rate_limiter')->clear();
        $owner = new User('characters-journey-'.bin2hex(random_bytes(6)).'@example.test');
        $owner->verify();
        $owner->setPassword('unused');
        $this->em->persist($owner);
        $this->em->flush();
        $this->ownerId = $owner->getId();
        static::getContainer()->get(AiKeys::class)->save($owner, 'characters-synthetic-never-real-1234567890');
        $this->em->flush();
        $this->client->loginUser($owner);
        $this->out = dirname(__DIR__, 2).'/artifacts/character-journey/'.$this->name();
        if (!is_dir($this->out)) {
            mkdir($this->out, 0775, true);
        }
        file_put_contents($this->out.'/report.json', '{"completed":false}');
        $this->clip = tempnam(sys_get_temp_dir(), 'character-clip-');
        $this->temporary[] = $this->clip;
        (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=purple:s=360x640:r=10', '-t', '5', '-c:v', 'libx264', '-threads', '1', '-preset', 'ultrafast', '-pix_fmt', 'yuv420p', '-f', 'mp4', $this->clip]))->mustRun();
        copy($this->clip, $this->out.'/synthetic-clip.mp4');
        $this->media['synthetic-clip.mp4'] = ['sha256' => hash_file('sha256', $this->clip), 'seconds' => 5, 'width' => 360, 'height' => 640];
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
            try {
                $this->em->getConnection()->rollBack();
                $this->em->clear();
                $after = $this->counts();
            } finally {
                foreach (array_diff(glob($this->videoDirectory.'/*') ?: [], $this->filesBefore) as $file) {
                    if (is_file($file) && !is_link($file)) {
                        unlink($file);
                    }
                }
                $directory = dirname(__DIR__, 2).'/var/storage/ai-references/test/'.$this->ownerId;
                foreach (glob($directory.'/*.jpg') ?: [] as $file) {
                    unlink($file);
                }
                if (is_dir($directory)) {
                    rmdir($directory);
                }
                foreach ($this->temporary as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            self::assertSame($this->before, $after);
            self::assertSame($this->filesBefore, glob($this->videoDirectory.'/*') ?: []);
            self::assertDirectoryDoesNotExist($directory);
            self::assertSame(0, $this->unexpected);
            file_put_contents($this->out.'/report.json', json_encode(['completed' => $this->completed, 'before' => $this->before, 'after' => $after, 'filesRestored' => true, 'unexpectedHttp' => $this->unexpected, 'http' => $this->calls, 'posts' => $this->posts, 'images' => $this->images, 'media' => $this->media, 'snapshots' => $this->snapshots], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } finally {
            foreach ($this->oldEnv as $source => $value) {
                if (null === $value) {
                    unset($GLOBALS[$source]['AI_GENERATION_ENABLED']);
                } else {
                    $GLOBALS[$source]['AI_GENERATION_ENABLED'] = $value;
                }
            }
            parent::tearDown();
        }
    }

    private function respond(string $method, string $url, array $options): MockResponse
    {
        $this->calls[] = ['method' => $method, 'url' => $url];
        if ('POST' === $method && in_array($url, ['https://queue.fal.run/fal-ai/vidu/q1/reference-to-video', 'https://queue.fal.run/fal-ai/kling-video/v3/pro/image-to-video'], true)) {
            $body = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
            $this->posts[] = $this->redact($body);
            if ($this->uncertain) {
                throw new \RuntimeException('Synthetic ambiguous submission.');
            }
            $remote = $url.'/requests/characters-'.count($this->posts);
            $this->routes[$remote.'/status'] = 'status';
            $this->routes[$remote] = 'response';

            return new MockResponse(json_encode(['status_url' => $remote.'/status', 'response_url' => $remote], JSON_THROW_ON_ERROR));
        }
        if ('GET' === $method && 'status' === ($this->routes[$url] ?? null)) {
            if ($this->failPoll) {
                $this->failPoll = false;

                return new MockResponse('{"error":"synthetic unavailable"}', ['http_code' => 503]);
            }

            return new MockResponse('{"status":"COMPLETED"}');
        }
        if ('GET' === $method && 'response' === ($this->routes[$url] ?? null)) {
            return new MockResponse('{"video":{"url":"https://v3.fal.media/files/characters-synthetic.mp4"}}');
        }
        if ('GET' === $method && 'https://v3.fal.media/files/characters-synthetic.mp4' === $url) {
            return new MockResponse(file_get_contents($this->clip));
        }
        ++$this->unexpected;
        throw new \LogicException('Unlisted simulated HTTP.');
    }

    private function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->redact(...), $value);
        }
        if (is_string($value) && str_starts_with($value, 'data:image/jpeg;base64,')) {
            $bytes = base64_decode(substr($value, strlen('data:image/jpeg;base64,')), true);
            self::assertNotFalse($bytes);
            $size = getimagesizefromstring($bytes);
            self::assertNotFalse($size);

            return ['sha256' => hash('sha256', $bytes), 'bytes' => strlen($bytes), 'width' => $size[0], 'height' => $size[1]];
        }

        return $value;
    }

    private function owner(): User
    {
        return $this->em->find(User::class, $this->ownerId);
    }

    private function flow(): AiEpisodeFlow
    {
        return static::getContainer()->get(AiEpisodeFlow::class);
    }

    /** @return list<array<string,mixed>> */
    private function submitted(): array
    {
        return $this->posts;
    }

    private function image(string $color): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'character-image-');
        $this->temporary[] = $file;
        (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c='.$color.':s=360x640', '-frames:v', '1', '-c:v', 'mjpeg', '-threads', '1', '-f', 'image2', $file]))->mustRun();

        return new UploadedFile($file, 'synthetic.jpg', 'image/jpeg', null, true);
    }

    private function cast(bool $views = false): array
    {
        $characters = static::getContainer()->get(AiCharacters::class);
        $milo = $characters->save($this->owner(), null, 0, 'Milo', 'Ginger cat, blue coat.', $this->image('red'), $views ? [$this->image('orange')] : []);
        $lea = $characters->save($this->owner(), null, 0, 'Lea', 'White cat, green coat.', $this->image('green'), $views ? [$this->image('yellow')] : []);
        foreach ([$milo, $lea] as $character) {
            foreach ([$character->getPortrait(), ...$character->getViews()] as $image) {
                $this->hash($image);
            }
        }

        return [$milo, $lea];
    }

    private function hash(string $name): string
    {
        $hash = hash_file('sha256', static::getContainer()->get(AiReferenceStorage::class)->path($this->owner(), $name));
        $this->images[$name] = $hash;
        copy(static::getContainer()->get(AiReferenceStorage::class)->path($this->owner(), $name), $this->out.'/reference-'.$hash.'.jpg');

        return $hash;
    }

    private function snapshot(string $name): void
    {
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        file_put_contents($this->out.'/'.$name.'.html', $html);
        $this->snapshots[$name] = hash('sha256', $html);
    }

    private function prepare(?AiEpisode $episode, string $model, array $cast, array $presence, ?string $frame = null): AiEpisode
    {
        $url = $episode ? '/studio/series/episodes/'.$episode->getId() : '/studio/creation';
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $values = $crawler->selectButton('Voir le récapitulatif')->form()->getPhpValues();
        $values['intent'] = 'compose';
        $values['ai_idea'] = array_replace($values['ai_idea'], ['title' => 'Garden characters', 'prompt' => 'Cats explore a quiet garden.', 'model' => $model, 'source' => 'vidu-q1-subject' === $model ? 'subject' : 'image', 'seconds' => '5', 'target' => (string) (5 * count($presence)), 'planCount' => count($presence) > 1 ? (string) count($presence) : '', 'ratio' => '9:16', 'accessMode' => 'api', 'characterSelectionManaged' => '1', 'characterSelection' => array_map(static fn (AiCharacter $c): string => $c->getId().':'.$c->getRevision(), $cast), 'shots' => []]);
        foreach ($presence as $ids) {
            $values['ai_idea']['shots'][] = ['prompt' => 'A cat walks slowly across the garden.', 'promptOnly' => 'manual', 'seconds' => '5', 'characterIdsManaged' => '1', 'characterIds' => array_map('strval', $ids), 'startReference' => $frame];
        }
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(303);
        $path = $this->client->getResponse()->headers->get('Location');
        preg_match('~/episodes/([0-9]+)~', $path, $match);

        return $this->em->find(AiEpisode::class, (int) $match[1]);
    }

    private function complete(AiEpisode $episode): void
    {
        foreach ($this->flow()->shots($episode) as $shot) {
            $this->flow()->advance($episode->getId());
            self::assertNotNull($shot->getGeneration(), (string) $episode->getFlowIssue());
            $job = $shot->getGeneration();
            $service = static::getContainer()->get(AiGenerations::class);
            $service->process($job->getId());
            $service->process($job->getId());
            $this->em->refresh($job);
            self::assertSame('available', $job->getStatus(), (string) $job->getIssue());
            $video = $job->getVideo();
            $file = 'clip-'.$video->getId().'.mp4';
            $path = $this->videoDirectory.'/'.$video->getStorageName();
            copy($path, $this->out.'/'.$file);
            $this->media[$file] = ['sha256' => hash_file('sha256', $path), 'seconds' => $video->getDuration(), 'width' => $video->getWidth(), 'height' => $video->getHeight(), 'videoId' => $video->getId()];
        }
    }

    public function testTwoEpisodesKeepFrozenCastThroughLibraryChangesAndSendOnlyTheirSceneReferences(): void
    {
        [$milo, $lea] = $this->cast();
        $miloId = $milo->getId();
        $leaId = $lea->getId();
        $old = $this->hash($milo->getPortrait());
        $other = $this->hash($lea->getPortrait());
        $recipe = ['model' => 'vidu-q1-subject', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'imageFit' => 'crop', 'reference' => null, 'endReference' => null, 'characterPool' => static::getContainer()->get(\App\Service\AiCharacterSelection::class)->resolve($this->owner(), ['characterSelectionManaged' => '1', 'characterSelection' => [$miloId.':1', $leaId.':1']])];
        $series = new AiSeries($this->owner(), 'Garden', 'Cats explore a garden.', '', '', $recipe);
        $first = new AiEpisode($series, 1);
        $second = new AiEpisode($series, 2);
        foreach ([$series, $first, $second] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $seriesId = $series->getId();
        $secondId = $second->getId();
        $presence = [[$miloId], [$leaId], [$miloId, $leaId]];
        $first = $this->prepare($first, 'vidu-q1-subject', [$milo, $lea], $presence);
        $quote = $this->flow()->quote($first);
        $characters = static::getContainer()->get(AiCharacters::class);
        $milo = $characters->save($this->owner(), $miloId, 1, 'Milo updated', 'Ginger cat, red coat.', $this->image('blue'), []);
        $characters->archive($this->owner(), $leaId, 1, true);
        $updated = $this->hash($milo->getPortrait());
        self::assertNotSame($old, $updated);
        self::assertSame($quote['hash'], $this->flow()->quote($first)['hash']);
        $series = $this->em->find(AiSeries::class, $seriesId);
        static::getContainer()->get(AiSeriesStudio::class)->configure($this->owner(), ['revision' => $series->getRevision(), 'name' => 'Garden updated', 'theme' => 'Cats explore a garden.', 'model' => 'vidu-q1-subject', 'seconds' => 5, 'totalSeconds' => 15, 'ratio' => '9:16', 'accessMode' => 'api', 'imageFit' => 'crop', 'image' => null, 'endImage' => null, 'characterSelectionManaged' => '1', 'characterSelection' => [$miloId.':2', $leaId.':1']], $series);
        $firstId = $first->getId();
        $this->em->clear();
        $first = $this->em->find(AiEpisode::class, $firstId);
        self::assertSame($quote['hash'], $this->flow()->quote($first)['hash']);
        $this->flow()->confirm($first, $quote['hash']);
        $this->flow()->confirm($first, $quote['hash']);
        self::assertSame([], $this->submitted());
        // A later library edit must change neither the confirmed first episode nor
        // the second episode's copy already saved in its series.
        $latest = $characters->save($this->owner(), $miloId, 2, 'Milo newest', 'Ginger cat, white coat.', $this->image('white'), []);
        self::assertNotSame($updated, $this->hash($latest->getPortrait()));
        self::assertSame(1, $first->getFlowRecipe()['characterPool'][0]['revision']);
        $firstId = $first->getId();
        $this->em->clear();
        $first = $this->em->find(AiEpisode::class, $firstId);
        $this->complete($first);
        self::assertSame([[$old], [$other], [$old, $other]], array_map(static fn (array $p): array => array_column($p['reference_image_urls'], 'sha256'), $this->submitted()));
        $this->flow()->advance($firstId);
        self::assertSame('assembling', $first->getFlowState());
        static::getContainer()->get(VideoEdits::class)->process($first->getAssembly()->getId());
        $this->flow()->advance($firstId);
        self::assertSame('ready', $first->getFlowState(), (string) $first->getFlowIssue());
        self::assertEqualsWithDelta(15, $first->getAssembly()->getOutput()->getDuration(), 0.3);
        $output = $first->getAssembly()->getOutput();
        $outputFile = $this->videoDirectory.'/'.$output->getStorageName();
        copy($outputFile, $this->out.'/assembled.mp4');
        $this->media['assembled.mp4'] = ['sha256' => hash_file('sha256', $outputFile), 'seconds' => $output->getDuration(), 'width' => $output->getWidth(), 'height' => $output->getHeight(), 'videoId' => $output->getId()];
        self::assertNull($first->getApprovedVideo());
        $this->client->request('GET', '/studio/series/episodes/'.$firstId);
        $this->snapshot('first-ready');
        $second = $this->em->find(AiEpisode::class, $secondId);
        // Choose the archived Lea snapshot already held by this series, not her current revision.
        $crawler = $this->client->request('GET', '/studio/series/episodes/'.$secondId);
        $values = $crawler->selectButton('Voir le récapitulatif')->form()->getPhpValues();
        $values['intent'] = 'compose';
        $values['ai_idea']['target'] = '15';
        $values['ai_idea']['planCount'] = '3';
        $values['ai_idea']['characterSelection'] = [$miloId.':2', $leaId.':1'];
        $values['ai_idea']['shots'] = array_map(static fn (array $ids): array => ['prompt' => 'A cat walks across the garden.', 'promptOnly' => 'manual', 'seconds' => '5', 'characterIdsManaged' => '1', 'characterIds' => array_map('strval', $ids)], $presence);
        $this->client->request('POST', '/studio/series/episodes/'.$secondId, $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        $this->snapshot('second-review');
        $second = $this->em->find(AiEpisode::class, $secondId);
        $secondQuote = $this->flow()->quote($second);
        $this->flow()->confirm($second, $secondQuote['hash']);
        $this->complete($second);
        self::assertSame([[$updated], [$other], [$updated, $other]], array_map(static fn (array $p): array => array_column($p['reference_image_urls'], 'sha256'), array_slice($this->submitted(), 3)));
        self::assertStringNotContainsString('Milo updated', $this->submitted()[0]['prompt']);
        self::assertStringContainsString('Milo updated', $this->submitted()[3]['prompt']);
        self::assertStringNotContainsString('Lea', $this->submitted()[3]['prompt']);
        self::assertCount(6, $this->submitted());
        $this->completed = true;
    }

    public function testPollingFailureRecoversSameRequestWithoutResendingOrLosingRemainingCharacters(): void
    {
        [$milo, $lea] = $this->cast();
        $hashes = [$this->hash($milo->getPortrait()), $this->hash($lea->getPortrait())];
        $episode = $this->prepare(null, 'vidu-q1-subject', [$milo, $lea], [[$milo->getId()], [$lea->getId()]]);
        $this->flow()->confirm($episode, $this->flow()->quote($episode)['hash']);
        $this->flow()->advance($episode->getId());
        $shots = $this->flow()->shots($episode);
        $jobId = $shots[0]->getGeneration()->getId();
        $generations = static::getContainer()->get(AiGenerations::class);
        $generations->process($jobId);
        $this->failPoll = true;
        $generations->process($jobId);
        $this->flow()->advance($episode->getId());
        self::assertSame('paused', $episode->getFlowState());
        self::assertNotEmpty($shots[0]->getGeneration()->getRemote());
        self::assertNull($shots[1]->getGeneration());
        self::assertCount(1, $this->submitted());
        $id = $episode->getId();
        $this->client->request('GET', '/studio/series/episodes/'.$id);
        $this->snapshot('polling-paused');
        $episode = $this->em->find(AiEpisode::class, $id);
        $this->flow()->resume($episode);
        $this->em->clear();
        $episode = $this->em->find(AiEpisode::class, $id);
        $this->complete($episode);
        self::assertSame($jobId, $this->flow()->shots($episode)[0]->getGeneration()->getId());
        self::assertCount(2, $this->submitted());
        self::assertSame([[$hashes[0]], [$hashes[1]]], array_map(static fn (array $p): array => array_column($p['reference_image_urls'], 'sha256'), $this->submitted()));
        $this->completed = true;
    }

    public function testKlingDispatchKeepsStartFrameAndGroupedViewsSeparateFromScenePresence(): void
    {
        [$milo, $lea] = $this->cast(true);
        $miloPortrait = $this->hash($milo->getPortrait());
        $miloView = $this->hash($milo->getViews()[0]);
        $leaPortrait = $this->hash($lea->getPortrait());
        $leaView = $this->hash($lea->getViews()[0]);
        $storage = static::getContainer()->get(AiReferenceStorage::class);
        $frame = $storage->store($this->owner(), $this->image('blue'));
        $this->hash($frame);
        $expectedFrame = hash('sha256', $storage->bytes($this->owner(), $frame, '9:16', 'crop'));
        $episode = $this->prepare(null, 'kling-3-image', [$milo, $lea], [[$milo->getId()], [$lea->getId(), $milo->getId()], []], $frame);
        $quote = $this->flow()->quote($episode);
        self::assertSame([], $quote['references'][2]['subjectDetails']);
        $this->flow()->confirm($episode, $quote['hash']);
        $this->complete($episode);
        self::assertCount(3, $this->submitted());
        foreach ($this->submitted() as $post) {
            self::assertSame($expectedFrame, $post['start_image_url']['sha256']);
            self::assertSame(1080, $post['start_image_url']['width']);
            self::assertSame(1920, $post['start_image_url']['height']);
            self::assertArrayNotHasKey('reference_image_urls', $post);
        }
        self::assertSame([$miloPortrait], array_column(array_column($this->submitted()[0]['elements'], 'frontal_image_url'), 'sha256'));
        self::assertSame($miloView, $this->submitted()[0]['elements'][0]['reference_image_urls'][0]['sha256']);
        self::assertSame([$leaPortrait, $miloPortrait], array_column(array_column($this->submitted()[1]['elements'], 'frontal_image_url'), 'sha256'));
        self::assertSame($leaView, $this->submitted()[1]['elements'][0]['reference_image_urls'][0]['sha256']);
        self::assertSame($miloView, $this->submitted()[1]['elements'][1]['reference_image_urls'][0]['sha256']);
        self::assertStringContainsString('@Element1 identifies the character named "Lea"', $this->submitted()[1]['prompt']);
        self::assertStringContainsString('@Element2 identifies the character named "Milo"', $this->submitted()[1]['prompt']);
        self::assertArrayNotHasKey('elements', $this->submitted()[2]);
        self::assertStringNotContainsString('Lea', $this->submitted()[0]['prompt']);
        self::assertStringNotContainsString('@Element', $this->submitted()[2]['prompt']);
        $this->client->request('GET', '/studio/series/episodes/'.$episode->getId().'?review=1');
        $this->snapshot('kling-empty-scene');
        $this->completed = true;
    }

    public function testAmbiguousSubmissionDoesNotResendOrStartTheFollowingCharacter(): void
    {
        [$milo, $lea] = $this->cast();
        $episode = $this->prepare(null, 'vidu-q1-subject', [$milo, $lea], [[$milo->getId()], [$lea->getId()]]);
        $oldQuote = $this->flow()->quote($episode);
        $rows = array_map(static fn ($s): array => ['prompt' => $s->getPrompt(), 'seconds' => $s->getSeconds()] + $s->getInputs(), $this->flow()->shots($episode));
        $rows[0]['characterIds'] = [(string) $lea->getId()];
        $this->flow()->save($episode, $episode->getRevision(), $episode->getTitle(), $rows);
        try {
            $this->flow()->confirm($episode, $oldQuote['hash']);
            self::fail('Old quote authorized a changed presence.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('changé', $error->getMessage());
        }
        self::assertSame([], $this->submitted());
        $this->flow()->confirm($episode, $this->flow()->quote($episode)['hash']);
        $this->flow()->advance($episode->getId());
        $this->uncertain = true;
        $jobId = $this->flow()->shots($episode)[0]->getGeneration()->getId();
        static::getContainer()->get(AiGenerations::class)->process($jobId);
        $this->flow()->advance($episode->getId());
        self::assertSame('paused', $episode->getFlowState());
        try {
            $this->flow()->resume($episode);
            self::fail('Ambiguous request was resent.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('incertaine', $error->getMessage());
        }
        $this->flow()->advance($episode->getId());
        self::assertCount(1, $this->submitted());
        self::assertSame([$this->hash($lea->getPortrait())], array_column($this->submitted()[0]['reference_image_urls'], 'sha256'));
        self::assertNull($this->flow()->shots($episode)[1]->getGeneration());
        self::assertSame('Lea', $this->flow()->shots($episode)[0]->getGeneration()->getInputSettings()['subjectDetails'][0]['name']);
        $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        $this->snapshot('ambiguous-paused');
        $this->completed = true;
    }
}
