<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model;

use Elme\QuickOrder\Model\ResourceModel\QuickOrderList;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Named Quick Order lists that replace Magento wishlist (My Shopping List). cart_list is the cart, not a
 * shopping list — every method here skips it.
 */
class SavedLists
{
    private const PREVIEW_LIMIT = 3;

    public function __construct(
        private readonly QuickOrderList $resource,
        private readonly CartList $cartList,
        private readonly CollectionFactory $collectionFactory,
        private readonly ImageHelper $imageHelper
    ) {
    }

    /**
     * Dashboard payload for /quick-order/list/getall and the header badge.
     *
     * @return array{lists: list<array<string, mixed>>, saved_skus: list<string>, total_item_count: int}
     */
    public function getPayload(int $customerId): array
    {
        $rawLists = $this->resource->loadNamedLists($customerId, CartList::NAME);
        $savedSkuMap = [];
        $previewSkuMap = [];
        $lists = [];

        foreach ($rawLists as $list) {
            $items = is_array($list['items']) ? $list['items'] : [];
            $itemCount = 0;
            $previewSkus = [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $sku = trim((string) ($item['sku'] ?? ''));
                if ($sku === '') {
                    continue;
                }
                $itemCount++;
                $savedSkuMap[$sku] = $sku;
                if (count($previewSkus) < self::PREVIEW_LIMIT) {
                    $previewSkus[] = $sku;
                    $previewSkuMap[$sku] = $sku;
                }
            }

            $lists[] = [
                'id' => (int) $list['id'],
                'name' => (string) $list['name'],
                'item_count' => $itemCount,
                '_preview_skus' => $previewSkus,
            ];
        }

        $previews = $this->loadPreviews(array_values($previewSkuMap));
        foreach ($lists as &$row) {
            $row['preview'] = [];
            foreach ($row['_preview_skus'] as $sku) {
                $row['preview'][] = $previews[$sku] ?? [
                    'sku' => $sku,
                    'name' => $sku,
                    'thumbnail_url' => '',
                ];
            }
            unset($row['_preview_skus']);
        }
        unset($row);

        $totalItemCount = 0;
        foreach ($lists as $list) {
            $totalItemCount += (int) $list['item_count'];
        }

        return [
            'lists' => $lists,
            'saved_skus' => array_values($savedSkuMap),
            'total_item_count' => $totalItemCount,
        ];
    }

    /**
     * Sum of SKUs across named lists (header badge). One SELECT, no product loads.
     */
    public function getTotalItemCount(int $customerId): int
    {
        $count = 0;
        foreach ($this->resource->loadNamedLists($customerId, CartList::NAME) as $list) {
            foreach ($list['items'] as $item) {
                if (is_array($item) && trim((string) ($item['sku'] ?? '')) !== '') {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function delete(int $customerId, int $listId): void
    {
        if ($listId <= 0) {
            throw new LocalizedException(__('List id is required.'));
        }

        if (!$this->resource->deleteOwned($listId, $customerId)) {
            throw new NoSuchEntityException(__('List not found.'));
        }
    }

    /**
     * Merge or replace items on a named list. cart_list is refused — use the cart endpoints.
     *
     * @param array<string, float> $rows sku => qty
     * @throws LocalizedException
     */
    public function save(int $customerId, string $name, array $rows, bool $replace = false): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new LocalizedException(__('List name is required.'));
        }
        if ($name === CartList::NAME) {
            throw new LocalizedException(__('This list cannot be edited here.'));
        }

        $current = $replace ? [] : $this->cartList->get($customerId, $name);
        foreach ($rows as $sku => $qty) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $total = ($current[$sku] ?? 0.0) + (float) $qty;
            if ($total > 0) {
                $current[$sku] = $total;
            } else {
                unset($current[$sku]);
            }
        }

        $this->cartList->replace($customerId, $current, $name);
    }

    /**
     * @param list<string> $skus
     * @return array<string, array{sku: string, name: string, thumbnail_url: string}>
     */
    private function loadPreviews(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['name', 'small_image', 'thumbnail']);
        $collection->addAttributeToFilter('sku', ['in' => $skus]);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);

        $out = [];
        /** @var Product $product */
        foreach ($collection as $product) {
            $sku = (string) $product->getSku();
            $out[$sku] = [
                'sku' => $sku,
                'name' => (string) $product->getName(),
                'thumbnail_url' => (string) $this->imageHelper
                    ->init($product, 'product_thumbnail_image')
                    ->getUrl(),
            ];
        }

        return $out;
    }
}
