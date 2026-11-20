<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Ui\DataProvider;

use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

/**
 * Data provider of the Page Builder entry picker: the stock listing provider,
 * narrowed to entries of `standalone="true"` types - the same set the widget
 * chooser offers. A nested-only block type has no identifier to place.
 */
class PageBuilderSelectDataProvider extends DataProvider
{
    private bool $typeFilterAdded = false;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param SchemaConfig $schemaConfig
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        private readonly SchemaConfig $schemaConfig,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

    /**
     * @inheritDoc
     */
    public function getSearchCriteria()
    {
        if (!$this->typeFilterAdded) {
            $this->typeFilterAdded = true;
            $codes = array_keys($this->schemaConfig->getStandaloneTypes());
            // An empty registry must yield an empty grid, not every entry: an
            // `in` filter with no values is a condition MySQL drops entirely.
            $this->addFilter(
                $this->filterBuilder->setField('type')
                    ->setConditionType('in')
                    ->setValue($codes ?: [''])
                    ->create()
            );
        }

        return parent::getSearchCriteria();
    }
}
