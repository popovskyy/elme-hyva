<?php

declare(strict_types=1);

namespace Elme\Checkout\Plugin;

use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Exception\LocalizedException;

/**
 * Core still routes checkout/onepage/saveOrder: with a form key and a payment method it submits the session quote
 * directly, past CheckoutManagement and so past the place-order validators (terms, minimum order value, every B2B
 * rule). The storefront never calls it; orders go through elme_checkout/action/placeorder.
 * The web APIs are closed by RefuseApiOrderPlugin.
 */
class RefuseOnepageOrderPlugin
{
    /**
     * @param Onepage $subject
     * @return null
     * @throws LocalizedException
     */
    public function beforeSaveOrder(Onepage $subject)
    {
        throw new LocalizedException(__('Please place your order through the checkout.'));
    }
}
