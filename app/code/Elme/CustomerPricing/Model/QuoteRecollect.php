<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Flags active customer carts for a totals recollect (quote.trigger_recollect) after the Pronto prices behind them
 * change. Magento's cart page shows the stored totals and only recollects a flagged quote when it loads it
 * (Quote::_afterLoad), and the recollect is what reprices the lines (Observer\ApplyCustomerPrices).
 *
 * Call it after every write to the price mappings or to a customer's pronto_customer_id: the CSV loader, the customer
 * save observer and the Pronto feed.
 */
class QuoteRecollect
{
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Carts holding one of these SKUs (a configurable line carries the SKU of the simple product chosen).
     *
     * @param string[] $skus
     * @return int Carts flagged
     */
    public function bySkus(array $skus): int
    {
        $skus = array_values(array_unique(array_filter(array_map('strval', $skus), 'strlen')));
        if (!$skus) {
            return 0;
        }
        $items = $this->resource->getConnection()->select()
            ->distinct()
            ->from($this->resource->getTableName('quote_item'), ['quote_id'])
            ->where('sku IN (?)', $skus);

        return $this->flag(['entity_id IN ?' => $items]);
    }

    /**
     * Carts of these customers.
     *
     * @param int[] $customerIds
     * @return int Carts flagged
     */
    public function byCustomerIds(array $customerIds): int
    {
        $customerIds = array_values(array_unique(array_filter(array_map('intval', $customerIds))));

        return $customerIds ? $this->flag(['customer_id IN (?)' => $customerIds]) : 0;
    }

    /**
     * Every customer cart, for a change whose SKUs or customers are not known (an item group discount, a customer's
     * marketing flag or region).
     *
     * @return int Carts flagged
     */
    public function all(): int
    {
        return $this->flag([]);
    }

    /**
     * @param array<string, mixed> $where
     */
    private function flag(array $where): int
    {
        return $this->resource->getConnection()->update(
            $this->resource->getTableName('quote'),
            // Keep updated_at: a reprice is not customer activity (abandoned cart and cleanup cron read it).
            ['trigger_recollect' => 1, 'updated_at' => new \Zend_Db_Expr('updated_at')],
            [
                'is_active = ?' => 1,
                'customer_id IS NOT NULL',
                'items_count > ?' => 0,
                'trigger_recollect = ?' => 0,
            ] + $where
        );
    }
}
