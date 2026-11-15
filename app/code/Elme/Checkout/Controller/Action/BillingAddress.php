<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Elme\Checkout\Api\Data\CheckoutStateInterface;

/**
 * POST elme_checkout/action/billingaddress - {address_id}.
 */
class BillingAddress extends AbstractAction
{
    protected function run(int $cartId): CheckoutStateInterface
    {
        return $this->checkoutManagement->setBillingAddress($cartId, (int) ($this->getPayload()['address_id'] ?? 0));
    }
}
