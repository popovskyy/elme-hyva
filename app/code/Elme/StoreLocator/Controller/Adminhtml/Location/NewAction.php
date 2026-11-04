<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Adminhtml\Location;

use Elme\StoreLocator\Controller\Adminhtml\Location;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

class NewAction extends Location implements HttpGetActionInterface
{
    public function execute()
    {
        return $this->resultFactory->create(ResultFactory::TYPE_FORWARD)->forward('edit');
    }
}
