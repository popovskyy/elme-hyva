<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Adminhtml\Location;

use Elme\StoreLocator\Api\LocationRepositoryInterface;
use Elme\StoreLocator\Controller\Adminhtml\Location;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;

class Delete extends Location implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        private readonly LocationRepositoryInterface $locationRepository
    ) {
        parent::__construct($context, $coreRegistry);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $locationId = (int) $this->getRequest()->getParam('location_id');
        if (!$locationId) {
            $this->messageManager->addErrorMessage(__('We can\'t find a location to delete.'));
            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->locationRepository->deleteById($locationId);
            $this->messageManager->addSuccessMessage(__('You deleted the location.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the location.'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
