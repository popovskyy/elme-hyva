<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Console\Command;

use Bart\ContentStudio\Model\ImportExport\Processor\Import as ImportProcessor;
use Bart\ContentStudio\Model\OptionSource\ImportMode;
use Magento\Framework\Serialize\Serializer\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI counterpart to the entry Import page - for CI and for copying entries
 * between environments without a browser (plan section 9).
 */
class Import extends Command
{
    /**
     * @param ImportProcessor $importProcessor
     * @param Json $json
     */
    public function __construct(
        private readonly ImportProcessor $importProcessor,
        private readonly Json $json
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('bart:studio:import')
            ->setDescription('Imports Content Studio entries from a JSON file or a directory of JSON files.')
            ->addArgument('path', InputArgument::REQUIRED, 'A .json file or a directory containing .json files')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'update or skip', ImportMode::MODE_UPDATE)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would happen without writing.');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string)$input->getArgument('path');
        $mode = (string)$input->getOption('mode');
        $dryRun = (bool)$input->getOption('dry-run');

        if (!in_array($mode, [ImportMode::MODE_UPDATE, ImportMode::MODE_SKIP], true)) {
            $output->writeln("<error>--mode must be \"update\" or \"skip\".</error>");

            return Command::FAILURE;
        }

        $payloads = $this->readPayloads($path, $output);

        if ($payloads === []) {
            $output->writeln('<error>No JSON files found.</error>');

            return Command::FAILURE;
        }

        $report = $this->importProcessor->importBatch($payloads, $mode, $dryRun);

        return $this->printReport($report, $dryRun, $output);
    }

    /**
     * @param string $path
     * @param OutputInterface $output
     * @return array<int, array<string, mixed>>
     */
    private function readPayloads(string $path, OutputInterface $output): array
    {
        if (is_file($path)) {
            $files = [$path];
        } elseif (is_dir($path)) {
            $files = glob(rtrim($path, '/') . '/*.json') ?: [];
        } else {
            $output->writeln("<error>{$path} does not exist.</error>");

            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $file): ?array => $this->readPayload($file, $output),
            $files
        )));
    }

    /**
     * @param string $file
     * @param OutputInterface $output
     * @return array<string, mixed>|null
     */
    private function readPayload(string $file, OutputInterface $output): ?array
    {
        try {
            $decoded = $this->json->unserialize((string)file_get_contents($file));
        } catch (\InvalidArgumentException $e) {
            $output->writeln("<comment>Skipped {$file}: invalid JSON.</comment>");

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<int, array<string, string>> $report
     * @param bool $dryRun
     * @param OutputInterface $output
     * @return int
     */
    private function printReport(array $report, bool $dryRun, OutputInterface $output): int
    {
        $hasErrors = false;

        foreach ($report as $row) {
            $hasErrors = $hasErrors || $row['status'] === 'error';
            $line = "{$row['identifier']}: {$row['status']}";

            $output->writeln($row['message'] !== '' ? "{$line} ({$row['message']})" : $line);
        }

        if ($hasErrors) {
            $output->writeln('<error>Import failed, nothing was saved.</error>');

            return Command::FAILURE;
        }

        $output->writeln($dryRun ? 'Dry run complete - nothing was saved.' : 'Import complete.');

        return Command::SUCCESS;
    }
}
