<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Observer;

use Elme\QuickOrder\Model\CartList;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * The order is placed: the cart is empty now. Runs on sales_model_service_quote_submit_success, so a failed
 * payment or an abandoned checkout keeps the cart.
 */
class ClearCartAfterOrder implements ObserverInterface
{
    public function __construct(
        private readonly CartList $cartList,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        $customerId = $order instanceof OrderInterface ? (int) $order->getCustomerId() : 0;
        if ($customerId <= 0) {
            return;
        }

        try {
            $this->cartList->clear($customerId);
        } catch (\Throwable $e) {
            // The order is placed; a cart left full is fixed by the customer, an exception here would hide the order
            $this->logger->error('Elme_QuickOrder: the cart could not be cleared after the order.', [
                'customer_id' => $customerId,
                'exception' => $e,
            ]);
        }
    }
}
