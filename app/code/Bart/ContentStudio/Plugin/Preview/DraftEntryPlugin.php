<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Plugin\Preview;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Bart\ContentStudio\Model\Preview\DraftContext;

/**
 * Serves the editor's unsaved draft in place of the stored entry, for the one
 * identifier being previewed.
 *
 * Wrapping the repository rather than {@see \Bart\ContentStudio\Block\Entry}
 * is the point of the design: every storefront consumer already resolves
 * entries through `getByIdentifier()` - the block, the widget and the Page
 * Builder content type all render through it. One plugin therefore reaches
 * all of them, and no consumer needs a preview branch.
 *
 * Frontend only (etc/frontend/di.xml). In the admin area the repository must
 * keep returning what is actually stored - the grid, export and the optimistic
 * lock all depend on it.
 */
class DraftEntryPlugin
{
    /**
     * @param DraftContext $draftContext
     * @param EntryFactory $entryFactory
     */
    public function __construct(
        private readonly DraftContext $draftContext,
        private readonly EntryFactory $entryFactory
    ) {
    }

    /**
     * @param EntryRepositoryInterface $subject
     * @param callable $proceed
     * @param string $identifier
     * @param int $storeId
     * @return EntryInterface
     */
    public function aroundGetByIdentifier(
        EntryRepositoryInterface $subject,
        callable $proceed,
        string $identifier,
        int $storeId
    ): EntryInterface {
        $draft = $this->draftContext->getDraftFor($identifier);

        if ($draft === null) {
            return $proceed($identifier, $storeId);
        }

        return $this->buildDraftEntry($draft, $identifier);
    }

    /**
     * An entry object that was never persisted and never will be.
     *
     * Always active: previewing is most useful *before* an entry is switched
     * on, and `Block\Entry::resolveEntry()` drops a disabled entry on the
     * floor. A draft that reached this point was
     * requested explicitly by an admin, so there is nothing left to gate.
     *
     * @param array<string, mixed> $draft
     * @param string $identifier
     * @return EntryInterface
     */
    private function buildDraftEntry(array $draft, string $identifier): EntryInterface
    {
        $entry = $this->entryFactory->create();

        $entry->setIdentifier($identifier);
        $entry->setType((string)($draft['type'] ?? ''));
        $entry->setTitle(isset($draft['title']) ? (string)$draft['title'] : null);
        $entry->setIsActive(true);
        $entry->setStoreIds(array_map('intval', (array)($draft['store_ids'] ?? [0])));
        $entry->setEntryData(is_array($draft['data'] ?? null) ? $draft['data'] : []);

        return $entry;
    }
}
