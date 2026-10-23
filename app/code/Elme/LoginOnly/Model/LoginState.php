<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Model;

use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;

/**
 * Logged-in state that is safe to use while rendering cacheable pages.
 *
 * The customer session is emptied on cacheable pages (depersonalisation), the HTTP context is not, and the
 * full page cache (built-in and Varnish) already varies by it. So a page rendered for a guest is only ever
 * served to guests.
 */
class LoginState
{
    public function __construct(
        private readonly HttpContext $httpContext
    ) {
    }

    public function isLoggedIn(): bool
    {
        return (bool) $this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }

    public function isGuest(): bool
    {
        return !$this->isLoggedIn();
    }
}
