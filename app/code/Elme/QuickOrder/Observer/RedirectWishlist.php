<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Observer;

use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;

/**
 * Magento wishlist is disabled. Any leftover /wishlist URL goes to My Shopping List (QO named lists).
 */
class RedirectWishlist implements ObserverInterface
{
    public function __construct(
        private readonly ActionFlag $actionFlag,
        private readonly HttpResponse $response,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    public function execute(Observer $observer): void
    {
        $request = $observer->getEvent()->getData('request');
        if (!$request instanceof HttpRequest || $request->getRouteName() !== 'wishlist') {
            return;
        }

        if ($this->actionFlag->get('', ActionInterface::FLAG_NO_DISPATCH)) {
            return;
        }

        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
        $this->response->setRedirect($this->urlBuilder->getUrl('quick_order/template'));
    }
}
