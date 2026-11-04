<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $resultPageFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        return $this->resultPageFactory->create();
    }
}
