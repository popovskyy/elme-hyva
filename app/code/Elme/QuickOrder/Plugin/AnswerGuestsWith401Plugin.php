<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Plugin;

use Elme\LoginOnly\Observer\RedirectGuests;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;

/**
 * Elme_LoginOnly sends guests on quick_order_* to the login page. The JSON endpoints answer a guest themselves
 * with 401 (Controller\AbstractJson): a script needs a status, not a login page.
 */
class AnswerGuestsWith401Plugin
{
    /**
     * @param string[] $endpoints Full action names
     */
    public function __construct(
        private readonly array $endpoints = []
    ) {
    }

    public function aroundExecute(RedirectGuests $subject, callable $proceed, Observer $observer): void
    {
        $request = $observer->getEvent()->getData('request');
        if ($request instanceof HttpRequest && in_array($request->getFullActionName(), $this->endpoints, true)) {
            return;
        }

        $proceed($observer);
    }
}
