<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Plugin;

use Elme\ProductFamily\Model\FamilyLoader;
use Magento\Catalog\Model\Product;

/**
 * Adds the family tag (old and new family_code) to a product's identities, so saving a product
 * purges the pages of every sibling that shows its swatch, also when it joins or leaves a family.
 */
class AddFamilyCacheTag
{
    /**
     * @param string[] $result
     * @return string[]
     */
    public function afterGetIdentities(Product $subject, array $result): array
    {
        $codes = [
            trim((string) $subject->getData(FamilyLoader::ATTR_CODE)),
            trim((string) $subject->getOrigData(FamilyLoader::ATTR_CODE)),
        ];
        foreach (array_unique(array_filter($codes)) as $code) {
            $result[] = FamilyLoader::cacheTag($code);
        }
        return $result;
    }
}
