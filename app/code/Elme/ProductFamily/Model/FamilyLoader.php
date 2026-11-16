<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Loads the members of a product family (enabled, visible simple products sharing family_code).
 *
 * Results are kept per family code for the request. A product listing registers its collection
 * (Plugin\RegisterListingCollection); the first lookup after that collection is loaded fetches the
 * families of every product on the page in one query instead of one query per card.
 */
class FamilyLoader
{
    public const ATTR_CODE = 'family_code';
    public const ATTR_AXES = 'family_axes';
    public const ATTR_SORT = 'family_sort_order';
    public const ATTR_RANGE = 'range';
    public const ATTR_MADE_TO_ORDER = 'made_to_order';
    public const ATTR_CONDITION = 'condition_code';
    public const CACHE_TAG = 'elme_pf';

    /** @var array<string, array<int, Product>> */
    private array $cache = [];

    /** @var array<string, array<int, Product>> */
    private array $rangeCache = [];

    /** @var Collection[] */
    private array $listings = [];

    /** @var array<string, AbstractAttribute>|null */
    private ?array $axisAttributes = null;

    /**
     * @param string[] $axisCodes attributes that may be a family axis, in default order
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Visibility $visibility,
        private readonly EavConfig $eavConfig,
        private readonly array $axisCodes = []
    ) {
    }

    /**
     * Attributes that can be a family axis: the configured codes that exist as option attributes.
     *
     * @return array<string, AbstractAttribute> keyed by code, in the configured order
     */
    public function getAxisAttributes(): array
    {
        if ($this->axisAttributes !== null) {
            return $this->axisAttributes;
        }
        $this->axisAttributes = [];
        foreach ($this->axisCodes as $code) {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
            if ($attribute && $attribute->getId() && $attribute->usesSource()
                && $attribute->getFrontendInput() !== 'multiselect') {
                $this->axisAttributes[$code] = $attribute;
            }
        }
        return $this->axisAttributes;
    }

    /**
     * Cache tag of a family: pages that show its swatches carry it, saving a member purges it.
     */
    public static function cacheTag(string $familyCode): string
    {
        return self::CACHE_TAG . '_' . md5(trim($familyCode));
    }

    public function registerListing(Collection $collection): void
    {
        $this->listings[spl_object_id($collection)] = $collection;
    }

    /**
     * @return array<int, Product> members keyed by product id
     */
    public function getMembers(string $familyCode): array
    {
        $familyCode = trim($familyCode);
        if ($familyCode === '') {
            return [];
        }
        if (!isset($this->cache[$familyCode])) {
            $this->preloadListings();
        }
        if (!isset($this->cache[$familyCode])) {
            $this->load([$familyCode]);
        }
        return $this->cache[$familyCode] ?? [];
    }

    /**
     * One query for the families of every product in the listings that are already loaded.
     */
    private function preloadListings(): void
    {
        $codes = [];
        foreach ($this->listings as $key => $collection) {
            if (!$collection->isLoaded()) {
                continue;
            }
            foreach ($collection->getItems() as $product) {
                $code = trim((string) $product->getData(self::ATTR_CODE));
                if ($code !== '' && !isset($this->cache[$code])) {
                    $codes[$code] = $code;
                }
            }
            unset($this->listings[$key]);
        }
        if ($codes) {
            $this->load(array_values($codes));
        }
    }

    /**
     * Enabled, visible simple products of one range (the configurations of a design share it), keyed
     * by product id. One query per range for the request.
     *
     * @return array<int, Product>
     */
    public function getRangeProducts(string $range): array
    {
        $range = trim($range);
        if ($range === '') {
            return [];
        }
        if (!isset($this->rangeCache[$range])) {
            $this->rangeCache[$range] = [];
            $collection = $this->createCollection();
            $collection->addAttributeToFilter(self::ATTR_RANGE, ['eq' => $range]);
            /** @var Product $product */
            foreach ($collection as $product) {
                $this->rangeCache[$range][(int) $product->getId()] = $product;
            }
        }
        return $this->rangeCache[$range];
    }

    /**
     * Cache tag of a range: the configurations of a page carry it.
     */
    public static function rangeCacheTag(string $range): string
    {
        return self::CACHE_TAG . '_r_' . md5(trim($range));
    }

    /**
     * @param string[] $familyCodes
     */
    private function load(array $familyCodes): void
    {
        foreach ($familyCodes as $code) {
            $this->cache[$code] = [];
        }

        $collection = $this->createCollection();
        $collection->addAttributeToFilter(self::ATTR_CODE, ['in' => $familyCodes]);

        /** @var Product $product */
        foreach ($collection as $product) {
            $code = trim((string) $product->getData(self::ATTR_CODE));
            if (isset($this->cache[$code])) {
                $this->cache[$code][(int) $product->getId()] = $product;
            }
        }
    }

    /**
     * Enabled, visible simple products of this store with everything a picker, a chip or a card needs.
     * Configurables (old data) are never members: every variant is its own simple product.
     */
    private function createCollection(): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId((int) $this->storeManager->getStore()->getId());
        $collection->addStoreFilter();
        $collection->addAttributeToSelect(array_merge(
            ['name', 'url_key', 'small_image', 'thumbnail', 'swatch_image', self::ATTR_CODE, self::ATTR_AXES, self::ATTR_SORT],
            $this->existingAttributes([self::ATTR_RANGE, self::ATTR_MADE_TO_ORDER, self::ATTR_CONDITION]),
            array_keys($this->getAxisAttributes())
        ));
        $collection->addAttributeToFilter('type_id', ['eq' => Type::TYPE_SIMPLE]);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->visibility->getVisibleInSiteIds());
        $collection->addUrlRewrite();

        return $collection;
    }

    /**
     * @param string[] $codes
     * @return string[] the codes that exist as product attributes on this instance
     */
    private function existingAttributes(array $codes): array
    {
        return array_values(array_filter($codes, function (string $code): bool {
            $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);
            return $attribute && $attribute->getId();
        }));
    }
}
