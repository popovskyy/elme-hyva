<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller\Template;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ViewInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * /quick-order/template — My Shopping List (named QO lists). Login only via Elme_LoginOnly.
 * Magento wishlist is off; this page is the account/header destination instead.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        ViewInterface $view
    ) {
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('My Shopping List'));

        return $page;
    }
}
