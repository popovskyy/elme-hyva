<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Model\Import;

use Elme\CustomerPricing\Model\ProntoCustomerResolver;
use Elme\CustomerPricing\Model\ResourceModel\ProntoPriceData;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Loads Pronto price mappings from a CSV whose header names the table columns. Test data and manual fixes only: the
 * Pronto feed is its own integration. Rows are upserted on the table's unique key; --replace empties the table first.
 *
 * Type "customer-link" sets the customer attribute pronto_customer_id by e-mail instead of filling a table.
 */
class CsvLoader
{
    public const TYPE_CUSTOMER_LINK = 'customer-link';

    /**
     * Table and columns per type; required columns are marked with true.
     */
    private const TYPES = [
        'customer' => [ProntoPriceData::TABLE_CUSTOMER, [
            'pronto_customer_id' => true, 'marketing_flag' => false, 'region_group' => false,
        ]],
        'price-list' => [ProntoPriceData::TABLE_PRICE_LIST, [
            'sku' => true, 'price' => true, 'prom_price' => false, 'is_prom_price_active' => false,
        ]],
        'region-price' => [ProntoPriceData::TABLE_REGION_PRICE, [
            'sku' => true, 'region_group' => true, 'price' => true, 'prom_price' => false,
            'is_prom_price_active' => false,
        ]],
        'sku-group' => [ProntoPriceData::TABLE_SKU_GROUP, [
            'sku' => true, 'group_code' => true,
        ]],
        'cust-mkt' => [ProntoPriceData::TABLE_CUST_MKT, [
            'discount_type' => true, 'sku' => false, 'item_group' => false, 'pronto_customer_id' => false,
            'marketing_flag' => false, 'discount' => false, 'price' => false,
        ]],
        self::TYPE_CUSTOMER_LINK => [null, [
            'email' => true, 'pronto_customer_id' => false,
        ]],
    ];

    private const NUMERIC = ['price', 'prom_price', 'discount', 'is_prom_price_active'];

    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerFactory $customerFactory,
        private readonly CustomerResource $customerResource,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return string[]
     */
    public function getTypes(): array
    {
        return array_keys(self::TYPES);
    }

    /**
     * @return int Rows written
     * @throws LocalizedException On an unknown type, a bad file or an invalid row (nothing is written then)
     */
    public function load(string $type, string $file, bool $replace = false): int
    {
        if (!isset(self::TYPES[$type])) {
            throw new LocalizedException(__('Unknown type "%1", use one of: %2', $type, implode(', ', $this->getTypes())));
        }
        [$table, $columns] = self::TYPES[$type];

        $rows = [];
        foreach ($this->readCsv($file, $columns) as $line => $row) {
            $rows[] = $this->validate($type, $row, $line);
        }

        return $table === null ? $this->linkCustomers($rows) : $this->write($table, $rows, $replace);
    }

    /**
     * @param array<string, bool> $columns
     * @return \Generator<int, array<string, ?string>> line number => row with the known columns
     */
    private function readCsv(string $file, array $columns): \Generator
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new LocalizedException(__('Cannot read %1', $file));
        }
        $handle = fopen($file, 'r');
        try {
            $header = array_map(
                static fn ($name): string => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $name))),
                $this->readLine($handle) ?: []
            );
            $unknown = array_diff($header, array_keys($columns));
            $missing = array_diff(array_keys(array_filter($columns)), $header);
            if ($unknown || $missing) {
                throw new LocalizedException(__(
                    'Header must use the columns %1 (required: %2)',
                    implode(', ', array_keys($columns)),
                    implode(', ', array_keys(array_filter($columns)))
                ));
            }

            $line = 1;
            while (($values = $this->readLine($handle)) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                $row = [];
                foreach ($header as $i => $name) {
                    $value = trim((string) ($values[$i] ?? ''));
                    $row[$name] = $value === '' ? null : $value;
                }
                yield $line => $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     * @return array<int, ?string>|false
     */
    private function readLine($handle): array|false
    {
        return fgetcsv($handle, null, ',', '"', '');
    }

    /**
     * @param array<string, ?string> $row
     * @return array<string, ?string>
     */
    private function validate(string $type, array $row, int $line): array
    {
        foreach (array_keys(array_filter(self::TYPES[$type][1])) as $column) {
            if ($row[$column] === null) {
                throw new LocalizedException(__('Line %1: %2 is empty', $line, $column));
            }
        }
        foreach (self::NUMERIC as $column) {
            if (($row[$column] ?? null) !== null && !is_numeric($row[$column])) {
                throw new LocalizedException(__('Line %1: %2 is not a number', $line, $column));
            }
        }
        if (array_key_exists('is_prom_price_active', $row)) {
            $row['is_prom_price_active'] = (string) (int) $row['is_prom_price_active'];
        }

        if ($type === 'cust-mkt') {
            $needs = match ($row['discount_type']) {
                ProntoPriceData::TYPE_CUSTOMER_PRICE => ['pronto_customer_id', 'sku', 'price'],
                ProntoPriceData::TYPE_MARKETING_SKU => ['marketing_flag', 'sku'],
                ProntoPriceData::TYPE_MARKETING_GROUP => ['marketing_flag', 'item_group', 'discount'],
                default => throw new LocalizedException(
                    __('Line %1: discount_type must be A1, 20 or 25', $line)
                ),
            };
            foreach ($needs as $column) {
                if ($row[$column] === null) {
                    throw new LocalizedException(
                        __('Line %1: discount type %2 needs %3', $line, $row['discount_type'], $column)
                    );
                }
            }
            if ($row['discount_type'] === ProntoPriceData::TYPE_MARKETING_SKU
                && $row['price'] === null && $row['discount'] === null
            ) {
                throw new LocalizedException(__('Line %1: discount type 20 needs a price or a discount', $line));
            }
        }

        return $row;
    }

    /**
     * @param array<int, array<string, ?string>> $rows
     */
    private function write(string $table, array $rows, bool $replace): int
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName($table);

        $connection->beginTransaction();
        try {
            if ($replace) {
                $connection->delete($tableName);
            }
            foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
                $connection->insertOnDuplicate($tableName, $batch, array_keys($batch[0]));
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return count($rows);
    }

    /**
     * @param array<int, array<string, ?string>> $rows
     */
    private function linkCustomers(array $rows): int
    {
        $websiteId = (int) $this->storeManager->getDefaultStoreView()?->getWebsiteId();
        foreach ($rows as $row) {
            try {
                $customerId = (int) $this->customerRepository->get((string) $row['email'], $websiteId)->getId();
            } catch (NoSuchEntityException) {
                throw new LocalizedException(__('No customer with e-mail %1', $row['email']));
            }
            $customer = $this->customerFactory->create();
            $this->customerResource->load($customer, $customerId);
            $customer->setData(ProntoCustomerResolver::ATTRIBUTE_CODE, $row['pronto_customer_id']);
            $this->customerResource->saveAttribute($customer, ProntoCustomerResolver::ATTRIBUTE_CODE);
        }

        return count($rows);
    }
}
