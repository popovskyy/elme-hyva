<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Pagebuilder;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * One entry's title and status for the "Content Studio" Page Builder type:
 * the picker shows it under the button, the stage decides between the frame
 * and a "no longer exists" notice from it.
 *
 * Resolved by identifier for the default store view, the same lookup the
 * storefront and the stage frame make. An empty JSON array means "not found":
 * that is what Page Builder's block-chooser treats as an unknown selection.
 */
class Metadata extends Action implements HttpGetActionInterface, HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry';

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
        $identifier = trim((string)$this->getRequest()->getParam('identifier'));
        $store = $this->storeManager->getDefaultStoreView();

        if ($identifier === '' || !$store) {
            return $result->setData([]);
        }

        try {
            $entry = $this->entryRepository->getByIdentifier($identifier, (int)$store->getId());
        } catch (NoSuchEntityException $e) {
            return $result->setData([]);
        }

        $typeCode = (string)$entry->getType();
        $type = $this->schemaConfig->getType($typeCode);

        return $result->setData([
            'entry_id' => (int)$entry->getEntryId(),
            'identifier' => (string)$entry->getIdentifier(),
            'title' => (string)($entry->getTitle() ?: $entry->getIdentifier()),
            // A string, as block-chooser compares it: `is_active === '1'`.
            'is_active' => $entry->isActive() ? '1' : '0',
            'type' => $typeCode,
            'type_label' => (string)($type['label'] ?? $typeCode),
            'store_id' => (int)$store->getId(),
        ]);
    }
}
