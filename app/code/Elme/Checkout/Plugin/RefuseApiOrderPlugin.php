<?php

declare(strict_types=1);

namespace Elme\Checkout\Plugin;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;

/**
 * The place-order validators run in CheckoutManagement, so an order through core's APIs (carts/mine/payment-information,
 * guest-carts as a logged-in customer, GraphQL placeOrder) would skip them, and with them the terms and every later B2B
 * rule. Customers order through the checkout page. Admin and integration tokens stay allowed for order imports.
 */
class RefuseApiOrderPlugin
{
    private const BACK_OFFICE_USERS = [
        UserContextInterface::USER_TYPE_ADMIN,
        UserContextInterface::USER_TYPE_INTEGRATION,
    ];

    public function __construct(
        private readonly UserContextInterface $userContext
    ) {
    }

    /**
     * @param CartManagementInterface $subject
     * @return null
     * @throws LocalizedException
     */
    public function beforePlaceOrder(CartManagementInterface $subject)
    {
        if (!in_array($this->userContext->getUserType(), self::BACK_OFFICE_USERS, true)) {
            throw new LocalizedException(__('Please place your order through the checkout.'));
        }

        return null;
    }
}
