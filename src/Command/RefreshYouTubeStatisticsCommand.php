<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\YouTubePublication;
use App\Service\PublicationException;
use App\Service\PublicationStatistics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:youtube:statistics:refresh', description: 'Demande un relevé YouTube pour une publication existante, sans publier de vidéo.')]
final class RefreshYouTubeStatisticsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private PublicationStatistics $statistics)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('publication', InputArgument::REQUIRED, 'Identifiant local de la publication YouTube.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (string) $input->getArgument('publication');
        $job = ctype_digit($id) && (int) $id > 0 ? $this->em->find(YouTubePublication::class, (int) $id) : null;
        if (!$job) {
            $output->writeln('<error>Publication YouTube introuvable.</error>');

            return Command::FAILURE;
        }
        try {
            $queued = $this->statistics->queue($job->getOwner(), 'youtube', (int) $job->getId());
        } catch (PublicationException $error) {
            $output->writeln('<error>'.$error->getMessage().'</error>');

            return Command::FAILURE;
        }
        $output->writeln($queued ? 'Relevé mis en file. Le worker récupérera les compteurs.' : 'Une demande récente existe déjà. Attends une minute.');

        return Command::SUCCESS;
    }
}
