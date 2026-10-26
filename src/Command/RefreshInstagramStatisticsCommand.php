<?php

namespace App\Command;

use App\Entity\InstagramConnection;
use App\Entity\InstagramPublication;
use App\Entity\PublicationStatistic;
use App\Service\InstagramApi;
use App\Service\PublicationException;
use App\Service\PublicationStatistics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:instagram:statistics:refresh', description: 'Lit les compteurs d’un Reel existant, sans publier de vidéo.')]
final class RefreshInstagramStatisticsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private PublicationStatistics $statistics, private InstagramApi $api)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('publication', InputArgument::REQUIRED)->addOption('now', null, InputOption::VALUE_NONE, 'Collecter immédiatement le relevé demandé.')->addOption('link', null, InputOption::VALUE_NONE, 'Vérifier aussi le lien fourni par Instagram.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = (string) $input->getArgument('publication');
        $job = ctype_digit($id) ? $this->em->find(InstagramPublication::class, (int) $id) : null;
        if (!$job || 'published' !== $job->getStatus() || !$job->getRemoteId()) {
            $output->writeln('Reel publié introuvable.');

            return Command::FAILURE;
        }
        try {
            $queued = $this->statistics->queue($job->getOwner(), 'instagram', (int) $job->getId());
            $snapshot = $this->em->getRepository(PublicationStatistic::class)->findOneBy(['owner' => $job->getOwner(), 'network' => 'instagram', 'publicationId' => $job->getId()]);
            if ($input->getOption('now') && $snapshot) {
                $this->statistics->refresh((int) $snapshot->getId(), $snapshot->getRequestKey());
                $this->em->refresh($snapshot);
                $output->writeln('État du relevé : '.$snapshot->getStatus());
                if ('unavailable' === $snapshot->getStatus()) {
                    $output->writeln('Vérifie les autorisations du compte avant une nouvelle demande.');

                    return Command::FAILURE;
                }
            } else {
                $output->writeln($queued ? 'Relevé mis en file.' : 'Une demande récente existe déjà.');
            }
            if ($input->getOption('link')) {
                $connection = $this->em->getRepository(InstagramConnection::class)->findOneBy(['owner' => $job->getOwner(), 'instagramId' => $job->getInstagramId()]);
                if ($connection && $connection->getInstagramId() === $job->getInstagramId()) {
                    $output->writeln($this->api->permalink($connection, $job->getRemoteId()));
                }
            }
        } catch (PublicationException $error) {
            $output->writeln($error->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
