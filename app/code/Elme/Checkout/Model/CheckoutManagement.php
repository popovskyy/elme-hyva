<?php

declare(strict_types=1);

namespace Elme\Checkout\Model;

use Elme\Checkout\Api\CheckoutManagementInterface;
use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * The methods that throw are filled in by BE-02 (addresses), BE-03 (methods), BE-04 (place order), BE-05 (details).
 */
class CheckoutManagement implements CheckoutManagementInterface
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StateBuilder $stateBuilder,
        private readonly PlaceOrderValidatorPool $placeOrderValidators
    ) {
    }

    public function getState(int $cartId): CheckoutStateInterface
    {
        return $this->stateBuilder->build($this->cartRepository->getActive($cartId));
    }

    public function setShippingAddress(int $cartId, AddressInterface $address): CheckoutStateInterface
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function setBillingAddress(int $cartId, AddressInterface $address): CheckoutStateInterface
    {
        throw $this->notImplemented(__FUNCTION__);
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
