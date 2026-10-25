<?php

namespace App\Command;

use App\Entity\User;
use App\Service\AiReferenceMaintenance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:ai:references', description: 'Examiner les références inutilisées depuis 30 jours, sans supprimer par défaut.')]
final class InspectAiReferencesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private AiReferenceMaintenance $maintenance)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('owner', InputArgument::REQUIRED, 'Identifiant numérique du propriétaire.')->addOption('apply', null, InputOption::VALUE_NONE, 'Supprimer uniquement les images anciennes sans référence durable ou en session.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (string) $input->getArgument('owner');
        $owner = ctype_digit($id) ? $this->em->find(User::class, (int) $id) : null;
        if (!$owner) {
            $output->writeln('Propriétaire indisponible.');

            return Command::INVALID;
        }
        $result = $this->maintenance->inspect($owner, (bool) $input->getOption('apply'));
        $output->writeln(sprintf('%d image(s), %d octets %s.', $result['files'], $result['bytes'], $input->getOption('apply') ? 'retirés' : 'éligibles ; simulation sans suppression'));

        return Command::SUCCESS;
    }
}
