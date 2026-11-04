<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\Location;

use Elme\StoreLocator\Model\ResourceModel\Location\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class DataProvider extends AbstractDataProvider
{
    /**
     * @var array<int|string, array<string, mixed>>|null
     */
    private ?array $loadedData = null;

    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $this->loadedData = [];
        foreach ($this->collection->getItems() as $location) {
            $this->loadedData[$location->getLocationId()] = $location->getData();
        }

        $persisted = $this->dataPersistor->get('elme_store_locator_location');
        if (!empty($persisted)) {
            $location = $this->collection->getNewEmptyItem();
            $location->setData($persisted);
            $this->loadedData[$location->getLocationId() ?: ''] = $location->getData();
            $this->dataPersistor->clear('elme_store_locator_location');
        }

        return $this->loadedData;
    }
}
