<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-77: replace soft sample lookbook JPEGs with sharp lifestyle frames and re-pin hotspots.
 *
 * CreateShopTheLooksPage returns early when the gallery entry already exists, so a patch-only
 * edit of that class never updates this database. This patch re-syncs media and rewrites the
 * saved entry (same SKUs, new x/y and image copy).
 */
class UpdateShopTheLooksSharpFrames implements DataPatchInterface
{
    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        CreateShopTheLooksPage::syncImagesToMedia();

        try {
            $entry = $this->entryRepository->getByIdentifier(
                CreateShopTheLooksPage::ENTRY_IDENTIFIER,
                Store::DEFAULT_STORE_ID
            );
        } catch (NoSuchEntityException) {
            return $this;
        }

        $mediaBase = CreateShopTheLooksPage::mediaBaseUrl($this->storeManager);
        $entry->setEntryData(CreateShopTheLooksPage::galleryData($mediaBase));
        $this->entryRepository->save($entry);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [CreateShopTheLooksPage::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
