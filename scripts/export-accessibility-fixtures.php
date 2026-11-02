<?php

// Offline browser recipe: test database only, all fixture writes rolled back.
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
        throw new LogicException('No external HTTP allowed.');
    }));
}
$fixtureSet = $argv[1] ?? 'r53';
if (!in_array($fixtureSet, ['r53', 'r54', 'r55'], true)) {
    throw new RuntimeException('Unknown fixture set.');
}
if (in_array($fixtureSet, ['r54', 'r55'], true)) {
    $container->set(App\Service\LocalStoryWriter::class, new App\Service\LocalStoryWriter(new Symfony\Component\HttpClient\MockHttpClient(static function () use (&$httpCalls): never {
        ++$httpCalls;
        throw new LogicException('No local inference allowed.');
    }), true, 'http://127.0.0.1:9', 'synthetic'));
}
$out = dirname(__DIR__).'/artifacts/'.$fixtureSet;
if (!is_dir($out)) {
    mkdir($out, 0775, true);
}
$counts = static function () use ($db): array {
    $result = [];
    foreach (['app_user', 'account_token', 'video_asset', 'ai_series', 'ai_episode', 'ai_shot', 'ai_generation', 'video_edit', 'publication_draft', 'publication_draft_item', 'messenger_messages'] as $table) {
        $result[$table] = (int) $db->fetchOne('SELECT COUNT(*) FROM '.$table);
    }

    return $result;
};
$before = $counts();
$db->beginTransaction();
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->disableReboot();
$routes = [];
$save = static function (string $name, string $url) use ($client, $out, &$routes): void {
    $client->request('GET', $url);
    if (200 !== $client->getResponse()->getStatusCode()) {
        throw new RuntimeException('Unexpected response: '.$name.' '.$client->getResponse()->getStatusCode());
    }
    $html = $client->getResponse()->getContent();
    file_put_contents($out.'/'.$name.'.html', $html);
    $routes[$name] = ['url' => $url, 'sha256' => hash('sha256', $html)];
};
try {
    foreach (['landing' => '/', 'login' => '/connexion', 'register' => '/inscription', 'legal' => '/informations-legales'] as $name => $url) {
        $save($name, $url);
    }
    if ('r55' === $fixtureSet) {
        $save('contact-public', '/contact');
    }
    $crawler = $client->request('GET', '/inscription');
    $client->submit($crawler->selectButton('Créer mon compte')->form([
        'registration[email]' => 'invalid',
        'registration[password][first]' => 'short',
        'registration[password][second]' => 'different',
    ]));
    if (422 !== $client->getResponse()->getStatusCode()) {
        throw new RuntimeException('Expected rejected registration.');
    }
    $html = $client->getResponse()->getContent();
    file_put_contents($out.'/register-error.html', $html);
    $routes['register-error'] = ['url' => '/inscription?error', 'sha256' => hash('sha256', $html)];
    $owner = new App\Entity\User('accessibility-'.bin2hex(random_bytes(6)).'@example.test');
    $owner->verify();
    $owner->setPassword('unused');
    $em->persist($owner);
    $em->flush();
    $client->loginUser($owner);
    if ('r55' === $fixtureSet) {
        $save('contact-studio', '/contact');
    }
    foreach (['videos' => '/studio', 'creation' => '/studio/creation', 'series-new' => '/studio/series/nouvelle', 'calendar' => '/studio/calendrier', 'connections' => '/studio/connexions', 'publications' => '/studio/publications', 'profile' => '/studio/mon-compte', 'upload' => '/studio/bibliotheque/importer'] as $name => $url) {
        $save($name, $url);
    }
    // KernelBrowser resets Doctrine between requests, even with reboot disabled.
    $owner = $em->find(App\Entity\User::class, $owner->getId());
    $recipe = ['model' => 'kling-25', 'seconds' => 5, 'totalSeconds' => 15, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop'];
    $series = new App\Entity\AiSeries($owner, 'Les aventures de Milo', 'Un chat blanc découvre les objets du quotidien.', 'Cinéma réaliste', 'Milo, chat blanc sans collier.', $recipe);
    $em->persist($series);
    $episode = new App\Entity\AiEpisode($series, 1);
    $episode->configurePreparation($recipe, 'Milo découvre un carton, y entre puis dort.', 'independent', false);
    $em->persist($episode);
    foreach (['renifle', 'entre dans', 'dort dans'] as $index => $action) {
        $shot = new App\Entity\AiShot($episode, $index + 1, 5);
        $shot->revise('A white cat with no collar. One simple action in a cardboard box. Fixed camera, soft daylight, no text.', 5);
        $shot->configureInputs(['sceneDescription' => 'Milo '.$action.' le carton.']);
        $em->persist($shot);
    }
    $video = new App\Entity\VideoAsset($owner, 'Clip de recette synthétique', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1000, 4, 96, 160, 'h264');
    $em->persist($video);
    $em->flush();
    $edit = new App\Entity\VideoEdit($owner, 'Montage de recette', [['id' => $video->getId(), 'start' => 0.0, 'end' => 4.0]]);
    $em->persist($edit);
    $em->flush();
    $save('series', '/studio/series/'.$series->getId());
    $save('episode', '/studio/series/episodes/'.$episode->getId());
    $save('edit', '/studio/montage/'.$edit->getId());
    if (in_array($fixtureSet, ['r54', 'r55'], true)) {
        $save('videos-filled', '/studio');
    }
    $owner = $em->find(App\Entity\User::class, $owner->getId());
    $longTitle = 'Titre '.str_repeat('LongSansEspace', 8);
    $generation = new App\Entity\AiGeneration($owner, 'r55' === $fixtureSet ? $longTitle : 'Génération simulée', 'A white cat sits in a cardboard box.', 'kling-25', 5, '9:16', 35);
    $generation->progress('processing');
    $em->persist($generation);
    $em->flush();
    $save('generation', '/studio/ia/generations/'.$generation->getId());
    if ('r55' === $fixtureSet) {
        $generation = $em->find(App\Entity\AiGeneration::class, $generation->getId());
        $generation->progress('review', 'Recette synthétique : relis la préparation.');
        $em->flush();
        $save('generation-review', '/studio/ia/generations/'.$generation->getId());
        $owner = $em->find(App\Entity\User::class, $owner->getId());
        $export = new App\Entity\VideoEdit($owner, $longTitle, [['id' => $video->getId(), 'start' => 0.0, 'end' => 4.0]]);
        $export->progress('rendering');
        $em->persist($export);
        $em->flush();
        $save('edit-export', '/studio/montage/'.$export->getId());
    }
    $client->request('GET', '/studio/composer', ['videos' => [$video->getId()]]);
    $location = $client->getResponse()->headers->get('Location');
    if (!$location) {
        throw new RuntimeException('Expected composer redirect.');
    }
    $save('composer', $location);
} finally {
    $db->rollBack();
    $em->clear();
}
$after = $counts();
if ($before !== $after || 0 !== $httpCalls) {
    throw new RuntimeException('Isolation failed.');
}
file_put_contents($out.'/exports.json', json_encode(['routes' => $routes, 'before' => $before, 'after' => $after, 'httpCalls' => $httpCalls], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo count($routes)." exports; rollback verified; zero provider HTTP.\n";
