<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ImportExport;

use Bart\ContentStudio\Api\Data\EntryInterface;

/**
 * Picks a filename per entry for export. Identifiers are not unique (the
 * same identifier can carry different content per store, see plan section 2), so a
 * plain `$identifier . '.json'` map would silently drop every entry but the
 * last one sharing an identifier - this appends `-<entry_id>` whenever that
 * would happen.
 */
class ExportFileNamer
{
    /**
     * @param iterable<EntryInterface> $entries
     * @return array<int, string> entry_id => filename (without extension)
     */
    public function nameFiles(iterable $entries): array
    {
        $byId = [];
        $identifiers = [];

        foreach ($entries as $entry) {
            $byId[(int)$entry->getEntryId()] = (string)$entry->getIdentifier();
            $identifiers[] = (string)$entry->getIdentifier();
        }

        $counts = array_count_values($identifiers);
        $fileNames = [];

        foreach ($byId as $entryId => $identifier) {
            $fileNames[$entryId] = $counts[$identifier] > 1 ? $identifier . '-' . $entryId : $identifier;
        }

        return $fileNames;
    }
}
