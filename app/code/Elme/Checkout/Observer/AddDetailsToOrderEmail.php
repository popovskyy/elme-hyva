<?php

declare(strict_types=1);

namespace Elme\Checkout\Observer;

use Elme\Checkout\Api\OrderDetailsInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * email_order_set_template_vars_before: the order details as variables of the order email. Email templates in strict
 * mode cannot read a custom column from $order.
 */
class AddDetailsToOrderEmail implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $transport = $observer->getData('transportObject');
        $order = $transport->getData('order');

        foreach ([OrderDetailsInterface::CUSTOMER_REFERENCE, OrderDetailsInterface::DELIVERY_INSTRUCTIONS] as $field) {
            $transport->setData($field, (string) $order->getData($field));
        }
    }
}
