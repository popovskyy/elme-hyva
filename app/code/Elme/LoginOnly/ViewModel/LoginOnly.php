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

    public function getPricesUrl(): string
    {
        return $this->urlBuilder->getUrl('elme_customer_price/prices');
    }
}
