<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Elme\Checkout\Api\Data\CheckoutStateInterface;

/**
 * POST elme_checkout/action/shippingaddress - {address_id} or {address: {...}, save_to_book}.
 */
class ShippingAddress extends AbstractAction
{
    protected function run(int $cartId): CheckoutStateInterface
    {
        $payload = $this->getPayload();
        $address = is_array($payload['address'] ?? null) ? $payload['address'] : (int) ($payload['address_id'] ?? 0);

        return $this->checkoutManagement->setShippingAddress($cartId, $address, !empty($payload['save_to_book']));
    }
}
