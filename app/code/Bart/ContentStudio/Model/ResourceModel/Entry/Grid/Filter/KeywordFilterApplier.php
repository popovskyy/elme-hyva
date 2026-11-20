<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ResourceModel\Entry\Grid\Filter;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\FilterApplierInterface;

/**
 * Matches the grid's search box against title, identifier and (for a purely
 * numeric query) the entry ID - three columns, one box, OR'd together.
 *
 * Registered under condition type `studio_keyword` in di.xml against the
 * global {@see \Magento\Framework\View\Element\UiComponent\DataProvider\FilterPool},
 * the same extension point Magento's own `regular` and `fulltext` appliers use.
 * The key is namespaced, so this only ever activates for a filter that
 * explicitly asks for it - every other grid in the admin is untouched.
 */
class KeywordFilterApplier implements FilterApplierInterface
{
    /**
     * @inheritDoc
     * @throws \InvalidArgumentException
     */
    public function apply(Collection $collection, Filter $filter): void
    {
        if (!$collection instanceof AbstractDb) {
            throw new \InvalidArgumentException('Database collection required.');
        }

        $value = trim((string)$filter->getValue());

        if ($value === '') {
            return;
        }

        $connection = $collection->getConnection();
        // '%' and '_' in $value are not escaped before wrapping - a literal
        // wildcard typed into the search box behaves as one, same trade-off
        // Magento's own core LIKE filters make. Not a safety issue: quoteInto()
        // binds $value as a single parameter, so this stays injection-safe
        // regardless.
        $like = '%' . $value . '%';
        $conditions = [
            $connection->quoteInto('title LIKE ?', $like),
            $connection->quoteInto('identifier LIKE ?', $like),
        ];

        // A bare number is almost certainly someone pasting an ID from a URL
        // or another grid row, not a coincidental substring - worth matching
        // exactly rather than only as a LIKE fragment against a numeric column.
        if (ctype_digit($value)) {
            $conditions[] = $connection->quoteInto('entry_id = ?', $value);
        }

        $collection->getSelect()->where('(' . implode(' OR ', $conditions) . ')');
    }
}
