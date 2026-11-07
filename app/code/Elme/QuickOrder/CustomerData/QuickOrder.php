<?php

declare(strict_types=1);

namespace Elme\QuickOrder\CustomerData;

use Elme\QuickOrder\Model\CartList;
use Elme\QuickOrder\Model\SavedLists;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session as CustomerSession;

/**
 * Customer section quick_order: what the header needs.
 * summary_count / items_count = cart_list; lists_item_count = named shopping lists (wishlist replacement).
 */
class QuickOrder implements SectionSourceInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CartList $cartList,
        private readonly SavedLists $savedLists
    ) {
    }

    public function getSectionData(): array
    {
        if (!$this->customerSession->isLoggedIn()) {
            return ['summary_count' => 0, 'items_count' => 0, 'lists_item_count' => 0];
        }

        $customerId = (int) $this->customerSession->getCustomerId();
        $rows = $this->cartList->get($customerId);

        return [
            'summary_count' => array_sum($rows),
            'items_count' => count($rows),
            'lists_item_count' => $this->savedLists->getTotalItemCount($customerId),
        ];
    }
}
