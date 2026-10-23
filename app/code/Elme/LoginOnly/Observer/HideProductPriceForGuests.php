<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Observer;

use Elme\LoginOnly\Model\LoginState;
use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Marks product collections loaded for a guest with can_show_price = false; core price boxes respect the flag.
 * Second line of defence behind PriceRenderPlugin. Not for the PDP product: Catalog\Block\Product\Price renders
 * nothing with the flag, and for guests that block shows "Sign in to see pricing" (elme_customer_guest.xml).
 */
class HideProductPriceForGuests implements ObserverInterface
{
    public function __construct(
        private readonly LoginState $loginState
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->loginState->isLoggedIn()) {
            return;
        }

        foreach ($observer->getEvent()->getData('collection') ?? [] as $product) {
            if ($product instanceof Product) {
                $product->setData('can_show_price', false);
            }
        }
    }
}
