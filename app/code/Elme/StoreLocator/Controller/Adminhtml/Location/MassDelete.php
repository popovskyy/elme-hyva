<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Adminhtml\Location;

use Elme\StoreLocator\Api\LocationRepositoryInterface;
use Elme\StoreLocator\Controller\Adminhtml\Location;
use Elme\StoreLocator\Model\ResourceModel\Location\CollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Registry;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Location implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly LocationRepositoryInterface $locationRepository
    ) {
        parent::__construct($context, $coreRegistry);
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $deleted = 0;
        foreach ($collection as $location) {
            $this->locationRepository->delete($location);
            $deleted++;
        }
        if ($deleted) {
            $this->messageManager->addSuccessMessage(__('A total of %1 location(s) have been deleted.', $deleted));
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/');
    }
}
