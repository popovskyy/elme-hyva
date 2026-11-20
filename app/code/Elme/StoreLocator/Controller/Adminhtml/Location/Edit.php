<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Adminhtml\Location;

use Elme\StoreLocator\Api\Data\LocationInterfaceFactory;
use Elme\StoreLocator\Api\LocationRepositoryInterface;
use Elme\StoreLocator\Controller\Adminhtml\Location;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;

class Edit extends Location implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly LocationInterfaceFactory $locationFactory
    ) {
        parent::__construct($context, $coreRegistry);
    }

    public function execute()
    {
        $locationId = (int) $this->getRequest()->getParam('location_id');
        if ($locationId) {
            try {
                $location = $this->locationRepository->getById($locationId);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This location no longer exists.'));
                return $this->resultRedirectFactory->create()->setPath('*/*/');
            }
        } else {
            $location = $this->locationFactory->create();
        }

        $this->coreRegistry->register('elme_store_locator_location', $location);

        /** @var Page $resultPage */
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $this->initPage($resultPage)
            ->addBreadcrumb(
                $locationId ? __('Edit Location') : __('New Location'),
                $locationId ? __('Edit Location') : __('New Location')
            );
        $resultPage->getConfig()->getTitle()->prepend(__('Showroom Locations'));
        $resultPage->getConfig()->getTitle()->prepend(
            $location->getLocationId() ? $location->getName() : __('New Location')
        );

        return $resultPage;
    }
}
