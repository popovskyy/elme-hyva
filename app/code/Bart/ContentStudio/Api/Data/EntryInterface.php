<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Api\Data;

/**
 * A single Content Studio entry: a content-type instance plus admin metadata.
 */
interface EntryInterface
{
    public const ENTRY_ID = 'entry_id';
    public const IDENTIFIER = 'identifier';
    public const TYPE = 'type';
    public const TITLE = 'title';
    public const IS_ACTIVE = 'is_active';
    public const DATA = 'data';
    public const VERSION = 'version';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';
    public const STORE_IDS = 'store_ids';

    /**
     * @return int|null
     */
    public function getEntryId(): ?int;

    /**
     * @param int $entryId
     * @return $this
     */
    public function setEntryId(int $entryId): self;

    /**
     * @return string|null
     */
    public function getIdentifier(): ?string;

    /**
     * @param string $identifier
     * @return $this
     */
    public function setIdentifier(string $identifier): self;

    /**
     * @return string|null
     */
    public function getType(): ?string;

    /**
     * @param string $type
     * @return $this
     */
    public function setType(string $type): self;

    /**
     * @return string|null
     */
    public function getTitle(): ?string;

    /**
     * @param string|null $title
     * @return $this
     */
    public function setTitle(?string $title): self;

    /**
     * @return bool
     */
    public function isActive(): bool;

    /**
     * @param bool $isActive
     * @return $this
     */
    public function setIsActive(bool $isActive): self;

    /**
     * Content payload, decoded from the stored JSON and already validated
     * against the type's schema. Distinct from the inherited generic
     * DataObject::getData($key) accessor used for object attributes.
     *
     * @return array<string, mixed>
     */
    public function getEntryData(): array;

    /**
     * @param array<string, mixed> $data
     * @return $this
     */
    public function setEntryData(array $data): self;

    /**
     * @return int
     */
    public function getVersion(): int;

    /**
     * @param int $version
     * @return $this
     */
    public function setVersion(int $version): self;

    /**
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Store view ids this entry is assigned to. [0] means All Store Views.
     *
     * @return int[]
     */
    public function getStoreIds(): array;

    /**
     * @param int[] $storeIds
     * @return $this
     */
    public function setStoreIds(array $storeIds): self;
}
