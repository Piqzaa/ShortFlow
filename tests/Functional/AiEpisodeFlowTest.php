<?php

namespace App\Tests\Functional;

use App\Entity\AiEpisode;
use App\Entity\AiGeneration;
use App\Entity\AiSeries;
use App\Entity\AiShot;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Service\AiEpisodeFlow;
use App\Service\AiGenerations;
use App\Service\AiKeys;
use App\Service\AiReferenceStorage;
use App\Service\EpisodeReservations;
use App\Service\VideoEdits;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;

final class AiEpisodeFlowTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    private int $submissions = 0;
    private bool $uncertain = false;
    /** @var list<string> */
    private array $files = [];
    private static string $clip;

    public static function setUpBeforeClass(): void
    {
        self::$clip = tempnam(sys_get_temp_dir(), 'episode-clip-');
        (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=purple:s=360x640:r=10', '-t', '10', '-c:v', 'libx264', '-preset', 'ultrafast', '-pix_fmt', 'yuv420p', '-f', 'mp4', self::$clip]))->mustRun();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$clip);
    }

    protected function setUp(): void
    {
        $_ENV['AI_GENERATION_ENABLED'] = '1';
        $this->client = static::createClient();
        $this->client->disableReboot();
        $db = static::getContainer()->get(Connection::class);
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        foreach (['owner', 'other'] as $name) {
            $user = new User('story-'.$name.'@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $this->em()->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
        static::getContainer()->set('ai.http', new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            if ('POST' === $method) {
                ++$this->submissions;
                $body = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
                self::assertStringContainsString('Milo', $body['prompt']);
                self::assertStringContainsString('Scene', $body['prompt']);
                if ($this->uncertain) {
                    throw new \RuntimeException('Synthetic ambiguous provider response');
                }

                return new MockResponse(json_encode(['status_url' => 'https://queue.fal.run/fal-ai/kling-video/requests/test-'.$this->submissions.'/status', 'response_url' => 'https://queue.fal.run/fal-ai/kling-video/requests/test-'.$this->submissions], JSON_THROW_ON_ERROR));
            }
            if (str_ends_with($url, '/status')) {
                return new MockResponse('{"status":"COMPLETED"}');
            }
            if (str_contains($url, '/requests/')) {
                return new MockResponse('{"video":{"url":"https://v3.fal.media/files/story-test.mp4"}}');
            }
            self::assertSame('https://v3.fal.media/files/story-test.mp4', $url);

            return new MockResponse(file_get_contents(self::$clip));
        }));
        static::getContainer()->get(AiKeys::class)->save($this->owner, 'synthetic-story-key-never-real-1234567890');
        $this->em()->flush();
        $this->client->loginUser($this->owner);
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        parent::tearDown();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function flow(): AiEpisodeFlow
    {
        return static::getContainer()->get(AiEpisodeFlow::class);
    }

    private function fresh(AiEpisode $episode): AiEpisode
    {
        $found = $this->em()->find(AiEpisode::class, $episode->getId());
        $this->em()->refresh($found);

        return $found;
    }

    private function episode(bool $image = false): AiEpisode
    {
        $reference = null;
        if ($image) {
            $file = tempnam(sys_get_temp_dir(), 'story-image-');
            $this->files[] = $file;
            (new Process(['ffmpeg', '-nostdin', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=orange:s=360x640', '-frames:v', '1', '-c:v', 'png', '-f', 'image2', $file]))->mustRun();
            $reference = static::getContainer()->get(AiReferenceStorage::class)->store($this->owner, new UploadedFile($file, 'milo.png', 'image/png', null, true));
            $this->files[] = static::getContainer()->get(AiReferenceStorage::class)->path($this->owner, $reference);
        }
        $series = new AiSeries($this->owner, 'Milo', 'Milo explores the Moon.', 'Miniature cinema.', 'Milo is a ginger cat.', ['model' => $image ? 'kling-25-image' : 'kling-25', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => $reference, 'endReference' => null, 'imageFit' => 'crop']);
        $episode = new AiEpisode($series, 1);
        $this->em()->persist($series);
        $this->em()->persist($episode);
        $this->em()->flush();

        return $episode;
    }

    private function planned(bool $linked = false, int $target = 30): AiEpisode
    {
        $episode = $this->episode($linked);
        $this->flow()->initialize($episode, $episode->getRevision(), $target, $linked ? 'linked' : 'independent', true);
        $this->flow()->save($episode, $episode->getRevision(), 'Milo discovers a door', array_map(static fn (AiShot $s): array => ['prompt' => 'Scene '.$s->getPosition().': Milo takes a step forward.', 'seconds' => $s->getSeconds()], $this->flow()->shots($episode)));

        return $episode;
    }

    private function completeShot(AiShot $shot): void
    {
        $job = $shot->getGeneration();
        self::assertNotNull($job);
        $generations = static::getContainer()->get(AiGenerations::class);
        $generations->process($job->getId());
        $generations->process($job->getId());
        $this->em()->refresh($job);
        self::assertSame('available', $job->getStatus(), $job->getIssue() ?? '');
    }

    private function finished(int $target = 30): AiEpisode
    {
        $episode = $this->planned(false, $target);
        $this->flow()->confirm($episode, $this->flow()->quote($episode)['hash']);
        foreach ($this->flow()->shots($episode) as $shot) {
            $this->flow()->advance($episode->getId());
            $this->completeShot($shot);
        }
        $this->flow()->advance($episode->getId());
        self::assertSame('assembling', $episode->getFlowState());
        static::getContainer()->get(VideoEdits::class)->process($episode->getAssembly()->getId());
        $this->flow()->advance($episode->getId());
        self::assertSame('ready', $episode->getFlowState(), $episode->getFlowIssue() ?? '');

        return $episode;
    }

    public function testPlannerSupportsThirtyToSixtySecondsWithoutGenerationOrInventedScenes(): void
    {
        foreach ([30, 45, 60] as $duration) {
            $parts = $this->flow()->durations('kling-25', $duration);
            self::assertSame($duration, array_sum($parts));
            self::assertLessThanOrEqual(8, count($parts));
            self::assertGreaterThanOrEqual(2, count($parts));
        }
        $episode = $this->episode();
        $crawler = $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        self::assertSelectorNotExists('input[name="ai_idea[mute]"][checked]');
        self::assertSelectorExists('select[name="ai_idea[model]"]');
        self::assertSelectorExists('input[name="ai_idea[target]"][value="30"]');
        self::assertSelectorCount(3, '[data-shot-editor-target="list"] .story-shot');
        $crawler = $this->client->submit($crawler->selectButton('Répartir la durée')->form());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, '[data-shot-editor-target="list"] .story-shot');
        self::assertSame(0, $this->submissions);
        self::assertSame(0, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
    }

    public function testQuoteMustMatchAndConfirmationIsIdempotentWithoutProviderCall(): void
    {
        $episode = $this->planned();
        $quote = $this->flow()->quote($episode);
        self::assertSame(210, $quote['cents']);
        self::assertSame(30, $quote['seconds']);
        try {
            $this->flow()->confirm($episode, 'outdated');
            self::fail('Old quote accepted');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('changé', $error->getMessage());
        }
        self::assertSame('draft', $episode->getFlowState());
        $this->flow()->confirm($episode, $quote['hash']);
        $this->flow()->confirm($episode, $quote['hash']);
        self::assertSame(1, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(4, static::getContainer()->get(EpisodeReservations::class)->slots($this->owner));
        self::assertSame(0, $this->submissions);
    }

    public function testSequentialChainUsesPreviousEndFrameAndNeverSubmitsInParallel(): void
    {
        $episode = $this->planned(true);
        $this->flow()->confirm($episode, $this->flow()->quote($episode)['hash']);
        $shots = $this->flow()->shots($episode);
        $this->flow()->advance($episode->getId());
        $this->flow()->advance($episode->getId());
        self::assertSame(1, $this->em()->getRepository(AiGeneration::class)->count([]));
        self::assertSame(0, $this->submissions);
        self::assertSame($episode->getFlowRecipe()['reference'], $shots[0]->getGeneration()->getReferenceImage());
        $this->completeShot($shots[0]);
        $this->flow()->advance($episode->getId());
        self::assertNotNull($shots[1]->getGeneration());
        self::assertNotSame($shots[0]->getGeneration()->getReferenceImage(), $shots[1]->getGeneration()->getReferenceImage());
        $this->files[] = static::getContainer()->get(AiReferenceStorage::class)->path($this->owner, $shots[1]->getGeneration()->getReferenceImage());
        self::assertSame(1, $this->submissions);
    }

    public function testAmbiguousSubmissionPausesWithoutResendingAndDoesNotStartFollowingPlan(): void
    {
        $episode = $this->planned();
        $this->flow()->confirm($episode, $this->flow()->quote($episode)['hash']);
        $this->flow()->advance($episode->getId());
        $shot = $this->flow()->shots($episode)[0];
        $this->uncertain = true;
        static::getContainer()->get(AiGenerations::class)->process($shot->getGeneration()->getId());
        $this->flow()->advance($episode->getId());
        self::assertSame('paused', $episode->getFlowState());
        try {
            $this->flow()->resume($episode);
            self::fail('Uncertain request resumed');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('incertaine', $error->getMessage());
        }
        $this->flow()->advance($episode->getId());
        self::assertSame(1, $this->submissions);
        self::assertNull($this->flow()->shots($episode)[1]->getGeneration());
    }

    public function testManualPauseRetainsConsentAndResumeDoesNotDuplicateFirstPlan(): void
    {
        $episode = $this->planned();
        $this->flow()->confirm($episode, $this->flow()->quote($episode)['hash']);
        $this->flow()->advance($episode->getId());
        $shots = $this->flow()->shots($episode);
        $firstId = $shots[0]->getGeneration()->getId();
        $this->flow()->pause($episode);
        static::getContainer()->get(AiGenerations::class)->process($shots[0]->getGeneration()->getId());
        self::assertSame(0, $this->submissions);
        self::assertSame('queued', $shots[0]->getGeneration()->getStatus());
        $this->flow()->advance($episode->getId());
        self::assertNull($shots[1]->getGeneration());
        $this->flow()->resume($episode);
        $this->completeShot($shots[0]);
        $this->flow()->advance($episode->getId());
        self::assertSame($firstId, $shots[0]->getGeneration()->getId());
        self::assertNotNull($shots[1]->getGeneration());
    }

    public function testCompleteEpisodeIsAssembledOnceAndRequiresEditorialValidation(): void
    {
        $episode = $this->finished();
        $assembly = $episode->getAssembly();
        self::assertNotNull($assembly->getOutput());
        self::assertEqualsWithDelta(30, $assembly->getOutput()->getDuration(), 0.3);
        self::assertNull($episode->getApprovedVideo());
        $this->flow()->advance($episode->getId());
        self::assertSame($assembly->getId(), $episode->getAssembly()->getId());
        $crawler = $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        self::assertSelectorNotExists('a[href*="publications/preparer"]');
        self::assertSelectorNotExists('form[action$="/publication"]');
        $this->client->submit($crawler->selectButton('Valider cette version →')->form());
        self::assertResponseStatusCodeSame(303);
        $this->client->followRedirect();
        self::assertSelectorExists('form[method="post"][action="/studio/series/episodes/'.$episode->getId().'/publication"] button[type="submit"]');
        self::assertNotNull($this->fresh($episode)->getApprovedVideo());
        self::assertSame(3, $this->submissions);
    }

    public function testOneMinuteEpisodeUsesSixClipsAndTheExistingRenderer(): void
    {
        $episode = $this->finished(60);
        self::assertCount(6, $this->flow()->shots($episode));
        self::assertEqualsWithDelta(60, $episode->getAssembly()->getOutput()->getDuration(), 0.3);
        self::assertSame(6, $this->submissions);
    }

    public function testStopReleasesFutureReservationsAndPreventsQueuedBillablePost(): void
    {
        $episode = $this->planned();
        $this->flow()->confirm($episode, $this->flow()->quote($episode)['hash']);
        $this->flow()->advance($episode->getId());
        $shot = $this->flow()->shots($episode)[0];
        $this->flow()->stop($episode);
        static::getContainer()->get(AiGenerations::class)->process($shot->getGeneration()->getId());
        $this->flow()->advance($episode->getId());
        self::assertSame(0, $this->submissions);
        self::assertSame(0, static::getContainer()->get(EpisodeReservations::class)->slots($this->owner));
        self::assertSame('stopped', $episode->getFlowState());
    }

    public function testRepairOnlyRegeneratesChosenShotAndInvalidatesApproval(): void
    {
        $episode = $this->finished();
        $episode->approve($episode->getAssembly()->getOutput());
        $this->em()->flush();
        $shots = $this->flow()->shots($episode);
        $old = array_map(static fn (AiShot $s): int => $s->getGeneration()->getId(), $shots);
        $quote = $this->flow()->quote($episode, $shots[1]->getId());
        self::assertSame(70, $quote['cents']);
        $this->flow()->confirm($episode, $quote['hash'], $shots[1]->getId());
        $this->flow()->confirm($episode, $quote['hash'], $shots[1]->getId());
        self::assertNull($episode->getApprovedVideo());
        self::assertNull($episode->getAssembly());
        self::assertNull($shots[1]->getGeneration(), 'The chosen shot must be unbound before advancing');
        $this->flow()->advance($episode->getId());
        self::assertNotSame('paused', $episode->getFlowState(), $episode->getFlowIssue() ?? '');
        $this->completeShot($shots[1]);
        self::assertSame($old[0], $shots[0]->getGeneration()->getId());
        self::assertSame($old[2], $shots[2]->getGeneration()->getId());
        self::assertNotSame($old[1], $shots[1]->getGeneration()->getId());
        self::assertSame([$old[1]], $shots[1]->getPreviousGenerations());
        self::assertSame(4, $this->submissions);
    }

    public function testCapacityIncludesAllFuturePlansAndExportAndProtectsOtherGenerations(): void
    {
        $episode = $this->planned();
        $full = new VideoAsset($this->owner, 'Synthetic storage', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 600 * 1048576, 10, 360, 640, 'h264');
        $this->em()->persist($full);
        $this->em()->flush();
        $this->flow()->confirm($episode, $this->flow()->quote($episode)['hash']);
        $job = new AiGeneration($this->owner, 'Another idea', 'Scene with Milo.', 'kling-25', 5, '9:16', 35);
        $this->em()->persist($job);
        $this->em()->flush();
        try {
            static::getContainer()->get(AiGenerations::class)->confirm($this->owner, $job->getId());
            self::fail('Reserved space consumed');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('Place insuffisante', $error->getMessage());
        }
        self::assertSame(0, $this->submissions);
    }

    public function testOwnerCsrfMissingConsentAndDirectPlanSubmissionAreProtected(): void
    {
        $episode = $this->planned();
        $this->client->loginUser($this->other);
        $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/studio/series/episodes/'.$episode->getId().'/etat');
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->owner);
        $this->client->request('POST', '/studio/series/episodes/'.$episode->getId(), ['intent' => 'confirm', '_token' => 'wrong']);
        self::assertResponseStatusCodeSame(403);
        $episode = $this->fresh($episode);
        $this->flow()->confirm($episode, $this->flow()->quote($episode)['hash']);
        $this->flow()->advance($episode->getId());
        $job = $this->flow()->shots($episode)[0]->getGeneration();
        $this->client->request('POST', '/studio/ia/generations/'.$job->getId(), ['confirm' => '1']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->submissions);
    }

    public function testSuggestionsOfferFlexibleDurationsAndNeverRoundWithoutSelection(): void
    {
        $choices = $this->flow()->suggestions('kling-3', 30);
        self::assertContains('15,15', array_column($choices, 'value'));
        self::assertContains('10,10,10', array_column($choices, 'value'));
        self::assertSame(37, array_sum($this->flow()->durations('kling-3', 37)));
        self::assertSame([30, 35], array_values(array_unique(array_column($this->flow()->suggestions('kling-25', 32), 'seconds'))));
        self::assertSame([5], $this->flow()->durations('kling-25', 5));
        try {
            $this->flow()->durations('kling-25', 32);
            self::fail('Target silently rounded');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('impossible', $error->getMessage());
        }
        foreach ([0, 61] as $invalid) {
            try {
                $this->flow()->suggestions('kling-3', $invalid);
                self::fail('Invalid target accepted');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('entre 1 et 60', $error->getMessage());
            }
        }
    }

    public function testPreparedEpisodeCanBecomePlansAndOldConfirmationIsBlocked(): void
    {
        $episode = $this->episode();
        // A legacy prepared episode already had its common brief saved before this UI change.
        $episode->edit($episode->getTitle(), $episode->getSeries()->initialPrompt());
        $job = new AiGeneration($this->owner, $episode->getTitle(), $episode->getPrompt(), 'kling-25', 5, '9:16', 35);
        $episode->prepare($job);
        $this->em()->persist($job);
        $this->em()->flush();
        $this->client->request('GET', '/studio/ia/generations/'.$job->getId());
        self::assertSelectorExists('a[href="/studio/series/episodes/'.$episode->getId().'"]');
        $crawler = $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[target]' => 25, 'ai_idea[shots][0][seconds]' => 10, 'ai_idea[shots][1][seconds]' => 10, 'ai_idea[shots][2][seconds]' => 5, 'ai_idea[shots][0][prompt]' => 'Scene one with Milo', 'ai_idea[shots][1][prompt]' => 'Scene two with Milo', 'ai_idea[shots][2][prompt]' => 'Scene three with Milo']));
        self::assertResponseRedirects('/studio/series/episodes/'.$episode->getId().'?review=1', 303);
        $episode = $this->fresh($episode);
        self::assertSame('replaced', $episode->getGeneration()->getStatus());
        self::assertFalse($episode->isMutePlans());
        self::assertSame($job->getPrompt(), $episode->getPrompt());
        $this->flow()->save($episode, $episode->getRevision(), 'Milo', array_map(static fn (AiShot $s): array => ['prompt' => 'Scene with Milo', 'seconds' => $s->getSeconds()], $this->flow()->shots($episode)));
        self::assertSame(25, $this->flow()->quote($episode)['seconds']);
        static::getContainer()->get(AiGenerations::class)->confirm($episode->getOwner(), $job->getId());
        self::assertSame(0, $this->submissions);
        $this->client->request('POST', '/studio/ia/generations/'.$job->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/studio/ia/generations/'.$job->getId());
        self::assertResponseRedirects('/studio/series/episodes/'.$episode->getId(), 303);
    }

    public function testDraftPlansCanBeAddedReorderedAndRemovedWithMixedDurations(): void
    {
        $recipe = ['model' => 'kling-3', 'seconds' => 5, 'ratio' => '9:16', 'accessMode' => 'api', 'reference' => null, 'endReference' => null, 'imageFit' => 'crop'];
        $episode = new AiEpisode($this->owner, 1, 'Milo', 'Milo in space', $recipe);
        $this->em()->persist($episode);
        $this->em()->flush();
        $this->flow()->initialize($episode, $episode->getRevision(), 30, 'independent', false, '15,15');
        $rows = [['prompt' => 'Scene first', 'seconds' => 5], ['prompt' => 'Scene second', 'seconds' => 15], ['prompt' => 'Scene third', 'seconds' => 10]];
        $this->flow()->save($episode, $episode->getRevision(), 'Milo', $rows);
        self::assertSame([5, 15, 10], array_map(static fn (AiShot $s): int => $s->getSeconds(), $this->flow()->shots($episode)));
        $crawler = $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        $this->client->submit($crawler->selectButton('up:2')->form());
        self::assertResponseStatusCodeSame(303);
        self::assertSame(['Scene first', 'Scene third', 'Scene second'], array_map(static fn (AiShot $s): string => $s->getPrompt(), $this->flow()->shots($episode)));
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('remove:1')->form());
        self::assertResponseStatusCodeSame(303);
        self::assertCount(2, $this->flow()->shots($episode));
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('add')->form());
        self::assertResponseStatusCodeSame(303);
        self::assertCount(3, $this->flow()->shots($episode));
        $episode = $this->fresh($episode);
        $this->flow()->save($episode, $episode->getRevision(), 'Milo', [$rows[0]]);
        self::assertSame(5, $this->flow()->quote($episode)['seconds']);
        $this->flow()->save($episode, $episode->getRevision(), 'Milo', $rows);
        self::assertSame(30, $this->flow()->quote($episode)['seconds']);
        try {
            $this->flow()->save($episode, $episode->getRevision(), 'Milo', array_fill(0, 5, ['prompt' => 'Scene', 'seconds' => 15]));
            self::fail('Total over 60 accepted');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('entre 1 et 60', $error->getMessage());
        }
        self::assertSame(0, $this->submissions);
    }

    public function testStudioCreatesPrivateReusableProjectWithoutSeriesAndCompletesTwentyFiveSeconds(): void
    {
        $crawler = $this->client->request('GET', '/studio/creation');
        $crawler = $this->client->submit($crawler->selectButton('Répartir la durée')->form(['ai_idea[prompt]' => 'Milo in space', 'ai_idea[model]' => 'kling-25', 'ai_idea[seconds]' => '5', 'ai_idea[target]' => 25, 'ai_idea[ratio]' => '9:16', 'ai_idea[accessMode]' => 'api']));
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[shots][0][prompt]' => 'Scene one with Milo', 'ai_idea[shots][1][prompt]' => 'Scene two with Milo', 'ai_idea[shots][2][prompt]' => 'Scene three with Milo']);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $url = $this->client->getResponse()->headers->get('Location');
        $this->client->submit($form);
        self::assertResponseRedirects($url, 303);
        self::assertSame(0, $this->em()->getRepository(AiSeries::class)->count([]));
        self::assertSame(1, $this->em()->getRepository(AiEpisode::class)->count([]));
        $episode = $this->em()->getRepository(AiEpisode::class)->findOneBy(['owner' => $this->owner]);
        self::assertTrue($episode->isStandalone());
        $this->client->loginUser($this->other);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', parse_url($url, PHP_URL_PATH).'/etat');
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->owner);
        $episode = $this->fresh($episode);
        $this->flow()->save($episode, $episode->getRevision(), 'Milo', array_map(static fn (AiShot $s): array => ['prompt' => 'Scene with Milo', 'seconds' => $s->getSeconds()], $this->flow()->shots($episode)));
        $this->flow()->confirm($episode, $this->flow()->quote($episode)['hash']);
        self::assertSame(4, static::getContainer()->get(EpisodeReservations::class)->slots($this->owner));
        foreach ($this->flow()->shots($episode) as $shot) {
            $this->flow()->advance($episode->getId());
            $this->completeShot($shot);
        }
        $this->flow()->advance($episode->getId());
        self::assertSame('assembling', $episode->getFlowState());
        self::assertSame(100, $episode->getAssembly()->getSettings()['sourceVolume']);
        static::getContainer()->get(VideoEdits::class)->process($episode->getAssembly()->getId());
        $this->flow()->advance($episode->getId());
        self::assertSame('ready', $episode->getFlowState());
        self::assertEqualsWithDelta(25, $episode->getAssembly()->getOutput()->getDuration(), .3);
        $this->client->request('GET', '/studio/creation/historique');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/studio/series/episodes/'.$episode->getId().'"]');
        $crawler = $this->client->request('GET', '/studio/creation');
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form());
        self::assertResponseStatusCodeSame(303);
        self::assertNotSame($url, $this->client->getResponse()->headers->get('Location'));
        self::assertSame(2, $this->em()->getRepository(AiEpisode::class)->count([]));
        self::assertSame(0, static::getContainer()->get(EpisodeReservations::class)->slots($this->owner));
    }

    public function testUnifiedPreparationCanChangeModelAndPreserveScenesWithoutProviderCall(): void
    {
        $episode = $this->planned();
        $crawler = $this->client->request('GET', '/studio/series/episodes/'.$episode->getId());
        self::assertSelectorExists('input[name="ai_idea[target]"]');
        self::assertSelectorExists('select[name="ai_idea[model]"]');
        $old = $this->flow()->quote($this->fresh($episode))['hash'];
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[model]' => 'kling-3', 'ai_idea[shots][0][seconds]' => 5, 'ai_idea[shots][1][seconds]' => 15, 'ai_idea[shots][2][seconds]' => 10]));
        self::assertResponseStatusCodeSame(303);
        $episode = $this->fresh($episode);
        self::assertSame('kling-3', $episode->getFlowRecipe()['model']);
        self::assertSame([5, 15, 10], array_map(static fn (AiShot $s): int => $s->getSeconds(), $this->flow()->shots($episode)));
        self::assertStringContainsString('Scene 2', $this->flow()->shots($episode)[1]->getPrompt());
        self::assertSame(504, $this->flow()->quote($episode)['cents']);
        try {
            $this->flow()->confirm($episode, $old);
            self::fail('Old quote accepted after changing the model');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('changé', $error->getMessage());
        }
        self::assertSame(0, $this->submissions);
    }

    public function testUnifiedPreparationKeepsInputWhenReferenceOrDurationIsIncompatible(): void
    {
        $episode = $this->planned();
        $url = '/studio/series/episodes/'.$episode->getId();
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Voir le récapitulatif →')->form(['ai_idea[source]' => 'image', 'ai_idea[model]' => 'kling-25-image', 'ai_idea[continuity]' => 'linked']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.creation-form', 'Importe une image');
        self::assertSelectorExists('select[name="ai_idea[model]"]');
        self::assertSelectorExists('textarea[name="ai_idea[shots][1][prompt]"]');
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Répartir la durée')->form(['ai_idea[target]' => 32]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('input[name="ai_idea[target]"][value="32"]');
        self::assertSelectorTextContains('.creation-form', 'total exact');
        self::assertSelectorTextContains('textarea[name="ai_idea[shots][1][prompt]"]', 'Scene 2');
        self::assertSame(0, $this->submissions);
    }
}
