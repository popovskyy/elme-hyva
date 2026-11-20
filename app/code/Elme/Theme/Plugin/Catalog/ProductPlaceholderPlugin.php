<?php

declare(strict_types=1);

namespace Elme\Theme\Plugin\Catalog;

use Magento\Catalog\Model\View\Asset\Placeholder;
use Magento\Framework\View\Asset\Repository;

/**
 * A product with no photo gets one vector mark. The gallery uses object-fit:
 * scale-down, which will not enlarge a small file, so the SVG is 2000px and
 * the browser scales the vectors into the slot.
 */
class ProductPlaceholderPlugin
{
    private const ASSET = 'Elme_Theme::images/product-placeholder.svg';

    public function __construct(private readonly Repository $assetRepo)
    {
    }

    public function afterGetUrl(Placeholder $subject, string $result): string
    {
        return $this->assetRepo->createAsset(self::ASSET)->getUrl();
    }
}
