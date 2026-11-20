<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\ResourceModel\Location;

use Elme\StoreLocator\Model\Location;
use Elme\StoreLocator\Model\ResourceModel\Location as LocationResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = LocationResource::ID_FIELD;

    protected function _construct(): void
    {
        $this->_init(Location::class, LocationResource::class);
    }
}
