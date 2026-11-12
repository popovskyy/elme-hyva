<?php

declare(strict_types=1);

namespace Elme\Checkout\Model;

use Elme\Checkout\Api\CheckoutManagementInterface;
use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Elme\Checkout\Model\Address\QuoteAddresses;
use Elme\Checkout\Model\Details\QuoteDetails;
use Elme\Checkout\Model\Method\QuotePaymentMethod;
use Elme\Checkout\Model\Method\QuoteShippingMethod;
use Elme\Checkout\Model\Method\ShippingRatesFreshness;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
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
        private readonly PlacedOrderRecovery $placedOrderRecovery,
        private readonly ShippingRatesFreshness $ratesFreshness
    ) {
    }

    public function getState(int $cartId): CheckoutStateInterface
    {
        return $this->buildState($this->cartRepository->getActive($cartId), false);
    }

    public function setShippingAddress(int $cartId, int|array $address, bool $saveToBook = false): CheckoutStateInterface
    {
        $quote = $this->cartRepository->getActive($cartId);
        if (is_array($address)) {
            $this->quoteAddresses->setNewShipping($quote, $address, $saveToBook);
        } else {
            $this->quoteAddresses->selectShipping($quote, $address);
        }

        return $this->buildState($quote, true);
    }

    public function setBillingAddress(int $cartId, int $addressId): CheckoutStateInterface
    {
        $quote = $this->cartRepository->getActive($cartId);
        $this->quoteAddresses->selectBilling($quote, $addressId);

        return $this->buildState($quote, true);
    }

    public function setShippingMethod(int $cartId, string $carrierCode, string $methodCode): CheckoutStateInterface
    {
        $quote = $this->cartRepository->getActive($cartId);
        $this->shippingMethod->select($quote, $carrierCode, $methodCode);

        return $this->buildState($quote, true);
    }

    public function setPaymentMethod(int $cartId, string $method): CheckoutStateInterface
    {
        $quote = $this->cartRepository->getActive($cartId);
        $this->paymentMethod->select($quote, $method);

        return $this->buildState($quote, true);
    }

    public function saveDetails(int $cartId, array $details): CheckoutStateInterface
    {
        $quote = $this->cartRepository->getActive($cartId);
        $this->quoteDetails->save($quote, $details);

        return $this->buildState($quote, true);
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

    /**
     * Builds the state from the quote the action just changed. When $ratesFresh is true the carriers already answered
     * in this request (or rates on the address are still valid); refresh() reuses them. Page load passes false so the
     * carriers are asked once, unless initialize() already collected and marked them fresh.
     */
    private function buildState(Quote $quote, bool $ratesFresh): CheckoutStateInterface
    {
        $this->quoteAddresses->initialize($quote);
        $this->shippingMethod->refresh($quote, $ratesFresh || $this->ratesFreshness->isFresh());
        $this->paymentMethod->refresh($quote);

        return $this->stateBuilder->build($quote);
    }
}
