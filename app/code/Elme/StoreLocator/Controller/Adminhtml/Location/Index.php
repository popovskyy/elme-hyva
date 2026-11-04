<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Adminhtml\Location;

use Elme\StoreLocator\Controller\Adminhtml\Location;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class Index extends Location implements HttpGetActionInterface
{
    public function execute()
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $this->initPage($resultPage)->getConfig()->getTitle()->prepend(__('Showroom Locations'));

        return $resultPage;
    }
}
