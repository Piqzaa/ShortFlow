<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\AiSeries;
use App\Entity\SeriesOccurrence;
use App\Entity\SeriesRecurrence;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Message\PrepareSeriesEpisode;
use App\Service\AiEpisodeFlow;
use App\Service\AiGenerations;
use App\Service\AiKeys;
use App\Service\EpisodePublications;
use App\Service\LocalStoryWriter;
use App\Service\SeriesRecurrences;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SeriesRecurrencesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $owner;
    private AiSeries $series;
    private MockClock $clock;
    private int $textCalls = 0;
    private int $paidCalls = 0;
    private int $providerPosts = 0;
    private ?\Closure $duringWriting = null;
    private bool $badStory = false;
    private bool $acceptProvider = false;
    private bool $suspiciousStory = false;

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
        $this->clock = new MockClock('2026-10-24T08:00:00Z');
        static::getContainer()->set('app.publication_clock', $this->clock);
        foreach (['youtube.http', 'instagram.http', 'tiktok.http'] as $id) {
            static::getContainer()->set($id, new MockHttpClient(static function (): never { throw new \LogicException('No social calls'); }));
        }
        static::getContainer()->set('ai.http', new MockHttpClient(function (string $method): MockResponse {
            ++$this->paidCalls;
            if ('POST' === $method) {
                ++$this->providerPosts;
            }
            if ($this->acceptProvider && 'POST' === $method) {
                return new MockResponse('{"status_url":"https://queue.fal.run/fal-ai/kling-video/requests/synthetic/status","response_url":"https://queue.fal.run/fal-ai/kling-video/requests/synthetic"}');
            }
            if ($this->acceptProvider && 'GET' === $method) {
                return new MockResponse('{"status":"IN_QUEUE"}');
            }
            throw new \LogicException('No billable provider request expected');
        }));
        static::getContainer()->set(LocalStoryWriter::class, new LocalStoryWriter(new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            ++$this->textCalls;
            if ($this->duringWriting) {
                $action = $this->duringWriting;
                $this->duringWriting = null;
                $action();
            }
            if ($this->badStory) {
                return new MockResponse('{"done":true,"done_reason":"stop","message":{"content":"bad json"}}');
            }
            $input = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
            $brief = json_decode($input['messages'][1]['content'], true, 32, JSON_THROW_ON_ERROR);
            if (isset($input['format']['properties']['beats'])) {
                self::assertSame('Milo, chat blanc joueur', $brief['context']['characters']);
                $count = $input['format']['properties']['beats']['maxItems'];
                $result = ['title' => 'Milo et la boîte', 'summary' => 'Milo découvre une boîte et finit par dormir dedans.', 'identity' => 'white playful cat', 'beats' => array_map(static fn (int $i): string => 'Milo explore la boîte, étape '.$i, range(1, $count))];
            } else {
                $result = ['identity' => 'White playful cat Milo'.($this->suspiciousStory ? ' wearing a leather collar' : ''), 'shots' => array_map(static fn (int $s, int $i): array => ['seconds' => $s, 'prompt' => 'The same white cat Milo explores a cardboard box in a sunny room, scene '.$i.'. Slow camera push.'], $brief['seconds'], array_keys($brief['seconds']))];
            }

            return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($result, JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR));
        }), true, 'http://localhost:11434', 'synthetic'));
        $this->owner = new User('recurrences@example.test');
        $this->owner->verify();
        $this->owner->setPassword('unused');
        $this->em->persist($this->owner);
        $this->em->flush();
        static::getContainer()->get(AiKeys::class)->save($this->owner, 'synthetic-recurrence-key-never-real-1234567890');
        $this->series = new AiSeries($this->owner, 'Milo', 'Milo découvre les objets du quotidien', 'réaliste', 'Milo, chat blanc joueur', ['model' => 'kling-25', 'totalSeconds' => 30, 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop']);
        $this->em->persist($this->series);
        $this->em->flush();
        $this->client->loginUser($this->owner);
    }

    private function service(): SeriesRecurrences
    {
        return static::getContainer()->get(SeriesRecurrences::class);
    }

    public function testOccurrenceWithoutPublicationShowsItsOwnSavedDestination(): void
    {
        $id = 'UC'.str_repeat('a', 22);
        $this->em->persist(new \App\Entity\YouTubeConnection($this->owner, $id, 'Original destination', 'synthetic', 'synthetic', 86400));
        $this->em->persist(new \App\Entity\YouTubeConnection($this->owner, 'UC'.str_repeat('b', 22), 'Other destination', 'synthetic', 'synthetic', 86400));
        $this->em->flush();
        $recurrence = $this->service()->activate($this->series, $this->data());
        $episode = new AiEpisode($this->series, 1);
        $occurrence = new SeriesOccurrence($recurrence, $recurrence->getNextAt(), 'Europe/Paris', ['youtube'], $this->clock->now(), $episode, ['youtube' => $id]);
        $this->em->persist($episode);
        $this->em->persist($occurrence);
        $this->em->flush();
        $this->client->request('GET', '/studio/series/'.$this->series->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#episode-'.$episode->getId(), 'Original destination · '.$id);
        self::assertSelectorTextNotContains('#episode-'.$episode->getId(), 'Other destination');
        self::assertSame(0, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
    }

    private function confirmReviewed(AiEpisode $episode): void
    {
        $flow = static::getContainer()->get(AiEpisodeFlow::class);
        $quote = $flow->quote($episode);
        static::getContainer()->get(\App\Service\SeriesGenerationConsent::class)->manual($episode, fn () => $flow->confirm($episode, $quote['hash']));
    }

    /** @return array<string,mixed> */
    private function data(int $limit = 4): array
    {
        return ['weekday' => 2, 'hour' => '18:00', 'timezone' => 'Europe/Paris', 'networks' => ['youtube', 'tiktok'], 'limit' => $limit, 'tone' => 'auto', 'continuity' => 'independent', 'consent' => true, 'revision' => (string) ($this->service()->find($this->series)?->getRevision() ?? 0), 'quote' => $this->service()->preview($this->series)['hash']];
    }

    private function message(SeriesRecurrence $r): PrepareSeriesEpisode
    {
        return new PrepareSeriesEpisode($r->getId(), $r->getRevision(), $r->getNextAt()->format(DATE_ATOM));
    }

    private function due(): void
    {
        $this->clock->modify('2026-10-26T17:00:00Z');
    }

    private function interruptedOccurrence(SeriesRecurrence $recurrence, int $ageSeconds = 660): SeriesOccurrence
    {
        return $this->em->getConnection()->transactional(function () use ($recurrence, $ageSeconds): SeriesOccurrence {
            $episode = new AiEpisode($this->series, 1);
            $occurrence = new SeriesOccurrence($recurrence, $recurrence->getNextAt(), 'Europe/Paris', ['youtube'], $this->clock->now()->modify('-'.$ageSeconds.' seconds'), $episode);
            $recurrence->advanceSchedule($recurrence->getNextAt()->modify('+7 days'));
            $this->em->persist($episode);
            $this->em->persist($occurrence);
            $this->em->flush();

            return $occurrence;
        });
    }

    public function testRecoverMarksAnInterruptedWritingBeforeWakingOtherSlotsWithoutReplayingIt(): void
    {
        $recurrence = $this->service()->activate($this->series, $this->data());
        $message = $this->message($recurrence);
        $this->due();
        $occurrence = $this->interruptedOccurrence($recurrence);
        $wakeUps = (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'story_writing'");
        $this->service()->produce($message);
        self::assertSame('writing', $occurrence->getStatus(), 'A redelivery must not create another episode or restart its writer.');
        $tester = new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\RecoverSeriesRecurrencesCommand($this->em, $this->service()));
        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('1 interrupted writing', $tester->getDisplay());
        self::assertSame('attention', $occurrence->getStatus());
        self::assertStringContainsString('dix minutes', $occurrence->getIssue());
        self::assertSame('paused', $recurrence->getStatus());
        self::assertSame($wakeUps, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'story_writing'"), 'Recovery must pause before considering another wake-up.');
        self::assertFalse($this->service()->inspectWriting($occurrence->getId()));
        $this->service()->produce($message);
        self::assertSame(1, $this->em->getRepository(AiEpisode::class)->count([]));
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
    }

    public function testReauthorizationSkipsAnOccurrenceAlreadyCreatedForTheSameSlot(): void
    {
        $recurrence = $this->service()->activate($this->series, $this->data());
        $message = $this->message($recurrence);
        $this->due();
        $this->service()->produce($message);
        $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $recurrence]);
        $firstSlot = $occurrence->getScheduledAt();
        $this->service()->activate($this->series, $this->data());
        self::assertGreaterThan($firstSlot, $recurrence->getNextAt());
        $this->service()->produce($message);
        self::assertSame(1, $this->em->getRepository(SeriesOccurrence::class)->count(['recurrence' => $recurrence]));
        self::assertSame(2, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
    }

    public function testSixMinuteWritingIsNotInterruptedAndItsWatchIsQueuedInTheBackground(): void
    {
        $recurrence = $this->service()->activate($this->series, $this->data());
        $message = $this->message($recurrence);
        $this->due();
        $this->duringWriting = function () use ($recurrence): void {
            $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $recurrence]);
            self::assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'background'"));
            $this->clock->modify('+6 minutes');
            self::assertFalse($this->service()->inspectWriting($occurrence->getId()));
            self::assertSame('writing', $occurrence->getStatus());
            self::assertSame('active', $recurrence->getStatus());
        };
        $this->service()->produce($message);
        $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $recurrence]);
        self::assertSame('manual', $occurrence->getStatus());
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
        self::assertFalse($this->service()->inspectWriting($occurrence->getId()));
        self::assertSame(2, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
    }

    public function testAnOldWritingWatchCannotPauseANewerAuthorization(): void
    {
        $recurrence = $this->service()->activate($this->series, $this->data());
        $this->due();
        $occurrence = $this->interruptedOccurrence($recurrence);
        $oldRevision = $occurrence->getAuthorizationRevision();
        $recurrence = $this->service()->activate($this->series, $this->data());
        self::assertNotSame($oldRevision, $recurrence->getRevision());
        $next = $recurrence->getNextAt();
        self::assertTrue($this->service()->inspectWriting($occurrence->getId()));
        self::assertSame('attention', $occurrence->getStatus());
        self::assertSame('active', $recurrence->getStatus());
        self::assertEquals($next, $recurrence->getNextAt());
        self::assertSame(0, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
    }

    public function testManualTakeoverDuringWritingCannotBeOverwrittenByItsLateResult(): void
    {
        $recurrence = $this->service()->activate($this->series, $this->data());
        $message = $this->message($recurrence);
        $this->due();
        $this->duringWriting = function () use ($recurrence): void {
            $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $recurrence]);
            static::getContainer()->get(\App\Service\SeriesGenerationConsent::class)->manual($occurrence->getEpisode(), static fn () => null);
        };
        $this->service()->produce($message);
        $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $recurrence]);
        self::assertFalse($occurrence->isAutomatic());
        self::assertSame('manual', $occurrence->getStatus());
        self::assertNull($occurrence->getIssue());
        self::assertNull($occurrence->getEpisode()->getFlowState());
        self::assertFalse($this->service()->inspectWriting($occurrence->getId()));
        self::assertSame('active', $recurrence->getStatus());
        self::assertSame(0, $recurrence->getReservedCents());
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->paidCalls);
    }

    public function testQualityWarningPreservesAnEditableDraftWithoutAuthorizingAnyPaidRequest(): void
    {
        $r = $this->service()->activate($this->series, $this->data());
        $message = $this->message($r);
        $this->due();
        $this->suspiciousStory = true;
        $this->service()->produce($message);
        self::assertSame('active', $r->getStatus());
        self::assertSame(0, $r->getReservedCents());
        self::assertSame(0, $this->paidCalls);
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
        $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $r]);
        self::assertSame('manual', $occurrence->getStatus());
        self::assertFalse($occurrence->isAutomatic());
        self::assertStringContainsString('Scénario à relire', $occurrence->getIssue());
        self::assertNotNull($occurrence->getSummary());
        self::assertSame('draft', $occurrence->getEpisode()->getFlowState());
        self::assertNotEmpty(static::getContainer()->get(AiEpisodeFlow::class)->shots($occurrence->getEpisode()));
        $this->service()->produce($message);
        self::assertSame(2, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
    }

    public function testRequestedSpeechRemainsForHumanReviewWhilePreparationStaysActive(): void
    {
        $this->series->configure('Milo', 'Milo dit en français « Bonjour ! ».', 'réaliste', 'Milo, chat blanc joueur', $this->series->getRecipe());
        $this->em->flush();
        $r = $this->service()->activate($this->series, $this->data());
        $message = $this->message($r);
        $this->due();
        $this->service()->produce($message);
        self::assertSame('active', $r->getStatus());
        self::assertSame(0, $r->getReservedCents());
        self::assertSame(0, $this->paidCalls);
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
        $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $r]);
        self::assertSame('manual', $occurrence->getStatus());
        self::assertFalse($occurrence->isAutomatic());
        self::assertStringContainsString('réplique', $occurrence->getIssue());
        self::assertStringContainsString('locuteur', $occurrence->getIssue());
        self::assertStringContainsString('2 point(s)', $occurrence->getIssue());
        self::assertMatchesRegularExpression('/[.!?]$/u', $occurrence->getIssue());
        self::assertLessThanOrEqual(255, mb_strlen($occurrence->getIssue()));
        self::assertSame('draft', $occurrence->getEpisode()->getFlowState());
        self::assertNotEmpty(static::getContainer()->get(AiEpisodeFlow::class)->shots($occurrence->getEpisode()));
        $this->service()->produce($message);
        self::assertSame(2, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
    }

    public function testActivationRequiresConsentAndCsrfAndIsPrivateAndRevisioned(): void
    {
        $url = '/studio/series/'.$this->series->getId();
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Activer la préparation des scénarios →')->form();
        $values = $form->getPhpValues();
        $values['series_recurrence']['networks'] = ['youtube'];
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(SeriesRecurrence::class)->count([]));
        $invalid = $values;
        $invalid['series_recurrence']['limit'] = 'not a number';
        $this->client->request('POST', $url, $invalid);
        self::assertResponseStatusCodeSame(422);
        $values['series_recurrence']['consent'] = '1';
        $safe = $values;
        $values['series_recurrence']['_token'] = 'bad';
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $url, $safe);
        self::assertResponseRedirects();
        $this->client->request('POST', $url, $safe);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->em->getRepository(SeriesRecurrence::class)->count([]));
        self::assertSame(0, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
        $other = new User('other-recurrence@example.test');
        $other->verify();
        $other->setPassword('unused');
        $this->em->persist($other);
        $this->em->flush();
        $this->client->loginUser($other);
        $this->client->request('POST', $url, $safe);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAUniqueOccurrenceOnlyPreparesForReviewAndLaterPrefillsUnarmedPublication(): void
    {
        $r = $this->service()->activate($this->series, $this->data());
        $m = $this->message($r);
        $this->service()->produce($m);
        self::assertSame(0, $this->textCalls);
        $this->due();
        $this->service()->produce($m);
        $this->service()->produce($m);
        self::assertSame(2, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
        self::assertSame(1, $r->getStarted());
        self::assertSame('2026-11-03T17:00:00+00:00', $r->getNextAt()->format(DATE_ATOM));
        $o = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $r]);
        self::assertSame('manual', $o->getStatus());
        self::assertFalse($o->isAutomatic());
        self::assertStringContainsString('Même sans alerte', $o->getIssue());
        self::assertSame(0, $r->getReservedCents());
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
        $episode = $o->getEpisode();
        self::assertSame('draft', $episode->getFlowState());
        self::assertNull($episode->getConsentHash());
        self::assertCount(3, static::getContainer()->get(AiEpisodeFlow::class)->shots($episode));
        $video = new VideoAsset($this->owner, 'Milo final', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 30, 720, 1280, 'h264');
        $this->em->persist($video);
        $episode->flow('ready');
        $episode->approve($video);
        $this->em->flush();
        $draft = static::getContainer()->get(EpisodePublications::class)->prepare($this->owner, $episode, $episode->getRevision());
        self::assertSame('draft', $draft->getStatus());
        self::assertSame(['youtube', 'tiktok'], $draft->getNetworks());
        self::assertSame($o->getScheduledAt()->format(DATE_ATOM), $draft->getScheduledAt()->format(DATE_ATOM));
        self::assertSame('2026-10-27T18:00', $draft->getLocalTime());
        self::assertNull($draft->getSubmissionKey());
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM youtube_publication'));
    }

    public function testPauseDuringWritingCancelsTheBillableContinuation(): void
    {
        $r = $this->service()->activate($this->series, $this->data());
        $m = $this->message($r);
        $this->due();
        $this->duringWriting = fn () => $this->service()->pause($r, $r->getRevision());
        $this->service()->produce($m);
        self::assertSame('paused', $r->getStatus());
        self::assertSame(0, $r->getReservedCents());
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->paidCalls);
        $this->service()->produce($m);
        self::assertSame(2, $this->textCalls);
    }

    public function testKeyChangedAfterQueueingCannotTriggerAProviderPost(): void
    {
        $r = $this->service()->activate($this->series, $this->data());
        $m = $this->message($r);
        $this->due();
        $this->service()->produce($m);
        $o = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $r]);
        $episode = $o->getEpisode();
        $this->confirmReviewed($episode);
        static::getContainer()->get(AiEpisodeFlow::class)->advance($episode->getId());
        $job = $this->em->getRepository(AiGeneration::class)->findOneBy(['owner' => $this->owner]);
        self::assertSame('queued', $job->getStatus());
        static::getContainer()->get(AiKeys::class)->save($this->owner, 'replacement-synthetic-key-never-real-1234567890');
        static::getContainer()->get(AiGenerations::class)->process($job->getId());
        self::assertSame('waiting_key', $job->getStatus());
        static::getContainer()->get(AiEpisodeFlow::class)->advance($episode->getId());
        self::assertSame('paused', $episode->getFlowState());
        self::assertSame(0, $this->paidCalls);
    }

    public function testMissedSlotDoesNotGenerateAndTheAuthorizedCountStopsFutureScheduling(): void
    {
        $r = $this->service()->activate($this->series, $this->data(1));
        $m = $this->message($r);
        $this->clock->modify('2026-10-27T18:00:00Z');
        $this->service()->produce($m);
        self::assertSame('finished', $r->getStatus());
        self::assertNull($r->getNextAt());
        self::assertSame(0, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
        self::assertSame(0, $this->em->getRepository(AiEpisode::class)->count([]));
        self::assertCount(1, $this->em->getRepository(SeriesOccurrence::class)->findAll());
        $before = (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages');
        $this->client->request('GET', '/studio/series/'.$this->series->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.recurrence-missed a[href="#add-episodes"]');
        self::assertSelectorExists('form#add-episodes');
        self::assertSelectorTextContains('.recurrence-missed', 'Aucune génération ni publication n’est relancée ici.');
        self::assertSame(0, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
        self::assertSame($before, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testChangedSeriesRequiresANewAuthorizationBeforeWriting(): void
    {
        $r = $this->service()->activate($this->series, $this->data());
        $m = $this->message($r);
        $this->due();
        $recipe = $this->series->getRecipe();
        $recipe['totalSeconds'] = 40;
        $this->series->configure('Milo', 'Autre thème', 'réaliste', 'Milo, chat blanc joueur', $recipe);
        $this->em->flush();
        $this->service()->produce($m);
        self::assertSame('paused', $r->getStatus());
        self::assertSame(0, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
    }

    public function testInvalidStoryPausesWithoutAnyVideoGeneration(): void
    {
        $this->badStory = true;
        $r = $this->service()->activate($this->series, $this->data());
        $m = $this->message($r);
        $this->due();
        $this->service()->produce($m);
        self::assertSame('paused', $r->getStatus());
        self::assertSame(0, $r->getReservedCents());
        self::assertSame(0, $this->paidCalls);
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
    }

    public function testLastPreparedEpisodeCanBeGeneratedManuallyIndependentlyOfPreparationPause(): void
    {
        $r = $this->service()->activate($this->series, $this->data(1));
        $m = $this->message($r);
        $this->due();
        $this->service()->produce($m);
        self::assertSame('finished', $r->getStatus());
        self::assertNull($r->getNextAt());
        $o = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $r]);
        $this->confirmReviewed($o->getEpisode());
        static::getContainer()->get(AiEpisodeFlow::class)->advance($o->getEpisode()->getId());
        self::assertSame(1, $this->em->getRepository(AiGeneration::class)->count(['status' => 'queued']));
        self::assertSame(0, $this->paidCalls);
        $this->acceptProvider = true;
        $job = $this->em->getRepository(AiGeneration::class)->findOneBy(['owner' => $this->owner]);
        static::getContainer()->get(AiGenerations::class)->process($job->getId());
        self::assertSame('processing', $job->getStatus());
        self::assertSame(1, $this->paidCalls);
        $this->service()->pause($r, $r->getRevision());
        self::assertSame('running', $o->getEpisode()->getFlowState());
    }

    public function testADeadlineReachedWhileWritingDoesNotLaunchGeneration(): void
    {
        $r = $this->service()->activate($this->series, $this->data());
        $m = $this->message($r);
        $this->due();
        $this->duringWriting = fn () => $this->clock->modify('2026-10-27T18:00:00Z');
        $this->service()->produce($m);
        self::assertSame('paused', $r->getStatus());
        self::assertSame(0, $r->getReservedCents());
        self::assertSame(0, $this->paidCalls);
    }

    public function testManualTakeoverOnlyTakesEffectAfterASuccessfulConfirmedAction(): void
    {
        $r = $this->service()->activate($this->series, $this->data());
        $this->due();
        $o = $this->interruptedOccurrence($r);
        $consent = static::getContainer()->get(\App\Service\SeriesGenerationConsent::class);
        try {
            $consent->manual($o->getEpisode(), static function (): never { throw new \RuntimeException('Invalid manual confirmation'); });
            self::fail('Failed confirmation must not take over automation');
        } catch (\RuntimeException) {
            self::assertTrue($o->isAutomatic());
        }
        $consent->manual($o->getEpisode(), static fn () => null);
        self::assertFalse($o->isAutomatic());
        self::assertSame(0, $this->paidCalls);
    }

    public function testPreparationRequiresNoVideoCredentialOrEnabledPaidGenerator(): void
    {
        $this->em->getConnection()->executeStatement('DELETE FROM ai_credential');
        $generator = static::getContainer()->get(AiGenerations::class);
        (new \ReflectionProperty($generator, 'enabled'))->setValue($generator, false);
        self::assertFalse($generator->enabled());
        $recurrence = $this->service()->activate($this->series, $this->data());
        self::assertSame('preparation', $recurrence->getSettings()['mode']);
        $message = $this->message($recurrence);
        $this->due();
        $this->service()->produce($message);
        $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $recurrence]);
        self::assertSame('manual', $occurrence->getStatus());
        self::assertSame('draft', $occurrence->getEpisode()->getFlowState());
        self::assertSame(0, $this->paidCalls);
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
    }

    public function testLegacyAuthorizationPausesBeforeInferenceAndKeepsItsHistory(): void
    {
        $recurrence = $this->service()->activate($this->series, $this->data());
        $settings = $recurrence->getSettings();
        unset($settings['mode']);
        $recurrence->activate($settings, $recurrence->getNextAt(), $this->clock->now());
        $recurrence->reserve($settings['cents']);
        $this->em->flush();
        $message = $this->message($recurrence);
        $this->due();
        $this->service()->produce($message);
        self::assertSame('paused', $recurrence->getStatus());
        self::assertStringContainsString('Ancienne autorisation', $recurrence->getNotice());
        self::assertSame($settings['cents'], $recurrence->getReservedCents());
        self::assertSame(0, $this->textCalls);
        self::assertSame(0, $this->paidCalls);
        self::assertSame(0, $this->em->getRepository(AiGeneration::class)->count([]));
    }

    public function testLegacyQueuedGenerationCannotPostButAnAlreadySubmittedClipCanStillBePolled(): void
    {
        $recurrence = $this->service()->activate($this->series, $this->data());
        $message = $this->message($recurrence);
        $this->due();
        $this->service()->produce($message);
        $occurrence = $this->em->getRepository(SeriesOccurrence::class)->findOneBy(['recurrence' => $recurrence]);
        $episode = $occurrence->getEpisode();
        $this->confirmReviewed($episode);
        static::getContainer()->get(AiEpisodeFlow::class)->advance($episode->getId());
        $job = $this->em->getRepository(AiGeneration::class)->findOneBy(['owner' => $this->owner]);
        $this->em->getConnection()->executeStatement('UPDATE series_occurrence SET automatic = true WHERE id = ?', [$occurrence->getId()]);
        $this->em->refresh($occurrence);
        static::getContainer()->get(AiGenerations::class)->process($job->getId());
        self::assertSame('waiting_key', $job->getStatus());
        self::assertSame(0, $this->providerPosts);
        self::assertSame('paused', $episode->getFlowState());

        // The existing review/quote/confirmation path recovers a known unsent legacy request.
        $flow = static::getContainer()->get(AiEpisodeFlow::class);
        $flow->reviewRemaining($episode, $episode->getRevision());
        $this->confirmReviewed($episode);
        $flow->advance($episode->getId());
        $job = $flow->shots($episode)[0]->getGeneration();
        $this->acceptProvider = true;
        static::getContainer()->get(AiGenerations::class)->process($job->getId());
        self::assertSame('processing', $job->getStatus());
        self::assertSame(1, $this->providerPosts);
        $remote = $job->getRemote();
        $this->em->getConnection()->executeStatement('UPDATE series_occurrence SET automatic = true WHERE id = ?', [$occurrence->getId()]);
        $this->em->refresh($occurrence);
        static::getContainer()->get(AiGenerations::class)->process($job->getId());
        self::assertSame(1, $this->providerPosts);
        self::assertSame($remote, $job->getRemote());
        self::assertSame('processing', $job->getStatus());
    }
}
