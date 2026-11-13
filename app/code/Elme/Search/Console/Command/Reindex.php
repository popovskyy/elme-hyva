<?php

declare(strict_types=1);

namespace Elme\Search\Console\Command;

use Elme\Search\Model\Indexer\ProductIndexer;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Indexer\IndexerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento elme:search:reindex [--store=1]
 */
class Reindex extends Command
{
    public function __construct(
        private readonly IndexerRegistry $indexerRegistry,
        private readonly ProductIndexer $productIndexer,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('elme:search:reindex')
            ->setDescription('Rebuild the Elme product search index elme_products_[store] (all stores by default)')
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Only this store view id');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
        } catch (\Throwable) {
            // area already set
        }
        $start = microtime(true);
        try {
            $store = $input->getOption('store');
            if ($store !== null) {
                $count = $this->productIndexer->rebuildStore((int)$store);
                $output->writeln(sprintf('<info>Store %d: %d products indexed.</info>', (int)$store, $count));
            } else {
                foreach ($this->productIndexer->getStoreIds() as $storeId) {
                    $count = $this->productIndexer->rebuildStore($storeId);
                    $output->writeln(sprintf('<info>Store %d: %d products indexed.</info>', $storeId, $count));
                }
                $indexer = $this->indexerRegistry->get(ProductIndexer::INDEXER_ID);
                $indexer->getState()->setStatus(\Magento\Framework\Indexer\StateInterface::STATUS_VALID)->save();
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln(sprintf('Done in %.1fs.', microtime(true) - $start));
        return Command::SUCCESS;
    }
}
