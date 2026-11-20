<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ViewInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    /**
     * `$view` is unused on purpose. Constructing App\View early pins the shared
     * Page\Config builder so Hyva head.additional scripts (hyva.js / variables)
     * are not dropped when layout generation later resolves ViewInterface.
     *
     * @param PageFactory $resultPageFactory
     * @param ViewInterface $view
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        private readonly PageFactory $resultPageFactory,
        ViewInterface $view
    ) {
    }

    public function execute(): ResultInterface
    {
        return $this->resultPageFactory->create();
    }
}
