<?php

declare(strict_types=1);

namespace Elme\LoginOnly\ViewModel;

use Elme\LoginOnly\Model\LoginState;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * For templates that show prices, qty or add to cart: is the visitor logged in (cache safe), where to sign in,
 * where the customer prices come from.
 */
class LoginOnly implements ArgumentInterface
{
    public function __construct(
        private readonly LoginState $loginState,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function isLoggedIn(): bool
    {
        return $this->loginState->isLoggedIn();
    }

    public function isGuest(): bool
    {
        return $this->loginState->isGuest();
    }

    /**
     * Login URL without a page-specific referer: product tiles are block cached and shared between pages.
     * Elme_LoginOnly::js/sign-in-link.phtml adds the current page as referer when the link is clicked.
     */
    public function getLoginUrl(): string
    {
        return $this->urlBuilder->getUrl('customer/account/login');
    }

    /**
     * Trade account application (FE-36 registration). Used by the PDP guest buy box "Apply here".
     */
    public function getRegisterUrl(): string
    {
        return $this->urlBuilder->getUrl('customer/account/create');
    }

    /**
     * Become a Stockist page (FE-67). Target of the UX-02 PLP/search "Apply for a trade account" link.
     */
    public function getBecomeStockistUrl(): string
    {
        return $this->urlBuilder->getDirectUrl('become-a-stockist');
    }

    public function getPricesUrl(): string
    {
        return $this->urlBuilder->getUrl('elme_customer_price/prices');
    }

    /**
     * Live MOQ max/salable for the PDP stepper (FE-27). min/step may also be read from cached HTML.
     */
    public function getQtyRulesUrl(): string
    {
        return $this->urlBuilder->getUrl('elme_qty_rules/rules');
    }
}
