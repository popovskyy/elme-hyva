<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Ui\Component\Listing\Filters;

use Magento\Ui\Component\Filters\Type\Search;

/**
 * The grid's search box, reusing Magento's stock `search.js` widget (the
 * text-input-plus-submit control) but with its own condition type so it does
 * not go through {@see \Magento\Framework\View\Element\UiComponent\DataProvider\FulltextFilter},
 * which requires a real MySQL FULLTEXT index - this table has none, and one
 * word ("42") would fall below MySQL's default 3-character token minimum
 * anyway, which is exactly the case this search has to cover (searching by
 * entry ID).
 *
 * {@see \Bart\ContentStudio\Model\ResourceModel\Entry\Grid\Filter\KeywordFilterApplier}
 * does the actual matching, registered against condition type
 * {@see self::CONDITION_TYPE} in di.xml.
 */
class KeywordSearch extends Search
{
    private const CONDITION_TYPE = 'studio_keyword';

    /**
     * @inheritDoc
     */
    protected function applyFilter(): void
    {
        $value = $this->getContext()->getRequestParam('search');

        if ((string)$value === '') {
            return;
        }

        $filter = $this->filterBuilder->setConditionType(self::CONDITION_TYPE)
            ->setField($this->getName())
            ->setValue($value)
            ->create();

        $this->getContext()->getDataProvider()->addFilter($filter);
    }
}
