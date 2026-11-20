<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Console\Command;

use Bart\ContentStudio\Model\ImportExport\ExportFileNamer;
use Bart\ContentStudio\Model\ImportExport\Processor\Export as ExportProcessor;
use Bart\ContentStudio\Model\ResourceModel\Entry\CollectionFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI counterpart to the grid's Export / Export All actions - for CI and for
 * copying entries between environments without a browser (plan section 9).
 */
class Export extends Command
{
    /**
     * @param ExportProcessor $exportProcessor
     * @param CollectionFactory $collectionFactory
     * @param ExportFileNamer $fileNamer
     * @param Json $json
     */
    public function __construct(
        private readonly ExportProcessor $exportProcessor,
        private readonly CollectionFactory $collectionFactory,
        private readonly ExportFileNamer $fileNamer,
        private readonly Json $json
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('bart:studio:export')
            ->setDescription('Exports Content Studio entries as JSON files.')
            ->addArgument('identifiers', InputArgument::IS_ARRAY, 'Identifiers to export')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Export every entry')
            ->addOption('out', null, InputOption::VALUE_REQUIRED, 'Output directory', getcwd());
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identifiers = (array)$input->getArgument('identifiers');
        $exportAll = (bool)$input->getOption('all');

        if (!$exportAll && $identifiers === []) {
            $output->writeln('<error>Pass one or more identifiers, or use --all.</error>');

            return Command::FAILURE;
        }

        $outDir = rtrim((string)$input->getOption('out'), '/');

        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }

        $entries = $exportAll
            ? $this->collectionFactory->create()->getItems()
            : $this->matchIdentifiers($identifiers, $output);
        $fileNames = $this->fileNamer->nameFiles($entries);

        foreach ($fileNames as $entryId => $fileName) {
            $payload = $this->exportProcessor->getExportData($entryId);
            $path = $outDir . '/' . $fileName . '.json';

            file_put_contents($path, $this->json->serialize($payload));
            $output->writeln("Wrote {$path}");
        }

        $output->writeln('Exported ' . count($fileNames) . " entr(y/ies) to {$outDir}");

        return Command::SUCCESS;
    }

    /**
     * @param string[] $identifiers
     * @param OutputInterface $output
     * @return array<int, \Bart\ContentStudio\Api\Data\EntryInterface>
     */
    private function matchIdentifiers(array $identifiers, OutputInterface $output): array
    {
        $entries = [];

        foreach ($identifiers as $identifier) {
            $collection = $this->collectionFactory->create();
            $collection->addIdentifierFilter($identifier);
            $matches = $collection->getItems();

            if ($matches === []) {
                $output->writeln("<comment>Skipped {$identifier}: not found.</comment>");

                continue;
            }

            array_push($entries, ...array_values($matches));
        }

        return $entries;
    }
}
