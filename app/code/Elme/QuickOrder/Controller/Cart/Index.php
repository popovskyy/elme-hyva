<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller\Cart;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ViewInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * /quick-order/cart, the cart page (FE-33 builds its UI). Login only through Elme_LoginOnly; never full page cached
 * (cacheable="false" in quick_order_cart_index.xml).
 */
class Index implements HttpGetActionInterface
{
    /**
     * `$view` is unused on purpose, as in Elme\StoreLocator\Controller\Index\Index: constructing App\View early
     * pins the shared Page\Config builder, or Hyva's head.additional scripts (hyva.js, variables) are dropped.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        private readonly PageFactory $pageFactory,
        ViewInterface $view
    ) {
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Cart'));

        return $page;
    }
}
