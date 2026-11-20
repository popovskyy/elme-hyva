<?php

declare(strict_types=1);

namespace Elme\RecentlyViewed\Block;

use Hyva\Theme\ViewModel\ProductListItem;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\ListProduct;
use Magento\Catalog\Block\Product\ReviewRendererInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Renders FE-19 product cards for recently viewed product ids (AJAX strip).
 */
class Cards extends Template
{
    /** @var int[] */
    private array $orderedIds = [];

    public function __construct(
        Context $context,
        private readonly ProductListItem $productListItem,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @param int[] $ids Newest-first order from the browser
     */
    public function setOrderedIds(array $ids): self
    {
        $this->orderedIds = array_values(array_unique(array_filter(array_map('intval', $ids))));

        return $this;
    }

    /**
     * @return int[]
     */
    public function getOrderedIds(): array
    {
        return $this->orderedIds;
    }

    public function getItemHtmlForId(int $productId): string
    {
        try {
            $product = $this->productRepository->getById(
                $productId,
                false,
                (int) $this->storeManager->getStore()->getId()
            );
        } catch (\Throwable) {
            return '';
        }

        if ((int) $product->getStatus() !== Status::STATUS_ENABLED) {
            return '';
        }

        if ((int) $product->getVisibility() === Visibility::VISIBILITY_NOT_VISIBLE) {
            return '';
        }

        $product->setData('image_custom_attributes', ['loading' => 'lazy']);

        $parent = $this->getListParent();
        if (!$parent) {
            return '';
        }

        return $this->productListItem->getItemHtml(
            $product,
            $parent,
            'grid',
            ReviewRendererInterface::SHORT_VIEW,
            'category_page_grid',
            false
        );
    }

    private function getListParent(): ?ListProduct
    {
        $layout = $this->getLayout();
        $parent = $layout->getBlock('elme.recently.viewed.list.parent');
        if ($parent instanceof ListProduct) {
            return $parent;
        }

        $parent = $layout->createBlock(ListProduct::class, 'elme.recently.viewed.list.parent');

        return $parent instanceof ListProduct ? $parent : null;
    }
}
