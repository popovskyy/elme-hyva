<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Console\Command;

use Elme\CustomerPricing\Model\Import\CsvLoader;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento elme:customer-pricing:import <type> <file> [--replace]
 */
class ImportCsv extends Command
{
    public function __construct(
        private readonly CsvLoader $loader
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('elme:customer-pricing:import')
            ->setDescription('Load Pronto price mappings (test data) from a CSV whose header names the columns')
            ->addArgument(
                'type',
                InputArgument::REQUIRED,
                'customer, price-list, region-price, sku-group, cust-mkt or customer-link'
            )
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the CSV file')
            ->addOption('replace', null, InputOption::VALUE_NONE, 'Empty the table before loading');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $count = $this->loader->load(
                (string) $input->getArgument('type'),
                (string) $input->getArgument('file'),
                (bool) $input->getOption('replace')
            );
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>%d rows loaded.</info>', $count));

        return Command::SUCCESS;
    }
}
