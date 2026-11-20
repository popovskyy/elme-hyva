<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Plugin;

use Elme\LoginOnly\Model\LoginState;
use Magento\Catalog\Block\Product\ProductList\Toolbar;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\FilterList;

/**
 * Guests see no prices, so the price filter (its ranges are prices) and "sort by price" are removed for them.
 */
class HidePriceSortingAndFilterPlugin
{
    public function __construct(
        private readonly LoginState $loginState
    ) {
    }

    /**
     * @param AbstractFilter[] $result
     * @return AbstractFilter[]
     */
    public function afterGetFilters(FilterList $subject, array $result): array
    {
        if ($this->loginState->isLoggedIn()) {
            return $result;
        }

        return array_values(array_filter(
            $result,
            static fn ($filter) => $filter->getRequestVar() !== 'price'
        ));
    }

    public function afterGetAvailableOrders(Toolbar $subject, $result)
    {
        if (is_array($result) && $this->loginState->isGuest()) {
            unset($result['price']);
        }

        return $result;
    }
}
