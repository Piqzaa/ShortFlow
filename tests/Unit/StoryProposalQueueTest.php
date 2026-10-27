<?php

namespace App\Tests\Unit;

use App\Entity\StoryProposal;
use App\Entity\User;
use App\Service\LocalStoryWriter;
use App\Service\StoryProposals;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\MessageBusInterface;

final class StoryProposalQueueTest extends TestCase
{
    /** @return iterable<string, array{int, string, string}> */
    public static function waitingCases(): iterable
    {
        foreach ([0, 11] as $minutes) {
            foreach (['story', 'editorial'] as $kind) {
                foreach (['owned', 'latest', 'process'] as $operation) {
                    yield $kind.' '.$operation.' after '.$minutes.' minutes' => [$minutes, $kind, $operation];
                }
            }
        }
    }

    #[DataProvider('waitingCases')]
    public function testWaitingDoesNotExpireAProposalBeforeItsWorkerStarts(int $minutes, string $kind, string $operation): void
    {
        $owner = new User('queue-regression@example.test');
        $input = 'story' === $kind
            ? ['idea' => 'Un chat blanc se couche sur un coussin.', 'durations' => [5], 'continuity' => 'independent', 'tone' => 'calme', 'context' => []]
            : ['source' => ['title' => 'Le chat et le coussin'], 'profile' => ['language' => 'fr'], 'networks' => ['youtube'], 'baseline' => []];
        $clock = new MockClock('2026-10-27T12:00:00Z');
        $job = new StoryProposal($owner, 42, 'unchanged-preparation', $input, $kind, $clock->now()->modify('-'.$minutes.' minutes'));
        self::assertSame('queued', $job->getStatus());

        $repository = $this->createStub(EntityRepository::class);
        $repository->method('find')->willReturn($job);
        $repository->method('findOneBy')->willReturnCallback(static function (array $criteria) use ($owner, $job, $kind): StoryProposal {
            self::assertSame($owner, $criteria['owner']);
            self::assertSame($kind, $criteria['kind']);

            return $job;
        });
        $connection = $this->createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('getConnection')->willReturn($connection);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $calls = 0;
        $http = new MockHttpClient(static function (string $method, string $url) use (&$calls, $kind): MockResponse {
            ++$calls;
            self::assertSame('POST', $method);
            self::assertSame('http://localhost:11434/api/chat', $url);
            $result = 'editorial' === $kind
                ? ['youtube' => ['title' => 'Le chat et le coussin', 'description' => 'Un chat blanc se couche sur un coussin.', 'hashtags' => []]]
                : (1 === $calls
                    ? ['title' => 'Un moment calme', 'summary' => 'Le chat se couche sur le coussin.', 'identity' => 'Un chat blanc.', 'beats' => ['Le chat blanc se couche sur un coussin.']]
                    : ['identity' => 'A white cat.', 'shots' => [['seconds' => 5, 'prompt' => 'The white cat slowly lies down on a cushion in one continuous take.']]]);

            return new MockResponse(json_encode(['done' => true, 'done_reason' => 'stop', 'message' => ['content' => json_encode($result, JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR));
        });
        $service = new StoryProposals($em, $bus, new LocalStoryWriter($http, true, 'http://localhost:11434', 'synthetic'), $clock);

        if ('process' === $operation) {
            $service->process($job->getId());
        } else {
            $found = 'owned' === $operation
                ? $service->owned($owner, $job->getId(), $kind)
                : $service->latest($owner, 42, $kind);
            self::assertSame($job, $found);
        }

        self::assertSame('unchanged-preparation', $job->getFingerprint());
        self::assertSame('process' === $operation ? 'ready' : 'queued', $job->getStatus(), sprintf(
            'R1.1: %s/%s after %d minutes waiting must not fail before execution (observed status: %s, simulated text requests: %d).',
            $kind, $operation, $minutes, $job->getStatus(), $calls,
        ));
        self::assertNull($job->getError());
        self::assertSame('process' === $operation ? ('story' === $kind ? 2 : 1) : 0, $calls);
        if ('process' !== $operation) {
            self::assertSame($input, $job->getInput());
            self::assertNull($job->getResult());
        } else {
            self::assertSame([], $job->getInput());
            self::assertNotNull($job->getResult());
            self::assertSame('story' === $kind ? 'Un moment calme' : 'Le chat et le coussin', 'story' === $kind ? $job->getResult()['title'] : $job->getResult()['texts']['youtube']['title']);
        }
    }

    /** @return iterable<string, array{string, int, ?int, ?int, string}> */
    public static function deadlineCases(): iterable
    {
        yield 'queue before one hour' => ['queued', 3599, null, null, 'queued'];
        yield 'queue at one hour' => ['queued', 3600, null, null, 'failed'];
        yield 'long queue then recent execution' => ['story', 1800, 1, 1, 'story'];
        yield 'phase before deadline' => ['shots', 1800, 400, 209, 'shots'];
        yield 'phase at deadline' => ['shots', 1800, 400, 210, 'failed'];
        yield 'execution before deadline despite recent phase' => ['shots', 1800, 419, 1, 'shots'];
        yield 'execution at deadline despite recent phase' => ['shots', 1800, 420, 1, 'failed'];
        yield 'legacy execution without timestamps' => ['story', 660, null, null, 'failed'];
    }

    #[DataProvider('deadlineCases')]
    public function testQueuePhaseAndExecutionHaveSeparateDeadlines(string $phase, int $createdAge, ?int $startAge, ?int $phaseAge, string $expected): void
    {
        $clock = new MockClock('2026-10-27T12:00:00Z');
        $job = new StoryProposal(new User('deadlines@example.test'), 1, 'fingerprint', [], 'story', $clock->now()->modify('-'.$createdAge.' seconds'));
        if ('queued' !== $phase) {
            $job->phase($phase, $clock->now());
            (new \ReflectionProperty($job, 'startedAt'))->setValue($job, null === $startAge ? null : $clock->now()->modify('-'.$startAge.' seconds'));
            (new \ReflectionProperty($job, 'phaseStartedAt'))->setValue($job, null === $phaseAge ? null : $clock->now()->modify('-'.$phaseAge.' seconds'));
        }

        $this->readingService($job, $clock)->owned($job->getOwner(), $job->getId());

        self::assertSame($expected, $job->getStatus());
        if ('failed' === $expected) {
            self::assertStringContainsString('queued' === $phase ? 'démarrer' : 'exécution', $job->getError());
        } else {
            self::assertNull($job->getError());
        }
    }

    public function testRepeatingAPhaseDoesNotRenewItsDeadline(): void
    {
        $clock = new MockClock('2026-10-27T12:00:00Z');
        $job = new StoryProposal(new User('phase@example.test'), 1, 'fingerprint', [], 'story', $clock->now());
        $job->phase('story', $clock->now());
        $started = $job->getStartedAt();
        $clock->sleep(209);
        $job->phase('story', $clock->now());
        self::assertSame($started, $job->getPhaseStartedAt());
        self::assertSame($started, $job->getStartedAt());
        $clock->sleep(1);

        $this->readingService($job, $clock)->owned($job->getOwner(), $job->getId());

        self::assertSame('failed', $job->getStatus());
    }

    public function testProgressMetadataUsesTheServerClockWithoutExposingThePreparation(): void
    {
        $clock = new MockClock('2026-10-27T12:00:00Z');
        $job = new StoryProposal(new User('timing@example.test'), 42, 'private-fingerprint', ['idea' => 'Private preparation'], 'editorial', $clock->now());
        $service = $this->readingService($job, $clock);
        $clock->sleep(660);
        self::assertSame(['kind' => 'editorial', 'createdAt' => '2026-10-27T12:00:00+00:00', 'startedAt' => null, 'observedAt' => '2026-10-27T12:11:00+00:00'], $service->timing($job));
        $job->phase('editorial', $clock->now());
        $clock->sleep(30);
        self::assertSame(['kind' => 'editorial', 'createdAt' => '2026-10-27T12:00:00+00:00', 'startedAt' => '2026-10-27T12:11:00+00:00', 'observedAt' => '2026-10-27T12:11:30+00:00'], $service->timing($job));
    }

    private function readingService(StoryProposal $job, MockClock $clock): StoryProposals
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($job);
        $connection = $this->createStub(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('getConnection')->willReturn($connection);
        $http = new MockHttpClient(static function (): never { throw new \LogicException('No inference during deadline checks.'); });

        return new StoryProposals($em, $this->createStub(MessageBusInterface::class), new LocalStoryWriter($http, false, 'http://localhost:11434', 'synthetic'), $clock);
    }
}
