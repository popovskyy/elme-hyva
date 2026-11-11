<?php

declare(strict_types=1);

namespace Elme\Checkout\Model;

use Elme\Checkout\Api\CheckoutManagementInterface;
use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Elme\Checkout\Model\Address\QuoteAddresses;
use Elme\Checkout\Model\Details\QuoteDetails;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;

class CheckoutManagement implements CheckoutManagementInterface
{
    private const PLACE_ORDER_LOCK = 'elme_checkout_place_order_';

    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StateBuilder $stateBuilder,
        private readonly PlaceOrderValidatorPool $placeOrderValidators,
        private readonly QuoteAddresses $quoteAddresses,
        private readonly QuoteShippingMethod $shippingMethod,
        private readonly QuotePaymentMethod $paymentMethod,
        private readonly CartManagementInterface $cartManagement,
        private readonly LockManagerInterface $lockManager,
        private readonly QuoteFactory $quoteFactory,
        private readonly QuoteDetails $quoteDetails,
        private readonly PlacedOrderRecovery $placedOrderRecovery
    ) {
    }

    public function getState(int $cartId): CheckoutStateInterface
    {
        $quote = $this->cartRepository->getActive($cartId);
        $this->quoteAddresses->initialize($quote);
        $this->shippingMethod->refresh($quote);
        $this->paymentMethod->refresh($quote);

        return $this->stateBuilder->build($quote);
    }

    public function setShippingAddress(int $cartId, int|array $address, bool $saveToBook = false): CheckoutStateInterface
    {
        $quote = $this->cartRepository->getActive($cartId);
        if (is_array($address)) {
            $this->quoteAddresses->setNewShipping($quote, $address, $saveToBook);
        } else {
            $this->quoteAddresses->selectShipping($quote, $address);
        }

        return $this->getState($cartId);
    }

    public function setBillingAddress(int $cartId, int $addressId): CheckoutStateInterface
    {
        $this->quoteAddresses->selectBilling($this->cartRepository->getActive($cartId), $addressId);

        return $this->getState($cartId);
    }

    public function setShippingMethod(int $cartId, string $carrierCode, string $methodCode): CheckoutStateInterface
    {
        $this->shippingMethod->select($this->cartRepository->getActive($cartId), $carrierCode, $methodCode);

        return $this->getState($cartId);
    }

    public function setPaymentMethod(int $cartId, string $method): CheckoutStateInterface
    {
        $this->paymentMethod->select($this->cartRepository->getActive($cartId), $method);

        return $this->getState($cartId);
    }

    public function saveDetails(int $cartId, array $details): CheckoutStateInterface
    {
        $this->quoteDetails->save($this->cartRepository->getActive($cartId), $details);

        return $this->getState($cartId);
    }

    /**
     * Core has a lock of its own around its placeOrder(), but the validators run before that and can save the quote: a
     * save from a request that loaded the quote before the other request placed it would make the cart active again.
     * Refusals are plain LocalizedExceptions: the action must not touch the quote while the other request runs.
     */
    public function placeOrder(int $cartId, array $input = []): int
    {
        $lockName = self::PLACE_ORDER_LOCK . $cartId;
        if (!$this->lockManager->lock($lockName, 0)) {
            throw new LocalizedException(__('Your order is already being placed. Please wait a moment.'));
        }

        try {
            // From the database: the repository still has the quote this request loaded before it got the lock
            $current = $this->quoteFactory->create();
            $current->setSharedStoreIds(['*']);
            $current->loadActive($cartId);
            if (!$current->getId()) {
                $orderId = $this->placedOrderRecovery->getOrderIdForCart($cartId);
                if ($orderId !== null) {
                    return $orderId;
                }
                throw new LocalizedException(__('This order has already been placed.'));
            }

            $this->placeOrderValidators->validate($this->cartRepository->getActive($cartId), $input);

            // Core also sets the checkout session's last-order data here, which the success page needs
            return (int) $this->cartManagement->placeOrder($cartId);
        } finally {
            $this->lockManager->unlock($lockName);
        }
    }
}
