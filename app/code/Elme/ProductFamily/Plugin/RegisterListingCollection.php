<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Plugin;

use Elme\ProductFamily\Model\FamilyLoader;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Model\ResourceModel\Product\Collection;

/**
 * Lets the family loader fetch the families of a whole category / search page in one query.
 * Registers only; the collection is not loaded here.
 */
class RegisterListingCollection
{
    public function __construct(private readonly FamilyLoader $familyLoader)
    {
    }

    /**
     * @param mixed $result
     * @return mixed
     */
    public function afterGetLoadedProductCollection(ListProduct $subject, $result)
    {
        if ($result instanceof Collection) {
            $this->familyLoader->registerListing($result);
        }
        return $result;
    }
}
