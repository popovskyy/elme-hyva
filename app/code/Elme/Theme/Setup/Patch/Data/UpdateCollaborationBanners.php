<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-89 QA: seed production Three Birds desktop/mobile hero banners on the existing entry.
 *
 * CreateCollaborationPage already ran with empty banner fields; a later edit of entryData()
 * does not reach it. This patch syncs the banner files and fills those fields only.
 */
class UpdateCollaborationBanners implements DataPatchInterface
{
    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        CreateCollaborationPage::syncImagesToMedia();

        try {
            $entry = $this->entryRepository->getByIdentifier(
                CreateCollaborationPage::ENTRY_IDENTIFIER,
                Store::DEFAULT_STORE_ID
            );
        } catch (NoSuchEntityException) {
            return $this;
        }

        $mediaBase = CreateCollaborationPage::mediaBaseUrl($this->storeManager);
        $data = is_array($entry->getEntryData()) ? $entry->getEntryData() : [];
        $entry->setEntryData(CreateCollaborationPage::withBannerFields($data, $mediaBase));
        $this->entryRepository->save($entry);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [CreateCollaborationPage::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
