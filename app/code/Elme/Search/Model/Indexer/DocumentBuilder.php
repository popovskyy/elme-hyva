<?php

declare(strict_types=1);

namespace Elme\Search\Model\Indexer;

use Magento\Catalog\Model\Indexer\Category\Product\TableMaintainer;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Magento catalog data -> search documents (the stand-in for "the Search Service reads the PIM").
 *
 * One document per enabled, visible product of the store's website. Field names follow the target model
 * (elme-product-data), so the Search Service maps 1:1; attributes that do not exist on this instance yet
 * (product_type, colour_group, style, features, seats, made_to_order, condition_code) are simply left out until
 * they are created. Families are NOT collapsed here: that happens at query time on family_key.
 */
class DocumentBuilder
{
    private const NUMERIC_FIELDS = ['width', 'depth', 'height', 'price', 'family_sort_order'];
    private const BOOLEAN_FIELDS = ['made_to_order'];

    /** @var array<string, \Magento\Eav\Model\Entity\Attribute\AbstractAttribute>|null */
    private ?array $attributes = null;

    /**
     * @param array<string, string> $fieldMap document field => product attribute code
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly EavConfig $eavConfig,
        private readonly ResourceConnection $resource,
        private readonly TableMaintainer $categoryIndexTable,
        private readonly StoreManagerInterface $storeManager,
        private readonly array $fieldMap = []
    ) {
    }

    /**
     * @param int[]|null $ids null = the whole catalog
     * @return \Generator<array<int, array>> batches of id => document
     */
    public function build(int $storeId, ?array $ids = null, int $batchSize = 500): \Generator
    {
        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        $attributes = $this->getAttributes();
        $chunks = $ids === null ? [null] : array_chunk(array_values(array_unique(array_map('intval', $ids))), $batchSize);

        foreach ($chunks as $chunk) {
            $lastId = 0;
            do {
                $collection = $this->collectionFactory->create();
                $collection->setStoreId($storeId)
                    ->addStoreFilter($storeId)
                    ->addAttributeToSelect(array_values(array_map(
                        static fn ($attribute) => $attribute->getAttributeCode(),
                        $attributes
                    )))
                    ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                    ->addAttributeToFilter('visibility', ['in' => [
                        Visibility::VISIBILITY_IN_CATALOG,
                        Visibility::VISIBILITY_IN_SEARCH,
                        Visibility::VISIBILITY_BOTH,
                    ]])
                    ->addAttributeToFilter('entity_id', ['gt' => $lastId])
                    ->addUrlRewrite()
                    ->setOrder('entity_id', 'ASC')
                    ->setPageSize($batchSize)
                    ->setCurPage(1);
                if ($chunk !== null) {
                    $collection->addAttributeToFilter('entity_id', ['in' => $chunk]);
                }
                /** @var Product[] $products */
                $products = $collection->getItems();
                if ($products === []) {
                    break;
                }
                $productIds = array_map('intval', array_keys($products));
                $lastId = max($productIds);

                $categories = $this->getCategoryData($productIds, $storeId);
                $stock = $this->getStockStatuses($productIds);
                $indexPrices = $this->getIndexPrices($productIds, $websiteId);

                $batch = [];
                foreach ($products as $product) {
                    $id = (int)$product->getId();
                    $batch[$id] = $this->buildDocument(
                        $product,
                        $attributes,
                        $categories[$id] ?? [],
                        $stock[$id] ?? false,
                        $indexPrices[$id] ?? null
                    );
                }
                yield $batch;
            } while (count($products) === $batchSize);
        }
    }

    private function buildDocument(
        Product $product,
        array $attributes,
        array $categories,
        bool $inStock,
        ?float $indexPrice
    ): array {
        $id = (int)$product->getId();
        $doc = [
            'id' => $id,
            'sku' => (string)$product->getSku(),
            'visibility' => (int)$product->getVisibility(),
            'in_stock' => $inStock,
            'url' => (string)($product->getRequestPath() ?: 'catalog/product/view/id/' . $id),
        ];

        foreach ($attributes as $field => $attribute) {
            $value = $this->readValue($product, $attribute, $field);
            if ($value !== null) {
                $doc[$field] = $value;
            }
        }

        // colour_group is derived from colour in the target; until it exists here, fall back to the colour.
        if (!isset($doc['colour_group']) && isset($doc['color'])) {
            $doc['colour_group'] = $doc['color'];
        }
        if (empty($doc['price']) && $indexPrice !== null) {
            $doc['price'] = $indexPrice;
        }
        $doc['family_key'] = !empty($doc['family_code']) ? 'f:' . $doc['family_code'] : 'p:' . $id;

        $doc['category_ids'] = $categories['ids'] ?? [];
        $doc['category_direct_ids'] = $categories['direct'] ?? [];
        $doc['category_paths'] = $categories['paths'] ?? [];
        $doc['category_positions'] = $categories['positions'] ?? [];

        return $doc;
    }

    private function readValue(Product $product, $attribute, string $field): mixed
    {
        $code = $attribute->getAttributeCode();
        $raw = $product->getData($code);
        if ($raw === null || $raw === '' || $raw === false) {
            return null;
        }
        $input = (string)$attribute->getFrontendInput();

        if (in_array($field, self::BOOLEAN_FIELDS, true) || $input === 'boolean') {
            return (bool)(int)$raw;
        }
        if (in_array($input, ['select', 'multiselect'], true)) {
            $text = $product->getAttributeText($code);
            if (is_array($text)) {
                $values = array_values(array_filter(array_map(
                    static fn ($v) => trim((string)$v),
                    $text
                ), static fn (string $v) => $v !== ''));
                return $values === [] ? null : $values;
            }
            $text = trim((string)$text);
            return $text === '' ? null : $text;
        }
        if (in_array($field, self::NUMERIC_FIELDS, true)) {
            // width / depth / height are text attributes today ("100", "100 cm"): take the first number
            if (is_numeric($raw)) {
                return $field === 'family_sort_order' ? (int)$raw : (float)$raw;
            }
            return preg_match('/-?\d+(?:[.,]\d+)?/', (string)$raw, $m) ? (float)str_replace(',', '.', $m[0]) : null;
        }
        if ($input === 'date' || $input === 'datetime' || $attribute->getBackendType() === 'datetime') {
            $time = strtotime((string)$raw);
            return $time ? date('Y-m-d H:i:s', $time) : null;
        }
        $value = trim((string)$raw);
        return $value === '' ? null : $value;
    }

    /**
     * Category ids (anchor parents included), direct categories, their paths and positions, from the
     * category / product index of the store (what Magento's own listing uses).
     *
     * @param int[] $productIds
     * @return array<int, array{ids: int[], direct: int[], paths: string[], positions: array}>
     */
    private function getCategoryData(array $productIds, int $storeId): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->categoryIndexTable->getMainTable($storeId);
        if (!$connection->isTableExists($table)) {
            return [];
        }
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['i' => $table], ['product_id', 'category_id', 'position', 'is_parent'])
                ->join(['c' => $this->resource->getTableName('catalog_category_entity')], 'c.entity_id = i.category_id', ['path'])
                ->where('i.product_id IN (?)', $productIds)
                ->where('c.level > ?', 1)
        );
        $data = [];
        foreach ($rows as $row) {
            $productId = (int)$row['product_id'];
            $categoryId = (int)$row['category_id'];
            $data[$productId]['ids'][] = $categoryId;
            $data[$productId]['positions'][] = ['id' => $categoryId, 'position' => (int)$row['position']];
            if ((int)$row['is_parent'] === 1) {
                $data[$productId]['direct'][] = $categoryId;
                $data[$productId]['paths'][] = (string)$row['path'];
            }
        }
        return $data;
    }

    /**
     * Stock until BE-23 lands: Magento's stock status of the default stock. BE-23 replaces this one method with
     * StockProviderInterface (Shipment Service); nothing else here reads stock.
     *
     * @param int[] $productIds
     * @return array<int, bool>
     */
    private function getStockStatuses(array $productIds): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($this->resource->getTableName('cataloginventory_stock_status'), ['product_id', 'stock_status'])
                ->where('product_id IN (?)', $productIds)
                ->where('website_id = ?', 0)
                ->where('stock_id = ?', 1)
        );
        return array_map(static fn ($status) => (int)$status === 1, $rows);
    }

    /**
     * Price for sorting when the product has no own price (configurable): the lowest indexed price, guest group.
     * Never a customer price.
     *
     * @param int[] $productIds
     * @return array<int, float>
     */
    private function getIndexPrices(array $productIds, int $websiteId): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('catalog_product_index_price');
        if (!$connection->isTableExists($table)) {
            return [];
        }
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from($table, ['entity_id', 'min_price'])
                ->where('entity_id IN (?)', $productIds)
                ->where('website_id = ?', $websiteId)
                ->where('customer_group_id = ?', 0)
        );
        return array_map('floatval', $rows);
    }

    /**
     * @return array<string, \Magento\Eav\Model\Entity\Attribute\AbstractAttribute> document field => attribute
     */
    private function getAttributes(): array
    {
        if ($this->attributes !== null) {
            return $this->attributes;
        }
        $this->attributes = [];
        foreach ($this->fieldMap as $field => $code) {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
            if ($attribute && $attribute->getId()) {
                $this->attributes[$field] = $attribute;
            }
        }
        return $this->attributes;
    }
}
