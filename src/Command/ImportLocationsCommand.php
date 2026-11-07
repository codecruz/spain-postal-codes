<?php

declare(strict_types=1);

namespace App\Command;

use App\Data\Province;
use App\Entity\Location;
use App\Import\LocationCsvReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:locations:import',
    description: 'Imports the postal code dataset (CSV) into the database, replacing existing rows',
)]
final class ImportLocationsCommand extends Command
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LocationCsvReader $reader,
        #[Autowire(param: 'app.dataset_path')]
        private readonly string $defaultDatasetPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to the CSV file', $this->defaultDatasetPath)
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command loads <comment>data/es_cp.csv</comment> into the database.

                The import runs in a single transaction and replaces all existing rows, so it
                is safe to run it again after updating the dataset:

                  <info>php %command.full_name%</info>

                Use <comment>-v</comment> to list rows whose postal code prefix does not match their province.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $file */
        $file = $input->getOption('file');

        $imported = 0;
        $duplicates = 0;
        /** @var list<string> $mismatches */
        $mismatches = [];

        try {
            $this->entityManager->wrapInTransaction(function (EntityManagerInterface $em) use ($file, &$imported, &$duplicates, &$mismatches): void {
                $em->createQueryBuilder()->delete(Location::class, 'l')->getQuery()->execute();

                $seen = [];
                foreach ($this->reader->read($file) as $line => $row) {
                    $key = $row['provincia']."\0".$row['poblacion']."\0".$row['cod_postal'];
                    if (isset($seen[$key])) {
                        ++$duplicates;
                        continue;
                    }
                    $seen[$key] = true;

                    if (Province::codeFromPostalCode($row['cod_postal']) !== Province::codeFromName($row['provincia'])) {
                        $mismatches[] = \sprintf('line %d: %s (%s) %s', $line, $row['cod_postal'], $row['provincia'], $row['poblacion']);
                    }

                    $em->persist(new Location($row['provincia'], $row['poblacion'], $row['cod_postal']));

                    if (0 === ++$imported % self::BATCH_SIZE) {
                        $em->flush();
                        $em->clear();
                    }
                }

                $em->flush();
                $em->clear();
            });
        } catch (\RuntimeException $e) {
            // \UnexpectedValueException extends \RuntimeException
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ([] !== $mismatches) {
            $io->warning(\sprintf('%d row(s) have a postal code that does not belong to their province (run with -v to list them).', \count($mismatches)));
            if ($io->isVerbose()) {
                $io->listing($mismatches);
            }
        }

        $io->success(\sprintf(
            'Imported %d locations from %s%s.',
            $imported,
            $file,
            $duplicates > 0 ? \sprintf(' (%d duplicate row(s) skipped)', $duplicates) : '',
        ));

        return Command::SUCCESS;
    }
}
