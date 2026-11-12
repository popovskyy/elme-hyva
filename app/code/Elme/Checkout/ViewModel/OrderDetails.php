<?php

declare(strict_types=1);

namespace Elme\Checkout\ViewModel;

use Elme\Checkout\Api\OrderDetailsInterface;
use Elme\Checkout\Model\Address\AddressFormatter;
use Magento\Checkout\Model\Session as CheckoutSession;
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
        private readonly CheckoutSession $checkoutSession,
        private readonly AddressFormatter $addressFormatter
    ) {
    }

    public function getDeliveryInstructions(): string
    {
        return (string) ($this->getOrder()?->getData(OrderDetailsInterface::DELIVERY_INSTRUCTIONS) ?? '');
    }

    /**
     * Structured delivery address for the success page, or null when the order has none.
     *
     * @return array{name: string, company: string, street: string, locality: string, telephone: string, line: string}|null
     */
    public function getShippingAddress(): ?array
    {
        $address = $this->getOrder()?->getShippingAddress();
        if ($address === null) {
            return null;
        }

        $formatted = $this->addressFormatter->fromOrderAddress($address);
        if ($formatted['line'] === '') {
            return null;
        }

        return $formatted;
    }

    private function getOrder(): ?Order
    {
        $current = $this->registry->registry('current_order');
        if ($current instanceof Order && $current->getId()) {
            return $current;
        }

        $last = $this->checkoutSession->getLastRealOrder();
        return $last instanceof Order && $last->getId() ? $last : null;
    }
}
