<?php

namespace App\Command;

use App\Service\PublicationDrafts;
use App\Service\PublicationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:publication:schedule:recover', description: 'Remet en file le réveil de la version actuellement programmée, sans réarmer un lot annulé ou dépassé.')]
final class RecoverPublicationScheduleCommand extends Command
{
    public function __construct(private PublicationDrafts $drafts)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Identifiant de préparation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('id');
        if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
            $io->error('Identifiant invalide.');

            return Command::INVALID;
        }
        try {
            $this->drafts->recover((int) $id);
        } catch (PublicationException $error) {
            $io->error($error->getMessage());

            return Command::FAILURE;
        }
        $io->success('Réveil remis en file. Les contrôles de version, créneau et comptes restent appliqués.');

        return Command::SUCCESS;
    }
}
