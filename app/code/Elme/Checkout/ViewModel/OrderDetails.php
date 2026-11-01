<?php

declare(strict_types=1);

namespace Elme\Checkout\ViewModel;

use Elme\Checkout\Api\OrderDetailsInterface;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Model\Order;

/**
 * The order details of the order on the page: the order being viewed (admin, My Orders), else the order just placed
 * (success page).
 */
class OrderDetails implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly CheckoutSession $checkoutSession
    ) {
    }

    public function getDeliveryInstructions(): string
    {
        return (string) $this->getOrder()->getData(OrderDetailsInterface::DELIVERY_INSTRUCTIONS);
    }

    private function getOrder(): Order
    {
        return $this->registry->registry('current_order') ?? $this->checkoutSession->getLastRealOrder();
    }
}
