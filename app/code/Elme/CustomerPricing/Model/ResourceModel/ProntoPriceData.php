<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Model\ResourceModel;

use Elme\CustomerPricing\Model\PriceSources;
use Elme\CustomerPricing\Model\ProntoCustomer;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Reads the Pronto price mappings of a set of SKUs for one customer.
 */
class ProntoPriceData
{
    public const TABLE_CUSTOMER = 'pronto_elme_customer_mapping';
    public const TABLE_PRICE_LIST = 'pronto_elme_price_list';
    public const TABLE_REGION_PRICE = 'pronto_elme_price_list_mapping';
    public const TABLE_SKU_GROUP = 'pronto_elme_sku_group_mapping';
    public const TABLE_CUST_MKT = 'pronto_elme_cust_mkt_mapping';

    public const TYPE_CUSTOMER_PRICE = 'A1';
    public const TYPE_MARKETING_SKU = '20';
    public const TYPE_MARKETING_GROUP = '25';

    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * @return array{marketing_flag: ?string, region_group: ?string}|null
     */
    public function getCustomerMapping(string $prontoCustomerId): ?array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE_CUSTOMER), ['marketing_flag', 'region_group'])
                ->where('pronto_customer_id = ?', $prontoCustomerId)
        );
        if (!$row) {
            return null;
        }

        return [
            'marketing_flag' => $this->nullIfEmpty($row['marketing_flag']),
            'region_group' => $this->nullIfEmpty($row['region_group']),
        ];
    }

    /**
     * @param string[] $skus
     * @return array<string, PriceSources> Every SKU asked for; listPrice is null when the SKU has no price list row.
     */
    public function getSources(array $skus, ?ProntoCustomer $customer): array
    {
        $skus = array_values(array_unique(array_filter(array_map('strval', $skus), 'strlen')));
        if (!$skus) {
            return [];
        }

        $connection = $this->resource->getConnection();
        $list = $this->getPriceRows($connection, self::TABLE_PRICE_LIST, $skus);
        $region = $customer?->regionGroup !== null
            ? $this->getPriceRows($connection, self::TABLE_REGION_PRICE, $skus, $customer->regionGroup)
            : [];
        $customerPrices = $customer !== null ? $this->getCustomerPrices($connection, $skus, $customer->id) : [];
        [$marketingPrices, $marketingDiscounts, $groupDiscounts] = $customer?->marketingFlag !== null
            ? $this->getMarketingRules($connection, $skus, $customer->marketingFlag)
            : [[], [], []];

        $sources = [];
        foreach ($skus as $sku) {
            $sources[$sku] = new PriceSources(
                $list[$sku]['price'] ?? null,
                $list[$sku]['promo'] ?? null,
                $region[$sku]['price'] ?? null,
                $region[$sku]['promo'] ?? null,
                $customerPrices[$sku] ?? null,
                $marketingPrices[$sku] ?? null,
                $marketingDiscounts[$sku] ?? null,
                $groupDiscounts[$sku] ?? []
            );
        }

        return $sources;
    }

    /**
     * @return array<string, array{price: float, promo: ?float}>
     */
    private function getPriceRows(
        AdapterInterface $connection,
        string $table,
        array $skus,
        ?string $regionGroup = null
    ): array {
        $select = $connection->select()
            ->from($this->resource->getTableName($table), ['sku', 'price', 'prom_price', 'is_prom_price_active'])
            ->where('sku IN (?)', $skus);
        if ($regionGroup !== null) {
            $select->where('region_group = ?', $regionGroup);
        }

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $promo = (float) $row['prom_price'];
            $rows[(string) $row['sku']] = [
                'price' => (float) $row['price'],
                'promo' => (int) $row['is_prom_price_active'] === 1 && $promo > 0 ? $promo : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, float>
     */
    private function getCustomerPrices(AdapterInterface $connection, array $skus, string $prontoCustomerId): array
    {
        return array_map('floatval', $connection->fetchPairs(
            $connection->select()
                ->from($this->resource->getTableName(self::TABLE_CUST_MKT), ['sku', 'price'])
                ->where('discount_type = ?', self::TYPE_CUSTOMER_PRICE)
                ->where('pronto_customer_id = ?', $prontoCustomerId)
                ->where('sku IN (?)', $skus)
                ->where('price IS NOT NULL')
        ));
    }

    /**
     * @return array{array<string, float>, array<string, float>, array<string, float[]>}
     *     type 20 prices, type 20 discounts and type 25 discounts per SKU
     */
    private function getMarketingRules(AdapterInterface $connection, array $skus, string $marketingFlag): array
    {
        $table = $this->resource->getTableName(self::TABLE_CUST_MKT);

        $prices = [];
        $discounts = [];
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($table, ['sku', 'price', 'discount'])
                ->where('discount_type = ?', self::TYPE_MARKETING_SKU)
                ->where('marketing_flag = ?', $marketingFlag)
                ->where('sku IN (?)', $skus)
        );
        foreach ($rows as $row) {
            // A row is a fixed price or a discount; the price wins when both are filled in, as on the old site.
            if ($row['price'] !== null) {
                $prices[(string) $row['sku']] = (float) $row['price'];
            } elseif ($row['discount'] !== null) {
                $discounts[(string) $row['sku']] = (float) $row['discount'];
            }
        }

        $groupDiscounts = [];
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['g' => $this->resource->getTableName(self::TABLE_SKU_GROUP)], ['sku'])
                ->join(['m' => $table], 'm.item_group = g.group_code', ['discount'])
                ->where('g.sku IN (?)', $skus)
                ->where('m.discount_type = ?', self::TYPE_MARKETING_GROUP)
                ->where('m.marketing_flag = ?', $marketingFlag)
                ->where('m.discount IS NOT NULL')
        );
        foreach ($rows as $row) {
            $groupDiscounts[(string) $row['sku']][] = (float) $row['discount'];
        }

        return [$prices, $discounts, $groupDiscounts];
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
