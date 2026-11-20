<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Controller\Index;

use Elme\QuickOrder\Model\CartList;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ViewInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * /quick-order and /quick-order/?list=Name — open or start a shopping list.
 * Login only through Elme_LoginOnly. The block is cacheable="false".
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RequestInterface $request,
        ViewInterface $view
    ) {
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $name = trim((string) $this->request->getParam('list'));
        $page->getConfig()->getTitle()->set(
            $name !== '' && $name !== CartList::NAME ? $name : __('New list')
        );

        return $page;
    }
}
