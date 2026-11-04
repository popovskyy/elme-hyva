<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\Api\Data\LocationInterfaceFactory;
use Elme\StoreLocator\Api\LocationRepositoryInterface;
use Elme\StoreLocator\Model\ResourceModel\Location as LocationResource;
use Elme\StoreLocator\Model\ResourceModel\Location\CollectionFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class LocationRepository implements LocationRepositoryInterface
{
    public function __construct(
        private readonly LocationResource $resource,
        private readonly LocationInterfaceFactory $locationFactory,
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    public function save(LocationInterface $location): LocationInterface
    {
        try {
            $this->resource->save($location);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the location: %1', $exception->getMessage()),
                $exception
            );
        }

        return $location;
    }

    public function getById(int $locationId): LocationInterface
    {
        $location = $this->locationFactory->create();
        $this->resource->load($location, $locationId);
        if (!$location->getLocationId()) {
            throw new NoSuchEntityException(__('Location with id "%1" does not exist.', $locationId));
        }

        return $location;
    }

    public function getByUrlKey(string $urlKey): LocationInterface
    {
        $location = $this->locationFactory->create();
        $this->resource->load($location, $urlKey, LocationInterface::URL_KEY);
        if (!$location->getLocationId()) {
            throw new NoSuchEntityException(__('Location with url key "%1" does not exist.', $urlKey));
        }

        return $location;
    }

    public function delete(LocationInterface $location): bool
    {
        try {
            $this->resource->delete($location);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the location: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    public function deleteById(int $locationId): bool
    {
        return $this->delete($this->getById($locationId));
    }

    /**
     * @return LocationInterface[]
     */
    public function getActiveList(?string $state = null): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(LocationInterface::IS_ACTIVE, 1);
        if ($state !== null && $state !== '') {
            $collection->addFieldToFilter(LocationInterface::STATE, strtoupper($state));
        }
        $collection->setOrder(LocationInterface::SORT_ORDER, 'ASC');
        $collection->setOrder(LocationInterface::NAME, 'ASC');

        return array_values($collection->getItems());
    }
}
