<?php

// Test-only subprocess. Every connection must prove its database before any work.
use App\Entity\User;
use App\Kernel;
use App\Service\LocalStoryWriter;
use App\Service\StoryProposals;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Worker;

require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
if ('test' !== ($_SERVER['APP_ENV'] ?? getenv('APP_ENV'))) {
    throw new LogicException('Test environment required.');
}
$base = $argv[1];
$config = json_decode(file_get_contents($base.'.json'), true, 32, JSON_THROW_ON_ERROR);
$kernel = new Kernel('test', false);
$kernel->boot();
$services = $kernel->getContainer()->get('test.service_container');
$em = $services->get(EntityManagerInterface::class);
$db = $em->getConnection();
if ('shortflow_test' !== $db->fetchOne('SELECT current_database()')) {
    throw new LogicException('Wrong test database.');
}
$db->executeStatement("SET lock_timeout = '8s'");
$clock = new MockClock($config['now']);
$services->set('app.publication_clock', $clock);
foreach (['youtube.http', 'instagram.http', 'tiktok.http', 'ai.http', 'story.http'] as $id) {
    $services->set($id, new MockHttpClient(static function (): never { throw new LogicException('External requests forbidden.'); }));
}
$wait = static function (string $file): void {
    $deadline = microtime(true) + 12;
    while (!is_file($file)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Test barrier timed out: '.basename($file));
        }
        usleep(20000);
        clearstatcache(true, $file);
    }
};
$pause = static function (string $point) use ($base, $config, $wait): void {
    if (($config['pause'] ?? '') === $point) {
        file_put_contents($base.'.paused', $point);
        $wait($base.'.release');
    }
};
$calls = 0;
$http = new MockHttpClient(static function (string $method, string $url, array $options) use ($db, $base, $pause, &$calls): MockResponse {
    if ($db->isTransactionActive() || 'POST' !== $method || 'http://localhost:11434/api/chat' !== $url) {
        throw new LogicException('Unexpected inference or transaction.');
    }
    ++$calls;
    file_put_contents($base.'.calls', "text\n", FILE_APPEND | LOCK_EX);
    $pause('http:'.$calls);
    $body = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
    $fields = $body['format']['properties'];
    $data = isset($fields['youtube'])
        ? ['youtube' => ['title' => 'Un moment calme', 'description' => 'Le chat se couche sur un coussin.', 'hashtags' => []]]
        : (isset($fields['beats'])
            ? ['title' => 'Un moment calme', 'summary' => 'Le chat se couche sur un coussin.', 'identity' => 'Un chat blanc.', 'beats' => ['Le chat se couche sur le coussin.']]
            : ['identity' => 'A white cat.', 'shots' => [['seconds' => 5, 'prompt' => 'A white cat slowly lies down on a cushion in one continuous take.']]]);

    return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($data, JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR));
});
$services->set(LocalStoryWriter::class, new LocalStoryWriter($http, true, 'http://localhost:11434', 'synthetic'));
$jobs = $services->get(StoryProposals::class);
$owner = isset($config['owner']) ? $em->find(User::class, $config['owner']) : null;
file_put_contents($base.'.ready', (string) $db->fetchOne('SELECT pg_backend_pid()'));
$wait($base.'.go');
$result = ['calls' => 0];
if ('create' === $config['action']) {
    try {
        $job = $jobs->create($owner, $config['scope'], $config['fingerprint'], $config['input'], $config['kind']);
        $result = ['id' => $job->getId()];
    } catch (RuntimeException $error) {
        $result = ['error' => $error->getMessage()];
    }
} elseif ('process' === $config['action']) {
    $jobs->process($config['id']);
    $result['stored'] = $db->fetchAssociative('SELECT status, kind, error, created_at, started_at FROM story_proposal WHERE id = ?', [$config['id']]);
} elseif ('consume' === $config['action']) {
    $dispatcher = new EventDispatcher();
    $worker = new Worker(['story_writing' => $services->get('messenger.transport.story_writing')], $services->get('messenger.default_bus'), $dispatcher);
    $dispatcher->addListener(WorkerMessageReceivedEvent::class, static fn () => $pause('received'));
    $dispatcher->addListener(WorkerMessageHandledEvent::class, static function () use ($pause, $worker): void {
        $pause('handled');
        $worker->stop();
    });
    $dispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event): never { throw new RuntimeException('Test worker handler failed.', previous: $event->getThrowable()); });
    $worker->run(['sleep' => 1000]);
} else {
    throw new LogicException('Unknown test action.');
}
$result['calls'] = $calls;
file_put_contents($base.'.result', json_encode($result, JSON_THROW_ON_ERROR));
$kernel->shutdown();
