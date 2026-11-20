<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\ResourceModel\Entry as EntryResource;
use Bart\ContentStudio\Model\ResourceModel\Entry\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class EntryRepository implements EntryRepositoryInterface
{
    /**
     * Resolved entries for this request, keyed by "identifier@storeId".
     *
     * A storefront page asks for the same entry more than once - the renderer
     * resolves it, then the block resolves it again to render and once more to
     * report its cache identities - and every miss (an identifier that is still
     * a legacy CMS block) costs a query too. On a page with several entries
     * that added up to roughly nine grouped joins per uncached render.
     *
     * `false` is stored for a miss so repeated lookups of a non-Studio
     * identifier stay free as well.
     *
     * @var array<string, EntryInterface|false>
     */
    private array $identifierCache = [];
    /**
     * @param EntryResource $resource
     * @param EntryFactory $entryFactory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly EntryResource $resource,
        private readonly EntryFactory $entryFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(EntryInterface $entry): EntryInterface
    {
        try {
            $this->resource->save($entry);
        } catch (LocalizedException $e) {
            // Already a message written for the person saving (stale version,
            // identifier taken on those store views) - wrapping it would bury
            // it under a generic "Could not save the entry:" prefix.
            $this->identifierCache = [];

            throw $e;
        } catch (\Exception $e) {
            $this->identifierCache = [];

            throw new CouldNotSaveException(__('Could not save the entry: %1', $e->getMessage()), $e);
        }

        $this->identifierCache = [];

        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $entryId): EntryInterface
    {
        $entry = $this->entryFactory->create();
        $this->resource->load($entry, $entryId);

        if (!$entry->getEntryId()) {
            throw new NoSuchEntityException(__('Entry with id "%1" does not exist.', $entryId));
        }

        return $entry;
    }

    /**
     * @inheritDoc
     */
    public function getByIdentifier(string $identifier, int $storeId): EntryInterface
    {
        $cacheKey = $identifier . '@' . $storeId;

        if (!array_key_exists($cacheKey, $this->identifierCache)) {
            $this->identifierCache[$cacheKey] = $this->loadByIdentifier($identifier, $storeId);
        }

        $entry = $this->identifierCache[$cacheKey];

        if ($entry === false) {
            throw new NoSuchEntityException(
                __('Entry with identifier "%1" does not exist for the current store.', $identifier)
            );
        }

        return $entry;
    }

    /**
     * @param string $identifier
     * @param int $storeId
     * @return EntryInterface|false
     */
    private function loadByIdentifier(string $identifier, int $storeId): EntryInterface|false
    {
        $collection = $this->collectionFactory->create();
        $collection->addIdentifierFilter($identifier);
        $collection->addStoreFilter($storeId);
        $collection->orderByStoreSpecificity();
        $collection->setPageSize(1);

        $entry = $collection->getFirstItem();

        return $entry->getEntryId() ? $entry : false;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var SearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(EntryInterface $entry): bool
    {
        try {
            $this->resource->delete($entry);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the entry: %1', $e->getMessage()), $e);
        } finally {
            $this->identifierCache = [];
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $entryId): bool
    {
        return $this->delete($this->getById($entryId));
    }
}
