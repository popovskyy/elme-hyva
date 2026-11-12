<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Observer;

use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;

/**
 * There is no Magento cart page: /checkout/cart (links, core redirects, an empty checkout) opens /quick-order/cart.
 * Messages stay in the session, so a refusal on the way still shows there.
 */
class RedirectCartPage implements ObserverInterface
{
    public function __construct(
        private readonly ActionFlag $actionFlag,
        private readonly HttpResponse $response,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        // Elme_LoginOnly already sent a guest to login
        if ($this->actionFlag->get('', ActionInterface::FLAG_NO_DISPATCH)) {
            return;
        }

        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->response->setRedirect($this->urlBuilder->getUrl('quick_order/cart'));
    }
}
