<?php

namespace App\Tests\Functional;

use App\Entity\StoryProposal;
use App\Entity\User;
use App\Service\StoryProposals;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class StoryProposalConcurrencyTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private User $owner;
    private string $directory;
    /** @var array<string,Process> */
    private array $children = [];

    protected function setUp(): void
    {
        static::bootKernel(['debug' => false]);
        $this->clock = new MockClock('2026-10-27T12:00:00Z');
        static::getContainer()->set('app.publication_clock', $this->clock);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $this->em->getConnection();
        self::assertSame('shortflow_test', $db->fetchOne('SELECT current_database()'));
        $db->executeStatement('DELETE FROM messenger_messages');
        $db->executeStatement('DELETE FROM app_user');
        $this->directory = sys_get_temp_dir().'/vydflow-story-races-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->owner = $this->user('owner');
    }

    protected function tearDown(): void
    {
        // Release only this test's locks/processes, including after assertion failures.
        if (isset($this->em)) {
            while ($this->em->getConnection()->isTransactionActive()) {
                $this->em->getConnection()->rollBack();
            }
        }
        foreach ($this->children as $process) {
            $process->stop(0, 9);
        }
        if (isset($this->directory)) {
            (new Filesystem())->remove($this->directory);
        }
        if (isset($this->em)) {
            foreach (['ai_generation', 'youtube_publication', 'instagram_publication', 'tiktok_publication'] as $table) {
                self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table), 'Text recovery must not create '.$table);
            }
            self::assertSame(0, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM messenger_messages WHERE queue_name <> 'story_writing'"));
        }
        parent::tearDown();
    }

    private function user(string $name): User
    {
        $user = new User($name.'-race@example.test');
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

    private function enqueue(string $kind = 'story', ?User $owner = null): StoryProposal
    {
        return $this->jobs()->create($owner ?? $this->owner, 42, 'original', $this->input($kind), $kind);
    }

    /** @param array<string,mixed> $config */
    private function start(string $name, array $config): void
    {
        $config += ['now' => $this->clock->now()->format(DATE_ATOM)];
        file_put_contents($this->directory.'/'.$name.'.json', json_encode($config, JSON_THROW_ON_ERROR));
        $process = new Process([PHP_BINARY, dirname(__DIR__).'/Fixtures/story-proposal-process.php', $this->directory.'/'.$name], dirname(__DIR__, 2), ['APP_ENV' => 'test', 'APP_DEBUG' => '0', 'TMPDIR' => $this->directory]);
        $process->setTimeout(20);
        $this->children[$name] = $process;
        $process->start();
        $this->await(fn () => is_file($this->directory.'/'.$name.'.ready'), $name.' ready');
    }

    private function await(\Closure $condition, string $description): void
    {
        $deadline = microtime(true) + 10;
        do {
            clearstatcache();
            if ($condition()) {
                return;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $diagnostics = array_map(static fn (Process $p): string => $p->getOutput().$p->getErrorOutput(), $this->children);
        $files = array_map(static fn (string $file): string => basename($file).': '.file_get_contents($file), glob($this->directory.'/*.{result,paused,calls}', GLOB_BRACE));
        self::fail($description.' timed out: '.implode("\n", [...$diagnostics, ...$files]));
    }

    private function signal(string $name, string $suffix): void
    {
        touch($this->directory.'/'.$name.'.'.$suffix);
    }

    /** @return array<string,mixed> */
    private function childResult(string $name): array
    {
        $process = $this->children[$name];
        $process->wait();
        self::assertTrue($process->isSuccessful(), $process->getOutput().$process->getErrorOutput());

        return json_decode(file_get_contents($this->directory.'/'.$name.'.result'), true, 32, JSON_THROW_ON_ERROR);
    }

    /** @param list<string> $names */
    private function awaitDatabaseLocks(array $names): void
    {
        $pids = array_map(fn (string $name): int => (int) file_get_contents($this->directory.'/'.$name.'.ready'), $names);
        self::assertCount(count($pids), array_unique($pids), 'Children must have distinct PostgreSQL connections.');
        self::assertNotContains((int) $this->em->getConnection()->fetchOne('SELECT pg_backend_pid()'), $pids);
        $this->await(fn () => count($pids) === (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pg_stat_activity WHERE pid IN ('.implode(',', $pids).") AND wait_event_type = 'Lock'"), 'children blocked at PostgreSQL barrier');
    }

    private function calls(): int
    {
        return array_sum(array_map(static fn (string $file): int => count(file($file)), glob($this->directory.'/*.calls')));
    }

    private function stored(string $id): StoryProposal
    {
        $this->em->clear();

        return $this->em->find(StoryProposal::class, $id);
    }

    public static function ownerRaces(): iterable
    {
        yield 'same request' => [false];
        yield 'different kind' => [true];
    }

    #[DataProvider('ownerRaces')]
    public function testConcurrentOwnerAdmissionIsIdempotentOrRefused(bool $different): void
    {
        $base = ['action' => 'create', 'owner' => $this->owner->getId(), 'scope' => 42, 'fingerprint' => 'same', 'input' => $this->input('story'), 'kind' => 'story'];
        $this->start('a', $base);
        $other = $base;
        if ($different) {
            $other['kind'] = 'editorial';
            $other['input'] = $this->input('editorial');
        }
        $this->start('b', $other);
        $db = $this->em->getConnection();
        $db->beginTransaction();
        $db->fetchOne('SELECT id FROM app_user WHERE id = ? FOR UPDATE', [$this->owner->getId()]);
        $this->signal('a', 'go');
        $this->signal('b', 'go');
        $this->awaitDatabaseLocks(['a', 'b']);
        $db->commit();
        $results = [$this->childResult('a'), $this->childResult('b')];
        if ($different) {
            self::assertCount(1, array_filter($results, static fn (array $r): bool => isset($r['id'])));
            self::assertStringContainsString('sur ton compte', implode('', array_column($results, 'error')));
        } else {
            self::assertSame($results[0]['id'], $results[1]['id']);
        }
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM story_proposal'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(0, $this->calls());
    }

    public function testTwoOwnersCompeteForTheLastGlobalSlot(): void
    {
        for ($i = 0; $i < StoryProposals::MAX_PENDING - 1; ++$i) {
            $this->enqueue(owner: $this->user('filled-'.$i));
        }
        foreach (['a', 'b'] as $name) {
            $owner = $this->user($name);
            $this->start($name, ['action' => 'create', 'owner' => $owner->getId(), 'scope' => 42, 'fingerprint' => 'same', 'input' => $this->input('story'), 'kind' => 'story']);
        }
        $db = $this->em->getConnection();
        $db->beginTransaction();
        $db->executeQuery('SELECT pg_advisory_xact_lock(1448698950, 1)')->free();
        $this->signal('a', 'go');
        $this->signal('b', 'go');
        $this->awaitDatabaseLocks(['a', 'b']);
        $db->commit();
        $results = [$this->childResult('a'), $this->childResult('b')];
        self::assertCount(1, array_filter($results, static fn (array $r): bool => isset($r['id'])));
        self::assertStringContainsString('pleine', implode('', array_column($results, 'error')));
        self::assertSame(10, (int) $db->fetchOne('SELECT COUNT(*) FROM story_proposal'));
        self::assertSame(10, (int) $db->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        self::assertSame(0, $this->calls());
    }

    public static function kinds(): iterable
    {
        yield 'scenario' => ['story'];
        yield 'publication texts' => ['editorial'];
    }

    #[DataProvider('kinds')]
    public function testTwoProcessesClaimOneJobOnlyOnce(string $kind): void
    {
        $job = $this->enqueue($kind);
        foreach (['a', 'b'] as $name) {
            $this->start($name, ['action' => 'process', 'id' => $job->getId(), 'pause' => 'http:1']);
        }
        $db = $this->em->getConnection();
        $db->beginTransaction();
        $db->fetchOne('SELECT id FROM story_proposal WHERE id = ? FOR UPDATE', [$job->getId()]);
        $this->signal('a', 'go');
        $this->signal('b', 'go');
        $this->awaitDatabaseLocks(['a', 'b']);
        $db->commit();
        $this->await(fn () => 1 === count(glob($this->directory.'/*.paused')), 'one inference starts');
        $this->await(fn () => 1 === count(array_filter($this->children, static fn (Process $p): bool => !$p->isRunning())), 'duplicate delivery returns');
        self::assertSame(1, $this->calls());
        $this->signal('a', 'release');
        $this->signal('b', 'release');
        $this->childResult('a');
        $this->childResult('b');
        self::assertSame('ready', $this->stored($job->getId())->getStatus());
        self::assertSame('story' === $kind ? 2 : 1, $this->calls());
    }

    public function testDifferentOwnersAreIsolatedAndBusyLocalWritingCanBeRetried(): void
    {
        $other = $this->user('other');
        $a = $this->enqueue('story');
        $b = $this->enqueue('editorial', $other);
        self::assertNotSame($a->getId(), $b->getId());
        $this->start('a', ['action' => 'process', 'id' => $a->getId(), 'pause' => 'http:1']);
        $this->signal('a', 'go');
        $this->await(fn () => is_file($this->directory.'/a.paused'), 'first local inference starts');
        $this->start('b', ['action' => 'process', 'id' => $b->getId()]);
        $this->signal('b', 'go');
        self::assertSame(0, $this->childResult('b')['calls']);
        self::assertSame('failed', $this->stored($b->getId())->getStatus());
        self::assertStringContainsString('occupé', $this->stored($b->getId())->getError());
        self::assertSame(1, $this->calls(), 'The local file mutex rejects another inference without blocking PostgreSQL.');
        $this->signal('a', 'release');
        $this->childResult('a');
        self::assertSame('ready', $this->stored($a->getId())->getStatus());
        $owner = $this->em->find(User::class, $this->owner->getId());
        self::assertNull($this->jobs()->owned($owner, $b->getId(), 'editorial'));
        self::assertSame($a->getId(), $this->jobs()->latest($owner, 42)->getId());
        $other = $this->em->find(User::class, $other->getId());
        $retry = $this->jobs()->create($other, 42, 'manual retry', $this->input('editorial'), 'editorial');
        $this->start('retry', ['action' => 'process', 'id' => $retry->getId()]);
        $this->signal('retry', 'go');
        self::assertSame(1, $this->childResult('retry')['calls']);
        self::assertSame('ready', $this->stored($retry->getId())->getStatus());
    }

    #[DataProvider('kinds')]
    public function testAnOldProcessCannotSaveOverTheReplacement(string $kind): void
    {
        $old = $this->enqueue($kind);
        $this->start('old', ['action' => 'process', 'id' => $old->getId(), 'pause' => 'http:1']);
        $this->signal('old', 'go');
        $this->await(fn () => is_file($this->directory.'/old.paused'), 'old inference suspended');
        $this->clock->sleep(StoryProposals::PHASE_TIMEOUT);
        $this->em->clear();
        $owner = $this->em->find(User::class, $this->owner->getId());
        self::assertSame('failed', $this->jobs()->owned($owner, $old->getId(), $kind)->getStatus());
        $new = $this->jobs()->create($owner, 42, 'replacement', $this->input($kind), $kind);
        $this->signal('old', 'release');
        $this->childResult('old');
        self::assertSame('failed', $this->stored($old->getId())->getStatus());
        self::assertNull($this->stored($old->getId())->getResult());
        self::assertSame('queued', $this->stored($new->getId())->getStatus());
        self::assertSame($this->input($kind), $this->stored($new->getId())->getInput());
        self::assertSame(1, $this->calls(), 'An obsolete story must not start its second inference.');
        $this->start('new', ['action' => 'process', 'id' => $new->getId()]);
        $this->signal('new', 'go');
        $this->childResult('new');
        self::assertSame('ready', $this->stored($new->getId())->getStatus());
    }

    public static function interruptions(): iterable
    {
        yield 'before claim' => ['story', 'received', 'queued', 0];
        yield 'before claim beyond queue deadline' => ['story', 'received', 'queued', 0, 3600];
        yield 'during outline' => ['story', 'http:1', 'story', 1];
        yield 'during shots' => ['story', 'http:2', 'shots', 2];
        yield 'during editorial' => ['editorial', 'http:1', 'editorial', 1];
        yield 'ready before ack' => ['story', 'handled', 'ready', 2];
    }

    #[DataProvider('interruptions')]
    public function testKilledMessengerWorkerRedeliversWithoutRepeatingAnExecution(string $kind, string $pause, string $status, int $calls, int $delay = 601): void
    {
        $job = $this->enqueue($kind);
        $this->start('killed', ['action' => 'consume', 'id' => $job->getId(), 'pause' => $pause]);
        $this->signal('killed', 'go');
        $this->await(fn () => is_file($this->directory.'/killed.paused'), 'worker reached kill point');
        self::assertSame($status, $this->stored($job->getId())->getStatus());
        $savedResult = $this->stored($job->getId())->getResult();
        self::assertSame($calls, $this->calls());
        $this->children['killed']->signal(9);
        $this->children['killed']->wait();
        self::assertTrue($this->children['killed']->hasBeenSignaled());
        self::assertSame(9, $this->children['killed']->getTermSignal());
        $db = $this->em->getConnection();
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NOT NULL'));
        // Transport uses the system clock: age only this test message, not the host.
        $db->executeStatement("UPDATE messenger_messages SET delivered_at = CURRENT_TIMESTAMP - INTERVAL '601 seconds' WHERE queue_name = 'story_writing'");
        $this->clock->sleep($delay);
        $this->start('resumed', ['action' => 'consume', 'id' => $job->getId()]);
        $this->signal('resumed', 'go');
        $this->childResult('resumed');
        $expected = 'ready' === $status || ('queued' === $status && $delay < StoryProposals::QUEUE_TIMEOUT) ? 'ready' : 'failed';
        self::assertSame($expected, $this->stored($job->getId())->getStatus());
        self::assertSame('queued' === $status && 'ready' === $expected ? 2 : $calls, $this->calls());
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM messenger_messages'));
        if ('failed' === $expected) {
            self::assertStringContainsString('queued' === $status ? 'démarrer' : 'exécution', $this->stored($job->getId())->getError());
            self::assertNull($this->stored($job->getId())->getResult());
            $owner = $this->em->find(User::class, $this->owner->getId());
            $manual = $this->jobs()->create($owner, 42, 'manual retry', $this->input($kind), $kind);
            self::assertSame('queued', $manual->getStatus());
            $this->start('manual', ['action' => 'process', 'id' => $manual->getId()]);
            $this->signal('manual', 'go');
            self::assertSame('story' === $kind ? 2 : 1, $this->childResult('manual')['calls']);
            self::assertSame('ready', $this->stored($manual->getId())->getStatus());
        } else {
            self::assertNotNull($this->stored($job->getId())->getResult());
            if ('ready' === $status) {
                self::assertSame($savedResult, $this->stored($job->getId())->getResult());
            }
        }
    }
}
