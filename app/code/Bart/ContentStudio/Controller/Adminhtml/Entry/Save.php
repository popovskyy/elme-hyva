<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Controller\Adminhtml\Entry;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Controller\Adminhtml\Entry;
use Bart\ContentStudio\Model\EntryFactory;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Bart\ContentStudio\Model\Schema\SchemaValidator;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

/**
 * Saves a Content Studio entry from the editor's JSON payload.
 *
 * A JSON API rather than a classic form POST + redirect: the editor is a
 * single mounted view, and returning structured {success, errors, version}
 * lets it show inline field errors or an optimistic-lock conflict without a
 * page reload (see plan section 3, section 7).
 */
class Save extends Entry implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Bart_ContentStudio::entry_save';

    /**
     * Tries at a free identifier for a copy before giving up.
     */
    private const DUPLICATE_ATTEMPTS = 5;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param EntryRepositoryInterface $entryRepository
     * @param EntryFactory $entryFactory
     * @param SchemaValidator $validator
     * @param SchemaConfig $schemaConfig
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
        private readonly SchemaConfig $schemaConfig,
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
            return $result->setData(['success' => false, 'errors' => ['_root' => (string)__('Invalid request.')]]);
        }

        try {
            return $result->setData($this->save($payload));
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'errors' => ['_root' => $e->getMessage()]]);
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);

            return $result->setData([
                'success' => false,
                'errors' => ['_root' => (string)__('Something went wrong while saving. Please try again.')],
            ]);
        }
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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function save(array $payload): array
    {
        $typeCode = (string)($payload['type'] ?? '');
        $entryId = (int)($payload['entry_id'] ?? 0);
        $submittedVersion = (int)($payload['version'] ?? 0);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        $entry = $this->resolveEntry($entryId);
        $conflict = $this->checkOptimisticLock($entry, $entryId, $submittedVersion);

        if ($conflict !== null) {
            return $conflict;
        }

        $normalizedData = $this->validator->normalize($typeCode, $data);
        $errors = array_merge(
            $this->validateMeta($payload),
            $this->validator->validate($typeCode, $normalizedData)
        );

        if ($errors !== []) {
            return ['success' => false, 'errors' => $errors];
        }

        // Dry run for the editor's pre-save review. Everything above has already
        // happened - the row was resolved, the optimistic lock checked, the data
        // normalized and validated - and nothing below it writes, so this
        // returns the same verdict the real save would reach.
        //
        // Deliberately this method rather than a second controller: a separate
        // validation path is a path that can drift from what actually saves,
        // and then the review gate starts passing entries the save rejects.
        if (!empty($payload['validateOnly'])) {
            return ['success' => true, 'validated' => true];
        }

        $this->applyPayload($entry, $payload, $typeCode, $normalizedData);
        $this->entryRepository->save($entry);

        $result = [
            'success' => true,
            'entryId' => $entry->getEntryId(),
            'version' => $entry->getVersion(),
        ];

        if (!empty($payload['duplicate']) && $this->isCopyable($typeCode)) {
            // Save-and-copy: the entry in front of the user is written first,
            // so nothing they typed is lost, and only then copied. Reusing this
            // method means the copy went through the same validation.
            $result['redirectUrl'] = $this->duplicate($entry, $typeCode, $normalizedData);
        }

        return $result;
    }

    /**
     * Writes a copy of `$entry` under a free identifier and returns its edit URL.
     *
     * The identifier has to stay unique per store-view set, and that rule lives
     * in the resource model rather than in a DB constraint (an identifier may
     * legitimately repeat across brands), so uniqueness is settled by trying a
     * suffix and letting the save reject it. A handful of attempts is plenty
     * against a four-digit suffix.
     *
     * @param EntryInterface $entry
     * @param string $typeCode
     * @param array<string, mixed> $normalizedData
     * @return string
     * @throws LocalizedException
     */
    private function duplicate(EntryInterface $entry, string $typeCode, array $normalizedData): string
    {
        $baseIdentifier = (string)$entry->getIdentifier();
        $lastError = null;

        for ($attempt = 0; $attempt < self::DUPLICATE_ATTEMPTS; $attempt++) {
            $copy = $this->entryFactory->create();
            $copy->setType($typeCode);
            $copy->setIdentifier($baseIdentifier . '-' . random_int(1000, 9999));
            $copy->setTitle($this->copyTitle($entry));
            $copy->setIsActive($entry->isActive());
            $copy->setStoreIds($entry->getStoreIds());
            $copy->setEntryData($normalizedData);

            try {
                $this->entryRepository->save($copy);

                return $this->getUrl('bart_content_studio/entry/edit', ['entry_id' => $copy->getEntryId()]);
            } catch (LocalizedException $e) {
                $lastError = $e;
            }
        }

        throw $lastError ?? new LocalizedException(__('The copy could not be created.'));
    }

    /**
     * Absent `copyable` means yes - only an explicit false opts a type out,
     * for an entry bound to one host record, where a copy would be an orphan
     * nothing points at.
     *
     * @param string $typeCode
     * @return bool
     */
    private function isCopyable(string $typeCode): bool
    {
        $type = $this->schemaConfig->getType($typeCode);

        return $type === null || ($type['copyable'] ?? true) !== false;
    }

    /**
     * @param EntryInterface $entry
     * @return string
     */
    private function copyTitle(EntryInterface $entry): string
    {
        $title = trim((string)$entry->getTitle());

        return $title === '' ? (string)__('Copy') : $title . ' ' . __('(copy)');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function validateMeta(array $payload): array
    {
        $identifier = trim((string)($payload['identifier'] ?? ''));

        return $identifier === '' ? ['meta.identifier' => (string)__('Identifier is required.')] : [];
    }

    /**
     * @param int $entryId
     * @return EntryInterface
     * @throws NoSuchEntityException When `$entryId` is set but the row is gone.
     */
    private function resolveEntry(int $entryId): EntryInterface
    {
        if (!$entryId) {
            return $this->entryFactory->create();
        }

        // Do not fall through to create(): a stale tab or deleted row must
        // fail the save, not silently fork a new entry under the same payload.
        return $this->entryRepository->getById($entryId);
    }

    /**
     * Rejects a save whose submitted version is behind the persisted one -
     * someone else saved this entry since the editor loaded it (plan section 7).
     *
     * @param EntryInterface $entry
     * @param int $entryId
     * @param int $submittedVersion
     * @return array<string, mixed>|null
     */
    private function checkOptimisticLock(EntryInterface $entry, int $entryId, int $submittedVersion): ?array
    {
        if (!$entryId || !$entry->getEntryId()) {
            return null;
        }

        if ($entry->getVersion() === $submittedVersion) {
            return null;
        }

        $message = __(
            'Someone else saved this entry since you opened it. Please reload and reapply your changes.'
        );

        return ['success' => false, 'errors' => ['_root' => (string)$message]];
    }

    /**
     * @param EntryInterface $entry
     * @param array<string, mixed> $payload
     * @param string $typeCode
     * @param array<string, mixed> $normalizedData
     * @return void
     */
    private function applyPayload(EntryInterface $entry, array $payload, string $typeCode, array $normalizedData): void
    {
        $entry->setType($typeCode);
        $entry->setIdentifier(trim((string)($payload['identifier'] ?? '')));
        // `?? null` rather than a bare read: the payload is free-form JSON off
        // an HTTP endpoint, so the key is not guaranteed to be there. Without
        // it the undefined-key warning becomes an exception in developer mode
        // and the save comes back as the generic error.
        $title = $payload['title'] ?? null;

        $entry->setTitle($title !== null ? (string)$title : null);
        $entry->setIsActive((bool)($payload['is_active'] ?? true));
        $entry->setStoreIds($this->readStoreIds($payload));
        $entry->setEntryData($normalizedData);
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
}
