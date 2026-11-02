<?php

// Owner-approved project film. Real local import and draft save, test DB rollback.
putenv('APP_ENV=test');
putenv('APP_DEBUG=0');
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';
require dirname(__DIR__).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$root = dirname(__DIR__);
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
        throw new LogicException('No external HTTP is allowed in this demonstration.');
    }));
}
$counts = static function () use ($db): array {
    $result = [];
    foreach (['app_user', 'account_token', 'video_asset', 'ai_series', 'ai_episode', 'ai_shot', 'ai_generation', 'video_edit', 'publication_draft', 'publication_draft_item', 'messenger_messages'] as $table) {
        $result[$table] = (int) $db->fetchOne('SELECT COUNT(*) FROM '.$table);
    }

    return $result;
};
$out = $root.'/artifacts/r62';
if (!is_dir($out)) {
    mkdir($out, 0775, true);
}
$source = $root.'/public/media/films/hero-v4.webm';
$sourceHash = hash_file('sha256', $source);
$temp = tempnam(sys_get_temp_dir(), 'vydflow-demo-');
copy($source, $temp);
copy($source, $out.'/film.webm');
$storage = $container->get(App\Service\PrivateVideoStorage::class);
if (!str_ends_with(str_replace('\\', '/', $storage->directory()), '/var/storage/videos/test')) {
    throw new RuntimeException('Only test video storage is allowed.');
}
$before = $counts();
$db->beginTransaction();
$client = new Symfony\Bundle\FrameworkBundle\KernelBrowser($kernel);
$client->disableReboot();
$routes = [];
$storedName = null;
$save = static function (string $name) use ($client, $out, &$routes): void {
    if (200 !== $client->getResponse()->getStatusCode()) {
        throw new RuntimeException('Unexpected demo response '.$name.': '.$client->getResponse()->getStatusCode());
    }
    $html = $client->getResponse()->getContent();
    file_put_contents($out.'/'.$name.'.html', $html);
    $routes[$name] = ['url' => $client->getRequest()->getRequestUri(), 'sha256' => hash('sha256', $html)];
};
try {
    $owner = new App\Entity\User('product-demo-'.bin2hex(random_bytes(6)).'@example.test');
    $owner->verify();
    $owner->setPassword('unused');
    $em->persist($owner);
    $em->flush();
    $ownerId = $owner->getId();
    $client->loginUser($owner);
    $crawler = $client->request('GET', '/studio/bibliotheque/importer?next=compose');
    $save('upload');
    $form = $crawler->selectButton('Importer ma vidéo')->form(['video_upload[title]' => 'Film de présentation — démonstration']);
    $client->request('POST', '/studio/bibliotheque/importer?next=compose', $form->getPhpValues(), ['video_upload' => ['file' => new Symfony\Component\HttpFoundation\File\UploadedFile($temp, 'presentation-vydflow.webm', 'video/webm', null, true)]]);
    if (303 !== $client->getResponse()->getStatusCode()) {
        throw new RuntimeException('Real import was rejected: '.$client->getResponse()->getStatusCode());
    }
    $owner = $em->find(App\Entity\User::class, $ownerId);
    $video = $em->getRepository(App\Entity\VideoAsset::class)->findOneBy(['owner' => $owner]);
    if (!$video) {
        throw new RuntimeException('The import did not create a video.');
    }
    $storedName = $video->getStorageName();
    if ($sourceHash !== hash_file('sha256', $storage->path($storedName))) {
        throw new RuntimeException('Imported bytes differ from the authorized film.');
    }
    $videoId = $video->getId();
    $technical = ['id' => $videoId, 'mime' => $video->getMimeType(), 'duration' => $video->getDuration(), 'width' => $video->getWidth(), 'height' => $video->getHeight()];
    $next = $client->getResponse()->headers->get('Location');
    $client->request('GET', '/studio/bibliotheque/'.$videoId);
    $save('imported');
    $client->request('GET', $next);
    $url = $client->getResponse()->headers->get('Location');
    if (!$url) {
        throw new RuntimeException('No preparation was opened.');
    }
    $crawler = $client->request('GET', $url);
    $payload = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
    $payload['intent'] = 'refresh';
    $payload['publication_composer']['networks'] = ['youtube'];
    $client->request('POST', $url, $payload);
    $crawler = $client->followRedirect();
    $save('prepare');
    $payload = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
    $payload['intent'] = 'save';
    $payload['publication_composer']['settings']['youtube']['videos'][$videoId]['title'] = 'Mon premier brouillon VydFlow';
    $payload['publication_composer']['settings']['youtube']['videos'][$videoId]['description'] = 'Film de présentation du projet. Démonstration locale, aucun envoi.';
    $client->request('POST', $url, $payload);
    if (303 !== $client->getResponse()->getStatusCode()) {
        throw new RuntimeException('Draft save was rejected: '.$client->getResponse()->getStatusCode());
    }
    $client->followRedirect();
    $save('saved');
    $draft = $em->getRepository(App\Entity\PublicationDraft::class)->findOneBy(['owner' => $em->find(App\Entity\User::class, $ownerId)]);
    if (!$draft || 'draft' !== $draft->getStatus() || $httpCalls || $counts()['messenger_messages'] !== $before['messenger_messages']) {
        throw new RuntimeException('Demo unexpectedly queued work or did not remain a draft.');
    }
    $draftResult = ['status' => $draft->getStatus(), 'networks' => $draft->getNetworks(), 'content' => $draft->content()];
} finally {
    $db->rollBack();
    $em->clear();
    if ($storedName) {
        $storage->discard($storedName);
    }
    if (is_file($temp)) {
        unlink($temp);
    }
}
$after = $counts();
if ($before !== $after || $httpCalls || $sourceHash !== hash_file('sha256', $source)) {
    throw new RuntimeException('Demo isolation failed.');
}
file_put_contents($out.'/demo.json', json_encode(['source' => 'public/media/films/hero-v4.webm', 'sourceSha256' => $sourceHash, 'video' => $technical, 'draft' => $draftResult, 'routes' => $routes, 'before' => $before, 'after' => $after, 'httpCalls' => $httpCalls, 'privateFileRemoved' => !is_file($storage->path($storedName))], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo "Real film imported and draft saved; rollback verified; zero external HTTP.\n";
