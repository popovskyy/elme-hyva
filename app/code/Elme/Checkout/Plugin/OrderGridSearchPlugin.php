<?php

declare(strict_types=1);

namespace Elme\Checkout\Plugin;

use Closure;
use Elme\Checkout\Api\OrderDetailsInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\View\Element\UiComponent\DataProvider\FulltextFilter;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection as OrderGridCollection;

/**
 * The keyword search of the order grid also looks in the customer reference.
 *
 * Takes the place of core's Sales FulltextFilterPlugin, whose list of columns is fixed: a LIKE on names and email, and
 * for a term of only digits just the exact order number. A PO number is often only digits, so that case gets the
 * reference too.
 */
class OrderGridSearchPlugin
{
    private const TEXT_FIELDS = ['increment_id', 'billing_name', 'shipping_name', 'customer_email'];

    public function aroundApply(
        FulltextFilter $subject,
        Closure $proceed,
        Collection $collection,
        Filter $filter
    ): void {
        if (!$collection instanceof OrderGridCollection) {
            $proceed($collection, $filter);
            return;
        }

        $term = ltrim(trim(trim((string) $filter->getValue()), '{}'), " \t#");
        if ($term === '') {
            return;
        }

        $like = ['like' => '%' . addcslashes($term, '%_') . '%'];
        if (ctype_digit($term)) {
            $fields = ['increment_id', OrderDetailsInterface::CUSTOMER_REFERENCE];
            $conditions = [['eq' => $term], $like];
        } else {
            $fields = [...self::TEXT_FIELDS, OrderDetailsInterface::CUSTOMER_REFERENCE];
            $conditions = array_fill(0, count($fields), $like);
        }

        $collection->addFieldToFilter($fields, $conditions);
    }
}
