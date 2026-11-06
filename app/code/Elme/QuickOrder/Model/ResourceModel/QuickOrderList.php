<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Table quick_order_list: one row per customer and list name, items as a JSON list of {sku, qty}.
 * Read and written straight through the connection, so a cart edit is one SELECT or one upsert.
 */
class QuickOrderList extends AbstractDb
{
    public const TABLE_NAME = 'quick_order_list';

    public function __construct(
        Context $context,
        private readonly Json $json,
        ?string $connectionName = null
    ) {
        parent::__construct($context, $connectionName);
    }

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'id');
    }

    /**
     * @return array<int, array{sku: string, qty: float}> In the order they were added; empty when there is no list
     */
    public function loadItems(int $customerId, string $name): array
    {
        $connection = $this->getConnection();
        $raw = $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), ['items'])
                ->where('customer_id = ?', $customerId)
                ->where('name = ?', $name)
        );
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $items = $this->json->unserialize($raw);
        } catch (\InvalidArgumentException) {
            return [];
        }

        return is_array($items) ? $items : [];
    }

    /**
     * Creates the list on first save; the unique key (customer_id, name) keeps one row even when two requests race.
     *
     * @param array<int, array{sku: string, qty: float}> $items
     */
    public function saveItems(int $customerId, string $name, array $items): void
    {
        $this->getConnection()->insertOnDuplicate(
            $this->getMainTable(),
            ['customer_id' => $customerId, 'name' => $name, 'items' => $this->json->serialize(array_values($items))],
            ['items']
        );
    }
}
