<?php

// Local synthetic benchmark, never a production command. Default: manifest only.
require dirname(__DIR__).'/vendor/autoload.php';

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\PublicationDraft;
use App\Entity\PublicationDraftItem;
use App\Entity\SeriesOccurrence;
use App\Entity\SeriesRecurrence;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Message\WriteStoryProposal;
use App\Service\AiEpisodeFlow;
use App\Service\LocalStoryWriter;
use App\Service\StoryProposals;
use Doctrine\DBAL\Logging\Middleware;
use Psr\Log\AbstractLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

final class StudioBenchmarkSql extends AbstractLogger
{
    public static array $queries = [];
    public static bool $enabled = false;

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (self::$enabled && isset($context['sql'])) {
            // Count executed queries/statements, never retain parameters or credentials.
            $sql = preg_replace('/\s+/', ' ', $context['sql']);
            self::$queries[$sql] = (self::$queries[$sql] ?? 0) + 1;
        }
    }
}

final class StudioBenchmarkKernel extends App\Kernel
{
    public function getProjectDir(): string { return dirname(__DIR__); }
    public function getCacheDir(): string { return $this->getProjectDir().'/var/cache/studio-benchmark'; }

    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->register('benchmark.sql', StudioBenchmarkSql::class);
        $container->register('benchmark.middleware', Middleware::class)
            ->setArguments([new Reference('benchmark.sql')])->addTag('doctrine.middleware');
    }
}

final class StudioBenchmarkBrowser extends KernelBrowser
{
    public float $serverMs = 0;
    public int $serverPeakBytes = 0;
    public int $serverPeakAboveStartBytes = 0;

    protected function doRequest(object $request): Response
    {
        memory_reset_peak_usage();
        $memory = memory_get_usage(false);
        $start = hrtime(true);
        try {
            return parent::doRequest($request);
        } finally {
            // handle + sendContent + terminate, before BrowserKit parses the DOM.
            $this->serverMs = (hrtime(true) - $start) / 1e6;
            $this->serverPeakBytes = memory_get_peak_usage(false);
            $this->serverPeakAboveStartBytes = max(0, $this->serverPeakBytes - $memory);
        }
    }
}

$options = getopt('', ['run', 'commit:']);
$manifest = [
    'version' => 1,
    'datasets' => ['small' => ['series' => 2, 'episodes' => 3, 'occurrences' => 3], 'large' => ['series' => 25, 'episodes' => 120, 'occurrences' => 120]],
    'warmupsPerRoute' => 1, 'samplesPerRoute' => 3,
    'queueHoldMicroseconds' => 250000, 'mockResponseHoldMicroseconds' => 20000,
    'environment' => 'test', 'debug' => false, 'database' => 'shortflow_test',
    'realInference' => false, 'realProviderCalls' => false,
];
if (!isset($options['run'])) {
    echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}
$commit = $options['commit'] ?? '';
if (!preg_match('/^[a-f0-9]{40}$/D', $commit)) {
    throw new InvalidArgumentException('Indique --commit=<SHA complet des sources applicatives mesurées>.');
}
foreach (['APP_ENV' => 'test', 'APP_DEBUG' => '0', 'AI_GENERATION_ENABLED' => '0', 'AI_STORY_ENABLED' => '0'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new StudioBenchmarkKernel('test', false);
$client = new StudioBenchmarkBrowser($kernel);
$client->disableReboot();
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$em = $container->get('doctrine')->getManager();
$db = $em->getConnection();
if ('shortflow_test' !== $db->fetchOne('SELECT current_database()')) {
    throw new RuntimeException('Base interdite : ce programme ne fonctionne que sur shortflow_test.');
}
$forbiddenCalls = 0;
$forbidden = new MockHttpClient(static function () use (&$forbiddenCalls): never {
    ++$forbiddenCalls;
    throw new RuntimeException('Tout HTTP réel est interdit dans cette mesure.');
});
foreach (['ai.http', 'story.http', 'youtube.http', 'instagram.http', 'tiktok.http'] as $service) {
    $container->set($service, $forbidden);
}
$container->set(LocalStoryWriter::class, new LocalStoryWriter($forbidden, false, 'http://localhost:11434', 'synthetic'));
$snapshot = static function () use ($db): array {
    $counts = [];
    foreach (['app_user', 'ai_series', 'ai_episode', 'ai_shot', 'series_recurrence', 'series_occurrence', 'video_asset', 'publication_draft', 'publication_draft_item', 'story_proposal', 'messenger_messages'] as $table) {
        $counts[$table] = (int) $db->fetchOne('SELECT count(*) FROM '.$table);
    }
    return $counts;
};
$before = $snapshot();
$result = $manifest + ['sourceCommit' => $commit, 'runnerSha256' => hash_file('sha256', __FILE__), 'startedAt' => gmdate(DATE_ATOM), 'php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'databaseVersion' => $db->fetchOne('SHOW server_version'), 'opcacheCli' => ini_get('opcache.enable_cli'), 'measurements' => []];
foreach (['src/Controller/AiSeriesController.php', 'src/Controller/AiStudioController.php', 'src/Service/AiSeriesStudio.php', 'src/Service/EpisodePublications.php', 'src/Service/EpisodeCovers.php', 'src/Service/StoryProposals.php', 'src/Service/LocalStoryWriter.php', 'templates/series/show.html.twig', 'templates/ai/studio.html.twig', 'composer.lock'] as $file) {
    $result['sourceSha256'][$file] = hash_file('sha256', dirname(__DIR__).'/'.$file);
}
StudioBenchmarkSql::$enabled = true;
$statement = $db->prepare('SELECT 1 AS benchmark_calibration');
$statement->executeQuery()->fetchOne();
$statement->executeQuery()->fetchOne();
StudioBenchmarkSql::$enabled = false;
$result['sqlCalibration'] = ['preparedOnceExecutedTwice' => array_sum(StudioBenchmarkSql::$queries)];
if (2 !== $result['sqlCalibration']['preparedOnceExecutedTwice']) { throw new RuntimeException('Le compteur ne mesure pas les exécutions préparées.'); }
$flow = $container->get(AiEpisodeFlow::class);
$db->beginTransaction();
try {
    $fixtures = [];
    foreach ($manifest['datasets'] as $label => $size) {
        $owner = new User('benchmark-'.$label.'-'.bin2hex(random_bytes(6)).'@example.test');
        $owner->verify();
        $owner->setPassword('synthetic');
        $em->persist($owner);
        $recipe = ['model' => 'kling-25', 'seconds' => 10, 'totalSeconds' => 30, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop'];
        $series = new AiSeries($owner, 'Série synthétique '.$label, 'Un chat blanc ouvre une porte puis entre dans la pièce.', '', '', $recipe);
        $em->persist($series);
        for ($i = 1; $i < $size['series']; ++$i) {
            $em->persist(new AiSeries($owner, 'Série synthétique '.$label.' '.$i, 'Un chat blanc marche dans la pièce.', '', '', $recipe));
        }
        $recurrence = new SeriesRecurrence($series);
        $now = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $recurrence->activate(['mode' => 'preparation', 'weekday' => 2, 'hour' => '18:00', 'timezone' => 'Europe/Paris', 'networks' => ['youtube'], 'accounts' => ['youtube' => null], 'limit' => 12, 'tone' => 'auto', 'continuity' => 'independent'], $now->modify('+7 days'), $now);
        $recurrence->pause('Historique synthétique ; aucune préparation à lancer.');
        $em->persist($recurrence);
        $em->flush();
        $review = null;
        $draft = null;
        $counts = ['unprepared' => 0, 'draft' => 0, 'approvedWithPublicationDraft' => 0];
        for ($i = 1; $i <= $size['episodes']; ++$i) {
            $episode = new AiEpisode($series, $i);
            $em->persist($episode);
            $em->flush();
            if (1 === $i % 3) {
                ++$counts['draft'];
                $flow->prepare($owner, ['model' => 'kling-25', 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => '', 'imageFit' => 'crop', 'title' => 'Épisode synthétique '.$i, 'prompt' => 'A white cat opens a door and enters a room.', 'target' => 30, 'mute' => false, 'continuity' => 'independent', 'revision' => $episode->getRevision(), 'shots' => [['seconds' => 10, 'prompt' => 'The white cat walks slowly toward a wooden door.'], ['seconds' => 10, 'prompt' => 'The white cat pushes the wooden door open.'], ['seconds' => 10, 'prompt' => 'The white cat enters the room and stops near a chair.']]], $episode);
                $draft ??= $episode;
            } elseif (2 === $i % 3) {
                ++$counts['approvedWithPublicationDraft'];
                // Metadata only: no media file or generation is necessary to measure GETs.
                $video = new VideoAsset($owner, 'Vidéo synthétique '.$i, bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 100, 30, 360, 640, 'h264');
                $em->persist($video);
                $episode->flow('ready');
                $episode->approve($video);
                $em->flush();
                $publication = new PublicationDraft($owner, 'Brouillon synthétique', new DateTimeImmutable());
                $publication->addItem(new PublicationDraftItem($publication, $video, 0));
                $publication->attachEpisode($episode);
                $em->persist($publication);
                $publication->edit('Brouillon synthétique', ['youtube'], ['youtube' => null], new DateTimeImmutable());
            } else {
                ++$counts['unprepared'];
                $episode->edit('Épisode synthétique '.$i, 'Un chat blanc ouvre une porte puis entre dans la pièce.');
            }
            $occurrence = new SeriesOccurrence($recurrence, new DateTimeImmutable('2026-01-01T12:00:00Z +'.$i.' days'), 'Europe/Paris', ['youtube'], new DateTimeImmutable('2026-01-01T00:00:00Z'), $episode, ['youtube' => null]);
            $occurrence->fail('Créneau synthétique manqué, aucun traitement à lancer.');
            $em->persist($occurrence);
        }
        $em->flush();
        $fixtures[$label] = ['owner' => $owner->getId(), 'series' => $series->getId(), 'draft' => $draft->getId(), 'counts' => $counts];
    }
    foreach ($fixtures as $label => $fixture) {
        $routes = ['studio' => '/studio/creation', 'seriesIndex' => '/studio/series', 'seriesShow' => '/studio/series/'.$fixture['series'], 'episodeDraft' => '/studio/series/episodes/'.$fixture['draft'], 'episodeReview' => '/studio/series/episodes/'.$fixture['draft'].'?review=1'];
        $client->loginUser($em->find(User::class, $fixture['owner']));
        $result['measurements'][$label] = ['fixtureCounts' => $fixture['counts'], 'routes' => []];
        foreach ($routes as $name => $path) {
            $samples = [];
            for ($iteration = 0; $iteration <= $manifest['samplesPerRoute']; ++$iteration) {
                $em->clear();
                gc_collect_cycles();
                StudioBenchmarkSql::$queries = [];
                StudioBenchmarkSql::$enabled = true;
                $start = hrtime(true);
                $crawler = $client->request('GET', $path);
                $elapsed = (hrtime(true) - $start) / 1e6;
                StudioBenchmarkSql::$enabled = false;
                $response = $client->getResponse();
                if (200 !== $response->getStatusCode() || $forbiddenCalls) {
                    throw new RuntimeException('GET non mesurable : '.$name.' / '.$response->getStatusCode());
                }
                $queries = StudioBenchmarkSql::$queries;
                if (!$queries) { throw new RuntimeException('Compteur SQL inactif.'); }
                arsort($queries);
                $sample = ['serverMs' => round($client->serverMs, 3), 'browserKitRequestMs' => round($elapsed, 3), 'sqlExecutions' => array_sum($queries), 'htmlBytes' => strlen($response->getContent()), 'serverPhpPeakBytes' => $client->serverPeakBytes, 'serverPhpPeakAboveStartBytes' => $client->serverPeakAboveStartBytes, 'forms' => $crawler->filter('form')->count(), 'inputs' => $crawler->filter('input, textarea, select')->count(), 'topSql' => array_slice($queries, 0, 5, true)];
                if (0 !== $iteration) { $samples[] = $sample; }
            }
            $times = array_column($samples, 'serverMs');
            sort($times);
            $result['measurements'][$label]['routes'][$name] = ['serverMs' => ['min' => $times[0], 'median' => $times[1], 'max' => $times[2]], 'samples' => $samples];
        }
    }
    // Real Doctrine test transport; consume only the one message created below.
    $owner = $em->find(User::class, $fixtures['small']['owner']);
    $mockCalls = [];
    $writerHttp = new MockHttpClient(static function (string $method, string $url) use (&$mockCalls, $manifest): MockResponse {
        if ('POST' !== $method || 'http://localhost:11434/api/chat' !== $url) { throw new RuntimeException('Unexpected synthetic writer request.'); }
        $start = hrtime(true);
        usleep($manifest['mockResponseHoldMicroseconds']);
        $data = !$mockCalls ? ['title' => 'Le chat et la porte', 'summary' => 'Le chat entre dans la pièce.', 'identity' => 'Un chat blanc.', 'beats' => ['Le chat approche de la porte.', 'Le chat ouvre la porte.', 'Le chat entre dans la pièce.']] : ['identity' => 'A white cat', 'shots' => [['seconds' => 10, 'prompt' => 'The white cat walks slowly toward a wooden door.'], ['seconds' => 10, 'prompt' => 'The white cat pushes the wooden door open.'], ['seconds' => 10, 'prompt' => 'The white cat enters the room and stops near a chair.']]];
        $mockCalls[] = round((hrtime(true) - $start) / 1e6, 3);
        return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($data, JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR));
    });
    $proposals = new StoryProposals($em, $container->get('messenger.default_bus'), new LocalStoryWriter($writerHttp, true, 'http://localhost:11434', 'synthetic'));
    $start = hrtime(true);
    $job = $proposals->create($owner, 0, 'synthetic-benchmark', ['idea' => 'Un chat blanc ouvre une porte puis entre dans la pièce.', 'durations' => [10, 10, 10], 'continuity' => 'independent', 'tone' => 'auto', 'context' => []]);
    $enqueueMs = (hrtime(true) - $start) / 1e6;
    $queuedAt = hrtime(true);
    usleep($manifest['queueHoldMicroseconds']);
    $transport = $container->get('messenger.transport.story_writing');
    $messageId = $db->fetchOne('SELECT id FROM messenger_messages WHERE queue_name = ? AND body LIKE ?', ['story_writing', '%'.$job->getId().'%']);
    $envelope = false === $messageId ? null : $transport->find($messageId);
    $message = $envelope?->getMessage();
    if (!$message instanceof WriteStoryProposal || $message->id !== $job->getId()) { throw new RuntimeException('Le message de test manque dans le transport Doctrine.'); }
    $waitingMs = (hrtime(true) - $queuedAt) / 1e6;
    $start = hrtime(true);
    $proposals->process($job->getId());
    $processingMs = (hrtime(true) - $start) / 1e6;
    $transport->ack($envelope);
    if ('ready' !== $job->getStatus() || 2 !== count($mockCalls)) { throw new RuntimeException('Rédaction synthétique non terminée : '.$job->getStatus()); }
    $result['writing'] = ['enqueueMs' => round($enqueueMs, 3), 'controlledQueueWaitMs' => round($waitingMs, 3), 'processingMs' => round($processingMs, 3), 'mockResponseMs' => $mockCalls, 'realModelInferenceMs' => null, 'status' => $job->getStatus(), 'timingResolution' => 'Monotonic runner timings; stored job timestamps have second resolution.', 'transport' => 'Doctrine test, manually handled and acked by job identity; not a worker capacity test.'];
} finally {
    StudioBenchmarkSql::$enabled = false;
    $db->rollBack();
    $em->clear();
    $after = $snapshot();
    $result['cleanup'] = ['transactionRolledBack' => true, 'countsBefore' => $before, 'countsAfter' => $after, 'forbiddenHttpCalls' => $forbiddenCalls];
    $kernel->shutdown();
}
if ($before !== $after || $forbiddenCalls) { throw new RuntimeException('Nettoyage ou contrôle HTTP incomplet.'); }
$result['finishedAt'] = gmdate(DATE_ATOM);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
