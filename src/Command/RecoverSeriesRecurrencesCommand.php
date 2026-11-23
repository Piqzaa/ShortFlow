<?php

namespace App\Command;

use App\Entity\SeriesRecurrence;
use App\Service\SeriesRecurrences;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:series:recover', description: 'Retire active scenario recurrences and recover interrupted writing.')]
final class RecoverSeriesRecurrencesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em, private SeriesRecurrences $recurrences)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $interrupted = $this->recurrences->recoverInterrupted();
        $rows = $this->em->getRepository(SeriesRecurrence::class)->findBy(['status' => 'active']);
        $retired = 0;
        foreach ($rows as $row) {
            $retired += (int) $this->recurrences->retire($row);
        }
        $output->writeln($retired.' active scenario recurrence(s) retired.');
        $output->writeln($interrupted.' interrupted writing occurrence(s) marked for review without generating a new video.');

        return Command::SUCCESS;
    }
}
