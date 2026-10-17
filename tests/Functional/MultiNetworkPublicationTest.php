<?php

namespace App\Tests\Functional;

use App\Entity\InstagramConnection;
use App\Entity\TikTokConnection;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Service\MultiNetworkPublishing;
use App\Service\PublicationException;
use App\Service\TokenVault;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MultiNetworkPublicationTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    private int $creatorQueries = 0;
    private int $changeDurationAfter = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        self::assertSame('shortflow_test', $this->db()->fetchOne('SELECT current_database()'));
        $this->db()->executeStatement('DELETE FROM messenger_messages');
        $this->db()->executeStatement('DELETE FROM app_user');
        foreach (['owner', 'other'] as $name) {
            $user = new User($name.'-batch@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $this->em()->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['youtube.http', 'instagram.http'] as $service) {
            static::getContainer()->set($service, new MockHttpClient(static function (): never {
                throw new \LogicException('No YouTube or Instagram HTTP call is expected during preparation.');
            }));
        }
        static::getContainer()->set('tiktok.http', new MockHttpClient(function (string $method, string $url): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://open.tiktokapis.com/v2/post/publish/creator_info/query/', $url);
            ++$this->creatorQueries;
            $maxDuration = $this->changeDurationAfter && $this->creatorQueries >= $this->changeDurationAfter ? 2 : 60;

            return new MockResponse(json_encode(['data' => ['creator_nickname' => 'Créateur test', 'privacy_level_options' => ['FOLLOWER_OF_CREATOR', 'SELF_ONLY'], 'max_video_post_duration_sec' => $maxDuration, 'comment_disabled' => false, 'duet_disabled' => false, 'stitch_disabled' => true], 'error' => ['code' => 'ok']], JSON_THROW_ON_ERROR));
        }));
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function connectAll(): void
    {
        $vault = new TokenVault($_ENV['YOUTUBE_TOKEN_KEY']);
        $this->em()->persist(new YouTubeConnection($this->owner, 'UC'.str_repeat('a', 22), 'Chaîne test', $vault->encrypt('synthetic-access'), $vault->encrypt('synthetic-refresh'), 86400));
        $this->em()->persist(new InstagramConnection($this->owner, '17841400000000001', 'compte_test', 'MEDIA_CREATOR', $vault->encrypt('synthetic-access'), 86400));
        $this->em()->persist(new TikTokConnection($this->owner, 'creator-test', 'Créateur test', $vault->encrypt('synthetic-access'), $vault->encrypt('synthetic-refresh'), 86400, 31536000));
        $this->em()->flush();
    }

    private function video(?User $owner = null): VideoAsset
    {
        $managedOwner = $this->em()->find(User::class, ($owner ?? $this->owner)->getId());
        $video = new VideoAsset($managedOwner, 'Clip <test>', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 4, 720, 1280, 'h264');
        $this->em()->persist($video);
        $this->em()->flush();

        return $video;
    }

    /** @param list<VideoAsset> $videos @param list<string> $networks */
    private function choose(array $videos, array $networks): Crawler
    {
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/publications/preparer', ['videos' => array_map(static fn (VideoAsset $video): ?int => $video->getId(), $videos)]);
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Adapter les publications')->form();
        $this->submitNetworks($form, $networks);
        self::assertResponseStatusCodeSame(303);

        return $this->client->followRedirect();
    }

    /** @param list<string> $networks */
    private function submitNetworks(Form $form, array $networks): void
    {
        $payload = $form->getPhpValues();
        $payload['form']['networks'] = $networks;
        $this->client->request('POST', $form->getUri(), $payload);
    }

    /** @param list<VideoAsset> $videos */
    private function review(Crawler $crawler, array $videos, bool $tiktokReady = true): Crawler
    {
        $form = $crawler->selectButton('Voir le récapitulatif')->form();
        foreach ($videos as $video) {
            $id = $video->getId();
            if ($crawler->filter('input[name="multi_network_prepare[youtube][videos]['.$id.'][title]"]')->count()) {
                $form['multi_network_prepare[youtube][videos]['.$id.'][privacy]'] = 'private';
                $form['multi_network_prepare[youtube][videos]['.$id.'][audience]'] = 'no';
                $form['multi_network_prepare[youtube][videos]['.$id.'][description]'] = 'Description YouTube conservée';
            }
            if ($tiktokReady && $crawler->filter('select[name="multi_network_prepare[tiktok][videos]['.$id.'][privacy]"]')->count()) {
                $form['multi_network_prepare[tiktok][videos]['.$id.'][privacy]'] = 'SELF_ONLY';
            }
        }
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);

        return $this->client->followRedirect();
    }

    private function assertJobs(int $perNetwork, int $messages): void
    {
        foreach (['youtube', 'instagram', 'tiktok'] as $network) {
            self::assertSame($perNetwork, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM '.$network.'_publication'));
        }
        self::assertSame($messages, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testOneConfirmationQueuesSixDestinationsAndReplayDoesNotDuplicate(): void
    {
        $this->connectAll();
        $videos = [$this->video(), $this->video()];
        $crawler = $this->choose($videos, ['youtube', 'instagram', 'tiktok']);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '.batch-network');
        self::assertSelectorCount(0, 'select[name$="[privacy]"] option[selected="selected"][value]:not([value=""])');
        $crawler = $this->review($crawler, $videos);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.batch-total strong', '6');
        self::assertSelectorCount(6, '.batch-review-grid article');
        self::assertStringNotContainsString('<test>', $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('synthetic-access', $this->client->getResponse()->getContent());
        $this->assertJobs(0, 0);
        $form = $crawler->selectButton('Confirmer et publier 6 publications')->form();
        $form['form[confirm]'] = true;
        $payload = $form->getPhpValues();
        $url = $form->getUri();
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/publications', 303);
        $this->assertJobs(2, 6);
        $this->client->request('POST', $url, $payload);
        self::assertResponseRedirects('/studio/publications', 303);
        $this->assertJobs(2, 6);
        $this->client->request('GET', '/studio/publications');
        self::assertSelectorCount(6, '.publication-item');
        self::assertGreaterThan(0, $this->creatorQueries);
        $queries = $this->creatorQueries;
        $key = $this->db()->fetchOne('SELECT submission_key FROM youtube_publication LIMIT 1');
        $this->db()->executeStatement("UPDATE youtube_publication SET status = 'published'");
        $publishing = static::getContainer()->get(MultiNetworkPublishing::class);
        $accounts = array_map(static fn (array $destination): mixed => $destination['account'], $publishing->destinations($this->owner));
        $publishing->enqueue($this->em()->find(User::class, $this->owner->getId()), array_map(static fn (VideoAsset $video): ?int => $video->getId(), $videos), ['youtube', 'instagram', 'tiktok'], [], $key, $accounts);
        $this->assertJobs(2, 6);
        self::assertSame($queries, $this->creatorQueries);
        self::assertSame(2, (int) $this->db()->fetchOne("SELECT COUNT(*) FROM youtube_publication WHERE status = 'published'"));
    }

    public function testOfflinePreparationShowsUnavailableDestinationsAndCannotPublish(): void
    {
        $videos = [$this->video()];
        $crawler = $this->choose($videos, ['youtube', 'instagram', 'tiktok']);
        self::assertSelectorExists('.batch-issues');
        self::assertSelectorCount(1, 'select[name$="[privacy]"] option[value="private"]');
        self::assertSelectorCount(0, 'select[name$="[privacy]"] option[value="SELF_ONLY"]');
        $crawler = $this->review($crawler, $videos, false);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button[type="submit"][disabled]');
        self::assertSelectorTextContains('.batch-review', 'À choisir après connexion');
        $payload = $crawler->filter('form[name="form"]')->form()->getPhpValues();
        $payload['form']['confirm'] = '1';
        $this->client->request('POST', $this->client->getRequest()->getUri(), $payload);
        self::assertResponseStatusCodeSame(422);
        $this->assertJobs(0, 0);
        self::assertSame(0, $this->creatorQueries);
    }

    public function testSelectionIsPrivateCsrfProtectedAndBoundToSessionOwner(): void
    {
        $foreign = $this->video($this->other);
        $this->client->request('GET', '/studio/publications/preparer', ['videos' => [$foreign->getId()]]);
        self::assertResponseRedirects('http://localhost/connexion');
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/publications/preparer', ['videos' => [$foreign->getId()]]);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/publications/preparer', ['videos' => []]);
        self::assertResponseRedirects('/studio/bibliotheque', 303);
        $video = $this->video();
        $this->client->request('GET', '/studio/publications/preparer', ['videos' => [$video->getId()]]);
        $url = $this->client->getResponse()->headers->get('Location');
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Adapter les publications')->form());
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $url, ['form' => ['networks' => ['youtube']]]);
        self::assertResponseStatusCodeSame(422);
        $this->client->loginUser($this->other);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
        $this->assertJobs(0, 0);
    }

    public function testDestinationLimitAndVideoCompatibilityPreventWholeBatch(): void
    {
        $this->connectAll();
        $videos = [];
        for ($index = 0; $index < 6; ++$index) {
            $videos[] = $this->video();
        }
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/publications/preparer', ['videos' => array_map(static fn (VideoAsset $video): ?int => $video->getId(), $videos)]);
        $crawler = $this->client->followRedirect();
        $form = $crawler->selectButton('Adapter les publications')->form();
        $this->submitNetworks($form, ['youtube', 'tiktok']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.batch-form', 'au maximum 5 vidéos');
        $incompatible = new VideoAsset($this->em()->find(User::class, $this->owner->getId()), 'Clip trop court', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 1, 720, 1280, 'h264');
        $this->em()->persist($incompatible);
        $this->em()->flush();
        $crawler = $this->review($this->choose([$incompatible], ['youtube', 'instagram']), [$incompatible]);
        self::assertSelectorExists('.batch-issues');
        self::assertSelectorExists('button[type="submit"][disabled]');
        $payload = $crawler->filter('form[name="form"]')->form()->getPhpValues();
        $payload['form']['confirm'] = '1';
        $this->client->request('POST', $this->client->getRequest()->getUri(), $payload);
        self::assertResponseStatusCodeSame(422);
        $this->assertJobs(0, 0);
    }

    public function testEditingInvalidatesOldConfirmationAndRetainsNetworkTexts(): void
    {
        $this->connectAll();
        $video = $this->video();
        $crawler = $this->review($this->choose([$video], ['youtube', 'instagram']), [$video]);
        $oldForm = $crawler->selectButton('Confirmer et publier 2 publications')->form();
        $oldForm['form[confirm]'] = true;
        $oldPayload = $oldForm->getPhpValues();
        $url = $oldForm->getUri();
        $crawler = $this->client->click($crawler->selectLink('Modifier les textes et réglages')->link());
        self::assertSelectorTextContains('textarea', 'Description YouTube conservée');
        $form = $crawler->selectButton('Voir le récapitulatif')->form();
        $form['multi_network_prepare[youtube][videos]['.$video->getId().'][description]'] = 'Description modifiée';
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.batch-review', 'Description modifiée');
        $this->client->request('POST', $url, $oldPayload);
        self::assertResponseStatusCodeSame(422);
        $this->assertJobs(0, 0);
        $crawler = $this->client->request('GET', $url);
        $crawler = $this->client->click($crawler->selectLink('Modifier les réseaux')->link());
        $form = $crawler->selectButton('Adapter les publications')->form();
        $this->submitNetworks($form, ['youtube']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('textarea', 'Description modifiée');
    }

    public function testChangedAccountBlocksAllDestinationsAndMissingConfirmationOrCsrfCannotQueue(): void
    {
        $this->connectAll();
        $video = $this->video();
        $crawler = $this->review($this->choose([$video], ['youtube', 'instagram']), [$video]);
        $form = $crawler->selectButton('Confirmer et publier 2 publications')->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $form['form[confirm]'] = true;
        $payload = $form->getPhpValues();
        unset($payload['form']['_token']);
        $this->client->request('POST', $form->getUri(), $payload);
        self::assertResponseStatusCodeSame(422);
        $connection = $this->em()->getRepository(InstagramConnection::class)->findOneBy(['owner' => $this->owner]);
        $connection->reconnect('17841400000000002', 'autre_compte', 'MEDIA_CREATOR', 'encrypted', 86400);
        $this->em()->flush();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.batch-issues', 'Le compte destinataire a changé');
        $this->assertJobs(0, 0);
    }

    public function testLateTikTokFailureRollsBackYouTubeInstagramAndEveryMessage(): void
    {
        $this->connectAll();
        $video = $this->video();
        $id = $video->getId();
        $publishing = static::getContainer()->get(MultiNetworkPublishing::class);
        $destinations = $publishing->destinations($this->owner);
        $accounts = array_map(static fn (array $destination): mixed => $destination['account'], $destinations);
        $content = [
            'youtube' => ['videos' => [$id => ['title' => 'Clip', 'description' => '', 'privacy' => 'private', 'audience' => 'no', 'synthetic' => false]]],
            'instagram' => ['videos' => [$id => ['caption' => '', 'shareToFeed' => false, 'synthetic' => false]]],
            'tiktok' => ['videos' => [$id => ['caption' => '', 'privacy' => 'SELF_ONLY', 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => false]]],
        ];
        $this->changeDurationAfter = 2;
        try {
            $publishing->enqueue($this->owner, [$id], ['youtube', 'instagram', 'tiktok'], $content, bin2hex(random_bytes(32)), $accounts);
            self::fail('The changed TikTok capability must abort the whole transaction.');
        } catch (PublicationException $error) {
            self::assertStringContainsString('2 secondes', $error->getMessage());
        }
        self::assertSame(2, $this->creatorQueries);
        $this->assertJobs(0, 0);
    }

    public function testPreparationsStayIndependentAndExpiredDraftCannotBeUsed(): void
    {
        $first = $this->video();
        $second = $this->video();
        $first->rename('Premier clip');
        $second->rename('Deuxième clip');
        $this->em()->flush();
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/publications/preparer', ['videos' => [$first->getId()]]);
        $firstUrl = $this->client->getResponse()->headers->get('Location');
        $this->client->request('GET', '/studio/publications/preparer', ['videos' => [$second->getId()]]);
        $secondUrl = $this->client->getResponse()->headers->get('Location');
        self::assertNotSame($firstUrl, $secondUrl);
        $this->client->request('GET', $firstUrl);
        self::assertSelectorTextContains('.batch-selection', 'Premier clip');
        self::assertSelectorTextNotContains('.batch-selection', 'Deuxième clip');
        $session = $this->client->getRequest()->getSession();
        $drafts = $session->get('publication_drafts');
        $firstKey = basename($firstUrl);
        $drafts[$firstKey]['expires'] = time() - 1;
        $session->set('publication_drafts', $drafts);
        $session->save();
        $this->client->request('GET', $firstUrl);
        self::assertResponseStatusCodeSame(410);
        $this->client->request('GET', $secondUrl);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.batch-selection', 'Deuxième clip');
        $this->assertJobs(0, 0);
    }

    public function testAccountReplacedBetweenPreparationAndLockIsReadAgainFromDatabase(): void
    {
        $this->connectAll();
        $video = $this->video();
        $publishing = static::getContainer()->get(MultiNetworkPublishing::class);
        $accounts = array_map(static fn (array $destination): mixed => $destination['account'], $publishing->destinations($this->owner));
        // Simulate a reconnect completed by another request while this ORM still holds old objects.
        $this->db()->executeStatement('UPDATE youtube_connection SET channel_id = ? WHERE owner_id = ?', ['UC'.str_repeat('b', 22), $this->owner->getId()]);
        try {
            $publishing->enqueue($this->owner, [$video->getId()], ['youtube', 'instagram'], [], bin2hex(random_bytes(32)), $accounts);
            self::fail('A replaced account must block the whole batch before reading rows or queuing.');
        } catch (PublicationException $error) {
            self::assertStringContainsString('vérifie les comptes', $error->getMessage());
        }
        $this->assertJobs(0, 0);
    }

    public function testOfflineTikTokPrivacyMustBeCompletedEvenAfterAccountBecomesReady(): void
    {
        $this->connectAll();
        $video = $this->video();
        $publishing = static::getContainer()->get(MultiNetworkPublishing::class);
        $accounts = array_map(static fn (array $destination): mixed => $destination['account'], $publishing->destinations($this->owner));
        $context = $publishing->context($this->owner, [$video->getId()], ['tiktok'], $accounts, ['tiktok' => ['videos' => [$video->getId() => ['caption' => 'Texte préparé hors connexion', 'privacy' => null]]]]);
        self::assertFalse($context['ready']);
        self::assertStringContainsString('Reviens aux textes et réglages', $context['issues']['tiktok'][0]);
        $this->assertJobs(0, 0);
    }

    public function testScheduleCanBeChosenDirectlyInThePublicationFlow(): void
    {
        $this->connectAll();
        $video = $this->video();
        $crawler = $this->review($this->choose([$video], ['youtube']), [$video]);
        self::assertSelectorExists('#programmer');
        self::assertSelectorCount(1, '#publication_plan_local');
        self::assertSelectorCount(1, '#form_confirm');
        $date = (new \DateTimeImmutable('+1 hour'))->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d\TH:i');
        $plan = $crawler->selectButton('Vérifier ce créneau')->form();
        $plan['publication_plan[local]'] = $date;
        $plan['publication_plan[timezone]'] = 'Europe/Paris';
        $this->client->submit($plan);
        self::assertResponseStatusCodeSame(303);
        $this->assertJobs(0, 0);
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.batch-total', 'Départ prévu');
        $form = $crawler->selectButton('Confirmer la programmation')->form();
        $form['form[confirm]'] = true;
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertSame('scheduled', $this->db()->fetchOne('SELECT status FROM publication_draft LIMIT 1'));
        foreach (['youtube', 'instagram', 'tiktok'] as $network) {
            self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM '.$network.'_publication'));
        }
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }
}
