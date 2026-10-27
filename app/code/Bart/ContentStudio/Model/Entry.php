<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Model\ResourceModel\Entry as EntryResource;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * A Content Studio entry. See {@see EntryInterface} for the field contract.
 */
class Entry extends AbstractModel implements EntryInterface, IdentityInterface
{
    public const CACHE_TAG = 'bart_content_studio_entry';

    /**
     * @var string
     */
    protected $_cacheTag = self::CACHE_TAG;

    /**
     * @var string
     */
    protected $_eventPrefix = 'bart_content_studio_entry';

    /**
     * @var string
     */
    protected $_eventObject = 'entry';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param Json $serializer
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        private readonly Json $serializer,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(EntryResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        $tags = [self::CACHE_TAG];

        if ($this->getIdentifier()) {
            $tags[] = self::CACHE_TAG . '_' . $this->getIdentifier();
        }

        return $tags;
    }

    /**
     * @inheritDoc
     */
    public function getEntryId(): ?int
    {
        $id = $this->getData(self::ENTRY_ID);

        return $id !== null ? (int)$id : null;
    }

    /**
     * @inheritDoc
     */
    public function setEntryId(int $entryId): EntryInterface
    {
        return $this->setData(self::ENTRY_ID, $entryId);
    }

    /**
     * @inheritDoc
     */
    public function getIdentifier(): ?string
    {
        return $this->getData(self::IDENTIFIER);
    }

    /**
     * @inheritDoc
     */
    public function setIdentifier(string $identifier): EntryInterface
    {
        return $this->setData(self::IDENTIFIER, $identifier);
    }

    /**
     * @inheritDoc
     */
    public function getType(): ?string
    {
        return $this->getData(self::TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setType(string $type): EntryInterface
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): ?string
    {
        return $this->getData(self::TITLE);
    }

    /**
     * @inheritDoc
     */
    public function setTitle(?string $title): EntryInterface
    {
        return $this->setData(self::TITLE, $title);
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return (bool)$this->getData(self::IS_ACTIVE);
    }

    /**
     * @inheritDoc
     */
    public function setIsActive(bool $isActive): EntryInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    /**
     * @inheritDoc
     */
    public function getEntryData(): array
    {
        $raw = (string)$this->getData(self::DATA);

        if ($raw === '') {
            return [];
        }

        try {
            $decoded = $this->serializer->unserialize($raw);
        } catch (\InvalidArgumentException $e) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @inheritDoc
     */
    public function setEntryData(array $data): EntryInterface
    {
        return $this->setData(self::DATA, $this->serializer->serialize($data));
    }

    /**
     * @inheritDoc
     */
    public function getVersion(): int
    {
        return (int)$this->getData(self::VERSION);
    }

    /**
     * @inheritDoc
     */
    public function setVersion(int $version): EntryInterface
    {
        return $this->setData(self::VERSION, $version);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getStoreIds(): array
    {
        return array_map('intval', (array)$this->getData(self::STORE_IDS));
    }

    /**
     * @inheritDoc
     */
    public function setStoreIds(array $storeIds): EntryInterface
    {
        return $this->setData(self::STORE_IDS, array_map('intval', $storeIds));
    }
}
