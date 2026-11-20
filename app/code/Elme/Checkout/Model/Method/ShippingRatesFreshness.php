<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Method;

/**
 * Request-scoped marker: carriers already answered for this quote in this request, so a later cart save must not ask
 * them again. Core's quote SaveHandler runs ShippingAddressManagement::assign(), which sets collect_shipping_rates and
 * would trigger a second collectTotals() rate request without this.
 */
class ShippingRatesFreshness
{
    private bool $fresh = false;

    public function mark(): void
    {
        $this->fresh = true;
    }

    public function isFresh(): bool
    {
        return $this->fresh;
    }
}
