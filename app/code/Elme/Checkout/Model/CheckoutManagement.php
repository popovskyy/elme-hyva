<?php

declare(strict_types=1);

namespace Elme\Checkout\Model;

use Elme\Checkout\Api\CheckoutManagementInterface;
use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Elme\Checkout\Model\Address\QuoteAddresses;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * The methods that throw are filled in by BE-03 (methods), BE-04 (place order), BE-05 (details).
 */
class CheckoutManagement implements CheckoutManagementInterface
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StateBuilder $stateBuilder,
        private readonly PlaceOrderValidatorPool $placeOrderValidators,
        private readonly QuoteAddresses $quoteAddresses
    ) {
    }

    public function getState(int $cartId): CheckoutStateInterface
    {
        $quote = $this->cartRepository->getActive($cartId);
        $this->quoteAddresses->initialize($quote);

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
        throw $this->notImplemented(__FUNCTION__);
    }

    public function setPaymentMethod(int $cartId, string $method): CheckoutStateInterface
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function saveDetails(int $cartId, array $details): CheckoutStateInterface
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function placeOrder(int $cartId): int
    {
        $this->placeOrderValidators->validate($this->cartRepository->getActive($cartId));

        throw $this->notImplemented(__FUNCTION__);
    }

    private function notImplemented(string $method): LocalizedException
    {
        return new LocalizedException(__('Checkout action "%1" is not available yet.', $method));
    }
}
