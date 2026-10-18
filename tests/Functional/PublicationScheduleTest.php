<?php

namespace App\Tests\Functional;

use App\Entity\InstagramConnection;
use App\Entity\PublicationDraft;
use App\Entity\TikTokConnection;
use App\Entity\User;
use App\Entity\VideoAsset;
use App\Entity\YouTubeConnection;
use App\Service\MultiNetworkPublishing;
use App\Service\PublicationCalendar;
use App\Service\PublicationDrafts;
use App\Service\PublicationException;
use App\Service\TokenVault;
use App\Service\VideoLibrary;
use App\Service\VideoLibraryException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PublicationScheduleTest extends WebTestCase
{
    private KernelBrowser $client;
    private User $owner;
    private User $other;
    private MockClock $clock;
    private int $creatorQueries = 0;
    private int $failAt = 0;
    private int $slowAt = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->clock = new MockClock('2026-10-18T08:00:00+00:00');
        static::getContainer()->set('app.publication_clock', $this->clock);
        self::assertSame('shortflow_test', $this->db()->fetchOne('SELECT current_database()'));
        $this->db()->executeStatement('DELETE FROM messenger_messages');
        $this->db()->executeStatement('DELETE FROM app_user');
        foreach (['owner', 'other'] as $name) {
            $user = new User($name.'-schedule@example.test');
            $user->verify();
            $user->setPassword('unused');
            $this->em()->persist($user);
            $this->$name = $user;
        }
        $this->em()->flush();
        static::getContainer()->get('cache.rate_limiter')->clear();
        foreach (['youtube.http', 'instagram.http'] as $service) {
            static::getContainer()->set($service, new MockHttpClient(static function (): never {
                throw new \LogicException('No upload or external HTTP request is authorized by these tests.');
            }));
        }
        static::getContainer()->set('tiktok.http', new MockHttpClient(function (string $method, string $url): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://open.tiktokapis.com/v2/post/publish/creator_info/query/', $url);
            ++$this->creatorQueries;
            if ($this->slowAt === $this->creatorQueries) {
                $this->clock->sleep(121);
            }
            $duration = $this->failAt && $this->creatorQueries >= $this->failAt ? 2 : 60;

            return new MockResponse(json_encode(['data' => ['creator_nickname' => 'Test', 'privacy_level_options' => ['FOLLOWER_OF_CREATOR', 'SELF_ONLY'], 'max_video_post_duration_sec' => $duration, 'comment_disabled' => false, 'duet_disabled' => false, 'stitch_disabled' => false], 'error' => ['code' => 'ok']], JSON_THROW_ON_ERROR));
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

    private function service(): PublicationDrafts
    {
        return static::getContainer()->get(PublicationDrafts::class);
    }

    private function connect(): void
    {
        $vault = new TokenVault($_ENV['YOUTUBE_TOKEN_KEY']);
        $this->em()->persist(new YouTubeConnection($this->owner, 'UC'.str_repeat('a', 22), 'Test', $vault->encrypt('synthetic'), $vault->encrypt('synthetic'), 86400));
        $this->em()->persist(new InstagramConnection($this->owner, '17841400000000001', 'test', 'MEDIA_CREATOR', $vault->encrypt('synthetic'), 86400));
        $this->em()->persist(new TikTokConnection($this->owner, 'creator-test', 'Test', $vault->encrypt('synthetic'), $vault->encrypt('synthetic'), 86400, 31536000));
        $this->em()->flush();
    }

    private function video(): VideoAsset
    {
        $video = new VideoAsset($this->em()->find(User::class, $this->owner->getId()), 'Source <privée>', bin2hex(random_bytes(24)).'.mp4', 'video/mp4', 1234, 4, 720, 1280, 'h264');
        $this->em()->persist($video);
        $this->em()->flush();

        return $video;
    }

    /** @param list<string> $networks */
    private function draft(array $networks = ['youtube', 'instagram', 'tiktok']): PublicationDraft
    {
        $video = $this->video();
        $id = $video->getId();
        $content = [
            'youtube' => ['videos' => [$id => ['title' => 'Clip', 'description' => 'Texte sauvegardé', 'privacy' => 'private', 'audience' => 'no', 'synthetic' => false]]],
            'instagram' => ['videos' => [$id => ['caption' => 'Reel', 'shareToFeed' => false, 'synthetic' => false]]],
            'tiktok' => ['videos' => [$id => ['caption' => 'TikTok', 'privacy' => 'SELF_ONLY', 'comment' => false, 'duet' => false, 'stitch' => false, 'disclosure' => false, 'ownBrand' => false, 'partnerBrand' => false, 'synthetic' => false]]],
        ];
        $accounts = array_map(static fn (array $destination): mixed => $destination['account'], static::getContainer()->get(MultiNetworkPublishing::class)->destinations($this->owner));

        return $this->service()->save($this->owner, [$id], $networks, array_intersect_key($content, array_flip($networks)), $accounts, 'Lot sauvegardé');
    }

    private function arm(PublicationDraft $draft): int
    {
        $this->service()->plan($this->em()->find(User::class, $this->owner->getId()), $draft->getId(), $draft->getRevision(), '2026-10-18T11:00', 'Europe/Paris');
        $this->service()->confirm($this->owner, $draft->getId(), $draft->getRevision(), true);
        self::assertSame('scheduled', $draft->getStatus());

        return $draft->getRevision();
    }

    private function assertJobs(int $youtube, int $instagram = 0, int $tiktok = 0): void
    {
        foreach (['youtube' => $youtube, 'instagram' => $instagram, 'tiktok' => $tiktok] as $network => $count) {
            self::assertSame($count, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM '.$network.'_publication'));
        }
    }

    private function reload(int $id): PublicationDraft
    {
        $this->em()->clear();

        return $this->em()->find(PublicationDraft::class, $id);
    }

    public function testDurablePartialDraftDoesNotRequireAccountsOrAnyNetwork(): void
    {
        $video = $this->video();
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/publications/preparer', ['videos' => [$video->getId()]]);
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Enregistrer le brouillon')->form());
        self::assertResponseStatusCodeSame(303);
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Source <privée>');
        self::assertSelectorTextContains('.library-note', 'Aucun réseau choisi');
        $draft = $this->em()->getRepository(PublicationDraft::class)->findOneBy(['owner' => $this->owner]);
        self::assertSame([], $draft->getNetworks());
        $id = $draft->getId();
        // A fresh session can still retrieve the database preparation.
        $this->client->restart();
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/brouillons/'.$id);
        self::assertResponseIsSuccessful();
        $this->assertJobs(0);
        self::assertSame(0, $this->creatorQueries);
    }

    public function testIncompleteTextIsSavedButCannotBeConfirmedAndIsPrivate(): void
    {
        $video = $this->video();
        $draft = $this->service()->save($this->owner, [$video->getId()], ['youtube'], ['youtube' => ['videos' => [$video->getId() => ['title' => '', 'description' => 'À finir']]]], [], 'Brouillon privé');
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/brouillons/'.$draft->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.batch-review', 'À choisir');
        self::assertSelectorCount(1, '.draft-media video[controls][playsinline][preload="metadata"]:not([autoplay])');
        self::assertSelectorExists('.draft-media video[src="/studio/bibliotheque/'.$video->getId().'/lecture"]');
        $this->client->request('GET', '/studio/preparations');
        self::assertSelectorCount(1, '.draft-media video[preload="metadata"]:not([autoplay])');
        $this->client->request('GET', '/studio/brouillons/'.$draft->getId().'/confirmer');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button[disabled]');
        $this->client->loginUser($this->other);
        foreach (['', '/confirmer', '/edit', '/videos'] as $path) {
            $this->client->request('GET', '/studio/brouillons/'.$draft->getId().$path);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('GET', '/studio/preparations');
        self::assertSelectorTextNotContains('main', 'Brouillon privé');
        $this->assertJobs(0);
    }

    public function testDraftEntryPointExcludesDispatchedPreparationsButKeepsHistory(): void
    {
        $this->connect();
        $sent = $this->draft(['youtube']);
        $this->service()->confirm($this->owner, $sent->getId(), $sent->getRevision(), false);
        self::assertSame('dispatched', $this->reload($sent->getId())->getStatus());
        $this->owner = $this->em()->find(User::class, $this->owner->getId());
        $video = $this->video();
        $pending = $this->service()->save($this->owner, [$video->getId()], [], [], [], 'Préparation encore ouverte');
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/brouillons');
        self::assertResponseRedirects('/studio?tab=drafts');
        $this->client->followRedirect();
        self::assertSelectorExists('.workspace-draft a[href="/studio/composer?draft='.$pending->getId().'"]');
        self::assertSelectorNotExists('.workspace-draft a[href="/studio/composer?draft='.$sent->getId().'"]');
        $this->client->request('GET', '/studio/preparations');
        self::assertSelectorExists('a[href="/studio/brouillons/'.$sent->getId().'"]');
        self::assertSelectorExists('a[href="/studio/brouillons/'.$pending->getId().'"]');
        $this->assertJobs(1, 0, 0);
    }

    public function testConfirmationCsrfAndRevisionAreRequired(): void
    {
        $this->connect();
        $draft = $this->draft(['youtube']);
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/studio/brouillons/'.$draft->getId().'/confirmer');
        $form = $crawler->filter('form')->last()->form();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $payload = $form->getPhpValues();
        $payload['form']['confirm'] = '1';
        unset($payload['form']['_token']);
        $this->client->request('POST', $form->getUri(), $payload);
        self::assertResponseStatusCodeSame(422);
        $form['form[confirm]'] = true;
        $this->service()->plan($this->em()->find(User::class, $this->owner->getId()), $draft->getId(), $draft->getRevision(), '2026-10-18T11:00', 'Europe/Paris');
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        $this->assertJobs(0);
    }

    public function testScheduleIsDelayedAndDuplicateMessagesNeverDuplicateJobs(): void
    {
        $this->connect();
        $draft = $this->draft();
        $revision = $this->arm($draft);
        $id = $draft->getId();
        self::assertSame('2026-10-18T09:00:00+00:00', $draft->getScheduledAt()->format('c'));
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        // Doctrine transport uses the system clock; its delay must still match the injected clock's one-hour interval.
        $delay = (int) $this->db()->fetchOne('SELECT EXTRACT(EPOCH FROM (available_at - created_at)) FROM messenger_messages');
        self::assertGreaterThanOrEqual(3600, $delay);
        self::assertLessThanOrEqual(3601, $delay);
        $this->assertJobs(0);
        $this->service()->dispatchDue($id, $revision); // Unexpected early delivery only rearms a wake-up.
        $this->assertJobs(0);
        $this->clock->sleep(3600);
        $this->service()->dispatchDue($id, $revision);
        $this->assertJobs(1, 1, 1);
        $this->service()->dispatchDue($id, $revision);
        $this->assertJobs(1, 1, 1);
        self::assertSame('dispatched', $this->reload($id)->getStatus());
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(DISTINCT submission_key) FROM (SELECT submission_key FROM youtube_publication UNION ALL SELECT submission_key FROM instagram_publication UNION ALL SELECT submission_key FROM tiktok_publication) j'));
    }

    public function testCancelAndEditInvalidateOldScheduledMessages(): void
    {
        $this->connect();
        $draft = $this->draft(['youtube']);
        $old = $this->arm($draft);
        $this->service()->transition($this->owner, $draft->getId(), $old, 'edit');
        self::assertSame('draft', $draft->getStatus());
        $new = $this->arm($draft);
        $this->clock->sleep(3600);
        $this->service()->dispatchDue($draft->getId(), $old);
        $this->assertJobs(0);
        $this->service()->transition($this->owner, $draft->getId(), $new, 'cancel');
        $this->service()->dispatchDue($draft->getId(), $new);
        $this->assertJobs(0);
        self::assertSame('cancelled', $this->reload($draft->getId())->getStatus());
    }

    public function testLateWorkerBlocksWithoutJobsAndCalendarGetDoesNotMutateOrCallApi(): void
    {
        $this->connect();
        $draft = $this->draft();
        $revision = $this->arm($draft);
        $id = $draft->getId();
        $this->clock->sleep(3721);
        $queries = $this->creatorQueries;
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/calendrier?month=2026-10&zone=America%2FNew_York');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.calendar-grid', '05:00');
        self::assertSelectorTextContains('.calendar-grid', 'À reprogrammer');
        $this->client->request('GET', '/studio/brouillons/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSame($queries, $this->creatorQueries);
        self::assertSame('scheduled', $this->db()->fetchOne('SELECT status FROM publication_draft WHERE id = ?', [$id]));
        $this->service()->dispatchDue($id, $revision);
        self::assertSame('blocked', $this->reload($id)->getStatus());
        $this->assertJobs(0);
    }

    public function testChangedDestinationAtDueTimeBlocksWholeBatch(): void
    {
        $this->connect();
        $draft = $this->draft();
        $revision = $this->arm($draft);
        $this->db()->executeStatement('UPDATE youtube_connection SET channel_id = ? WHERE owner_id = ?', ['UC'.str_repeat('b', 22), $this->owner->getId()]);
        $this->clock->sleep(3600);
        $this->service()->dispatchDue($draft->getId(), $revision);
        self::assertSame('blocked', $this->reload($draft->getId())->getStatus());
        $this->assertJobs(0);
    }

    public function testLateNetworkFailureRollsBackAndNextMessageWorksWithSameService(): void
    {
        $this->connect();
        $first = $this->draft();
        $firstRevision = $this->arm($first);
        $second = $this->draft(['youtube']);
        $secondRevision = $this->arm($second);
        $firstId = $first->getId();
        $secondId = $second->getId();
        $this->failAt = $this->creatorQueries + 3;
        $this->clock->sleep(3600);
        $service = $this->service();
        $service->dispatchDue($firstId, $firstRevision);
        self::assertSame('blocked', $this->db()->fetchOne('SELECT status FROM publication_draft WHERE id = ?', [$firstId]));
        $this->assertJobs(0);
        self::assertSame(2, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $service->dispatchDue($secondId, $secondRevision);
        $this->assertJobs(1);
        self::assertSame('dispatched', $this->reload($secondId)->getStatus());
    }

    public function testSlowRemoteValidationRechecksDeadlineBeforeCommittingAnyJob(): void
    {
        $this->connect();
        $draft = $this->draft();
        $revision = $this->arm($draft);
        $this->slowAt = $this->creatorQueries + 3;
        $this->clock->sleep(3600);
        $this->service()->dispatchDue($draft->getId(), $revision);
        $this->assertJobs(0);
        $draft = $this->reload($draft->getId());
        self::assertSame('blocked', $draft->getStatus());
        self::assertStringContainsString('créneau est dépassé', $draft->getNotice());
    }

    public function testScheduledSourcesBlockWholeDeletionAndUnarmedDraftKeepsSnapshot(): void
    {
        $this->connect();
        $draft = $this->draft(['youtube']);
        $revision = $this->arm($draft);
        $source = $draft->getItems()->first()->getSource();
        $otherSource = $this->video();
        $library = static::getContainer()->get(VideoLibrary::class);
        try {
            $library->deleteMany([$otherSource, $source]);
            self::fail('An armed source protects the whole deletion selection.');
        } catch (VideoLibraryException $error) {
            self::assertStringContainsString('programmée', $error->getMessage());
        }
        self::assertSame(2, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM video_asset'));
        // Like a new HTTP request after a refused deletion, use a fresh manager and managed entities.
        static::getContainer()->get(ManagerRegistry::class)->resetManager();
        $this->owner = $this->em()->find(User::class, $this->owner->getId());
        $source = $this->em()->find(VideoAsset::class, $source->getId());
        $this->service()->transition($this->owner, $draft->getId(), $revision, 'edit');
        $library->deleteMany([$source]);
        $draft = $this->reload($draft->getId());
        self::assertNull($draft->getItems()->first()->getSource());
        self::assertSame('Source <privée>', $draft->getItems()->first()->getTitle());
        $this->client->loginUser($this->owner);
        $this->client->request('GET', '/studio/brouillons/'.$draft->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'la source a été supprimée');
        self::assertSelectorCount(0, '.draft-media video');
        self::assertSelectorTextContains('.draft-media-missing', 'aperçu indisponible');
    }

    public function testSecondDatabaseConnectionPreventsCancelAndDispatchUntilLockReleased(): void
    {
        $this->connect();
        $draft = $this->draft(['youtube']);
        $revision = $this->arm($draft);
        $id = $draft->getId();
        $ownerId = $this->owner->getId();
        $otherDb = DriverManager::getConnection($this->db()->getParams());
        self::assertSame('shortflow_test', $otherDb->fetchOne('SELECT current_database()'));
        self::assertTrue($otherDb->fetchOne('SELECT pg_try_advisory_lock(85402, ?)', [$ownerId]));
        $this->clock->sleep(3600);
        try {
            foreach (['cancel', 'dispatch'] as $action) {
                try {
                    if ('cancel' === $action) {
                        $this->service()->transition($this->owner, $id, $revision, 'cancel');
                    } else {
                        $this->service()->dispatchDue($id, $revision);
                    }
                    self::fail('The second connection must hold the owner lock.');
                } catch (PublicationException $error) {
                    self::assertStringContainsString('en cours', $error->getMessage());
                }
            }
            self::assertSame('scheduled', $this->db()->fetchOne('SELECT status FROM publication_draft WHERE id = ?', [$id]));
            $this->assertJobs(0);
        } finally {
            $otherDb->executeQuery('SELECT pg_advisory_unlock(85402, ?)', [$ownerId]);
            $otherDb->close();
        }
        $this->service()->dispatchDue($id, $revision);
        $this->assertJobs(1);
        try {
            $this->service()->transition($this->owner, $id, $revision, 'cancel');
            self::fail('Cancellation cannot be acknowledged after the handoff.');
        } catch (PublicationException $error) {
            self::assertStringContainsString('déjà engagé', $error->getMessage());
        }
        self::assertSame('dispatched', $this->reload($id)->getStatus());
    }

    public function testCalendarDerivesPartialAndPublishedStatesFromRealJobRows(): void
    {
        $this->connect();
        $draft = $this->draft(['youtube', 'instagram']);
        $this->service()->confirm($this->owner, $draft->getId(), $draft->getRevision(), false);
        $calendar = static::getContainer()->get(PublicationCalendar::class);
        self::assertSame('active', $calendar->states([$draft])[$draft->getId()]);
        $this->db()->executeStatement("UPDATE youtube_publication SET status = 'published'");
        $this->db()->executeStatement("UPDATE instagram_publication SET status = 'failed'");
        self::assertSame('partial', $calendar->states([$draft])[$draft->getId()]);
        $this->db()->executeStatement("UPDATE instagram_publication SET status = 'published'");
        self::assertSame('published', $calendar->states([$draft])[$draft->getId()]);
        $this->db()->executeStatement('DELETE FROM instagram_publication');
        self::assertSame('blocked', $calendar->states([$draft])[$draft->getId()]);
    }

    public function testRecoverOnlyRequeuesSameArmedVersionAndNeverRearmsCancelledDraft(): void
    {
        $this->connect();
        $draft = $this->draft(['youtube']);
        $revision = $this->arm($draft);
        $this->service()->recover($draft->getId());
        self::assertSame($revision, $draft->getRevision());
        self::assertSame(2, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $this->service()->transition($this->owner, $draft->getId(), $revision, 'cancel');
        $this->expectException(PublicationException::class);
        $this->service()->recover($draft->getId());
    }

    public function testPlanningFormOnlyArmsAfterExplicitConfirmationAndCancelUsesPost(): void
    {
        $this->connect();
        $draft = $this->draft(['youtube']);
        $id = $draft->getId();
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/studio/brouillons/'.$id);
        $form = $crawler->selectButton('Vérifier et confirmer')->form();
        $form['publication_plan[mode]'] = 'scheduled';
        $form['publication_plan[local]'] = '2026-10-18T11:00';
        $form['publication_plan[timezone]'] = 'Europe/Paris';
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/brouillons/'.$id.'/confirmer', 303);
        self::assertSame('draft', $this->db()->fetchOne('SELECT status FROM publication_draft WHERE id = ?', [$id]));
        self::assertSame(0, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.batch-total', '18/10/2026 à 11:00');
        $form = $crawler->selectButton('Confirmer la programmation')->form();
        $form['form[confirm]'] = true;
        $payload = $form->getPhpValues();
        $this->client->submit($form);
        self::assertResponseRedirects('/studio/brouillons/'.$id, 303);
        self::assertSame('scheduled', $this->db()->fetchOne('SELECT status FROM publication_draft WHERE id = ?', [$id]));
        $this->client->request('POST', $form->getUri(), $payload);
        self::assertSame(1, (int) $this->db()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        $crawler = $this->client->request('GET', '/studio/brouillons/'.$id.'/cancel');
        self::assertResponseIsSuccessful();
        self::assertSame('scheduled', $this->db()->fetchOne('SELECT status FROM publication_draft WHERE id = ?', [$id]));
        $cancel = $crawler->filter('form')->last()->form();
        $payload = $cancel->getPhpValues();
        unset($payload['form']['_token']);
        $this->client->request('POST', $cancel->getUri(), $payload);
        self::assertResponseStatusCodeSame(422);
        $this->client->submit($cancel);
        self::assertResponseStatusCodeSame(303);
        self::assertSame('cancelled', $this->db()->fetchOne('SELECT status FROM publication_draft WHERE id = ?', [$id]));
        $this->assertJobs(0);
    }

    public function testReplacingSourcesPreservesTextAndStaleEditorCannotOverwrite(): void
    {
        $draft = $this->draft(['youtube']);
        $id = $draft->getId();
        $oldRevision = $draft->getRevision();
        $sourceId = $draft->videoIds()[0];
        $newVideo = $this->video();
        $this->client->loginUser($this->owner);
        $crawler = $this->client->request('GET', '/studio/brouillons/'.$id.'/videos');
        $form = $crawler->filter('form')->last()->form();
        $payload = $form->getPhpValues();
        $payload['form']['videos'] = [(string) $sourceId, (string) $newVideo->getId()];
        $this->client->request('POST', $form->getUri(), $payload);
        self::assertResponseStatusCodeSame(303);
        $draft = $this->reload($id);
        self::assertCount(2, $draft->getItems());
        self::assertSame('Texte sauvegardé', $draft->content()['youtube']['videos'][$sourceId]['description']);
        self::assertNull($draft->content()['youtube']['videos'][$newVideo->getId()]['privacy']);
        try {
            $this->service()->save($this->em()->find(User::class, $this->owner->getId()), [$sourceId], ['youtube'], [], [], 'Ancien onglet', $id, $oldRevision);
            self::fail('The obsolete revision must not replace the newer selection.');
        } catch (PublicationException $error) {
            self::assertStringContainsString('a changé', $error->getMessage());
        }
        self::assertCount(2, $this->reload($id)->getItems());
        $this->assertJobs(0);
    }
}
