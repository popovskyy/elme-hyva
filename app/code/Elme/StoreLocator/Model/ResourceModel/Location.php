<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Location extends AbstractDb
{
    public const TABLE_NAME = 'elme_store_locator_location';

    public const ID_FIELD = 'location_id';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD);
    }
}
