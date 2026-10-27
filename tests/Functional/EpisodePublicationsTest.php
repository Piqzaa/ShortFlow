<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\InstagramConnection;
use App\Entity\InstagramPublication;
use App\Entity\PublicationDraft;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Entity\YouTubePublication;
use App\Service\EpisodePublications;
use App\Service\PublicationDrafts;
use App\Service\PublicationException;
use App\Service\TokenVault;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;

final class EpisodePublicationsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private AiEpisode $episode;
    private VideoAsset $video;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        static::getContainer()->set('app.publication_clock', new MockClock('2026-10-24T08:00:00+00:00'));
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['youtube.http', 'instagram.http', 'tiktok.http', 'ai.http'] as $id) {
            static::getContainer()->set($id, new MockHttpClient(static function (): never { throw new \LogicException('No external request allowed'); }));
        }
        $this->owner = new User('episode-publications@example.test');
        $this->owner->verify();
        $this->owner->setPassword('unused');
        $this->em->persist($this->owner);
        $series = new AiSeries($this->owner, 'Milo', 'Milo joue', 'réaliste', 'chat blanc', ['model' => 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $this->em->persist($series);
        $this->episode = new AiEpisode($series, 1);
        $this->episode->flow('ready');
        $this->video = $this->newVideo('Version approuvée');
        $this->episode->approve($this->video);
        $this->em->persist($this->episode);
        $this->em->flush();
        $this->client->loginUser($this->owner);
    }

    private function newVideo(string $title): VideoAsset
    {
        $this->owner = $this->owner->getId() ? $this->em->find(User::class, $this->owner->getId()) : $this->owner;
        $video = new VideoAsset($this->owner, $title, bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 10, 720, 1280, 'h264');
        $video->recordTechnicalMetadata(['fps' => 30, 'videoBitrate' => 3000000]);
        $this->em->persist($video);
        $this->em->flush();

        return $video;
    }

    private function service(): EpisodePublications
    {
        return static::getContainer()->get(EpisodePublications::class);
    }

    public function testAddingANetworkToAnAiEpisodeDefaultsToSyntheticWithoutSending(): void
    {
        $draft = $this->service()->prepare($this->owner, $this->episode, $this->episode->getRevision());
        $this->client->request('GET', '/studio/composer', ['draft' => $draft->getId()]);
        $crawler = $this->client->followRedirect();
        $values = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $values['publication_composer']['networks'] = ['youtube'];
        $values['intent'] = 'refresh';
        $this->client->request('POST', $this->client->getRequest()->getUri(), $values);
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name$="[synthetic]"][checked]');
        self::assertSelectorExists('input[name$="[title]"]');
        self::assertSelectorExists('textarea[name$="[description]"]');
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM youtube_publication'));
    }

    public function testPreparationIsDurablePrivateAndIdempotentAndKeepsItsSource(): void
    {
        $url = '/studio/series/episodes/'.$this->episode->getId();
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Préparer et programmer la publication →')->form();
        $this->client->submit($form);
        self::assertResponseRedirects();
        $draft = $this->em->getRepository(PublicationDraft::class)->findOneBy(['episode' => $this->episode]);
        self::assertNotNull($draft);
        self::assertSame([$this->video->getId()], $draft->videoIds());
        self::assertSame([], $draft->getNetworks());
        $this->client->submit($form);
        self::assertResponseRedirects();
        self::assertSame(1, $this->em->getRepository(PublicationDraft::class)->count(['episode' => $this->episode]));
        $this->client->request('GET', '/studio/composer', ['draft' => $draft->getId()]);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.library-note', 'version validée');
        self::assertSelectorNotExists('button[name="intent"][value^="edit_"]');
        $this->client->request('GET', '/studio/series/'.$this->episode->getSeries()->getId());
        self::assertSelectorTextContains('.episode-publications', 'Brouillon');
        $this->client->request('POST', $url.'/publication', ['revision' => $this->episode->getRevision(), '_token' => 'bad']);
        self::assertResponseStatusCodeSame(403);
        $other = new User('other-episode-publications@example.test');
        $other->verify();
        $other->setPassword('unused');
        $this->em->persist($other);
        $this->em->flush();
        $this->client->loginUser($other);
        $this->client->request('POST', $url.'/publication');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM youtube_publication'));
        $replacement = $this->newVideo('Autre vidéo');
        $this->expectException(PublicationException::class);
        static::getContainer()->get(PublicationDrafts::class)->save($this->owner, [$replacement->getId()], [], [], [], 'Changed', $draft->getId(), $draft->getRevision());
    }

    public function testSchedulingAppearsInSeriesAndCalendarAndRequiresCancelBeforeNewVersion(): void
    {
        $vault = new TokenVault($_ENV['YOUTUBE_TOKEN_KEY']);
        $connection = new YouTubeConnection($this->owner, 'UC'.str_repeat('a', 22), 'Test', $vault->encrypt('synthetic'), $vault->encrypt('synthetic'), 86400);
        $this->em->persist($connection);
        $this->em->flush();
        $draft = $this->service()->prepare($this->owner, $this->episode, $this->episode->getRevision());
        $id = $this->video->getId();
        $drafts = static::getContainer()->get(PublicationDrafts::class);
        $drafts->save($this->owner, [$id], ['youtube'], ['youtube' => ['videos' => [$id => ['title' => 'Milo', 'description' => 'Épisode', 'privacy' => 'private', 'audience' => 'no', 'synthetic' => true]]]], ['youtube' => $connection->getChannelId()], 'Milo', $draft->getId(), $draft->getRevision());
        $drafts->plan($this->owner, $draft->getId(), $draft->getRevision(), '2026-10-27T18:00', 'Europe/Paris');
        $drafts->confirm($this->owner, $draft->getId(), $draft->getRevision(), true);
        self::assertSame('scheduled', $draft->getStatus());
        self::assertSame($draft, $this->service()->prepare($this->owner, $this->episode, $this->episode->getRevision()));
        $this->client->request('GET', '/studio/series/'.$this->episode->getSeries()->getId());
        self::assertSelectorTextContains('.episode-publications', 'Programmée');
        self::assertSelectorTextContains('.episode-publications', '27/10/2026 à 18:00');
        self::assertSelectorTextContains('.episode-publications', 'YouTube');
        $this->client->request('GET', '/studio/calendrier?month=2026-10');
        self::assertSelectorExists('a.calendar-entry[href="/studio/brouillons/'.$draft->getId().'"]');
        $this->episode = $this->em->find(AiEpisode::class, $this->episode->getId());
        $draft = $this->em->find(PublicationDraft::class, $draft->getId());
        $this->episode->approve($this->newVideo('Nouvelle version'));
        $this->em->flush();
        try {
            $this->service()->prepare($this->owner, $this->episode, $this->episode->getRevision());
            self::fail('Old schedule must be cancelled first');
        } catch (PublicationException $error) {
            self::assertStringContainsString('Annule', $error->getMessage());
        }
        self::assertSame([$id], $draft->videoIds());
        $drafts->transition($this->owner, $draft->getId(), $draft->getRevision(), 'cancel');
        $next = $this->service()->prepare($this->owner, $this->episode, $this->episode->getRevision());
        self::assertNotSame($draft->getId(), $next->getId());
        $summaries = $this->service()->summaries([$this->episode])[$this->episode->getId()];
        self::assertCount(2, $summaries);
        self::assertTrue($summaries[0]['current']);
        self::assertFalse($summaries[1]['current']);
        self::assertSame('Annulée', $summaries[1]['label']);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM youtube_publication'));
    }

    public function testUnapprovedAndStaleEpisodesCannotCreatePreparations(): void
    {
        $revision = $this->episode->getRevision();
        $this->episode->approve($this->video);
        $this->em->flush();
        try {
            $this->service()->prepare($this->owner, $this->episode, $revision);
            self::fail('Stale revision must be refused');
        } catch (PublicationException $error) {
            self::assertStringContainsString('changé', $error->getMessage());
        }
        $this->episode->flow('running');
        $this->em->flush();
        $this->expectException(PublicationException::class);
        $this->service()->prepare($this->owner, $this->episode, $this->episode->getRevision());
    }

    public function testRealStoredDestinationStatesAreProjectedWithoutAnyApiCall(): void
    {
        $vault = new TokenVault($_ENV['YOUTUBE_TOKEN_KEY']);
        $youtube = new YouTubeConnection($this->owner, 'UC'.str_repeat('a', 22), 'Test', $vault->encrypt('synthetic'), $vault->encrypt('synthetic'), 86400);
        $instagram = new InstagramConnection($this->owner, '17841400000000001', 'test', 'MEDIA_CREATOR', $vault->encrypt('synthetic'), 86400);
        $this->em->persist($youtube);
        $this->em->persist($instagram);
        $draft = $this->service()->prepare($this->owner, $this->episode, $this->episode->getRevision());
        $now = new \DateTimeImmutable('2026-10-24T08:00:00Z');
        $draft->edit('Milo', ['youtube', 'instagram'], [], $now);
        $draft->beginImmediate($now);
        $draft->dispatched($now);
        $one = new YouTubePublication($this->owner, $this->video, $youtube, $draft->getSubmissionKey(), 'Milo', '', 'private', false, true);
        $one->complete('private');
        $two = new InstagramPublication($this->owner, $this->video, $instagram, $draft->getSubmissionKey(), 'Milo', false, true);
        $two->change('failed');
        $this->em->persist($one);
        $this->em->persist($two);
        $this->em->flush();
        $summary = $this->service()->summaries([$this->episode])[$this->episode->getId()][0];
        self::assertSame('Réussite partielle', $summary['label']);
        self::assertSame([['name' => 'YouTube', 'status' => 'Publiée'], ['name' => 'Instagram Reels', 'status' => 'Échec']], $summary['destinations']);
        $this->client->request('GET', '/studio/series/'.$this->episode->getSeries()->getId());
        self::assertSelectorTextContains('.episode-publications', 'YouTube · Publiée');
        self::assertSelectorTextContains('.episode-publications', 'Instagram Reels · Échec');
    }

    public function testCalendarSnapshotCountsSourcesWithoutLoadingTheirTexts(): void
    {
        $youtube = new YouTubeConnection($this->owner, 'UC'.str_repeat('b', 22), 'Test', 'synthetic', 'synthetic', 86400);
        $draft = new PublicationDraft($this->owner, 'Suivi léger', new \DateTimeImmutable());
        $draft->addItem(new \App\Entity\PublicationDraftItem($draft, $this->video, 0));
        $draft->edit('Suivi léger', ['youtube'], ['youtube' => $youtube->getChannelId()], new \DateTimeImmutable());
        $draft->beginImmediate(new \DateTimeImmutable());
        $draft->dispatched(new \DateTimeImmutable());
        $job = new YouTubePublication($this->owner, $this->video, $youtube, $draft->getSubmissionKey(), 'Milo', '', 'private', false, false);
        $job->change('published');
        $this->em->persist($youtube);
        $this->em->persist($draft);
        $this->em->persist($job);
        $this->em->flush();
        $id = $draft->getId();
        $key = $draft->getSubmissionKey();
        $this->em->clear();
        $loaded = $this->em->find(PublicationDraft::class, $id);
        $items = $loaded->getItems();
        self::assertInstanceOf(\Doctrine\ORM\PersistentCollection::class, $items);
        self::assertFalse($items->isInitialized());
        $snapshot = static::getContainer()->get(\App\Service\PublicationCalendar::class)->snapshot([$loaded]);
        self::assertSame('published', $snapshot['states'][$id]);
        self::assertSame('published', $snapshot['destinations'][$key]['youtube']);
        self::assertFalse($items->isInitialized());
    }

    public function testStandaloneStudioVideoUsesTheSamePublicationFlow(): void
    {
        $video = new AiEpisode($this->owner, 1, 'Une vidéo studio', '', $this->episode->getPreparationRecipe());
        $video->flow('ready');
        $video->approve($this->video);
        $this->em->persist($video);
        $this->em->flush();
        $draft = $this->service()->prepare($this->owner, $video, $video->getRevision());
        self::assertSame('Une vidéo studio', $draft->getTitle());
        self::assertSame($video, $draft->getEpisode());
    }
}
