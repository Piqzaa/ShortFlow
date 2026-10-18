<?php

namespace App\Tests\Functional;

use App\Entity\InstagramConnection;
use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\TokenVault;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\MockHttpClient;

final class PublicationComposerTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    private VideoAsset $video;
    private string $url;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        static::getContainer()->set('app.publication_clock', new MockClock('2026-10-18T08:00:00Z'));
        self::assertSame('shortflow_test', $this->db()->fetchOne('SELECT current_database()'));
        $this->db()->executeStatement('DELETE FROM messenger_messages');
        $this->db()->executeStatement('DELETE FROM app_user');
        foreach (['owner', 'other'] as $key) {
            $user = new User($key.'-composer@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$key = $user;
        }
        $this->em()->flush();
        $this->video = new VideoAsset($this->owner, 'Source privée', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 4, 720, 1280, 'h264');
        $this->em()->persist($this->video);
        $this->em()->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['youtube.http', 'instagram.http', 'tiktok.http'] as $service) {
            static::getContainer()->set($service, new MockHttpClient(static function (): never {
                throw new \LogicException('No external API call is authorized by these composer tests.');
            }));
        }
        $this->client->loginUser($this->owner);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function stored(): PublicationDraft
    {
        return $this->em()->getRepository(PublicationDraft::class)->findOneBy(['owner' => $this->owner]);
    }

    private function start(array $extra = []): Crawler
    {
        $this->client->request('GET', '/studio/composer', $extra + ['videos' => [$this->video->getId()]]);
        self::assertResponseStatusCodeSame(303);
        $this->url = $this->client->getResponse()->headers->get('Location');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    private function post(Crawler $crawler, string $intent, array $replace = []): void
    {
        $payload = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $payload['intent'] = $intent;
        $payload['publication_composer'] = array_replace_recursive($payload['publication_composer'], $replace);
        if (array_key_exists('networks', $replace)) {
            $payload['publication_composer']['networks'] = $replace['networks'];
        }
        $this->client->request('POST', $this->url, $payload);
    }

    private function networks(Crawler $crawler, array $networks): Crawler
    {
        $this->post($crawler, 'refresh', ['networks' => $networks]);
        self::assertResponseStatusCodeSame(303);

        return $this->client->followRedirect();
    }

    private function noSend(): void
    {
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        foreach (['youtube', 'instagram', 'tiktok'] as $network) {
            self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM '.$network.'_publication'));
        }
    }

    public function testWorkspaceIsPrivateAndCalendarDateAndZoneReachComposer(): void
    {
        $otherVideo = new VideoAsset($this->other, 'Invisible', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 4, 720, 1280, 'h264');
        $this->em()->persist($otherVideo);
        $this->em()->flush();
        $crawler = $this->client->request('GET', '/studio');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.workspace-grid', 'Source privée');
        self::assertStringNotContainsString('Invisible', $crawler->html());
        $this->client->request('GET', '/studio/composer', ['date' => '2026-10-20', 'zone' => 'America/Toronto']);
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        $form = $crawler->selectButton('Préparer la sélection →')->form(['videos' => [$this->video->getId()]]);
        $this->client->submit($form);
        $this->client->followRedirect();
        self::assertSelectorExists('input[name="publication_composer[mode]"][value="scheduled"][checked]');
        self::assertInputValueSame('publication_composer[local]', '2026-10-20T12:00');
        self::assertSelectorExists('option[value="America/Toronto"][selected]');
        $this->client->request('GET', '/studio/composer', ['videos' => [$otherVideo->getId()]]);
        self::assertResponseStatusCodeSame(404);
        $this->noSend();
    }

    public function testIncompleteDraftPersistsAcrossSessionsAndStrictReviewShowsMissingFields(): void
    {
        $crawler = $this->networks($this->start(), ['youtube']);
        $this->post($crawler, 'save', ['settings' => ['youtube' => ['videos' => [$this->video->getId() => ['title' => 'Mon titre sauvegardé', 'description' => 'Mon texte']]]]]);
        self::assertResponseStatusCodeSame(303);
        $id = $this->stored()->getId();
        $this->client->restart();
        $this->client->loginUser($this->owner);
        $crawler = $this->start(['draft' => $id]);
        self::assertInputValueSame('publication_composer[settings][youtube][videos]['.$this->video->getId().'][title]', 'Mon titre sauvegardé');
        $this->post($crawler, 'review');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('select[name$="[privacy]"][aria-invalid="true"]');
        $this->noSend();
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/composer', ['draft' => $id]);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', $this->url);
        self::assertResponseStatusCodeSame(404);
    }

    public function testScheduledInstagramWaitsForExplicitFinalConfirmation(): void
    {
        $vault = new TokenVault($_ENV['YOUTUBE_TOKEN_KEY']);
        $this->em()->persist(new InstagramConnection($this->owner, '17841400000000001', 'test', 'MEDIA_CREATOR', $vault->encrypt('synthetic'), 86400));
        $this->em()->flush();
        $crawler = $this->networks($this->start(), ['instagram']);
        self::assertSelectorExists('input[name$="[shareToFeed]"][checked]');
        $this->post($crawler, 'review', ['settings' => ['instagram' => ['videos' => [$this->video->getId() => ['caption' => 'Mon Reel']]]], 'mode' => 'scheduled', 'local' => '2026-10-20T12:30', 'timezone' => 'America/Toronto']);
        self::assertResponseRedirects('/studio/brouillons/'.$this->stored()->getId().'/confirmer', 303);
        self::assertSame('2026-10-20T16:30:00+00:00', $this->stored()->getScheduledAt()->format('c'));
        $this->noSend();
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.batch-total', 'America/Toronto');
        $this->client->submit($crawler->selectButton('Confirmer la programmation ↗')->form(['form[confirm]' => true]));
        self::assertResponseStatusCodeSame(303);
        self::assertSame('scheduled', $this->stored()->getStatus());
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM instagram_publication'));
        $this->client->request('GET', $this->url);
        self::assertResponseRedirects('/studio/brouillons/'.$this->stored()->getId(), 303);
    }

    public function testDeselectedNetworkTextSurvivesDurableSaveAndReopening(): void
    {
        $crawler = $this->networks($this->start(), ['instagram']);
        $this->post($crawler, 'refresh', ['networks' => ['youtube'], 'settings' => ['instagram' => ['videos' => [$this->video->getId() => ['caption' => 'Texte conservé']]]]]);
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        $this->post($crawler, 'save');
        self::assertResponseStatusCodeSame(303);
        $id = $this->stored()->getId();
        self::assertSame('Texte conservé', $this->stored()->content()['instagram']['videos'][$this->video->getId()]['caption']);
        $crawler = $this->start(['draft' => $id]);
        $this->networks($crawler, ['instagram']);
        self::assertSelectorTextContains('textarea[name$="[caption]"]', 'Texte conservé');
        $this->noSend();
    }

    public function testInvalidDateRetainsSavedTextAndStaleFormCannotOverwriteIt(): void
    {
        $crawler = $this->networks($this->start(), ['instagram']);
        $stale = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $this->post($crawler, 'save');
        self::assertResponseStatusCodeSame(303);
        $this->client->request('POST', $this->url, $stale + ['intent' => 'save']);
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->request('GET', $this->url);
        $this->post($crawler, 'review', ['mode' => 'scheduled', 'local' => '2026-10-17T12:00', 'settings' => ['instagram' => ['videos' => [$this->video->getId() => ['caption' => 'Date à corriger']]]]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('Date à corriger', $this->stored()->content()['instagram']['videos'][$this->video->getId()]['caption']);
        self::assertSame('draft', $this->stored()->getStatus());
        self::assertNull($this->stored()->getScheduledAt());
        $this->noSend();
    }

    public function testInvalidCsrfDoesNotSaveAnything(): void
    {
        $crawler = $this->start();
        $this->post($crawler, 'save', ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM publication_draft'));
        $this->noSend();
    }

    public function testCalendarHasOnlyNeededWeeksAndDraftDeletionPreservesVideo(): void
    {
        $crawler = $this->client->request('GET', '/studio/calendrier?month=2026-10&zone=America%2FToronto');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(35, '.calendar-day');
        self::assertSelectorExists('dialog .calendar-video-card input[type="radio"][name="videos[]"][value="'.$this->video->getId().'"]');
        self::assertSelectorExists('.calendar-create-day[data-date="2026-10-20"]');
        self::assertStringNotContainsString('Les anciennes publications restent', $crawler->html());
        $this->post($this->start(), 'save');
        $id = $this->stored()->getId();
        $crawler = $this->client->request('GET', '/studio?tab=drafts');
        self::assertSelectorExists('.draft-delete[href="/studio/brouillons/'.$id.'/delete"]');
        $this->client->request('POST', '/studio/brouillons/'.$id.'/delete', ['form' => ['revision' => (string) $this->stored()->getRevision()]]);
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->request('GET', '/studio/brouillons/'.$id.'/delete');
        $this->client->submit($crawler->selectButton('Supprimer la préparation')->form());
        self::assertResponseRedirects('/studio?tab=drafts', 303);
        $this->client->followRedirect();
        self::assertSelectorExists('.workspace-tabs a[aria-current=page][href="/studio?tab=drafts"]');
        self::assertSelectorNotExists('.draft-delete');
        self::assertNull($this->em()->find(PublicationDraft::class, $id));
        self::assertNotNull($this->em()->find(VideoAsset::class, $this->video->getId()));
        $this->noSend();
    }

    public function testCalendarVideoSearchIsPrivateAndPaginated(): void
    {
        for ($i = 0; $i < 13; ++$i) {
            $this->em()->persist(new VideoAsset($this->owner, 'Archive '.$i, bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 4, 720, 1280, 'h264'));
        }
        $this->em()->persist(new VideoAsset($this->other, 'Archive secrète', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 4, 720, 1280, 'h264'));
        $this->em()->flush();
        $this->client->request('GET', '/studio/calendrier/videos?q=ARCHIVE');
        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $result = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(13, $result['total']);
        self::assertSame(2, $result['nextPage']);
        self::assertSame(12, substr_count($result['html'], 'type="radio"'));
        self::assertStringContainsString('Archive 12', $result['html']);
        self::assertStringNotContainsString('secrète', $result['html']);
        $this->client->request('GET', '/studio/calendrier/videos?q=archive&page=2');
        $last = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNull($last['nextPage']);
        self::assertSame(1, substr_count($last['html'], 'type="radio"'));
        self::assertStringContainsString('Archive 0', $last['html']);
        $this->client->request('GET', '/studio/calendrier/videos?q=%25');
        $empty = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(0, $empty['total']);
        self::assertStringContainsString('Aucune vidéo trouvée', $empty['html']);
        $this->noSend();
    }
}
