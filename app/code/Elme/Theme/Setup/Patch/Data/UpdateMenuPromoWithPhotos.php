<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-08 follow-up: replace gradient menu promo placeholders with real Elme photos.
 */
class UpdateMenuPromoWithPhotos implements DataPatchInterface
{
    public function __construct(
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly BlockRepositoryInterface $blockRepository
    ) {
    }

    public function apply(): self
    {
        CreateDesktopMegaMenu::syncPromoImagesToMedia();
        foreach (CreateDesktopMegaMenu::PROMO_BLOCKS as $identifier => $meta) {
            try {
                $block = $this->getBlockByIdentifier->execute($identifier, Store::DEFAULT_STORE_ID);
            } catch (NoSuchEntityException) {
                continue;
            }
            $block->setContent(CreateDesktopMegaMenu::promoHtml($meta));
            $this->blockRepository->save($block);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [CreateDesktopMegaMenu::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
