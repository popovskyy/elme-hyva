<?php

declare(strict_types=1);

namespace Elme\Checkout\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Checkout\Model\Session\SuccessValidator;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;

/**
 * When the order is in the checkout session but the cart is already gone (answer lost, second click, reload).
 */
class PlacedOrderRecovery
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly CustomerSession $customerSession,
        private readonly SuccessValidator $successValidator,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Success page URL when this session holds a last order of the logged-in customer that the success page can show.
     */
    public function getSuccessUrl(): ?string
    {
        return $this->getLastOrderForCustomer() !== null
            ? $this->urlBuilder->getUrl('checkout/onepage/success')
            : null;
    }

    /**
     * Order id when the session last order was placed from this cart by this customer.
     */
    public function getOrderIdForCart(int $cartId): ?int
    {
        $order = $this->getLastOrderForCustomer();
        if ($order === null || (int) $order->getQuoteId() !== $cartId) {
            return null;
        }

        return (int) $order->getId();
    }

    private function getLastOrderForCustomer(): ?Order
    {
        if (!$this->customerSession->isLoggedIn() || !$this->successValidator->isValid()) {
            return null;
        }

        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order->getId()) {
            return null;
        }

        if ((int) $order->getCustomerId() !== (int) $this->customerSession->getCustomerId()) {
            return null;
        }

        return $order;
    }
}
