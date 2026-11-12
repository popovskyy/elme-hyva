<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model\ResourceModel;

use Magento\Framework\DB\Adapter\DuplicateException;
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
     * Whether this customer has a row with this name, including an empty list.
     */
    public function exists(int $customerId, string $name): bool
    {
        return $this->getId($customerId, $name) !== null;
    }

    /**
     * Id of this customer's list with this name. The column collation ignores case, as the unique key does.
     */
    public function getId(int $customerId, string $name): ?int
    {
        $connection = $this->getConnection();
        $id = $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), ['id'])
                ->where('customer_id = ?', $customerId)
                ->where('name = ?', $name)
                ->limit(1)
        );

        return $id !== false ? (int) $id : null;
    }

    /**
     * A new empty list. Never touches an existing one: the unique key turns a taken name into DuplicateException.
     *
     * @throws DuplicateException
     */
    public function insertEmpty(int $customerId, string $name): void
    {
        $this->getConnection()->insert(
            $this->getMainTable(),
            ['customer_id' => $customerId, 'name' => $name, 'items' => $this->json->serialize([])]
        );
    }

    /**
     * Renames a list only when it belongs to this customer. A taken name is DuplicateException (unique key).
     *
     * @throws DuplicateException
     */
    public function renameOwned(int $listId, int $customerId, string $name): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['name' => $name],
            ['id = ?' => $listId, 'customer_id = ?' => $customerId]
        );
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

    /**
     * Named shopping lists for a customer (not cart_list). Used by My Shopping List and Add to list.
     *
     * @return list<array{id: int, name: string, items: array<int, array{sku: string, qty: float}>}>
     */
    public function loadNamedLists(int $customerId, string $excludeName): array
    {
        $connection = $this->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->getMainTable(), ['id', 'name', 'items'])
                ->where('customer_id = ?', $customerId)
                ->where('name <> ?', $excludeName)
                ->order(['updated_at DESC', 'id DESC'])
        );

        $lists = [];
        foreach ($rows as $row) {
            $items = [];
            $raw = $row['items'] ?? '';
            if (is_string($raw) && $raw !== '') {
                try {
                    $decoded = $this->json->unserialize($raw);
                    $items = is_array($decoded) ? $decoded : [];
                } catch (\InvalidArgumentException) {
                    $items = [];
                }
            }
            $lists[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'items' => $items,
            ];
        }

        return $lists;
    }

    /**
     * Deletes a list only when it belongs to this customer. Returns false when missing or not owned.
     */
    public function deleteOwned(int $listId, int $customerId): bool
    {
        $connection = $this->getConnection();
        $deleted = $connection->delete(
            $this->getMainTable(),
            ['id = ?' => $listId, 'customer_id = ?' => $customerId]
        );

        return $deleted > 0;
    }
}
