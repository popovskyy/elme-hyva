<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Elme\Checkout\Api\Data\CheckoutStateInterface;

/**
 * POST elme_checkout/action/shippingmethod - {carrier_code, method_code}.
 */
class ShippingMethod extends AbstractAction
{
    protected function run(int $cartId): CheckoutStateInterface
    {
        $payload = $this->getPayload();

        return $this->checkoutManagement->setShippingMethod(
            $cartId,
            (string) ($payload['carrier_code'] ?? ''),
            (string) ($payload['method_code'] ?? '')
        );
    }
}
