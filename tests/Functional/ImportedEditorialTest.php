<?php

namespace App\Tests\Functional;

use App\Entity\PublicationDraft;
use App\Entity\StoryProposal;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\VideoSeries;
use App\Entity\VideoSeriesEpisode;
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

final class ImportedEditorialTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private VideoSeries $series;
    private VideoSeriesEpisode $episode;
    private PublicationDraft $draft;
    private int $calls = 0;

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
        foreach (['youtube.http', 'instagram.http', 'tiktok.http', 'ai.http', 'story.http'] as $name) {
            static::getContainer()->set($name, new MockHttpClient(static function (): never { throw new \LogicException('No real HTTP permitted.'); }));
        }
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            ++$this->calls;
            self::assertStringContainsString('Un mug bleu tourne sur une table.', $options['body']);

            return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode(['youtube' => ['title' => 'Un mug en mouvement', 'description' => 'Un mug bleu tourne sur une table.', 'hashtags' => ['Mug']]])]], JSON_THROW_ON_ERROR));
        });
        static::getContainer()->set(LocalStoryWriter::class, new LocalStoryWriter($http, 'testDisabledWriterKeepsManualEditingAvailable' !== $this->name(), 'http://localhost:11434', 'synthetic-local-model'));
        $this->owner = new User('imported-editorial@example.test');
        $this->owner->verify();
        $this->owner->setPassword('unused');
        $this->em->persist($this->owner);
        $this->series = new VideoSeries($this->owner, 'Mes objets');
        $this->series->setPublicationSettings(['networks' => ['youtube'], 'accounts' => ['youtube' => null]]);
        $video = new VideoAsset($this->owner, 'Mon mug', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 100, 5, 720, 1280, 'h264');
        $video->recordTechnicalMetadata(['fps' => 30, 'videoBitrate' => 3000000]);
        $this->episode = new VideoSeriesEpisode($this->series, $video, 1);
        foreach ([$this->series, $video, $this->episode] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->draft = static::getContainer()->get(EpisodePublications::class)->prepareVideo($this->owner, $this->episode, $this->series->getRevision());
        $this->client->loginUser($this->owner);
    }

    private function editorial(): PublicationEditorial
    {
        return static::getContainer()->get(PublicationEditorial::class);
    }

    private function jobs(): StoryProposals
    {
        return static::getContainer()->get(StoryProposals::class);
    }

    private function ready(): StoryProposal
    {
        $this->owner = $this->em->find(User::class, $this->owner->getId());
        $this->draft = $this->em->find(PublicationDraft::class, $this->draft->getId());
        $job = $this->editorial()->request($this->owner, $this->draft, 'Un mug bleu tourne sur une table.');
        $this->jobs()->process($job->getId());
        self::assertSame('ready', $job->getStatus(), $job->getError() ?? '');

        return $job;
    }

    public function testBriefRequiredBeforeQueueAndOrdinarySaveDoesNotGenerate(): void
    {
        foreach (['', 'Mon mug'] as $brief) {
            try {
                $this->editorial()->request($this->owner, $this->draft, $brief);
                self::fail('A description distinct from the title is required.');
            } catch (PublicationException $error) {
                self::assertSame('context', $error->reason);
            }
        }
        self::assertNull($this->jobs()->latest($this->owner, $this->draft->getId(), 'editorial'));
        self::assertNull($this->editorial()->suggestMissing($this->owner, $this->draft));
        $this->client->request('GET', '/studio/composer', ['draft' => $this->draft->getId()]);
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('#editorial-brief');
        self::assertSelectorExists('a[href="/studio/series/videos/'.$this->series->getId().'/textes"]');
        $values = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $values['editorial_brief'] = 'Un mug bleu tourne sur une table.';
        $values['intent'] = 'save';
        $this->client->request('POST', $this->client->getRequest()->getUri(), $values);
        self::assertResponseStatusCodeSame(303);
        self::assertNull($this->jobs()->latest($this->owner, $this->draft->getId(), 'editorial'));
        self::assertSame('Un mug bleu tourne sur une table.', $this->em->find(PublicationDraft::class, $this->draft->getId())->getEditorial()['brief']);
        self::assertSame(0, $this->calls);
    }

    public function testComposerProposalApplicationPreservesOtherTextsAndTiming(): void
    {
        $this->client->request('GET', '/studio/composer', ['draft' => $this->draft->getId()]);
        $crawler = $this->client->followRedirect();
        $url = $this->client->getRequest()->getUri();
        $values = $crawler->selectButton('Enregistrer et proposer mes textes')->form()->getPhpValues();
        $video = $this->draft->videoIds()[0];
        $values['publication_composer']['settings']['youtube']['videos'][$video]['title'] = 'Mon titre humain';
        $values['publication_composer']['mode'] = 'scheduled';
        $values['publication_composer']['local'] = '2030-11-10T18:00';
        $values['editorial_brief'] = 'Un mug bleu tourne sur une table.';
        $values['intent'] = 'editorial';
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(303);
        $draft = $this->em->find(PublicationDraft::class, $this->draft->getId());
        $job = $this->jobs()->latest($draft->getOwner(), $draft->getId(), 'editorial');
        self::assertNotNull($job);
        self::assertSame('queued', $job->getStatus());
        self::assertSame('Mon titre humain', $draft->content()['youtube']['videos'][$video]['title']);
        self::assertSame(0, $this->calls);
        $this->jobs()->process($job->getId());
        $crawler = $this->client->request('GET', $url);
        self::assertSelectorExists('input[name="publication_composer[mode]"][value="scheduled"][checked]');
        self::assertSelectorExists('input[name="publication_composer[local]"][value="2030-11-10T18:00"]');
        self::assertSelectorNotExists('input[name="editorial_fields[]"][value="youtube.title"][checked]');
        file_put_contents('/app/artifacts/imported-editorial-preview.html', $this->client->getResponse()->getContent());
        $values = $crawler->selectButton('Enregistrer et appliquer les champs cochés')->form()->getPhpValues();
        $values['editorial_fields'] = ['youtube.description'];
        $values['intent'] = 'editorial_apply';
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(303);
        $draft = $this->em->find(PublicationDraft::class, $draft->getId());
        self::assertSame('Mon titre humain', $draft->content()['youtube']['videos'][$video]['title']);
        self::assertStringContainsString('#Mug', $draft->content()['youtube']['videos'][$video]['description']);
        self::assertFalse($draft->content()['youtube']['videos'][$video]['synthetic']);
        self::assertSame('draft', $draft->getStatus());
        self::assertNull($draft->getSubmissionKey());
        self::assertSame(1, $this->calls);
    }

    public function testProfileSavedWithRevisionAndCsrfAndUnavailableToOtherOwner(): void
    {
        $url = '/studio/series/videos/'.$this->series->getId().'/textes';
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $values = $crawler->selectButton('Enregistrer le style des textes')->form()->getPhpValues();
        $values['series_editorial']['language'] = 'en';
        $values['series_editorial']['tone'] = 'Calm';
        $bad = $values;
        $bad['series_editorial']['_token'] = 'invalid';
        $this->client->request('POST', $url, $bad);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $url, $values);
        self::assertResponseRedirects($url, 303);
        $series = $this->em->find(VideoSeries::class, $this->series->getId());
        self::assertSame('en', $series->getEditorialProfile()['language']);
        self::assertSame('Calm', $series->getEditorialProfile()['tone']);
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form[name="series_editorial"]', 'La série a changé');
        $crawler = $this->client->request('GET', $url);
        self::assertSelectorExists('a[href="/studio/series/videos/'.$series->getId().'"]');
        file_put_contents('/app/artifacts/imported-editorial-profile.html', $this->client->getResponse()->getContent());
        $other = new User('imported-editorial-other@example.test');
        $other->verify();
        $other->setPassword('unused');
        $this->em->persist($other);
        $this->em->flush();
        $this->client->loginUser($other);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->calls);
    }

    public function testReorderKeepsProposalButProfileChangeInvalidatesWithoutOverwriting(): void
    {
        $job = $this->ready();
        $this->episode->place(2);
        $this->series->revise('Nouveau nom');
        $this->em->flush();
        self::assertFalse($this->editorial()->view($this->draft)['stale']);
        $this->series->configureEditorial(['language' => 'fr', 'tone' => 'Calme']);
        $this->em->flush();
        self::assertTrue($this->editorial()->view($this->draft)['stale']);
        $before = $this->draft->content();
        try {
            $this->editorial()->apply($this->owner, $this->draft, $job->getId(), ['youtube.description']);
            self::fail('A changed profile must invalidate the result.');
        } catch (PublicationException $error) {
            self::assertStringContainsString('ne correspond plus', $error->getMessage());
        }
        self::assertSame($before, $this->draft->content());
        self::assertSame(1, $this->calls);
    }

    public function testChangedHumanTextRequiresExplicitOverwriteAndArchivedEpisodeIsBlocked(): void
    {
        $job = $this->ready();
        $content = $this->draft->content();
        $video = $this->draft->videoIds()[0];
        $content['youtube']['videos'][$video]['description'] = 'Mon texte personnel';
        static::getContainer()->get(PublicationDrafts::class)->save($this->owner, [$video], ['youtube'], $content, [], 'Mon brouillon', $this->draft->getId(), $this->draft->getRevision());
        try {
            $this->editorial()->apply($this->owner, $this->draft, $job->getId(), ['youtube.description']);
            self::fail('A new human edit must require overwrite consent.');
        } catch (PublicationException $error) {
            self::assertStringContainsString('a changé', $error->getMessage());
        }
        self::assertSame('Mon texte personnel', $this->draft->content()['youtube']['videos'][$video]['description']);
        $this->editorial()->apply($this->owner, $this->draft, $job->getId(), ['youtube.description'], true);
        self::assertStringContainsString('#Mug', $this->draft->content()['youtube']['videos'][$video]['description']);
        $this->episode->place(-$this->episode->getId(), false);
        $this->em->flush();
        self::assertFalse($this->editorial()->view($this->draft)['available']);
        $this->expectException(PublicationException::class);
        $this->editorial()->request($this->owner, $this->draft, 'Un mug bleu tourne sur une table.');
    }

    public function testRequestCsrfAndStatusOwnershipAndScheduledDraftAreProtected(): void
    {
        $this->client->request('GET', '/studio/composer', ['draft' => $this->draft->getId()]);
        $crawler = $this->client->followRedirect();
        $values = $crawler->selectButton('Enregistrer et proposer mes textes')->form()->getPhpValues();
        $values['publication_composer']['_token'] = 'invalid';
        $values['editorial_brief'] = 'Un mug bleu tourne sur une table.';
        $values['intent'] = 'editorial';
        $this->client->request('POST', $this->client->getRequest()->getUri(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(StoryProposal::class)->count([]));
        $job = $this->ready();
        $status = '/studio/brouillons/'.$this->draft->getId().'/textes/'.$job->getId();
        $this->client->request('GET', $status);
        self::assertResponseIsSuccessful();
        self::assertSame('ready', json_decode($this->client->getResponse()->getContent(), true)['status']);
        $other = new User('imported-status-other@example.test');
        $other->verify();
        $other->setPassword('unused');
        $this->em->persist($other);
        $this->em->flush();
        $this->client->loginUser($other);
        $this->client->request('GET', $status);
        self::assertResponseStatusCodeSame(404);
        $this->draft = $this->em->find(PublicationDraft::class, $this->draft->getId());
        $this->owner = $this->em->find(User::class, $this->owner->getId());
        $this->draft->arm(new \DateTimeImmutable());
        $this->em->flush();
        $this->expectException(PublicationException::class);
        $this->editorial()->apply($this->owner, $this->draft, $job->getId(), ['youtube.description']);
    }

    public function testDisabledWriterKeepsManualEditingAvailable(): void
    {
        $this->client->request('GET', '/studio/composer', ['draft' => $this->draft->getId()]);
        $crawler = $this->client->followRedirect();
        self::assertSelectorExists('button[data-publication-writer-target="generate"][disabled]');
        self::assertSelectorTextContains('.story-context', 'La rédaction locale n’est pas activée');
        file_put_contents('/app/artifacts/imported-editorial-disabled.html', $this->client->getResponse()->getContent());
        $values = $crawler->selectButton('Enregistrer le brouillon')->form()->getPhpValues();
        $video = $this->draft->videoIds()[0];
        $values['publication_composer']['settings']['youtube']['videos'][$video]['description'] = 'Texte entièrement rédigé à la main';
        $values['intent'] = 'save';
        $this->client->request('POST', $this->client->getRequest()->getUri(), $values);
        self::assertResponseStatusCodeSame(303);
        $draft = $this->em->find(PublicationDraft::class, $this->draft->getId());
        self::assertSame('Texte entièrement rédigé à la main', $draft->content()['youtube']['videos'][$video]['description']);
        self::assertSame(0, $this->calls);
        self::assertNull($draft->getSubmissionKey());
    }
}
