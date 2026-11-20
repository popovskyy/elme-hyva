<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Plugin;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Webapi\Authorization;

/**
 * Elme is login-only, and RedirectGuests only guards the storefront pages. Core's anonymous REST and SOAP routes would
 * still give a guest prices (products-render-info, guest-carts totals) and a whole order (guest-carts payment-information).
 * The storefront calls none of them, so a guest gets the same 401 as on any other closed route, whichever module adds it.
 */
class CloseAnonymousWebapiPlugin
{
    private const ANONYMOUS = 'anonymous';

    /**
     * @param UserContextInterface $userContext
     * @param Request $request
     * @param string[] $allowedPaths Routes that stay open, as the exact path after the store code
     */
    public function __construct(
        private readonly UserContextInterface $userContext,
        private readonly Request $request,
        private readonly array $allowedPaths = []
    ) {
    }

    /**
     * @param Authorization $subject
     * @param bool $result
     * @param string[] $aclResources
     * @return bool
     */
    public function afterIsAllowed(Authorization $subject, bool $result, $aclResources): bool
    {
        if (!$result || !in_array(self::ANONYMOUS, (array) $aclResources, true) || $this->userContext->getUserId()) {
            return $result;
        }

        return in_array($this->request->getPathInfo(), $this->allowedPaths, true);
    }
}
