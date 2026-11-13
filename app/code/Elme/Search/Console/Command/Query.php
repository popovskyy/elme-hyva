<?php

declare(strict_types=1);

namespace Elme\Search\Console\Command;

use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Api\ProductSearchInterface;
use Elme\Search\Model\SkuQuery;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * QA / debugging: ask ProductSearchInterface directly and print the answer as JSON.
 *
 *   bin/magento elme:search:query "vase"
 *   bin/magento elme:search:query --category=13 --filter=colour_group=Natural --range=width=50-150 --sort=price
 *   bin/magento elme:search:query "VG.228" --suggest
 */
class Query extends Command
{
    public function __construct(
        private readonly ProductSearchInterface $productSearch,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('elme:search:query')
            ->setDescription('Run a ProductSearchInterface search or suggest and print the result as JSON')
            ->addArgument('query', InputArgument::OPTIONAL, 'Search text')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Category id')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'code=value1,value2')
            ->addOption('range', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'code=min-max')
            ->addOption('sort', 's', InputOption::VALUE_REQUIRED, implode(' | ', SearchRequest::SORTS))
            ->addOption('dir', 'd', InputOption::VALUE_REQUIRED, 'asc | desc')
            ->addOption('page', 'p', InputOption::VALUE_REQUIRED, 'Page', '1')
            ->addOption('page-size', null, InputOption::VALUE_REQUIRED, 'Page size', '24')
            ->addOption('store', null, InputOption::VALUE_REQUIRED, 'Store view id', '1')
            ->addOption('no-collapse', null, InputOption::VALUE_NONE, 'Do not collapse families')
            ->addOption('suggest', null, InputOption::VALUE_NONE, 'Autocomplete instead of search');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
        } catch (\Throwable) {
            // area already set
        }
        $query = $input->getArgument('query');
        $storeId = (int)$input->getOption('store');

        try {
            if ($input->getOption('suggest')) {
                $result = $this->productSearch->suggest((string)$query, (int)$input->getOption('page-size'), $storeId);
            } else {
                $filters = [];
                foreach ((array)$input->getOption('filter') as $filter) {
                    [$code, $values] = array_pad(explode('=', (string)$filter, 2), 2, '');
                    $filters[$code] = array_values(array_filter(array_map('trim', explode(',', $values))));
                }
                $ranges = [];
                foreach ((array)$input->getOption('range') as $range) {
                    [$code, $bounds] = array_pad(explode('=', (string)$range, 2), 2, '');
                    [$min, $max] = array_pad(explode('-', $bounds, 2), 2, '');
                    $ranges[$code] = [
                        'min' => is_numeric($min) ? (float)$min : null,
                        'max' => is_numeric($max) ? (float)$max : null,
                    ];
                }
                $category = $input->getOption('category');
                $sort = $input->getOption('sort')
                    ?: ($query ? SearchRequest::SORT_RELEVANCE : SearchRequest::SORT_POSITION);
                $result = $this->productSearch->search(new SearchRequest(
                    $storeId,
                    $query !== null ? (string)$query : null,
                    $category !== null ? (int)$category : null,
                    $filters,
                    $ranges,
                    (string)$sort,
                    $input->getOption('dir'),
                    (int)$input->getOption('page'),
                    (int)$input->getOption('page-size'),
                    !$input->getOption('no-collapse') && !SkuQuery::looksLikeSku($query) // as the storefront
                ));
            }
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
        $output->writeln((string)json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return Command::SUCCESS;
    }
}
