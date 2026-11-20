<?php

declare(strict_types=1);

namespace Elme\QtyRules\Plugin;

use Elme\QtyRules\Model\QtyRules;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Framework\DataObject;

/**
 * Refuses a cart line with a qty below the MOQ or not a multiple of it.
 *
 * Every change of a quote item qty ends in this check (add, update, mini cart, REST, GraphQL, reorder, loading a saved
 * cart), and Magento already turns its answer into an item error. It also leaves admin orders alone: the core validator
 * ignores the answer when the quote is in super mode.
 */
class EnforceQtyRulesPlugin
{
    private const ERROR_CODE = 'elme_qty_rules';

    public function __construct(
        private readonly QtyRules $qtyRules
    ) {
    }

    /**
     * Runs on top of Magento's answer: the customer sees the MOQ first, stock only for a qty that could be ordered.
     *
     * @param StockStateInterface $subject
     * @param DataObject $result
     * @param int $productId
     * @param int|float $itemQty
     * @param int|float $qtyToCheck
     * @param int|float $origQty
     * @param int|null $scopeId
     * @return DataObject
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterCheckQuoteItemQty(
        StockStateInterface $subject,
        DataObject $result,
        $productId,
        $itemQty,
        $qtyToCheck,
        $origQty,
        $scopeId = null
    ): DataObject {
        $message = $this->qtyRules->getRefusalMessage(
            (int) $productId,
            (float) $itemQty,
            $scopeId === null ? null : (int) $scopeId
        );
        if ($message === null) {
            return $result;
        }

        // A string, not a Phrase: core's GraphQL cart update runs str_contains() on it
        return $result->setHasError(true)
            ->setMessage((string) $message)
            ->setQuoteMessage(__('Please correct the quantity for some products.'))
            ->setQuoteMessageIndex('qty')
            ->setErrorCode(self::ERROR_CODE);
    }
}
