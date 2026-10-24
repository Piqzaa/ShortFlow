<?php

namespace App\Command;

use App\Entity\SeriesRecurrence;
use App\Service\SeriesRecurrences;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:series:recover', description: 'Requeue the current wake-up of active series without replaying past episodes.')]
final class RecoverSeriesRecurrencesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private SeriesRecurrences $recurrences)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->em->getRepository(SeriesRecurrence::class)->findBy(['status' => 'active']);
        foreach ($rows as $row) {
            $this->recurrences->wake($row);
        }
        $output->writeln(count($rows).' current series wake-ups queued. Duplicate deliveries do not create another episode.');

        return Command::SUCCESS;
    }
}
