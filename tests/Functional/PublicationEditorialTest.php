<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiSeries;
use App\Entity\PublicationDraft;
use App\Entity\StoryProposal;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\EpisodePublications;
use App\Service\LocalStoryWriter;
use App\Service\PublicationDrafts;
use App\Service\PublicationEditorial;
use App\Service\PublicationException;
use App\Service\StoryProposals;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PublicationEditorialTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private AiSeries $series;
    private AiEpisode $episode;
    private PublicationDraft $draft;
    private int $calls = 0;
    private bool $invalidPack = false;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $this->em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['youtube.http', 'instagram.http', 'tiktok.http', 'ai.http'] as $id) {
            static::getContainer()->set($id, new MockHttpClient(static function (): never { throw new \LogicException('No provider or social request authorized.'); }));
        }
        $http = new MockHttpClient(function (): MockResponse {
            ++$this->calls;
            $texts = ['youtube' => ['title' => 'Une découverte avec Milo', 'description' => 'Milo ouvre sa boîte.', 'hashtags' => ['Milo']], 'instagram' => ['caption' => 'Une pause avec Milo.', 'hashtags' => ['Milo']], 'tiktok' => ['caption' => 'La boîte de Milo.', 'hashtags' => ['Milo']]];
            if ($this->invalidPack) {
                $texts['youtube']['description'] .= ' #Milo';
            }

            return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($texts, JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR));
        });
        static::getContainer()->set(LocalStoryWriter::class, new LocalStoryWriter($http, true, 'http://localhost:11434', 'synthetic-local-model'));
        $this->owner = new User('editorial-synthetic@example.test');
        $this->owner->verify();
        $this->owner->setPassword('unused');
        $this->em->persist($this->owner);
        $this->series = new AiSeries($this->owner, 'Milo', 'Milo ouvre une boîte.', 'Réaliste', 'Chat blanc', ['model' => 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $this->episode = new AiEpisode($this->series, 1);
        $this->episode->flow('ready');
        $video = new VideoAsset($this->owner, 'Version approuvée', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 10, 720, 1280, 'h264');
        $video->recordTechnicalMetadata(['fps' => 30, 'videoBitrate' => 3000000]);
        $this->em->persist($video);
        $this->em->persist($this->series);
        $this->em->persist($this->episode);
        $this->episode->approve($video);
        $this->em->flush();
        $draft = static::getContainer()->get(EpisodePublications::class)->prepare($this->owner, $this->episode, $this->episode->getRevision());
        $id = $video->getId();
        $content = ['youtube' => ['videos' => [$id => ['title' => 'Mon titre', 'description' => 'Mon texte initial.', 'privacy' => 'public', 'audience' => 'yes', 'synthetic' => true]]], 'instagram' => ['videos' => [$id => ['caption' => 'Ma légende Instagram.', 'shareToFeed' => true, 'synthetic' => true]]], 'tiktok' => ['videos' => [$id => ['caption' => 'Ma légende TikTok.', 'privacy' => null, 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => true]]]];
        $this->draft = $this->drafts()->save($this->owner, [$id], ['youtube', 'instagram', 'tiktok'], $content, [], 'Mon brouillon', $draft->getId(), $draft->getRevision());
        $this->client->loginUser($this->owner);
    }

    private function editorial(): PublicationEditorial
    {
        return static::getContainer()->get(PublicationEditorial::class);
    }

    private function drafts(): PublicationDrafts
    {
        return static::getContainer()->get(PublicationDrafts::class);
    }

    private function jobs(): StoryProposals
    {
        return static::getContainer()->get(StoryProposals::class);
    }

    private function ready(): StoryProposal
    {
        $job = $this->editorial()->request($this->owner, $this->draft, 'Milo ouvre la boîte.');
        $this->jobs()->process($job->getId());
        self::assertSame('ready', $job->getStatus(), $job->getError() ?? '');

        return $job;
    }

    public function testRejectedPackKeepsHumanTextsAndRequiresManualRetryAndApplication(): void
    {
        $before = $this->draft->content();
        $this->invalidPack = true;
        $job = $this->editorial()->request($this->owner, $this->draft, 'Milo ouvre la boîte.');
        $this->jobs()->process($job->getId());
        $this->jobs()->process($job->getId());
        self::assertSame('failed', $job->getStatus());
        self::assertStringContainsString('Les hashtags doivent rester séparés', $job->getError());
        self::assertStringContainsString('Demande une nouvelle proposition', $job->getError());
        self::assertNull($job->getResult());
        self::assertSame([], $this->editorial()->view($this->draft)['texts']);
        self::assertSame($before, $this->draft->content());
        self::assertSame(1, $this->calls);
        $this->client->request('GET', '/studio/composer', ['draft' => $this->draft->getId()]);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-publication-writer-target="status"]', 'Les hashtags doivent rester séparés');
        self::assertSame(1, $this->calls);

        $this->owner = $this->em->find(User::class, $this->owner->getId());
        $this->draft = $this->em->find(PublicationDraft::class, $this->draft->getId());
        $this->invalidPack = false;
        $retry = $this->editorial()->request($this->owner, $this->draft, 'Milo ouvre la boîte.');
        self::assertNotSame($job->getId(), $retry->getId());
        $this->jobs()->process($retry->getId());
        self::assertSame('ready', $retry->getStatus(), $retry->getError() ?? '');
        self::assertSame(2, $this->calls);
        self::assertSame($before, $this->draft->content());
        self::assertSame('draft', $this->draft->getStatus());
        self::assertNull($this->draft->getSubmissionKey());
    }

    public function testMissingSeriesTextsQueueOneProposalWithoutApplyingOrPublishing(): void
    {
        $video = $this->draft->videoIds()[0];
        $content = $this->draft->content();
        $content['youtube']['videos'][$video]['description'] = '';
        $this->drafts()->save($this->owner, [$video], $this->draft->getNetworks(), $content, [], 'Brouillon', $this->draft->getId(), $this->draft->getRevision());
        $job = $this->editorial()->suggestMissing($this->owner, $this->draft);
        self::assertNotNull($job);
        self::assertSame('queued', $job->getStatus());
        self::assertNull($this->editorial()->suggestMissing($this->owner, $this->draft));
        self::assertSame(0, $this->calls);
        $this->jobs()->process($job->getId());
        self::assertSame('ready', $job->getStatus(), $job->getError() ?? '');
        self::assertStringContainsString('#Milo', $job->getResult()['texts']['youtube']['description']);
        self::assertSame('', $this->draft->content()['youtube']['videos'][$video]['description'] ?? '');
        self::assertSame('Mon titre', $this->draft->content()['youtube']['videos'][$video]['title']);
        self::assertSame('draft', $this->draft->getStatus());
        self::assertNull($this->draft->getSubmissionKey());
        self::assertSame(1, $this->calls);
    }

    public function testOldFrozenDraftRemainsUsableWhenAutomaticSuggestionCannotUseItsSource(): void
    {
        $this->episode->flow('draft');
        $this->em->flush();
        self::assertNull($this->editorial()->suggestMissing($this->owner, $this->draft));
        self::assertSame('Mon titre', $this->draft->content()['youtube']['videos'][$this->draft->videoIds()[0]]['title']);
        self::assertNull($this->jobs()->latest($this->owner, $this->draft->getId(), 'editorial'));
        self::assertSame(0, $this->calls);
    }

    public function testAutomaticSuggestionPreservesWrittenAndDeliberatelyClearedTexts(): void
    {
        self::assertNull($this->editorial()->suggestMissing($this->owner, $this->draft));
        $job = $this->ready();
        $content = $this->draft->content();
        $content['youtube']['videos'][$this->draft->videoIds()[0]]['description'] = '';
        $this->drafts()->save($this->owner, $this->draft->videoIds(), $this->draft->getNetworks(), $content, [], 'Brouillon', $this->draft->getId(), $this->draft->getRevision());
        $this->series->configureEditorial(['language' => 'fr', 'tone' => 'Doux']);
        $this->em->flush();
        self::assertNull($this->editorial()->suggestMissing($this->owner, $this->draft));
        self::assertSame($job, $this->jobs()->latest($this->owner, $this->draft->getId(), 'editorial'));
        self::assertSame('human', $this->editorial()->fields($this->draft)['youtube.description']['origin']);
    }

    public function testSavingAnEmptyDescriptionInComposerAutomaticallyStartsTheProposal(): void
    {
        $this->client->request('GET', '/studio/composer', ['draft' => $this->draft->getId()]);
        $crawler = $this->client->followRedirect();
        $url = $this->client->getRequest()->getUri();
        $values = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $video = $this->draft->videoIds()[0];
        $values['publication_composer']['settings']['youtube']['videos'][$video]['description'] = '';
        $values['intent'] = 'save';
        $this->client->request('POST', $url, $values);
        self::assertResponseRedirects($url, 303);
        $draft = $this->em->find(PublicationDraft::class, $this->draft->getId());
        $job = $this->jobs()->latest($draft->getOwner(), $draft->getId(), 'editorial');
        self::assertNotNull($job);
        self::assertSame('queued', $job->getStatus());
        self::assertSame('', $draft->content()['youtube']['videos'][$video]['description'] ?? '');
        self::assertSame('draft', $draft->getStatus());
        self::assertNull($draft->getSubmissionKey());
        self::assertSame(0, $this->calls);
    }

    public function testEditorialJobsAreIsolatedAndApplyingOneFieldKeepsSettingsAndOtherTexts(): void
    {
        $job = $this->editorial()->request($this->owner, $this->draft, 'Milo ouvre la boîte.');
        self::assertSame('editorial', $job->getKind());
        self::assertNull($this->jobs()->latest($this->owner, $this->draft->getId()));
        self::assertSame($job, $this->editorial()->request($this->owner, $this->draft, 'Milo ouvre la boîte.'));
        self::assertSame(0, $this->calls);
        $this->jobs()->process($job->getId());
        $this->jobs()->process($job->getId());
        self::assertSame(1, $this->calls);
        self::assertSame('ready', $job->getStatus(), $job->getError() ?? '');
        $video = $this->draft->videoIds()[0];
        $this->editorial()->apply($this->owner, $this->draft, $job->getId(), ['youtube.description']);
        $content = $this->draft->content();
        self::assertSame('Mon titre', $content['youtube']['videos'][$video]['title']);
        self::assertStringContainsString('#Milo', $content['youtube']['videos'][$video]['description'] ?? '');
        self::assertSame('public', $content['youtube']['videos'][$video]['privacy']);
        self::assertSame('yes', $content['youtube']['videos'][$video]['audience']);
        self::assertSame('Ma légende TikTok.', $content['tiktok']['videos'][$video]['caption']);
        self::assertSame('draft', $this->draft->getStatus());
        self::assertNull($this->draft->getSubmissionKey());
        self::assertSame('generated', $this->editorial()->fields($this->draft)['youtube.description']['origin']);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM youtube_publication'));
        $this->client->request('GET', '/studio/creation/scenario/'.$job->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testEditsMadeDuringWritingAreSavedAndNeverOverwrittenWithoutExplicitAgreement(): void
    {
        $job = $this->ready();
        $video = $this->draft->videoIds()[0];
        $content = $this->draft->content();
        $content['youtube']['videos'][$video]['description'] = 'Mon texte changé pendant l’attente.';
        $this->drafts()->save($this->owner, $this->draft->videoIds(), $this->draft->getNetworks(), $content, [], $this->draft->getTitle(), $this->draft->getId(), $this->draft->getRevision());
        try {
            $this->editorial()->apply($this->owner, $this->draft, $job->getId(), ['youtube.description']);
            self::fail('A late result must not silently replace a human edit.');
        } catch (PublicationException $error) {
            self::assertStringContainsString('changé depuis', $error->getMessage());
        }
        self::assertSame('Mon texte changé pendant l’attente.', $this->draft->content()['youtube']['videos'][$video]['description'] ?? '');
        $this->editorial()->apply($this->owner, $this->draft, $job->getId(), ['youtube.description'], true);
        self::assertStringContainsString('#Milo', $this->draft->content()['youtube']['videos'][$video]['description'] ?? '');
        $content = $this->draft->content();
        $content['youtube']['videos'][$video]['description'] = '';
        $this->drafts()->save($this->owner, $this->draft->videoIds(), $this->draft->getNetworks(), $content, [], $this->draft->getTitle(), $this->draft->getId(), $this->draft->getRevision());
        self::assertSame('human', $this->editorial()->fields($this->draft)['youtube.description']['origin']);
        self::assertSame('', $this->draft->content()['youtube']['videos'][$video]['description'] ?? '');
    }

    public function testNewProfileOrVideoInvalidatesTheProposalAndScheduledDraftCannotBeChanged(): void
    {
        $job = $this->ready();
        $this->series->configureEditorial(['language' => 'fr', 'tone' => 'Calme']);
        $this->em->flush();
        self::assertTrue($this->editorial()->view($this->draft)['stale']);
        try {
            $this->editorial()->apply($this->owner, $this->draft, $job->getId(), ['youtube.title']);
            self::fail('A changed profile requires a new proposal.');
        } catch (PublicationException $error) {
            self::assertStringContainsString('ne correspond plus', $error->getMessage());
        }
        $this->draft->arm(new \DateTimeImmutable());
        $this->em->flush();
        $this->expectException(PublicationException::class);
        $this->editorial()->request($this->owner, $this->draft);
    }

    public function testReturningToTheDraftRestoresTheQueuedRequestWithoutStartingAnother(): void
    {
        $job = $this->editorial()->request($this->owner, $this->draft, 'Milo ouvre sa boîte.');
        $messages = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages');
        $this->client->request('GET', '/studio/series');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/studio/composer', ['draft' => $this->draft->getId()]);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-publication-writer-target="status"]', 'en attente');
        self::assertSelectorTextContains('[data-publication-writer-target="progress"]', 'Étape 1 sur 2');
        self::assertSelectorExists('button[data-publication-writer-target="generate"][disabled]');
        self::assertSelectorExists('[data-publication-writer-target="elapsed"][aria-live="off"]');
        $this->client->request('GET', '/studio/brouillons/'.$this->draft->getId().'/textes/'.$job->getId());
        self::assertResponseIsSuccessful();
        $state = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('queued', $state['status']);
        self::assertNull($state['startedAt']);
        self::assertSame('editorial', $state['kind']);
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame($messages, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(0, $this->calls);
    }

    public function testComposerPreviewAndPrivateStatusUseTheCurrentSavedDraft(): void
    {
        $job = $this->ready();
        $this->client->request('GET', '/studio/composer', ['draft' => $this->draft->getId()]);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#editorial-title', 'textes adaptés');
        self::assertSelectorExists('a[href="/studio/series/'.$this->series->getId().'/textes"]');
        self::assertSelectorNotExists('input[name="editorial_fields[]"][checked]');
        $url = '/studio/brouillons/'.$this->draft->getId().'/textes/'.$job->getId();
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSame('ready', json_decode($this->client->getResponse()->getContent(), true)['status']);
        $timed = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('editorial', $timed['kind']);
        self::assertNotNull($timed['startedAt']);
        // PostgreSQL timestamps may round a persisted phase to the next second.
        self::assertLessThanOrEqual(1, (new \DateTimeImmutable($timed['startedAt']))->getTimestamp() - (new \DateTimeImmutable($timed['observedAt']))->getTimestamp());
        $other = new User('other-editorial@example.test');
        $other->verify();
        $other->setPassword('unused');
        $this->em->persist($other);
        $this->em->flush();
        $this->client->loginUser($other);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/series/'.$this->series->getId().'/textes');
        self::assertResponseStatusCodeSame(404);
    }

    public function testComposerSavesCurrentEditsBeforeRequestAndApplicationWithoutArmingPublication(): void
    {
        $this->client->request('GET', '/studio/composer', ['draft' => $this->draft->getId()]);
        $crawler = $this->client->followRedirect();
        $url = $this->client->getRequest()->getUri();
        $values = $crawler->selectButton('Enregistrer et proposer mes textes')->form()->getPhpValues();
        $video = $this->draft->videoIds()[0];
        $values['publication_composer']['settings']['youtube']['videos'][$video]['title'] = 'Mon titre saisi juste avant la demande';
        $values['editorial_brief'] = 'Milo ouvre la boîte.';
        $values['intent'] = 'editorial';
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(303);
        $draft = $this->em->find(PublicationDraft::class, $this->draft->getId());
        self::assertSame('Mon titre saisi juste avant la demande', $draft->content()['youtube']['videos'][$video]['title']);
        $job = $this->jobs()->latest($draft->getOwner(), $draft->getId(), 'editorial');
        self::assertNotNull($job);
        self::assertSame('queued', $job->getStatus());
        self::assertSame(0, $this->calls);
        $this->jobs()->process($job->getId());
        $crawler = $this->client->request('GET', $url);
        $values = $crawler->selectButton('Enregistrer et appliquer les champs cochés')->form()->getPhpValues();
        $values['editorial_fields'] = ['youtube.description'];
        $values['publication_composer']['settings']['youtube']['videos'][$video]['title'] = 'Encore mon titre humain';
        $values['intent'] = 'editorial_apply';
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(303);
        $draft = $this->em->find(PublicationDraft::class, $draft->getId());
        self::assertSame('Encore mon titre humain', $draft->content()['youtube']['videos'][$video]['title']);
        self::assertStringContainsString('#Milo', $draft->content()['youtube']['videos'][$video]['description'] ?? '');
        self::assertSame('draft', $draft->getStatus());
        self::assertNull($draft->getSubmissionKey());
        self::assertSame(1, $this->calls);
    }
}
