<?php

namespace App\Command;

use App\Repository\VideoAssetRepository;
use App\Service\VideoLibrary;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:videos:retention', description: 'Examiner la conservation des vidéos importées pour publier.')]
final class VideoRetentionCommand extends Command
{
    public function __construct(private VideoAssetRepository $videos, private VideoLibrary $library, private EntityManagerInterface $em, private ClockInterface $clock)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Enregistrer les échéances et retirer les fichiers arrivés à échéance.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $apply = (bool) $input->getOption('apply');
        $cursor = $examined = $eligible = $removed = 0;
        while ($ids = $this->videos->temporaryIdsAfter($cursor, 100)) {
            foreach ($ids as $id) {
                $cursor = $id;
                ++$examined;
                if (!$apply) {
                    $eligible += (int) (null !== $this->videos->lastSuccessfulPublicationIfUnprotected($id));
                    continue;
                }
                $video = $this->videos->find($id);
                if ($video && $this->library->checkRetention($video, $now)) {
                    ++$removed;
                }
                $this->em->clear();
            }
        }

        $output->writeln($apply
            ? sprintf('%d vidéo(s) temporaire(s) examinée(s), %d fichier(s) retiré(s).', $examined, $removed)
            : sprintf('Simulation sans écriture. %d vidéo(s) examinée(s), %d sans protection après une publication réussie.', $examined, $eligible));

        return Command::SUCCESS;
    }
}
