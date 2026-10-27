<?php

namespace App\Service;

use App\Entity\StoryProposal;
use App\Entity\User;
use App\Message\WriteStoryProposal;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\MessageBusInterface;

final class StoryProposals
{
    public const QUEUE_TIMEOUT = 3600;
    public const PHASE_TIMEOUT = 210;
    public const EXECUTION_TIMEOUT = 420;
    public const MAX_PENDING = 10;
    private const RUNNING = ['story', 'shots', 'editorial'];
    private const PENDING = ['queued', 'story', 'shots', 'editorial'];

    public function __construct(private EntityManagerInterface $em, private MessageBusInterface $bus, private LocalStoryWriter $writer, private ClockInterface $clock = new NativeClock('UTC'))
    {
    }

    /** @param array<string,mixed> $input */
    public function create(User $owner, int $scope, string $fingerprint, array $input, string $kind = 'story'): StoryProposal
    {
        if (strlen($fingerprint) > 24000) {
            throw new \InvalidArgumentException('La préparation est trop longue. Raccourcis les textes des scènes.');
        }

        return $this->em->getConnection()->transactional(function () use ($owner, $scope, $fingerprint, $input, $kind): StoryProposal {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $recent = $this->latest($owner, $scope, $kind);
            if ($recent && in_array($recent->getStatus(), self::PENDING, true)) {
                if ($recent->getFingerprint() === $fingerprint) {
                    return $recent;
                }
                throw new \RuntimeException('Une rédaction est déjà en cours pour cette vidéo. Tu pourras demander une autre proposition après son résultat.');
            }
            $pending = $this->em->getRepository(StoryProposal::class)->findBy(['owner' => $owner, 'status' => self::PENDING]);
            foreach ($pending as $job) {
                $this->expire($job);
                if (in_array($job->getStatus(), self::PENDING, true)) {
                    throw new \RuntimeException('Une rédaction est déjà en cours sur ton compte. Attends son résultat avant d’en lancer une autre.');
                }
            }
            $this->admit();
            $this->em->createQuery('DELETE FROM App\Entity\StoryProposal p WHERE p.owner = :owner AND p.status IN (:terminal) AND p.createdAt < :before')->setParameter('owner', $owner)->setParameter('terminal', ['ready', 'failed'])->setParameter('before', $this->clock->now()->modify('-1 day'))->execute();
            $job = new StoryProposal($owner, $scope, $fingerprint, $input, $kind, $this->clock->now());
            $this->em->persist($job);
            $this->em->flush();
            $this->bus->dispatch(new WriteStoryProposal($job->getId()));

            return $job;
        });
    }

    public function owned(User $owner, string $id, string $kind = 'story'): ?StoryProposal
    {
        $job = $this->em->getRepository(StoryProposal::class)->findOneBy(['id' => $id, 'owner' => $owner, 'kind' => $kind]);
        if ($job) {
            $this->expire($job);
        }

        return $job;
    }

    public function latest(User $owner, int $scope, string $kind = 'story'): ?StoryProposal
    {
        $job = $this->em->getRepository(StoryProposal::class)->findOneBy(['owner' => $owner, 'scope' => $scope, 'kind' => $kind], ['createdAt' => 'DESC', 'id' => 'DESC']);
        if ($job) {
            $this->expire($job);
        }

        return $job;
    }

    private function expire(StoryProposal $job): void
    {
        if (null !== $this->expiryReason($job)) {
            $this->em->getConnection()->transactional(function () use ($job): void {
                if ($this->refresh($job)) {
                    $this->failIfExpired($job);
                }
            });
        }
    }

    private function expiryReason(StoryProposal $job): ?string
    {
        $now = $this->clock->now()->getTimestamp();
        if ('queued' === $job->getStatus()) {
            return $now >= $job->getCreatedAt()->getTimestamp() + self::QUEUE_TIMEOUT
                ? 'La rédaction n’a pas pu démarrer dans le délai d’attente. Tes textes sont conservés ; tu peux demander une nouvelle proposition.' : null;
        }
        if (in_array($job->getStatus(), self::RUNNING, true)) {
            // Old rows have no execution timestamps: do not grant a new lease on every read.
            $start = $job->getStartedAt() ?? $job->getCreatedAt();
            $phase = $job->getPhaseStartedAt() ?? $start;
            if ($now >= $start->getTimestamp() + self::EXECUTION_TIMEOUT || $now >= $phase->getTimestamp() + self::PHASE_TIMEOUT) {
                return 'La rédaction a expiré pendant son exécution. Tes textes sont conservés ; tu peux demander une nouvelle proposition.';
            }
        }

        return null;
    }

    /** Call only while holding the proposal row lock. */
    private function failIfExpired(StoryProposal $job): bool
    {
        if (null === $reason = $this->expiryReason($job)) {
            return false;
        }
        $job->fail($reason);
        $this->em->flush();

        return true;
    }

    private function refresh(StoryProposal $job): bool
    {
        try {
            $this->em->refresh($job, LockMode::PESSIMISTIC_WRITE);

            return true;
        } catch (EntityNotFoundException) {
            return false;
        }
    }

    private function admit(): void
    {
        $db = $this->em->getConnection();
        // All callers already hold their own owner/job locks. This global lock only
        // serializes admission; the aggregate takes no other owners' row locks.
        $db->executeQuery('SELECT pg_advisory_xact_lock(1448698950, 1)')->free();
        $now = $this->clock->now();
        $pending = $db->fetchFirstColumn("SELECT id FROM story_proposal WHERE (status = 'queued' AND created_at > :queued) OR (status IN ('story', 'shots', 'editorial') AND COALESCE(started_at, created_at) > :started AND COALESCE(phase_started_at, started_at, created_at) > :phase) LIMIT ".self::MAX_PENDING,
            ['queued' => $now->modify('-'.self::QUEUE_TIMEOUT.' seconds'), 'started' => $now->modify('-'.self::EXECUTION_TIMEOUT.' seconds'), 'phase' => $now->modify('-'.self::PHASE_TIMEOUT.' seconds')],
            ['queued' => Types::DATETIME_IMMUTABLE, 'started' => Types::DATETIME_IMMUTABLE, 'phase' => Types::DATETIME_IMMUTABLE]);
        if (count($pending) >= self::MAX_PENDING) {
            throw new \RuntimeException('La file de rédaction est momentanément pleine. Réessaie un peu plus tard ; tes préparations sont conservées.');
        }
    }

    /** Never hold this transaction during inference. Recheck before every phase/result. */
    private function whileRunning(StoryProposal $job, \Closure $change): bool
    {
        return $this->em->getConnection()->transactional(function () use ($job, $change): bool {
            if (!$this->refresh($job) || !in_array($job->getStatus(), self::RUNNING, true) || $this->failIfExpired($job)) {
                return false;
            }
            $latest = $this->em->getRepository(StoryProposal::class)->findOneBy(['owner' => $job->getOwner(), 'scope' => $job->getScope(), 'kind' => $job->getKind()], ['createdAt' => 'DESC', 'id' => 'DESC']);
            if ($latest?->getId() !== $job->getId()) {
                $job->fail('Cette rédaction a été remplacée par une demande plus récente. Tes textes sont conservés.');
                $this->em->flush();

                return false;
            }
            $change();
            $this->em->flush();

            return true;
        });
    }

    public function process(string $id): void
    {
        $job = $this->em->getRepository(StoryProposal::class)->find($id);
        if (!$job) {
            return;
        }
        $claimed = $this->em->getConnection()->transactional(function () use ($job): bool {
            if (!$this->refresh($job) || $this->failIfExpired($job) || 'queued' !== $job->getStatus()) {
                return false;
            }
            $job->phase('editorial' === $job->getKind() ? 'editorial' : 'story', $this->clock->now());
            $this->em->flush();

            return true;
        });
        if (!$claimed) {
            return;
        }
        $result = [];
        $notice = null;
        try {
            $input = $job->getInput();
            if ('editorial' === $job->getKind()) {
                $texts = $this->writer->publication($input['source'], $input['profile'], $input['networks']);
                $result = ['texts' => $texts, 'source' => $input['source'], 'profile' => $input['profile'], 'baseline' => $input['baseline'], 'version' => PublicationTexts::VERSION];
            } else {
                $result = $this->writer->propose($input['idea'], $input['durations'], $input['continuity'], $input['tone'], $input['context'], function (string $phase) use ($job): void {
                    if (!$this->whileRunning($job, fn () => $job->phase($phase, $this->clock->now()))) {
                        throw new StoryWritingException('obsolete');
                    }
                });
            }
        } catch (StoryWritingException $failure) {
            $notice = 'editorial' === $job->getKind() ? 'Le rédacteur local est indisponible ou occupé. Tes textes restent conservés ; réessaie ou rédige-les directement.' : $failure->getMessage();
        } catch (PublicationException $failure) {
            $notice = $failure->getMessage();
        } catch (\Throwable) {
            $notice = 'editorial' === $job->getKind() ? 'Le rédacteur n’a pas fourni de textes exploitables. Tes textes restent conservés ; tu peux réessayer ou les rédiger directement.' : 'Le rédacteur n’a pas fourni de scénario exploitable. Tes scènes sont conservées. Tu peux réessayer ou les écrire toi-même.';
        }
        $this->whileRunning($job, static function () use ($job, $result, $notice): void {
            if (isset($notice)) {
                $job->fail($notice);
            } else {
                $job->complete($result);
            }
        });
    }
}
