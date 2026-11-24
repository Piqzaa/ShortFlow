<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:subscription:simulate', description: 'Simule localement le quota Free ou abonné pour un compte de test.')]
final class SimulateSubscriptionCommand
{
    public function __construct(
        #[Autowire('%kernel.environment%')] private string $environment,
        private UserRepository $users,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Adresse email du compte local.')] string $email,
        #[Argument(description: 'Offre à appliquer : free ou paid.')] string $plan,
    ): int {
        if (!in_array($this->environment, ['dev', 'test'], true)) {
            $io->error('Cette commande est réservée aux environnements dev et test.');

            return 1;
        }

        $owner = $this->users->findByEmail($email);
        if (!$owner || !in_array($plan, ['free', 'paid'], true)) {
            $io->error('Indique un compte local existant et le statut free ou paid.');

            return 1;
        }

        $owner->setPaidUntil('paid' === $plan ? $this->clock->now()->modify('+30 days') : null);
        $this->em->flush();
        $io->success('paid' === $plan ? 'Quota abonné actif pour la simulation.' : 'Quota Free actif pour la simulation.');

        return 0;
    }
}
