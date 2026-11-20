<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Console\Command;

use Elme\StoreLocator\Model\Import\CsvImporter;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento elme:store-locator:import <file> [--replace]
 */
class ImportLocations extends Command
{
    public function __construct(
        private readonly CsvImporter $importer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('elme:store-locator:import')
            ->setDescription('Import showroom / distribution locations from a CSV')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the CSV file')
            ->addOption('replace', null, InputOption::VALUE_NONE, 'Delete all locations before importing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $count = $this->importer->import(
                (string) $input->getArgument('file'),
                (bool) $input->getOption('replace')
            );
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>%d location(s) imported.</info>', $count));

        return Command::SUCCESS;
    }
}
