<?php

declare(strict_types=1);

namespace Elme\Checkout\Plugin;

use Elme\Checkout\Api\OrderDetailsInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * The order details as extension attributes of an order from the repository, for the API and the Pronto order export.
 */
class OrderDetailsExtensionPlugin
{
    public function afterGet(OrderRepositoryInterface $subject, OrderInterface $order): OrderInterface
    {
        return $this->addDetails($order);
    }

    public function afterGetList(
        OrderRepositoryInterface $subject,
        OrderSearchResultInterface $result
    ): OrderSearchResultInterface {
        foreach ($result->getItems() as $order) {
            $this->addDetails($order);
        }

        return $result;
    }

    private function addDetails(OrderInterface $order): OrderInterface
    {
        /** @var Order $order */
        $extension = $order->getExtensionAttributes();
        $extension->setCustomerReference($order->getData(OrderDetailsInterface::CUSTOMER_REFERENCE));
        $extension->setDeliveryInstructions($order->getData(OrderDetailsInterface::DELIVERY_INSTRUCTIONS));
        $order->setExtensionAttributes($extension);

        return $order;
    }
}
