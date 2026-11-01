<?php

declare(strict_types=1);

namespace Elme\Checkout\Api;

use Elme\Checkout\Api\Data\CheckoutStateInterface;
use Magento\Framework\Exception\LocalizedException;

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
     * @param int|mixed[] $address Id of a saved address, or the fields of the checkout address form
     * @param bool $saveToBook Only for a new address
     * @return \Elme\Checkout\Api\Data\CheckoutStateInterface
     * @throws LocalizedException
     */
    public function setShippingAddress(int $cartId, int|array $address, bool $saveToBook = false): CheckoutStateInterface;

    /**
     * @param int $cartId
     * @param int $addressId Id of a saved address
     * @return \Elme\Checkout\Api\Data\CheckoutStateInterface
     * @throws LocalizedException
     */
    public function setBillingAddress(int $cartId, int $addressId): CheckoutStateInterface;

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
     * Runs the place-order validators, then places the order. A second call for the same cart is refused.
     *
     * @param int $cartId
     * @param mixed[] $input What the customer confirms with the order: "agreements" holds the accepted agreement ids
     * @return int Order id
     * @throws LocalizedException
     */
    public function placeOrder(int $cartId, array $input = []): int;
}
