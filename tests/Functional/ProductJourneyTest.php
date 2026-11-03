<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\VideoEdit;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Message\PublishYouTube;
use App\MessageHandler\PublishYouTubeHandler;
use App\Service\AiEpisodeFlow;
use App\Service\AiGenerations;
use App\Service\AiKeys;
use App\Service\PrivateVideoStorage;
use App\Service\PublicationDrafts;
use App\Service\TokenVault;
use App\Service\VideoEdits;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class ProductJourneyTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Connection $db;
    private int $ownerId;
    private string $channel;
    private string $out;
    private string $storage;
    private array $before;
    private array $filesBefore;
    private array $temporary = [];
    private array $stages = [];
    private array $calls = [];
    private array $media = [];
    private array $http = [];
    private array $oldEnv = [];
    private int $forbiddenCalls = 0;
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
        $this->db = $this->em->getConnection();
        self::assertSame('shortflow_test', $this->db->fetchOne('SELECT current_database()'));
        $this->storage = static::getContainer()->get(PrivateVideoStorage::class)->directory();
        self::assertStringEndsWith('/var/storage/videos/test', $this->storage);
        $this->filesBefore = glob($this->storage.'/*') ?: [];
        $this->before = $this->counts();
        $this->db->beginTransaction();
        static::getContainer()->set('app.publication_clock', new MockClock('2026-11-03T12:00:00Z'));
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['youtube', 'instagram', 'tiktok', 'ai', 'story'] as $provider) {
            $this->http[$provider] = new MockHttpClient(function (): never {
                ++$this->forbiddenCalls;
                throw new \LogicException('Unexpected HTTP: all R7.1 clients must be simulated.');
            });
            static::getContainer()->set($provider.'.http', $this->http[$provider]);
        }
        $owner = new User('r71-'.bin2hex(random_bytes(6)).'@example.test');
        $owner->verify();
        $owner->setPassword('unused');
        $this->em->persist($owner);
        $this->em->flush();
        $this->ownerId = $owner->getId();
        $this->channel = 'UC'.str_repeat('j', 22);
        $vault = new TokenVault($_ENV['YOUTUBE_TOKEN_KEY']);
        $this->em->persist(new YouTubeConnection($owner, $this->channel, 'Chaîne synthétique R7.1', $vault->encrypt('synthetic-only'), $vault->encrypt('synthetic-refresh'), 86400));
        $this->em->flush();
        $this->client->loginUser($owner);
        $this->out = dirname(__DIR__, 2).'/artifacts/r71/'.$this->name();
        if (!is_dir($this->out)) {
            mkdir($this->out, 0775, true);
        }
        file_put_contents($this->out.'/journey.json', '{"completed":false}');
    }

    protected function tearDown(): void
    {
        try {
            try {
                $this->db->rollBack();
                $this->em->clear();
                $after = $this->counts();
            } finally {
                foreach (array_diff(glob($this->storage.'/*') ?: [], $this->filesBefore) as $file) {
                    if (is_file($file) && !is_link($file)) {
                        unlink($file);
                    }
                }
                foreach ($this->temporary as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            self::assertSame($this->before, $after);
            self::assertSame($this->filesBefore, glob($this->storage.'/*') ?: []);
            self::assertSame(0, $this->forbiddenCalls);
            file_put_contents($this->out.'/journey.json', json_encode(['completed' => $this->completed, 'stages' => $this->stages, 'media' => $this->media, 'before' => $this->before, 'after' => $after, 'simulatedHttp' => $this->calls, 'unexpectedHttp' => $this->forbiddenCalls, 'privateFilesRestored' => true], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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

    private function counts(): array
    {
        $counts = [];
        foreach ($this->db->fetchFirstColumn("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename") as $table) {
            $counts[$table] = (int) $this->db->fetchOne('SELECT COUNT(*) FROM '.$this->db->quoteIdentifier($table));
        }

        return $counts;
    }

    private function owner(): User
    {
        return $this->em->find(User::class, $this->ownerId);
    }

    private function snapshot(string $name): void
    {
        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        file_put_contents($this->out.'/'.$name.'.html', $html);
        $this->stages[$name] = ['url' => $this->client->getRequest()->getRequestUri(), 'sha256' => hash('sha256', $html)];
    }

    private function socialCount(): int
    {
        return (int) $this->db->fetchOne('SELECT (SELECT COUNT(*) FROM youtube_publication) + (SELECT COUNT(*) FROM instagram_publication) + (SELECT COUNT(*) FROM tiktok_publication)');
    }

    private function preserveSyntheticMedia(VideoAsset $video): void
    {
        $source = $this->storage.'/'.$video->getStorageName();
        $file = 'video-'.$video->getId().'.mp4';
        copy($source, $this->out.'/'.$file);
        self::assertSame(hash_file('sha256', $source), hash_file('sha256', $this->out.'/'.$file));
        $this->media[$video->getId()] = ['file' => $file, 'sha256' => hash_file('sha256', $source), 'duration' => $video->getDuration(), 'width' => $video->getWidth(), 'height' => $video->getHeight()];
    }

    private function composerPost(Crawler $crawler, string $url, string $intent, array $replace = []): void
    {
        $payload = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $payload['intent'] = $intent;
        $payload['publication_composer'] = array_replace_recursive($payload['publication_composer'], $replace);
        $this->client->request('POST', $url, $payload);
        self::assertResponseStatusCodeSame(303);
    }

    private function prepare(Crawler $crawler, string $url, int $video, string $title): Crawler
    {
        $this->composerPost($crawler, $url, 'refresh', ['networks' => ['youtube']]);
        $crawler = $this->client->followRedirect();
        $this->composerPost($crawler, $url, 'save', ['accounts' => ['youtube' => $this->channel], 'settings' => ['youtube' => ['videos' => [$video => ['title' => $title, 'description' => 'Texte français propre à '.$title, 'privacy' => 'private', 'audience' => 'no', 'synthetic' => true]]]], 'mode' => 'scheduled', 'local' => '2026-11-04T14:00', 'timezone' => 'Europe/Paris']);

        return $this->client->request('GET', $url);
    }

    private function schedule(Crawler $crawler, string $url, VideoAsset $video, string $title): PublicationDraft
    {
        $jobs = $this->socialCount();
        $messages = (int) $this->db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'publications'");
        $this->composerPost($crawler, $url, 'review');
        self::assertSame($jobs, $this->socialCount());
        self::assertSame($messages, (int) $this->db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'publications'"));
        self::assertSame([], $this->calls['youtube'] ?? []);
        $crawler = $this->client->followRedirect();
        $this->snapshot('publication-confirmation');
        $form = $crawler->selectButton('Confirmer la programmation ↗')->form(['form[confirm]' => true]);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $draft = $this->em->getRepository(PublicationDraft::class)->findOneBy(['owner' => $this->owner(), 'status' => 'scheduled'], ['id' => 'DESC']);
        self::assertSame('scheduled', $draft->getStatus());
        self::assertSame([$video->getId()], $draft->videoIds());
        self::assertSame($this->channel, $draft->getAccounts()['youtube']);
        self::assertSame($title, $draft->content()['youtube']['videos'][$video->getId()]['title']);
        self::assertSame('2026-11-04T13:00:00+00:00', $draft->getScheduledAt()->format(DATE_ATOM));
        self::assertSame($jobs, $this->socialCount());
        self::assertSame($messages + 1, (int) $this->db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'publications'"));
        $revision = $draft->getRevision();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertSame($revision, $draft->getRevision());
        self::assertSame($messages + 1, (int) $this->db->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'publications'"));
        $this->client->request('GET', '/studio/calendrier?month=2026-11');
        $this->snapshot('calendar-scheduled');
        self::assertSelectorExists('a.calendar-entry[href="/studio/brouillons/'.$draft->getId().'"]');

        return $draft;
    }

    public function testImportedMontageReturnsToItsPreparationAndPublishesOnlyAfterConfirmation(): void
    {
        $initialJobs = $this->socialCount();
        $http = static::getContainer()->get('youtube.http');
        self::assertInstanceOf(MockHttpClient::class, $http);
        $http->setResponseFactory(function (string $method): MockResponse {
            $this->calls['youtube'][] = $method;

            return match (count($this->calls['youtube'])) {
                1 => new MockResponse('{}', ['response_headers' => ['Location: https://www.googleapis.com/upload/youtube/v3/videos?upload_id=r71-synthetic']]),
                2 => new MockResponse('', ['http_code' => 308]),
                3 => new MockResponse('{"id":"abcdefghijk"}', ['http_code' => 201]),
                4 => new MockResponse('{"items":[{"status":{"uploadStatus":"processed","privacyStatus":"private"},"processingDetails":{"processingStatus":"succeeded"}}]}'),
                default => throw new \LogicException('Unexpected simulated YouTube request.'),
            };
        });
        $source = dirname(__DIR__).'/Fixtures/reels-valid.mp4';
        $sourceHash = hash_file('sha256', $source);
        $temp = tempnam(sys_get_temp_dir(), 'r71-import-');
        $this->temporary[] = $temp;
        copy($source, $temp);
        $crawler = $this->client->request('GET', '/studio/bibliotheque/importer?next=compose');
        $this->snapshot('import');
        $form = $crawler->selectButton('Importer ma vidéo')->form(['video_upload[title]' => 'Import R7.1 à monter']);
        $this->client->request('POST', '/studio/bibliotheque/importer?next=compose', $form->getPhpValues(), ['video_upload' => ['file' => new UploadedFile($temp, 'r71.mp4', null, null, true)]]);
        self::assertResponseStatusCodeSame(303);
        $original = $this->em->getRepository(VideoAsset::class)->findOneBy(['owner' => $this->owner()]);
        $originalId = $original->getId();
        $this->preserveSyntheticMedia($original);
        self::assertSame($sourceHash, hash_file('sha256', $this->storage.'/'.$original->getStorageName()));
        $this->client->followRedirect();
        self::assertResponseStatusCodeSame(303);
        $url = $this->client->getResponse()->headers->get('Location');
        $crawler = $this->prepare($this->client->followRedirect(), $url, $originalId, 'Montage R7.1');
        $this->snapshot('prepare-before-edit');
        $this->composerPost($crawler, $url, 'edit_'.$originalId);
        $crawler = $this->client->followRedirect();
        $this->snapshot('montage-draft');
        $project = $this->em->getRepository(VideoEdit::class)->findOneBy(['owner' => $this->owner()]);
        $payload = $crawler->selectButton('Enregistrer le montage')->form()->getPhpValues();
        $payload['clips'] = [['id' => $originalId, 'start' => 0.1, 'end' => 0.7, 'at' => 0]];
        $payload['video_edit']['title'] = 'Export monté R7.1';
        $payload['video_edit']['format'] = '9:16';
        $payload['intent'] = 'export';
        $payload['confirm'] = '1';
        $this->client->request('POST', '/studio/montage/'.$project->getId(), $payload);
        self::assertResponseStatusCodeSame(303);
        self::assertSame($initialJobs, $this->socialCount());
        static::getContainer()->get(VideoEdits::class)->process($project->getId());
        $project = $this->em->find(VideoEdit::class, $project->getId());
        self::assertSame('ready', $project->getStatus(), (string) $project->getIssue());
        $output = $project->getOutput();
        self::assertNotSame($originalId, $output->getId());
        self::assertFileExists($this->storage.'/'.$output->getStorageName());
        self::assertEqualsWithDelta(0.6, $output->getDuration(), 0.15);
        $this->preserveSyntheticMedia($output);
        $crawler = $this->client->request('GET', '/studio/montage/'.$project->getId());
        $this->snapshot('montage-ready');
        $this->client->submit($crawler->selectButton('Appliquer et revenir →')->form());
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        if ($this->client->getResponse()->isRedirect()) {
            $crawler = $this->client->followRedirect();
        }
        $url = $this->client->getRequest()->getRequestUri();
        $this->snapshot('prepare-after-edit');
        $draft = $this->schedule($crawler, $url, $output, 'Montage R7.1');
        $clock = static::getContainer()->get('app.publication_clock');
        self::assertInstanceOf(MockClock::class, $clock);
        $clock->sleep(25 * 3600);
        $drafts = static::getContainer()->get(PublicationDrafts::class);
        $drafts->dispatchDue($draft->getId(), $draft->getRevision());
        $drafts->dispatchDue($draft->getId(), $draft->getRevision());
        $job = $this->em->getRepository(YouTubePublication::class)->findOneBy(['owner' => $this->owner()]);
        self::assertSame($output->getId(), $job->getSource()->getId());
        self::assertSame('queued', $job->getStatus());
        $handler = static::getContainer()->get(PublishYouTubeHandler::class);
        $message = new PublishYouTube($job->getId());
        $handler($message);
        self::assertSame('processing', $job->getStatus());
        $handler($message);
        self::assertSame('published', $job->getStatus());
        $handler($message);
        self::assertSame(['POST', 'PUT', 'PUT', 'GET'], $this->calls['youtube']);
        self::assertSame($initialJobs + 1, $this->socialCount());
        $this->client->request('GET', '/studio/publications');
        $this->snapshot('publication-tracked');
        self::assertSelectorTextContains('main', 'Montage R7.1');
        self::assertSame($sourceHash, hash_file('sha256', $source));
        self::assertSame($sourceHash, hash_file('sha256', $this->storage.'/'.$this->em->find(VideoAsset::class, $originalId)->getStorageName()));
        $this->completed = true;
    }

    public function testConfirmingOneVideoKeepsTheOtherAccountTextAndDateUnarmed(): void
    {
        $firstChannel = $this->channel;
        $secondChannel = 'UC'.str_repeat('k', 22);
        $vault = new TokenVault($_ENV['YOUTUBE_TOKEN_KEY']);
        $this->em->persist(new YouTubeConnection($this->owner(), $secondChannel, 'Deuxième chaîne synthétique', $vault->encrypt('synthetic-only'), $vault->encrypt('synthetic-refresh'), 86400));
        $videos = [];
        foreach (['Vidéo A', 'Vidéo B'] as $title) {
            $file = tempnam(sys_get_temp_dir(), 'r71-independent-');
            $this->temporary[] = $file;
            copy(dirname(__DIR__).'/Fixtures/reels-valid.mp4', $file);
            $video = static::getContainer()->get(\App\Service\VideoLibrary::class)->import($this->owner(), new UploadedFile($file, 'independent.mp4', null, null, true), $title);
            $this->preserveSyntheticMedia($video);
            $videos[] = $video;
        }
        $this->em->flush();
        $urls = [];
        foreach ($videos as $index => $video) {
            $this->channel = 0 === $index ? $firstChannel : $secondChannel;
            $this->client->request('GET', '/studio/composer', ['videos' => [$video->getId()]]);
            self::assertResponseStatusCodeSame(303);
            $urls[$index] = $this->client->getResponse()->headers->get('Location');
            $crawler = $this->prepare($this->client->followRedirect(), $urls[$index], $video->getId(), 'Texte '.(0 === $index ? 'A' : 'B'));
            if (1 === $index) {
                $this->composerPost($crawler, $urls[$index], 'save', ['local' => '2026-11-05T16:00']);
            }
        }
        $drafts = $this->em->getRepository(PublicationDraft::class)->findBy(['owner' => $this->owner()], ['id' => 'ASC']);
        self::assertCount(2, $drafts);
        $secondId = $drafts[1]->getId();
        $secondRevision = $drafts[1]->getRevision();
        $this->channel = $firstChannel;
        $first = $this->schedule($this->client->request('GET', $urls[0]), $urls[0], $videos[0], 'Texte A');
        self::assertSame($drafts[0]->getId(), $first->getId());
        $second = $this->em->find(PublicationDraft::class, $secondId);
        self::assertSame('draft', $second->getStatus());
        self::assertSame($secondRevision, $second->getRevision());
        self::assertSame($secondChannel, $second->getAccounts()['youtube']);
        self::assertSame('Texte B', $second->content()['youtube']['videos'][$videos[1]->getId()]['title']);
        self::assertSame('Texte français propre à Texte B', $second->content()['youtube']['videos'][$videos[1]->getId()]['description']);
        self::assertSame('2026-11-05T15:00:00+00:00', $second->getScheduledAt()->format(DATE_ATOM));
        static::getContainer()->get(PublicationDrafts::class)->dispatchDue($secondId, $secondRevision);
        self::assertSame('draft', $second->getStatus());
        self::assertSame([], $this->calls);
        $this->client->request('GET', '/studio/brouillons/'.$secondId);
        $this->snapshot('independent-unarmed-video');
        $this->completed = true;
    }

    public function testSeriesAssemblyRequiresApprovalBeforeOpeningItsScheduledPublication(): void
    {
        $initialJobs = $this->socialCount();
        $clip = tempnam(sys_get_temp_dir(), 'r71-clip-');
        $this->temporary[] = $clip;
        (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=purple:s=360x640:r=10', '-t', '5', '-c:v', 'libx264', '-threads', '1', '-preset', 'ultrafast', '-pix_fmt', 'yuv420p', '-f', 'mp4', $clip]))->mustRun();
        $this->http['ai']->setResponseFactory(function (string $method, string $url) use ($clip): MockResponse {
            $this->calls['ai'][] = $method;
            if ('POST' === $method) {
                $id = count(array_filter($this->calls['ai'], static fn (string $m): bool => 'POST' === $m));

                return new MockResponse(json_encode(['status_url' => 'https://queue.fal.run/fal-ai/kling-video/requests/r71-'.$id.'/status', 'response_url' => 'https://queue.fal.run/fal-ai/kling-video/requests/r71-'.$id], JSON_THROW_ON_ERROR));
            }
            if (str_ends_with($url, '/status')) {
                return new MockResponse('{"status":"COMPLETED"}');
            }
            if (str_contains($url, '/requests/')) {
                return new MockResponse('{"video":{"url":"https://v3.fal.media/files/r71-synthetic.mp4"}}');
            }
            self::assertSame('https://v3.fal.media/files/r71-synthetic.mp4', $url);

            return new MockResponse(file_get_contents($clip));
        });
        static::getContainer()->get(AiKeys::class)->save($this->owner(), 'r71-synthetic-api-key-never-real-1234567890');
        $this->em->flush();
        $crawler = $this->client->request('GET', '/studio/series/nouvelle');
        $this->client->submit($crawler->selectButton('Créer ma série →')->form(['ai_series[name]' => 'Série R7.1', 'ai_series[theme]' => 'Milo explores the Moon.', 'ai_series[style]' => 'Miniature cinema.', 'ai_series[characters]' => 'Milo is a ginger cat.', 'ai_series[episodeCount]' => 1, 'ai_series[model]' => 'kling-25', 'ai_series[accessMode]' => 'api', 'ai_series[totalSeconds]' => 10]));
        self::assertResponseStatusCodeSame(303);
        $series = $this->em->getRepository(AiSeries::class)->findOneBy(['owner' => $this->owner()]);
        $episode = $this->em->getRepository(AiEpisode::class)->findOneBy(['series' => $series]);
        $flow = static::getContainer()->get(AiEpisodeFlow::class);
        $url = '/studio/series/episodes/'.$episode->getId();
        $crawler = $this->client->request('GET', $url);
        $payload = $crawler->selectButton('Voir le récapitulatif →')->form()->getPhpValues();
        $payload['intent'] = 'compose';
        $payload['ai_idea']['title'] = 'Milo R7.1';
        $payload['ai_idea']['prompt'] = 'Milo explores the Moon.';
        $payload['ai_idea']['target'] = 10;
        $payload['ai_idea']['mute'] = '1';
        $payload['ai_idea']['shots'] = [['prompt' => 'Scene 1: Milo walks across the Moon.', 'seconds' => 5], ['prompt' => 'Scene 2: Milo looks up at the Earth.', 'seconds' => 5]];
        $this->client->request('POST', $url, $payload);
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        $this->snapshot('series-scenes-review');
        self::assertSame([], $this->calls);
        $form = $crawler->selectButton('Confirmer et générer la vidéo →')->form();
        $form['confirm'] = '1';
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertSame([], $this->calls);
        self::assertSame($initialJobs, $this->socialCount());
        $episode = $this->em->find(AiEpisode::class, $episode->getId());
        foreach ($flow->shots($episode) as $shot) {
            $flow->advance($episode->getId());
            self::assertNotNull($shot->getGeneration());
            $generations = static::getContainer()->get(AiGenerations::class);
            $generations->process($shot->getGeneration()->getId());
            $generations->process($shot->getGeneration()->getId());
            $this->em->refresh($shot->getGeneration());
            self::assertSame('available', $shot->getGeneration()->getStatus());
            $this->preserveSyntheticMedia($shot->getGeneration()->getVideo());
        }
        $flow->advance($episode->getId());
        self::assertSame('assembling', $episode->getFlowState());
        static::getContainer()->get(VideoEdits::class)->process($episode->getAssembly()->getId());
        $flow->advance($episode->getId());
        self::assertSame('ready', $episode->getFlowState(), (string) $episode->getFlowIssue());
        $output = $episode->getAssembly()->getOutput();
        self::assertEqualsWithDelta(10, $output->getDuration(), 0.3);
        $this->preserveSyntheticMedia($output);
        self::assertNull($episode->getApprovedVideo());
        $crawler = $this->client->request('GET', $url);
        $this->snapshot('series-result-unapproved');
        self::assertSelectorNotExists('form[action$="/publication"]');
        $this->client->submit($crawler->selectButton('Valider cette version →')->form());
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        $this->snapshot('series-result-approved');
        $this->client->submit($crawler->selectButton('Préparer et programmer la publication →')->form());
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        if ($this->client->getResponse()->isRedirect()) {
            $crawler = $this->client->followRedirect();
        }
        $composerUrl = $this->client->getRequest()->getRequestUri();
        $crawler = $this->prepare($crawler, $composerUrl, $output->getId(), 'Épisode R7.1');
        $this->snapshot('series-publication-prepare');
        $draft = $this->schedule($crawler, $composerUrl, $output, 'Épisode R7.1');
        self::assertSame($episode->getId(), $draft->getEpisode()->getId());
        self::assertSame(2, count(array_filter($this->calls['ai'], static fn (string $m): bool => 'POST' === $m)));
        self::assertSame($initialJobs, $this->socialCount());
        $this->client->request('GET', '/studio/series/'.$series->getId());
        $this->snapshot('series-scheduled');
        self::assertSelectorTextContains('.episode-publications', 'Programmée');
        self::assertSelectorTextContains('.episode-publications', '04/11/2026 à 14:00');
        $this->completed = true;
    }
}
