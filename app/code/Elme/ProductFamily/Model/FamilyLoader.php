<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
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
    public const CACHE_TAG = 'elme_pf';

    /** @var array<string, array<int, Product>> */
    private array $cache = [];

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
     * @param string[] $familyCodes
     */
    private function load(array $familyCodes): void
    {
        foreach ($familyCodes as $code) {
            $this->cache[$code] = [];
        }

        $collection = $this->collectionFactory->create();
        $collection->setStoreId((int) $this->storeManager->getStore()->getId());
        $collection->addStoreFilter();
        $collection->addAttributeToSelect(array_merge(
            ['name', 'url_key', 'small_image', 'thumbnail', 'swatch_image', self::ATTR_CODE, self::ATTR_AXES, self::ATTR_SORT],
            array_keys($this->getAxisAttributes())
        ));
        $collection->addAttributeToFilter(self::ATTR_CODE, ['in' => $familyCodes]);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->setVisibility($this->visibility->getVisibleInSiteIds());
        $collection->addUrlRewrite();

        /** @var Product $product */
        foreach ($collection as $product) {
            $code = trim((string) $product->getData(self::ATTR_CODE));
            if (isset($this->cache[$code])) {
                $this->cache[$code][(int) $product->getId()] = $product;
            }
        }
    }
}
