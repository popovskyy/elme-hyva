<?php

declare(strict_types=1);

namespace Elme\Checkout\Api;

use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;

/**
 * The checkout service: the server owns the state and every change returns the full state.
 *
 * The caller decides whose cart this is (frontend actions pass the cart of the session), so the same service
 * can go into webapi.xml for POS or Quick Order later.
 */
interface CheckoutManagementInterface
{
    /**
     * @param int $cartId
     * @return \Elme\Checkout\Api\Data\CheckoutStateInterface
     * @throws LocalizedException
     */
    public function getState(int $cartId): CheckoutStateInterface;

    /**
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\AddressInterface $address
     * @return \Elme\Checkout\Api\Data\CheckoutStateInterface
     * @throws LocalizedException
     */
    public function setShippingAddress(int $cartId, AddressInterface $address): CheckoutStateInterface;

    /**
     * @param int $cartId
     * @param \Magento\Quote\Api\Data\AddressInterface $address
     * @return \Elme\Checkout\Api\Data\CheckoutStateInterface
     * @throws LocalizedException
     */
    public function setBillingAddress(int $cartId, AddressInterface $address): CheckoutStateInterface;

    /**
     * @param int $cartId
     * @param string $carrierCode
     * @param string $methodCode
     * @return \Elme\Checkout\Api\Data\CheckoutStateInterface
     * @throws LocalizedException
     */
    public function setShippingMethod(int $cartId, string $carrierCode, string $methodCode): CheckoutStateInterface;

    /**
     * @param int $cartId
     * @param string $method
     * @return \Elme\Checkout\Api\Data\CheckoutStateInterface
     * @throws LocalizedException
     */
    public function setPaymentMethod(int $cartId, string $method): CheckoutStateInterface;

    /**
     * @param int $cartId
     * @param string[] $details
     * @return \Elme\Checkout\Api\Data\CheckoutStateInterface
     * @throws LocalizedException
     */
    public function saveDetails(int $cartId, array $details): CheckoutStateInterface;

    /**
     * Runs the place-order validators, then places the order.
     *
     * @param int $cartId
     * @return int Order id
     * @throws LocalizedException
     */
    public function placeOrder(int $cartId): int;
}
