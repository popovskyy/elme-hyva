<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Bart\ContentStudio\Model\EntryFactory;
use Bart\ContentStudio\Model\Preview\DraftStorage;
use Bart\ContentStudio\Model\Preview\TargetProvider;
use Bart\ContentStudio\Model\Schema\SchemaValidator;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

/**
 * Parks the editor's current, unsaved payload where the storefront can read it,
 * and answers with the pages it can be opened on.
 *
 * The same JSON body the save endpoint takes, so the editor has one payload
 * builder and a preview always reflects exactly what a save would write.
 * Nothing is validated here: an entry with an empty required field is
 * precisely the state someone wants to look at before filling it in, and
 * `SchemaValidator::normalize()` alone is enough to hand the storefront
 * templates the shapes they expect.
 *
 * Requires the save permission rather than a preview-specific one: this writes
 * nothing, but it does render unpublished content on the live storefront, and
 * that is the same trust level as publishing it.
 */
class PreviewDraft extends Entry implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry_save';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param EntryRepositoryInterface $entryRepository
     * @param EntryFactory $entryFactory
     * @param SchemaValidator $validator
     * @param DraftStorage $draftStorage
     * @param TargetProvider $targetProvider
     * @param JsonFactory $resultJsonFactory
     * @param JsonSerializer $jsonSerializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        EntryRepositoryInterface $entryRepository,
        private readonly EntryFactory $entryFactory,
        private readonly SchemaValidator $validator,
        private readonly DraftStorage $draftStorage,
        private readonly TargetProvider $targetProvider,
        private readonly JsonFactory $resultJsonFactory,
        private readonly JsonSerializer $jsonSerializer,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context, $resultPageFactory, $entryRepository);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->resultJsonFactory->create();
        $payload = $this->readPayload();

        if ($payload === null) {
            return $result->setData(['success' => false, 'message' => (string)__('Invalid request.')]);
        }

        try {
            return $result->setData($this->createDraft($payload));
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            return $result->setData([
                'success' => false,
                'message' => (string)__('The preview could not be prepared. Please try again.'),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function createDraft(array $payload): array
    {
        $identifier = trim((string)($payload['identifier'] ?? ''));

        if ($identifier === '') {
            // The storefront resolves entries by identifier and nothing else,
            // so there is no page a nameless draft could be shown on.
            return [
                'success' => false,
                'message' => (string)__('Give the entry an identifier first - previews are looked up by it.'),
            ];
        }

        $typeCode = (string)($payload['type'] ?? '');
        $storeIds = $this->readStoreIds($payload);
        $draft = [
            'identifier' => $identifier,
            'type' => $typeCode,
            'title' => $payload['title'] ?? null,
            'store_ids' => $storeIds,
            'data' => $this->validator->normalize(
                $typeCode,
                is_array($payload['data'] ?? null) ? $payload['data'] : []
            ),
        ];

        $token = $this->draftStorage->save($draft);
        $allStores = (bool)($payload['all_stores'] ?? false);

        return [
            'success' => true,
            'targets' => $this->targetProvider->getTargets($this->transientEntry($draft), $token, $allStores),
        ];
    }

    /**
     * An entry object for the resolvers to read, never handed to the repository.
     *
     * @param array<string, mixed> $draft
     * @return EntryInterface
     */
    private function transientEntry(array $draft): EntryInterface
    {
        $entry = $this->entryFactory->create();

        $entry->setIdentifier((string)$draft['identifier']);
        $entry->setType((string)$draft['type']);
        $entry->setIsActive(true);
        $entry->setStoreIds($draft['store_ids']);
        $entry->setEntryData($draft['data']);

        return $entry;
    }

    /**
     * @param array<string, mixed> $payload
     * @return int[]
     */
    private function readStoreIds(array $payload): array
    {
        $storeIds = is_array($payload['store_ids'] ?? null) ? $payload['store_ids'] : [0];

        return array_map('intval', $storeIds) ?: [0];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readPayload(): ?array
    {
        $body = $this->getRequest()->getContent();

        if (!is_string($body) || $body === '') {
            return null;
        }

        try {
            $decoded = $this->jsonSerializer->unserialize($body);
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
