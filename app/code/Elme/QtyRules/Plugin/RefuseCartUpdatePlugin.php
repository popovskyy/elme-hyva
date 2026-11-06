<?php

declare(strict_types=1);

namespace Elme\QtyRules\Plugin;

use Elme\QtyRules\Model\QtyRules;
use Magento\Checkout\Model\Cart;
use Magento\Framework\Message\ManagerInterface;

/**
 * The cart page form (checkout/cart/updatePost) is the one path the stock check cannot refuse by itself. Core first
 * rounds the qty the customer typed ("Quantity was recalculated"), and when the update fails it saves the cart anyway
 * without a message, counting on a JavaScript check to have run first. So a refused line leaves here, with the message,
 * before core touches it. Core calls suggestItemsQty() only from that controller.
 */
class RefuseCartUpdatePlugin
{
    public function __construct(
        private readonly QtyRules $qtyRules,
        private readonly ManagerInterface $messageManager
    ) {
    }

    /**
     * @param Cart $subject
     * @param array $result
     * @param array $data
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSuggestItemsQty(Cart $subject, array $result, $data): array
    {
        $messages = [];
        foreach ($data as $itemId => $line) {
            $item = $subject->getQuote()->getItemById($itemId);
            if (!$item || !empty($line['remove'])) {
                continue;
            }

            $message = $this->qtyRules->getRefusalForQuoteItem($item, (float) ($line['qty'] ?? 0));
            if ($message !== null) {
                $messages[] = (string) $message;
                unset($data[$itemId]);
            }
        }

        foreach (array_unique($messages) as $message) {
            $this->messageManager->addErrorMessage($message);
        }

        return $data;
    }
}
