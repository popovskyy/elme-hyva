<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Api;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface EntryRepositoryInterface
{
    /**
     * @param EntryInterface $entry
     * @return EntryInterface
     * @throws CouldNotSaveException
     */
    public function save(EntryInterface $entry): EntryInterface;

    /**
     * @param int $entryId
     * @return EntryInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $entryId): EntryInterface;

    /**
     * Resolves an entry by identifier for a given store, falling back to
     * All Store Views. Does not filter by is_active - callers decide.
     *
     * @param string $identifier
     * @param int $storeId
     * @return EntryInterface
     * @throws NoSuchEntityException
     */
    public function getByIdentifier(string $identifier, int $storeId): EntryInterface;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * @param EntryInterface $entry
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(EntryInterface $entry): bool;

    /**
     * @param int $entryId
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $entryId): bool;
}
