<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-77: the first look is the eucalyptus room, not the soft living-room sample.
 *
 * UpdateShopTheLooksSharpFrames has already run, so a later edit of galleryData()
 * does not reach an existing entry. This patch copies the new frame and rewrites it.
 */
class UpdateShopTheLooksRoomFrame implements DataPatchInterface
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
        return [UpdateShopTheLooksSharpFrames::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
