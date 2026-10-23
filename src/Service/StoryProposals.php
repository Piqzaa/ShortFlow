<?php

namespace App\Service;

use App\Entity\StoryProposal;
use App\Entity\User;
use App\Message\WriteStoryProposal;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class StoryProposals
{
    public function __construct(private EntityManagerInterface $em, private MessageBusInterface $bus, private LocalStoryWriter $writer)
    {
    }

    /** @param array<string,mixed> $input */
    public function create(User $owner, int $scope, string $fingerprint, array $input): StoryProposal
    {
        if (strlen($fingerprint) > 24000) {
            throw new \InvalidArgumentException('La préparation est trop longue. Raccourcis les textes des scènes.');
        }

        return $this->em->getConnection()->transactional(function () use ($owner, $scope, $fingerprint, $input): StoryProposal {
            $this->em->lock($owner, LockMode::PESSIMISTIC_WRITE);
            $recent = $this->latest($owner, $scope);
            if ($recent && in_array($recent->getStatus(), ['queued', 'story', 'shots'], true)) {
                if ($recent->getFingerprint() === $fingerprint) {
                    return $recent;
                }
                throw new \RuntimeException('Une rédaction est déjà en cours pour cette vidéo. Tu pourras demander une autre proposition après son résultat.');
            }
            $pending = $this->em->getRepository(StoryProposal::class)->findBy(['owner' => $owner, 'status' => ['queued', 'story', 'shots']]);
            foreach ($pending as $job) {
                $this->expire($job);
                if (in_array($job->getStatus(), ['queued', 'story', 'shots'], true)) {
                    throw new \RuntimeException('Une rédaction est déjà en cours sur ton compte. Attends son résultat avant d’en lancer une autre.');
                }
            }
            $this->em->createQuery('DELETE FROM App\Entity\StoryProposal p WHERE p.owner = :owner AND p.createdAt < :before')->setParameter('owner', $owner)->setParameter('before', new \DateTimeImmutable('-1 day'))->execute();
            $job = new StoryProposal($owner, $scope, $fingerprint, $input);
            $this->em->persist($job);
            $this->em->flush();
            $this->bus->dispatch(new WriteStoryProposal($job->getId()));

            return $job;
        });
    }

    public function owned(User $owner, string $id): ?StoryProposal
    {
        $job = $this->em->getRepository(StoryProposal::class)->findOneBy(['id' => $id, 'owner' => $owner]);
        if ($job) {
            $this->expire($job);
        }

        return $job;
    }

    public function latest(User $owner, int $scope): ?StoryProposal
    {
        $job = $this->em->getRepository(StoryProposal::class)->findOneBy(['owner' => $owner, 'scope' => $scope], ['createdAt' => 'DESC', 'id' => 'DESC']);
        if ($job) {
            $this->expire($job);
        }

        return $job;
    }

    private function expire(StoryProposal $job): void
    {
        if (in_array($job->getStatus(), ['queued', 'story', 'shots'], true) && $job->getCreatedAt() < new \DateTimeImmutable('-10 minutes')) {
            $job->fail('La rédaction a expiré. Tes scènes sont conservées ; tu peux demander une nouvelle proposition.');
            $this->em->flush();
        }
    }

    public function process(string $id): void
    {
        $job = $this->em->getRepository(StoryProposal::class)->find($id);
        if (!$job) {
            return;
        }
        $claimed = $this->em->getConnection()->transactional(function () use ($job): bool {
            $this->em->refresh($job, LockMode::PESSIMISTIC_WRITE);
            if ('queued' !== $job->getStatus()) {
                return false;
            }
            $this->expire($job);
            if ('queued' !== $job->getStatus()) {
                return false;
            }
            $job->phase('story');
            $this->em->flush();

            return true;
        });
        if (!$claimed) {
            return;
        }
        try {
            $input = $job->getInput();
            $result = $this->writer->propose($input['idea'], $input['durations'], $input['continuity'], $input['tone'], $input['context'], function (string $phase) use ($job): void {
                $job->phase($phase);
                $this->em->flush();
            });
            $job->complete($result);
        } catch (\Throwable) {
            $job->fail('Le rédacteur n’a pas fourni de scénario exploitable. Tes scènes sont conservées. Tu peux réessayer ou les écrire toi-même.');
        }
        $this->em->flush();
    }
}
