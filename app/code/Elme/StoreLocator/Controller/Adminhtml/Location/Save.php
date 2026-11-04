<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Adminhtml\Location;

use Elme\StoreLocator\Api\Data\LocationInterfaceFactory;
use Elme\StoreLocator\Api\LocationRepositoryInterface;
use Elme\StoreLocator\Controller\Adminhtml\Location;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;

class Save extends Location implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        private readonly LocationRepositoryInterface $locationRepository,
        private readonly LocationInterfaceFactory $locationFactory,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context, $coreRegistry);
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $locationId = (int) ($data['location_id'] ?? 0);
        try {
            $location = $locationId
                ? $this->locationRepository->getById($locationId)
                : $this->locationFactory->create();

            $location->setName((string) ($data['name'] ?? ''));
            $location->setType((string) ($data['type'] ?? ''));
            $location->setStreet(isset($data['street']) && $data['street'] !== '' ? (string) $data['street'] : null);
            $location->setCity(isset($data['city']) && $data['city'] !== '' ? (string) $data['city'] : null);
            $location->setState(strtoupper((string) ($data['state'] ?? '')));
            $location->setPostcode((string) ($data['postcode'] ?? ''));
            $location->setPhone(isset($data['phone']) && $data['phone'] !== '' ? (string) $data['phone'] : null);
            $location->setOpeningHours(
                isset($data['opening_hours']) && $data['opening_hours'] !== ''
                    ? (string) $data['opening_hours']
                    : null
            );
            $location->setUrlKey((string) ($data['url_key'] ?? ''));
            $location->setDescription(
                isset($data['description']) && $data['description'] !== ''
                    ? (string) $data['description']
                    : null
            );
            $location->setLatitude(
                isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null
            );
            $location->setLongitude(
                isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null
            );
            $location->setIsActive((bool) ($data['is_active'] ?? false));
            $location->setSortOrder((int) ($data['sort_order'] ?? 0));

            $this->locationRepository->save($location);
            $this->messageManager->addSuccessMessage(__('You saved the location.'));
            $this->dataPersistor->clear('elme_store_locator_location');

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['location_id' => $location->getLocationId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the location.'));
        }

        $this->dataPersistor->set('elme_store_locator_location', $data);

        return $resultRedirect->setPath('*/*/edit', ['location_id' => $locationId ?: null]);
    }
}
