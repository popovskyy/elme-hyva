<?php

declare(strict_types=1);

namespace Elme\QuickOrder\CustomerData;

use Elme\QuickOrder\Model\CartList;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Customer section quick_order: what the header needs, read from cart_list alone (one SELECT, no products).
 * summary_count is the sum of the qty, as the old site's badge; items_count the number of rows.
 */
class QuickOrder implements SectionSourceInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CartList $cartList
    ) {
    }

    public function getSectionData(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return ['summary_count' => 0, 'items_count' => 0];
        }

        $rows = $this->cartList->get((int) $this->customerSession->getCustomerId());

        return ['summary_count' => array_sum($rows), 'items_count' => count($rows)];
    }
}
