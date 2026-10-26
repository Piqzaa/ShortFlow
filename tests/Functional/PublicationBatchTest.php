<?php

namespace App\Tests\Functional;

use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\PublicationBatch;
use App\Service\PublicationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\MockHttpClient;

final class PublicationBatchTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private User $other;
    /** @var list<int> */
    private array $ids = [];
    private string $url;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $this->em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        static::getContainer()->set('app.publication_clock', new MockClock('2026-10-26T08:00:00Z'));
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['youtube.http', 'instagram.http', 'tiktok.http', 'ai.http'] as $service) {
            static::getContainer()->set($service, new MockHttpClient(static function (): never { throw new \LogicException('No external request allowed.'); }));
        }
        foreach (['owner', 'other'] as $name) {
            $user = new User($name.'-batch@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em->persist($user);
            $this->$name = $user;
        }
        for ($i = 1; $i <= 3; ++$i) {
            $video = new VideoAsset($this->owner, 'Ma vidéo '.$i, bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1000, 8, 720, 1280, 'h264');
            $video->recordTechnicalMetadata(['fps' => 30, 'videoBitrate' => 3000000]);
            $this->em->persist($video);
            $this->em->flush();
            $this->ids[] = $video->getId();
        }
        $this->client->loginUser($this->owner);
    }

    /** @return array<int,array{networks:list<string>,mode:string,local:?string,timezone:string}> */
    private function plans(): array
    {
        return [
            $this->ids[0] => ['networks' => ['youtube', 'instagram'], 'mode' => 'immediate', 'local' => null, 'timezone' => 'Europe/Paris'],
            $this->ids[1] => ['networks' => ['tiktok'], 'mode' => 'scheduled', 'local' => '2026-10-29T14:00', 'timezone' => 'Europe/Paris'],
            $this->ids[2] => ['networks' => ['youtube', 'instagram', 'tiktok'], 'mode' => 'scheduled', 'local' => '2026-11-02T14:00', 'timezone' => 'Europe/Paris'],
        ];
    }

    private function start(): Crawler
    {
        $this->client->request('GET', '/studio/composer', ['videos' => $this->ids]);
        self::assertResponseStatusCodeSame(303);
        $this->url = $this->client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/studio/composer/selection/', $this->url);

        return $this->client->followRedirect();
    }

    private function noSend(): void
    {
        foreach (['messenger_messages', 'youtube_publication', 'instagram_publication', 'tiktok_publication'] as $table) {
            self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table));
        }
    }

    public function testThreeVideosKeepIndependentNetworksAndDatesWithoutActivation(): void
    {
        $crawler = $this->start();
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '.publication-selection-row');
        $payload = $crawler->selectButton('Enregistrer mes préparations →')->form()->getPhpValues();
        $payload['publication_batch']['plans'] = $this->plans();
        $this->client->request('POST', $this->url, $payload);
        self::assertResponseRedirects($this->url, 303);
        $this->client->followRedirect();
        self::assertSelectorCount(3, '.publication-selection-result');
        self::assertSelectorCount(3, 'a.button[href^="/studio/composer?draft="]');
        $drafts = $this->em->getRepository(PublicationDraft::class)->findBy(['owner' => $this->owner], ['id' => 'ASC']);
        self::assertCount(3, $drafts);
        foreach ($drafts as $i => $draft) {
            self::assertSame([$this->ids[$i]], $draft->videoIds());
            self::assertSame($this->plans()[$this->ids[$i]]['networks'], $draft->getNetworks());
            self::assertSame('draft', $draft->getStatus());
            self::assertNull($draft->getSubmissionKey());
            self::assertNotNull($draft->getPreparationKey());
        }
        self::assertNull($drafts[0]->getScheduledAt());
        self::assertSame('2026-10-29T13:00:00+00:00', $drafts[1]->getScheduledAt()->format('c'));
        self::assertSame('2026-11-02T13:00:00+00:00', $drafts[2]->getScheduledAt()->format('c'));
        $this->client->request('POST', $this->url, $payload);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(3, $this->em->getRepository(PublicationDraft::class)->count([]));
        $crawler = $this->client->request('GET', $this->url);
        $this->client->click($crawler->selectLink('Textes et confirmation →')->eq(1)->link());
        $this->client->followRedirect();
        self::assertSelectorExists('a[href="'.$this->url.'"]');
        self::assertInputValueSame('publication_composer[local]', '2026-10-29T14:00');
        self::assertSelectorCount(1, 'input[name="publication_composer[networks][]"]:checked');
        self::assertSelectorExists('input[name="publication_composer[networks][]"][value="tiktok"]:checked');
        self::assertStringContainsString('/preparation/', static::getContainer()->get('router')->generate('app_publication_batch', ['key' => str_repeat('f', 64)]));
        $this->client->request('GET', '/studio/calendrier', ['month' => '2026-10', 'zone' => 'Europe/Paris']);
        self::assertResponseIsSuccessful();
        $this->noSend();
    }

    public function testDurableKeyPreventsDuplicatesEvenWithoutTheSessionResult(): void
    {
        $service = static::getContainer()->get(PublicationBatch::class);
        $first = $service->prepare($this->owner, str_repeat('a', 48), $this->plans());
        $ids = array_map(static fn (PublicationDraft $draft): int => $draft->getId(), $first);
        $ownerId = $this->owner->getId();
        $this->em->clear();
        $this->owner = $this->em->find(User::class, $ownerId);
        foreach ($ids as $id) {
            self::assertNotNull($this->em->find(PublicationDraft::class, $id)->getPreparationKey());
        }
        $again = $service->prepare($this->owner, str_repeat('a', 48), $this->plans());
        self::assertSame($ids, array_map(static fn (PublicationDraft $draft): int => $draft->getId(), $again));
        self::assertSame(3, $this->em->getRepository(PublicationDraft::class)->count([]));
        $this->noSend();
    }

    public function testBadDateInTheLastRowNeverCreatesAPartialSelection(): void
    {
        $crawler = $this->start();
        $payload = $crawler->selectButton('Enregistrer mes préparations →')->form()->getPhpValues();
        $payload['publication_batch']['plans'] = $this->plans();
        $payload['publication_batch']['plans'][$this->ids[2]]['local'] = '2026-10-25T14:00';
        $this->client->request('POST', $this->url, $payload);
        self::assertResponseStatusCodeSame(422);
        self::assertInputValueSame('publication_batch[plans]['.$this->ids[1].'][local]', '2026-10-29T14:00');
        self::assertSame(0, $this->em->getRepository(PublicationDraft::class)->count([]));
        $this->noSend();
    }

    public function testFailureAfterTheFirstFlushRollsBackEveryDraftAndCanRetry(): void
    {
        static::getContainer()->get('limiter.publication_draft')->create('draft-'.$this->owner->getId())->consume(29);
        $ownerId = $this->owner->getId();
        try {
            static::getContainer()->get(PublicationBatch::class)->prepare($this->owner, str_repeat('b', 48), $this->plans());
            self::fail('The rate limiter must interrupt the second row.');
        } catch (PublicationException $error) {
            self::assertStringContainsString('sauvegardes', $error->getMessage());
        }
        self::assertSame(0, $this->em->getRepository(PublicationDraft::class)->count([]));
        static::getContainer()->get('cache.rate_limiter')->clear();
        $owner = $this->em->find(User::class, $ownerId);
        self::assertCount(3, static::getContainer()->get(PublicationBatch::class)->prepare($owner, str_repeat('b', 48), $this->plans()));
        $this->noSend();
    }

    public function testWorkspaceIsPrivateAndInvalidCsrfOrExtraVideoIsRejected(): void
    {
        $crawler = $this->start();
        $payload = $crawler->selectButton('Enregistrer mes préparations →')->form()->getPhpValues();
        $payload['publication_batch']['plans'] = $this->plans();
        $valid = $payload;
        $payload['publication_batch']['_token'] = 'forged';
        $this->client->request('POST', $this->url, $payload);
        self::assertResponseStatusCodeSame(422);
        $valid['publication_batch']['plans'][999999] = $this->plans()[$this->ids[0]];
        $this->client->request('POST', $this->url, $valid);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(PublicationDraft::class)->count([]));
        $this->client->loginUser($this->other);
        $this->client->request('GET', $this->url);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/composer', ['videos' => $this->ids]);
        self::assertResponseStatusCodeSame(404);
        $this->noSend();
    }
}
