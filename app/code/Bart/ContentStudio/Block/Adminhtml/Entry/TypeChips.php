<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Block\Adminhtml\Entry;

use Bart\ContentStudio\Model\ResourceModel\Entry as EntryResource;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * "Type" quick-filter row shown above the entries grid - one chip per
 * registered content type plus "All types", each carrying the entry count.
 *
 * Deliberately not a ui_component filter: the grid's own advanced-filters
 * panel is hidden entirely (see _grid.less) in favour of this + the search
 * box, so counts are computed once per page load rather than kept live -
 * good enough, since the underlying data only changes on save/delete/import,
 * each of which is a full page reload anyway.
 *
 * Clicking a chip is handled client-side by grid-filters.js, which drives the
 * grid's existing Filters component through its own public API
 * (setData()+apply()) rather than duplicating Magento's filter-request
 * plumbing here.
 */
class TypeChips extends Template
{
    /**
     * @param Context $context
     * @param SchemaConfig $schemaConfig
     * @param EntryResource $entryResource
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly SchemaConfig $schemaConfig,
        private readonly EntryResource $entryResource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array{code: string, label: string, count: int}>
     */
    public function getChips(): array
    {
        $counts = $this->entryResource->getCountsByType();
        $chips = [];

        foreach ($this->schemaConfig->getStandaloneTypes() as $code => $type) {
            $chips[] = [
                'code' => (string)$code,
                'label' => (string)($type['label'] ?? $code),
                'count' => $counts[$code] ?? 0,
            ];
        }

        return $chips;
    }

    /**
     * @return int
     */
    public function getTotalCount(): int
    {
        return array_sum($this->entryResource->getCountsByType());
    }

    /**
     * Namespace of the listing's own ui_component, so the client JS can find
     * the live Filters component instance via uiRegistry without guessing it.
     *
     * @return string
     */
    public function getListingNamespace(): string
    {
        return 'bart_content_studio_entry_listing';
    }
}
