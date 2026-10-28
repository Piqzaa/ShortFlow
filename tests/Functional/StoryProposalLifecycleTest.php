<?php

namespace App\Tests\Functional;

use App\Entity\StoryProposal;
use App\Entity\User;
use App\Service\LocalStoryWriter;
use App\Service\StoryProposals;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class StoryProposalLifecycleTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private User $owner;
    private MockHttpClient $http;
    private int $calls = 0;
    private ?\Closure $duringText = null;

    protected function setUp(): void
    {
        static::bootKernel();
        $this->clock = new MockClock('2026-10-27T12:00:00Z');
        static::getContainer()->set('app.publication_clock', $this->clock);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $this->em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        foreach (['youtube.http', 'instagram.http', 'tiktok.http', 'ai.http'] as $id) {
            static::getContainer()->set($id, new MockHttpClient(static function (): never { throw new \LogicException('No provider or social request allowed.'); }));
        }
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            ++$this->calls;
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:11434/api/chat', $url);
            self::assertFalse($this->em->getConnection()->isTransactionActive(), 'Inference must run outside database locks.');
            $this->duringText?->__invoke();
            $body = json_decode($options['body'], true, 32, JSON_THROW_ON_ERROR);
            $fields = $body['format']['properties'];
            $data = isset($fields['youtube'])
                ? ['youtube' => ['title' => 'Un moment calme', 'description' => 'Le chat se couche sur un coussin.', 'hashtags' => []]]
                : (isset($fields['beats'])
                    ? ['title' => 'Un moment calme', 'summary' => 'Le chat se couche sur un coussin.', 'identity' => 'Un chat blanc.', 'beats' => ['Le chat se couche sur le coussin.']]
                    : ['identity' => 'A white cat.', 'shots' => [['seconds' => 5, 'prompt' => 'A white cat slowly lies down on a cushion in one continuous take.']]]);

            return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($data, JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR));
        });
        $this->http = $http;
        static::getContainer()->set(LocalStoryWriter::class, new LocalStoryWriter($http, true, 'http://localhost:11434', 'synthetic'));
        $this->owner = $this->user('owner');
    }

    /** @return iterable<string, array{string}> */
    public static function kinds(): iterable
    {
        yield 'scenario' => ['story'];
        yield 'publication texts' => ['editorial'];
    }

    private function user(string $name): User
    {
        $user = new User($name.'-lifecycle@example.test');
        $user->setPassword('unused');
        $user->verify();
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /** @return array<string,mixed> */
    private function input(string $kind): array
    {
        return 'story' === $kind
            ? ['idea' => 'Un chat blanc se couche sur un coussin.', 'durations' => [5], 'continuity' => 'independent', 'tone' => 'calme', 'context' => []]
            : ['source' => ['title' => 'Un moment calme', 'description' => 'Le chat se couche sur un coussin.'], 'profile' => ['language' => 'fr'], 'networks' => ['youtube'], 'baseline' => []];
    }

    private function jobs(): StoryProposals
    {
        return static::getContainer()->get(StoryProposals::class);
    }

    private function textRequests(): int
    {
        return $this->calls;
    }

    private function enqueue(string $kind = 'story', string $fingerprint = 'original', ?User $owner = null): StoryProposal
    {
        return $this->jobs()->create($owner ?? $this->owner, 42, $fingerprint, $this->input($kind), $kind);
    }

    #[DataProvider('kinds')]
    public function testLongQueueWaitDoesNotConsumeExecutionTimeAndRedeliveryDoesNotRepeatWriting(string $kind): void
    {
        $job = $this->enqueue($kind);
        $created = $job->getCreatedAt();
        $this->clock->sleep(660);
        self::assertSame('queued', $this->jobs()->owned($this->owner, $job->getId(), $kind)->getStatus());
        self::assertNull($job->getStartedAt());
        self::assertSame(0, $this->textRequests());
        $this->jobs()->process($job->getId());

        self::assertSame('ready', $job->getStatus());
        self::assertEquals($created, $job->getCreatedAt());
        self::assertEquals($this->clock->now(), $job->getStartedAt());
        self::assertNotNull($job->getResult());
        $expected = 'story' === $kind ? 2 : 1;
        self::assertSame($expected, $this->textRequests());
        $this->jobs()->process($job->getId());
        self::assertSame($expected, $this->http->getRequestsCount());
    }

    public function testQueueHasItsOwnOneHourDeadline(): void
    {
        $job = $this->enqueue();
        $this->clock->sleep(StoryProposals::QUEUE_TIMEOUT);
        $this->jobs()->process($job->getId());

        self::assertSame('failed', $job->getStatus());
        self::assertStringContainsString('démarrer', $job->getError());
        self::assertNull($job->getStartedAt());
        self::assertSame(0, $this->calls);
    }

    public function testEachStageGetsItsOwnDeadlineWithoutResettingTheTotalStart(): void
    {
        $job = $this->enqueue();
        $start = $this->clock->now();
        $this->duringText = fn () => $this->clock->sleep(209);
        $this->jobs()->process($job->getId());

        self::assertSame('ready', $job->getStatus());
        self::assertEquals($start, $job->getStartedAt());
        self::assertEquals($start->modify('+209 seconds'), $job->getPhaseStartedAt());
        self::assertSame(2, $this->calls);
    }

    #[DataProvider('kinds')]
    public function testLateResultIsDiscardedAtThePhaseDeadline(string $kind): void
    {
        $job = $this->enqueue($kind);
        $this->duringText = fn () => $this->clock->sleep(StoryProposals::PHASE_TIMEOUT);
        $this->jobs()->process($job->getId());

        self::assertSame('failed', $job->getStatus());
        self::assertStringContainsString('exécution', $job->getError());
        self::assertNull($job->getResult());
        self::assertSame(1, $this->calls);
    }

    #[DataProvider('kinds')]
    public function testExpiredWorkerCannotOverwriteANewRequest(string $kind): void
    {
        $job = $this->enqueue($kind);
        $replacement = null;
        $this->duringText = function () use ($job, $kind, &$replacement): void {
            $this->clock->sleep(StoryProposals::PHASE_TIMEOUT);
            self::assertSame('failed', $this->jobs()->owned($this->owner, $job->getId(), $kind)->getStatus());
            $replacement = $this->enqueue($kind, 'replacement');
        };
        $this->jobs()->process($job->getId());

        self::assertSame('failed', $job->getStatus());
        self::assertStringContainsString('exécution', $job->getError());
        self::assertNull($job->getResult());
        self::assertNotNull($replacement);
        self::assertSame('queued', $replacement->getStatus());
        self::assertNull($replacement->getResult());
        self::assertSame($replacement->getId(), $this->jobs()->latest($this->owner, 42, $kind)->getId());
        self::assertSame(1, $this->calls);
    }

    #[DataProvider('kinds')]
    public function testNewerProposalAlsoSupersedesALegacyActiveDuplicate(string $kind): void
    {
        $job = $this->enqueue($kind);
        $newer = null;
        $this->duringText = function () use ($kind, &$newer): void {
            if ('story' === $kind && 1 === $this->calls) {
                return;
            }
            // Model a pre-existing duplicate without using normal admission.
            $this->clock->sleep(1);
            $newer = new StoryProposal($this->owner, 42, 'newer', $this->input($kind), $kind, $this->clock->now());
            $this->em->persist($newer);
            $this->em->flush();
        };
        $this->jobs()->process($job->getId());

        self::assertSame('failed', $job->getStatus());
        self::assertStringContainsString('plus récente', $job->getError());
        self::assertNull($job->getResult());
        self::assertNotNull($newer);
        self::assertSame('queued', $newer->getStatus());
        self::assertNull($newer->getResult());
    }

    public function testAdmissionIsBoundedAcrossOwnersButKeepsIdempotentRequests(): void
    {
        $original = $this->enqueue();
        for ($i = 1; $i < StoryProposals::MAX_PENDING; ++$i) {
            $this->enqueue(owner: $this->user('pending-'.$i));
        }
        self::assertSame($original->getId(), $this->enqueue()->getId());
        $extra = $this->user('extra');
        try {
            $this->enqueue(owner: $extra);
            self::fail('The shared queue must refuse additional work.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('pleine', $error->getMessage());
        }
        self::assertSame(StoryProposals::MAX_PENDING, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM story_proposal'));
        self::assertSame(StoryProposals::MAX_PENDING, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(0, $this->calls);
    }

    public function testOneOwnerCannotSubmitAnotherKindWhileWaiting(): void
    {
        $original = $this->enqueue();
        try {
            $this->enqueue('editorial');
            self::fail('One owner must not occupy multiple queue slots.');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('sur ton compte', $error->getMessage());
        }
        self::assertSame('queued', $original->getStatus());
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM story_proposal'));
        self::assertSame(0, $this->calls);
    }

    public function testExpiredRowsDoNotPermanentlyFillAdmissionCapacity(): void
    {
        for ($i = 0; $i < StoryProposals::MAX_PENDING; ++$i) {
            $job = new StoryProposal($this->user('expired-'.$i), 42, 'old', [], 'story', $this->clock->now()->modify('-1 hour'));
            if (1 === $i % 2) {
                $job->phase('story', $this->clock->now()->modify('-420 seconds'));
            }
            $this->em->persist($job);
        }
        $this->em->flush();

        self::assertSame('queued', $this->enqueue()->getStatus());
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(0, $this->calls);
    }
}
