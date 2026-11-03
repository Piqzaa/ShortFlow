<?php

// R6 closure: synthetic member responses, test database only, rollback required.
putenv('APP_ENV=test');
putenv('APP_DEBUG=0');
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new App\Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$em = $container->get(Doctrine\ORM\EntityManagerInterface::class);
$db = $em->getConnection();
if ('shortflow_test' !== $db->fetchOne('SELECT current_database()')) {
    throw new RuntimeException('Only shortflow_test is allowed.');
}
$httpCalls = 0;
foreach (['youtube', 'instagram', 'tiktok', 'ai', 'story'] as $provider) {
    $container->set($provider.'.http', new Symfony\Component\HttpClient\MockHttpClient(static function () use (&$httpCalls): never {
        ++$httpCalls;
        throw new LogicException('No provider or local inference HTTP allowed.');
    }));
}
$counts = static function () use ($db): array {
    $result = [];
    foreach (['app_user', 'account_token', 'video_asset', 'ai_series', 'ai_episode', 'ai_shot', 'ai_generation', 'video_edit', 'publication_draft', 'publication_draft_item', 'messenger_messages'] as $table) {
        $result[$table] = (int) $db->fetchOne('SELECT COUNT(*) FROM '.$table);
    }

    return $result;
};
$out = dirname(__DIR__).'/artifacts/r66';
if (!is_dir($out)) {
    mkdir($out, 0775, true);
}
$before = $counts();
$routes = [];
$db->beginTransaction();
try {
    $owner = new App\Entity\User('r66-'.bin2hex(random_bytes(6)).'@example.test');
    $owner->verify();
    $owner->setPassword('unused');
    $em->persist($owner);
    $em->flush();
    $client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
    $client->disableReboot();
    $client->loginUser($owner);
    foreach (['account' => '/studio/mon-compte', 'contact' => '/contact', 'guide' => '/fonctionnement'] as $name => $url) {
        $client->request('GET', $url);
        if (200 !== $client->getResponse()->getStatusCode()) {
            throw new RuntimeException('Unexpected response: '.$url);
        }
        $html = $client->getResponse()->getContent();
        file_put_contents($out.'/'.$name.'.html', $html);
        $routes[$name] = ['url' => $url, 'sha256' => hash('sha256', $html)];
    }
} finally {
    $db->rollBack();
    $em->clear();
}
$after = $counts();
if ($before !== $after || 0 !== $httpCalls) {
    throw new RuntimeException('Isolation failed.');
}
file_put_contents($out.'/exports.json', json_encode(['routes' => $routes, 'before' => $before, 'after' => $after, 'httpCalls' => $httpCalls], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "3 exports; rollback verified; zero provider HTTP.\n";
