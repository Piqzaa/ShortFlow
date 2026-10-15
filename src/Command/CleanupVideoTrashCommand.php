<?php

namespace App\Command;

use App\Repository\VideoAssetRepository;
use App\Service\PrivateVideoStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:videos:cleanup-trash', description: 'Purger les fichiers déjà supprimés en base, en attente de nettoyage depuis plus de 24 h.')]
final class CleanupVideoTrashCommand extends Command
{
    public function __construct(private PrivateVideoStorage $storage, private VideoAssetRepository $videos)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(sprintf('%d fichier(s) de suppression nettoyé(s).', $this->storage->cleanupTrash($this->videos)));

        return Command::SUCCESS;
    }
}
