<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Catalog\Model\ResourceModel\Category as CategoryResource;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-18: replace the placeholder category posters with the lifestyle photos already used on the
 * home hero. Only an empty image or a fe18- placeholder is written, on whichever store holds it;
 * a photo an editor set stays. A store view with no row of its own keeps inheriting the default.
 *
 * Furniture uses the vase interior and Outdoor the orchid wall: those are the wide shots in the
 * theme that do not already have lettering baked in. A department that is not in this catalog is skipped.
 *
 * The write is SQL on purpose. Category::getResource()->saveAttribute() matches a row by entity and
 * attribute only, so a default value and a store-view poster can be the same attribute and the update
 * lands on one of them.
 */
class AssignCategoryBannerPhotos implements DataPatchInterface
{
    private const MEDIA = 'catalog/category';

    /** @var array<string, array{desktop: string, mobile: string, desktop_name: string, mobile_name: string}> */
    private const ASSIGNMENTS = [
        'flowers-plants' => [
            'desktop' => 'sample-images/stems-wide.jpg',
            'mobile' => 'sample-images/stems-tall.jpg',
            'desktop_name' => 'elme-banner-flowers.jpg',
            'mobile_name' => 'elme-banner-flowers-mobile.jpg',
        ],
        'homewares' => [
            'desktop' => 'sample-images/vases-desktop.jpg',
            'mobile' => 'sample-images/vases-mobile.jpg',
            'desktop_name' => 'elme-banner-homewares.jpg',
            'mobile_name' => 'elme-banner-homewares-mobile.jpg',
        ],
        'furniture' => [
            'desktop' => 'sample-images/vases-desktop.jpg',
            'mobile' => 'sample-images/vases-mobile.jpg',
            'desktop_name' => 'elme-banner-furniture.jpg',
            'mobile_name' => 'elme-banner-furniture-mobile.jpg',
        ],
        'outdoor' => [
            'desktop' => 'sample-images/orchids-wide.jpg',
            'mobile' => 'sample-images/orchids-tall.jpg',
            'desktop_name' => 'elme-banner-outdoor.jpg',
            'mobile_name' => 'elme-banner-outdoor-mobile.jpg',
        ],
    ];

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly CategoryResource $categoryResource
    ) {
    }

    public function apply(): self
    {
        foreach (self::ASSIGNMENTS as $urlKey => $files) {
            if (!$this->publish($files)) {
                continue;
            }

            $categoryId = $this->categoryId($urlKey);
            if ($categoryId === null) {
                continue;
            }

            $this->replace($categoryId, 'image', $files['desktop_name']);
            $this->replace($categoryId, 'mobile_image', $files['mobile_name']);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [AddCategoryBannerAttributes::class];
    }

    public function getAliases(): array
    {
        return [];
    }

    /**
     * @param array{desktop: string, mobile: string, desktop_name: string, mobile_name: string} $files
     */
    private function publish(array $files): bool
    {
        $targetDir = BP . '/pub/media/' . self::MEDIA;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return false;
        }

        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/';
        $desktop = $sourceDir . $files['desktop'];
        $mobile = $sourceDir . $files['mobile'];
        if (!is_file($desktop) || !is_file($mobile)) {
            return false;
        }

        return copy($desktop, $targetDir . '/' . $files['desktop_name'])
            && copy($mobile, $targetDir . '/' . $files['mobile_name']);
    }

    private function categoryId(string $urlKey): ?int
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId(Store::DEFAULT_STORE_ID);
        $collection->addAttributeToFilter('url_key', $urlKey);
        $collection->setPageSize(1);
        $id = (int) $collection->getFirstItem()->getId();

        return $id > 0 ? $id : null;
    }

    private function replace(int $categoryId, string $attributeCode, string $filename): void
    {
        $attribute = $this->categoryResource->getAttribute($attributeCode);
        if (!$attribute || !$attribute->getId()) {
            return;
        }

        $connection = $this->categoryResource->getConnection();
        $table = $attribute->getBackend()->getTable();
        $attributeId = (int) $attribute->getId();
        $placeholder = implode(' OR ', [
            "LOWER(value) LIKE 'fe18-%'",
            "LOWER(value) LIKE '%/fe18-%'",
            "LOWER(value) LIKE '%now-open%'",
            "LOWER(value) LIKE '%now\\_open%'",
            "value = ''",
        ]);

        $connection->update(
            $table,
            ['value' => $filename],
            implode(' AND ', [
                $connection->quoteInto('entity_id = ?', $categoryId),
                $connection->quoteInto('attribute_id = ?', $attributeId),
                '(' . $placeholder . ')',
            ])
        );

        $defaultRow = $connection->fetchOne(
            $connection->select()
                ->from($table, ['value_id'])
                ->where('entity_id = ?', $categoryId)
                ->where('attribute_id = ?', $attributeId)
                ->where('store_id = ?', Store::DEFAULT_STORE_ID)
                ->limit(1)
        );

        if ($defaultRow) {
            return;
        }

        $connection->insert($table, [
            'attribute_id' => $attributeId,
            'store_id' => Store::DEFAULT_STORE_ID,
            'entity_id' => $categoryId,
            'value' => $filename,
        ]);
    }
}
