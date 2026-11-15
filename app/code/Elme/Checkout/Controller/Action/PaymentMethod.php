<?php

declare(strict_types=1);

namespace Elme\Checkout\Controller\Action;

use Elme\Checkout\Api\Data\CheckoutStateInterface;

/**
 * POST elme_checkout/action/paymentmethod - {method}.
 */
class PaymentMethod extends AbstractAction
{
    protected function run(int $cartId): CheckoutStateInterface
    {
        return $this->checkoutManagement->setPaymentMethod($cartId, (string) ($this->getPayload()['method'] ?? ''));
    }
}
