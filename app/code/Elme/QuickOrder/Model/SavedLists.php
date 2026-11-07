<?php

declare(strict_types=1);

namespace Elme\QuickOrder\Model;

use Elme\QuickOrder\Model\ResourceModel\QuickOrderList;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;
use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;

/**
 * Named Quick Order lists that replace Magento wishlist (My Shopping List). cart_list is the cart, not a
 * shopping list: every method here skips it. List names are unique per customer, case ignored (the unique key).
 */
class SavedLists
{
    private const PREVIEW_LIMIT = 3;
    private const NAME_MAX_LENGTH = 255;

    public function __construct(
        private readonly QuickOrderList $resource,
        private readonly CartList $cartList,
        private readonly CollectionFactory $collectionFactory,
        private readonly ImageHelper $imageHelper,
        private readonly ConfigurableResource $configurableResource
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
     * A new empty list. A name that is already taken is refused, never emptied.
     *
     * @throws LocalizedException
     */
    public function create(int $customerId, string $name): string
    {
        $name = $this->normalizeName($name);
        try {
            $this->resource->insertEmpty($customerId, $name);
        } catch (DuplicateException) {
            throw new LocalizedException($this->duplicateMessage());
        }

        return $name;
    }

    /**
     * @return string The new name as stored
     * @throws LocalizedException
     */
    public function rename(int $customerId, string $from, string $to): string
    {
        $from = trim($from);
        $to = $this->normalizeName($to);
        $id = $from !== '' && $from !== CartList::NAME ? $this->resource->getId($customerId, $from) : null;
        if ($id === null) {
            throw new LocalizedException(__('List not found.'));
        }

        // the same list may keep its name or change only its case
        $takenBy = $this->resource->getId($customerId, $to);
        if ($takenBy !== null && $takenBy !== $id) {
            throw new LocalizedException($this->duplicateMessage());
        }

        try {
            $this->resource->renameOwned($id, $customerId, $to);
        } catch (DuplicateException) {
            throw new LocalizedException($this->duplicateMessage());
        }

        return $to;
    }

    /**
     * Adds typed SKUs to an existing list (the Add a SKU field of the editor). Every SKU has to be a product of
     * this website that is enabled and can be shown (on its own or through its configurable parent); the SKU is
     * stored as the catalogue writes it.
     *
     * @param array<string, float> $rows sku => qty
     * @throws LocalizedException
     */
    public function add(int $customerId, string $name, array $rows): void
    {
        $name = trim($name);
        if ($name === '' || $name === CartList::NAME || !$this->resource->exists($customerId, $name)) {
            throw new LocalizedException(__('List not found.'));
        }

        $known = $this->findSkus(array_map('strval', array_keys($rows)));
        $canonical = [];
        $missing = [];
        foreach ($rows as $sku => $qty) {
            $sku = trim((string) $sku);
            if ($sku === '') {
                continue;
            }
            $found = $known[mb_strtolower($sku)] ?? null;
            if ($found === null) {
                $missing[] = $sku;
                continue;
            }
            $canonical[$found] = ($canonical[$found] ?? 0.0) + (float) $qty;
        }

        if ($missing) {
            throw new LocalizedException(count($missing) === 1
                ? __('SKU not found.')
                : __('SKU not found: %1', implode(', ', $missing)));
        }

        $this->save($customerId, $name, $canonical);
    }

    /**
     * Merge or replace items on a named list. cart_list is refused: use the cart endpoints.
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
     * @throws LocalizedException
     */
    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new LocalizedException(__('List name is required.'));
        }
        if ($name === CartList::NAME) {
            throw new LocalizedException(__('This list cannot be edited here.'));
        }
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new LocalizedException(__('Please use less than %1 characters.', self::NAME_MAX_LENGTH + 1));
        }

        return $name;
    }

    private function duplicateMessage(): Phrase
    {
        return __('A list with this name already exists.');
    }

    /**
     * @param list<string> $skus
     * @return array<string, string> lower-case sku => sku as in the catalogue, for the SKUs that can go on a list
     */
    private function findSkus(array $skus): array
    {
        $skus = array_values(array_filter(array_map('trim', $skus), static fn (string $sku): bool => $sku !== ''));
        if ($skus === []) {
            return [];
        }

        $collection = $this->collectionFactory->create();
        $collection->addAttributeToSelect(['visibility']);
        $collection->addAttributeToFilter('sku', ['in' => $skus]);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addWebsiteFilter();

        $out = [];
        /** @var Product $product */
        foreach ($collection as $product) {
            $visible = (int) $product->getVisibility() !== Visibility::VISIBILITY_NOT_VISIBLE
                || $this->configurableResource->getParentIdsByChild((int) $product->getId()) !== [];
            if ($visible) {
                $sku = (string) $product->getSku();
                $out[mb_strtolower($sku)] = $sku;
            }
        }

        return $out;
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
