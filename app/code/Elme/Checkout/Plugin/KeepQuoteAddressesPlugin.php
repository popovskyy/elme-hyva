<?php

declare(strict_types=1);

namespace Elme\Checkout\Plugin;

use Magento\Checkout\Model\Type\Onepage;

/**
 * The core checkout controller calls initCheckout() on every page load, and its assignCustomer() puts the customer's
 * default addresses over the quote's in memory: the address picked earlier would show as the default after a reload,
 * and would be lost at the next quote save. Elme_Checkout manages the quote addresses itself (QuoteAddresses).
 */
class KeepQuoteAddressesPlugin
{
    public function aroundInitCheckout(Onepage $subject, callable $proceed): Onepage
    {
        $quote = $subject->getQuote();
        $shipping = $quote->getShippingAddress();
        $billing = $quote->getBillingAddress();
        $shippingData = $shipping->getData();
        $billingData = $billing->getData();

        $result = $proceed();

        $shipping->setData($shippingData);
        $billing->setData($billingData);

        return $result;
    }
}
