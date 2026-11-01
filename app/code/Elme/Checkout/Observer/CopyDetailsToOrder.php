<?php

declare(strict_types=1);

namespace Elme\Checkout\Observer;

use Elme\Checkout\Api\OrderDetailsInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * sales_model_service_quote_submit_before: the order takes the details of the quote.
 */
class CopyDetailsToOrder implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        $quote = $observer->getEvent()->getData('quote');

        foreach ([OrderDetailsInterface::CUSTOMER_REFERENCE, OrderDetailsInterface::DELIVERY_INSTRUCTIONS] as $field) {
            $order->setData($field, $quote->getData($field));
        }
    }
}
