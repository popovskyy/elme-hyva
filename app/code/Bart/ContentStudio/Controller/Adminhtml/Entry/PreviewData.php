<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The grid's eye-button preview: which stores the entry can be previewed in,
 * each with the URL of its PreviewFrame document, as JSON for
 * `js/grid/preview.js`.
 *
 * Read-only and admin-only. The frames are admin-served (see PreviewFrame for
 * why not the storefront sandbox).
 */
class PreviewData extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry';

    private const EDIT_PATH = 'bart_content_studio/entry/edit';
    private const FRAME_PATH = 'bart_content_studio/entry/previewFrame';

    /**
     * @param Context $context
     * @param EntryRepositoryInterface $entryRepository
     * @param SchemaConfig $schemaConfig
     * @param StoreManagerInterface $storeManager
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly SchemaConfig $schemaConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $entryId = (int)$this->getRequest()->getParam('entry_id');

        try {
            $entry = $this->entryRepository->getById($entryId);
        } catch (NoSuchEntityException $e) {
            return $result->setData(['success' => false, 'message' => (string)__('This entry no longer exists.')]);
        }

        $type = $this->schemaConfig->getType((string)$entry->getType());
        $stores = array_map(
            fn (StoreInterface $store): array => [
                'id' => (int)$store->getId(),
                'name' => (string)$store->getName(),
                'frameUrl' => $this->getUrl(self::FRAME_PATH, [
                    'entry_id' => $entryId,
                    'store_id' => (int)$store->getId(),
                ]),
            ],
            $this->resolveStores(array_map('intval', $entry->getStoreIds() ?: [0]))
        );

        return $result->setData([
            'success' => true,
            'entry' => [
                'id' => (int)$entry->getEntryId(),
                'identifier' => (string)$entry->getIdentifier(),
                'title' => (string)$entry->getTitle(),
                'typeLabel' => (string)($type['label'] ?? $entry->getType()),
                'isActive' => $entry->isActive(),
            ],
            'stores' => $stores,
            'editUrl' => $this->getUrl(self::EDIT_PATH, ['entry_id' => $entryId]),
        ]);
    }

    /**
     * Active store views the entry is assigned to; `0` means all of them. The
     * admin store is not a storefront - getStores() without `true` - and an
     * inactive view has no theme anyone sees.
     *
     * @param int[] $assigned
     * @return StoreInterface[]
     */
    private function resolveStores(array $assigned): array
    {
        $all = in_array(0, $assigned, true);

        return array_values(array_filter(
            $this->storeManager->getStores(),
            static fn (StoreInterface $store): bool => (bool)$store->getIsActive()
                && ($all || in_array((int)$store->getId(), $assigned, true))
        ));
    }
}
