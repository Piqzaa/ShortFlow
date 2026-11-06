<?php

namespace App\Command;

use App\Service\AiExchangeRateUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:ai:exchange-rate:refresh', description: 'Actualiser le taux indicatif EUR/USD depuis la BCE, sans accès fournisseur IA.')]
final class RefreshAiExchangeRateCommand extends Command
{
    public function __construct(private AiExchangeRateUpdater $updater)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $rate = $this->updater->refresh(true) ?? throw new \RuntimeException('Actualisation déjà en cours.');
            $output->writeln('Taux indicatif BCE '.$rate['date'].' : 1 EUR = '.$rate['usdPerEur'].' USD.');

            return Command::SUCCESS;
        } catch (\Throwable) {
            $output->writeln('<error>Actualisation BCE impossible. Le dernier taux reste conservé ; aucun montant fournisseur n’a été modifié.</error>');

            return Command::FAILURE;
        }
    }
}
