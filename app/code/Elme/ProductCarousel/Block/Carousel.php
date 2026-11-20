<?php

declare(strict_types=1);

namespace Elme\ProductCarousel\Block;

use Elme\ProductCarousel\Api\ProviderInterface;
use Elme\Theme\ViewModel\ProductTile;
use Hyva\Theme\ViewModel\ProductListItem;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Block\Product\ReviewRendererInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Widget\Block\BlockInterface;

/**
 * FE-17: a titled slider of FE-19 product cards.
 *
 * Widget "Product carousel" (etc/widget.xml), Content Studio type `product_carousel` (home) or a block in layout:
 *
 *   <block class="Elme\ProductCarousel\Block\Carousel" name="..." template="Elme_ProductCarousel::carousel.phtml">
 *       <arguments>
 *           <argument name="source" xsi:type="string">related</argument>
 *           <argument name="title" xsi:type="string" translate="true">You may also like</argument>
 *       </arguments>
 *   </block>
 *
 * Data: source (trending | new | category | related | cross_sell | upsell | range | similar), title, category_id
 * (category), product_id (the product sources; defaults to the product of the page, which is never listed), limit
 * (default 12, at most 24), heading_level (2 or 3, default 2), embedded (true: no outer margins, for a tab panel;
 * see tabs.phtml), tab_title (label of the tab in tabs.phtml).
 *
 * The cards are rendered on the server, so crawlers see them and nothing shifts while the page loads. Prices are the
 * card's own (guests see "Log in for price", customers get their price after render), so the HTML is the same for
 * everyone and the page cache keeps working. With no products the block renders nothing, title included.
 */
class Carousel extends Template implements BlockInterface, IdentityInterface
{
    public const DEFAULT_LIMIT = 12;
    public const MAX_LIMIT = 24;

    /** @var array<int, array{id: int, html: string}>|null */
    private ?array $items = null;

    /** @var int[] */
    private array $identities = [];

    private static int $instances = 0;

    private readonly int $instance;

    public function __construct(
        Context $context,
        private readonly ProviderInterface $provider,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductListItem $productListItem,
        private readonly ProductTile $productTile,
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->instance = ++self::$instances;
    }

    /**
     * @inheritdoc
     */
    public function getIdentities(): array
    {
        $this->getItems();

        return array_map(static fn (int $id): string => Product::CACHE_TAG . '_' . $id, $this->identities);
    }

    /**
     * Unique per carousel on the page, for ids and aria links.
     */
    public function getDomId(): string
    {
        return 'elme-pc-' . $this->instance;
    }

    public function getTitle(): string
    {
        return trim((string) $this->getData('title'));
    }

    public function isEmbedded(): bool
    {
        return (bool) $this->getData('embedded');
    }

    public function getTabTitle(): string
    {
        return trim((string) ($this->getData('tab_title') ?: $this->getData('title')));
    }

    public function getHeadingTag(): string
    {
        return (int) $this->getData('heading_level') === 3 ? 'h3' : 'h2';
    }

    public function getLimit(): int
    {
        $limit = (int) $this->getData('limit');

        return $limit > 0 ? min($limit, self::MAX_LIMIT) : self::DEFAULT_LIMIT;
    }

    /**
     * One entry per product that can be shown, in display order. Empty when there is nothing to show.
     *
     * @return array<int, array{id: int, html: string}>
     */
    public function getItems(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $this->items = [];
        $source = (string) $this->getData('source');
        if (!in_array($source, ProviderInterface::SOURCES, true)) {
            return $this->items;
        }

        $options = [
            'category_id' => (int) $this->getData('category_id'),
            'product_id' => (int) ($this->getData('product_id') ?: $this->getCurrentProductId()),
        ];
        $storeId = (int) $this->storeManager->getStore()->getId();
        $limit = $this->getLimit();
        $parent = $this->getListParent();
        if (!$parent) {
            return $this->items;
        }

        $seen = [];
        foreach ($this->provider->getProductIds($source, $storeId, $limit, $options) as $productId) {
            $productId = (int) $productId;
            // A product is never its own recommendation
            if (isset($seen[$productId]) || ($options['product_id'] > 0 && $productId === $options['product_id'])) {
                continue;
            }
            $seen[$productId] = true;

            $product = $this->loadVisibleProduct($productId, $storeId);
            if ($product === null || ($source === ProviderInterface::SOURCE_NEW && !$this->productTile->isNew($product))) {
                continue;
            }

            $product->setData('image_custom_attributes', ['loading' => 'lazy']);
            $html = $this->productListItem->getItemHtml(
                $product,
                $parent,
                'grid',
                ReviewRendererInterface::SHORT_VIEW,
                'category_page_grid',
                false
            );
            if (trim($html) === '') {
                continue;
            }

            $this->items[] = ['id' => $productId, 'html' => $html];
            $this->identities[] = $productId;
            if (count($this->items) >= $limit) {
                break;
            }
        }

        return $this->items;
    }

    private function loadVisibleProduct(int $productId, int $storeId): ?Product
    {
        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
        } catch (\Throwable) {
            return null;
        }

        if ((int) $product->getStatus() !== Status::STATUS_ENABLED
            || in_array((int) $product->getVisibility(), [Visibility::VISIBILITY_NOT_VISIBLE, Visibility::VISIBILITY_IN_SEARCH], true)
        ) {
            return null;
        }

        return $product instanceof Product ? $product : null;
    }

    private function getCurrentProductId(): int
    {
        $product = $this->registry->registry('current_product');

        return $product ? (int) $product->getId() : 0;
    }

    private function getListParent(): ?ListProduct
    {
        $layout = $this->getLayout();
        $parent = $layout->getBlock('elme.product.carousel.list.parent');
        if ($parent instanceof ListProduct) {
            return $parent;
        }

        $parent = $layout->createBlock(ListProduct::class, 'elme.product.carousel.list.parent');

        return $parent instanceof ListProduct ? $parent : null;
    }
}
