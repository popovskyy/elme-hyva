<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Observer;

use Elme\LoginOnly\Model\LoginState;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;

/**
 * Adds the layout handle "elme_customer_guest" or "elme_customer_logged_in" to every page, so layout XML can
 * remove qty inputs and add to cart buttons for guests. Safe with the full page cache: it varies by login state.
 */
class AddLoginStateHandle implements ObserverInterface
{
    public const HANDLE_GUEST = 'elme_customer_guest';
    public const HANDLE_LOGGED_IN = 'elme_customer_logged_in';

    public function __construct(
        private readonly LoginState $loginState
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var LayoutInterface $layout */
        $layout = $observer->getEvent()->getData('layout');
        $layout->getUpdate()->addHandle(
            $this->loginState->isLoggedIn() ? self::HANDLE_LOGGED_IN : self::HANDLE_GUEST
        );
    }
}
