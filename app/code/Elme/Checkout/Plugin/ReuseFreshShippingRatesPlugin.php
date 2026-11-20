<?php

declare(strict_types=1);

namespace Elme\Checkout\Plugin;

use Elme\Checkout\Model\Method\ShippingRatesFreshness;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\ShippingAddressManagementInterface;

/**
 * After core assign() forces collect_shipping_rates, clear it when this request already has fresh rates so the
 * SaveHandler collectTotals() that follows does not call the carriers again.
 */
class ReuseFreshShippingRatesPlugin
{
    public function __construct(
        private readonly ShippingRatesFreshness $ratesFreshness,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    /**
     * @param ShippingAddressManagementInterface $subject
     * @param int $result
     * @param int $cartId
     * @return int
     */
    public function afterAssign(
        ShippingAddressManagementInterface $subject,
        $result,
        $cartId
    ) {
        if (!$this->ratesFreshness->isFresh()) {
            return $result;
        }

        // assign() saved the address with collect_shipping_rates=1; clear it in memory and in the row so the
        // SaveHandler collectTotals() that follows, and the next request's collectTotals(), do not ask again.
        $address = $this->cartRepository->getActive((int) $cartId)->getShippingAddress();
        if ($address->getCollectShippingRates()) {
            $address->setCollectShippingRates(false);
            $address->save();
        }

        return $result;
    }
}
