<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\ImportExport\Processor;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds a portable export payload for one entry.
 *
 * Store assignment is exported as codes, not ids - ids are meaningless
 * across environments (see plan section 9). Absolute media URLs are collapsed to
 * `{{media url="..."}}`; the render pipeline (Block\Entry::getConfigJson())
 * already resolves that directive per-request, so import does not need to
 * expand it back - storing it raw is the portable, correct form, same as a
 * CMS block's own content.
 */
class Export
{
    public const VERSION = 1;

    /**
     * @var string[]|null
     */
    private ?array $mediaBaseUrls = null;

    /**
     * @param EntryRepositoryInterface $entryRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param int $entryId
     * @return array<string, mixed>
     */
    public function getExportData(int $entryId): array
    {
        return $this->buildPayload($this->entryRepository->getById($entryId));
    }

    /**
     * @param EntryInterface $entry
     * @return array<string, mixed>
     */
    private function buildPayload(EntryInterface $entry): array
    {
        return [
            'version' => self::VERSION,
            'entry' => [
                'identifier' => $entry->getIdentifier(),
                'type' => $entry->getType(),
                'title' => $entry->getTitle(),
                'is_active' => $entry->isActive(),
            ],
            'stores' => $this->storeIdsToCodes($entry->getStoreIds()),
            'data' => $this->collapseMedia($entry->getEntryData()),
        ];
    }

    /**
     * Empty array means All Store Views - store 0 has no meaningful "code".
     *
     * @param int[] $storeIds
     * @return string[]
     */
    private function storeIdsToCodes(array $storeIds): array
    {
        if (in_array(0, $storeIds, true)) {
            return [];
        }

        $codes = [];

        foreach ($storeIds as $storeId) {
            try {
                $codes[] = $this->storeManager->getStore($storeId)->getCode();
            } catch (\Exception $e) {
                continue;
            }
        }

        return $codes;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function collapseMedia(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->collapseMediaUrl($value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->collapseMedia($item), $value);
        }

        return $value;
    }

    /**
     * @param string $text
     * @return string
     */
    private function collapseMediaUrl(string $text): string
    {
        foreach ($this->getMediaBaseUrls() as $baseUrl) {
            if ($baseUrl !== '' && str_starts_with($text, $baseUrl)) {
                return '{{media url="' . substr($text, strlen($baseUrl)) . '"}}';
            }
        }

        return $text;
    }

    /**
     * Longest URL first, so a store on a subdirectory doesn't get matched by
     * a shorter sibling store's prefix.
     *
     * @return string[]
     */
    private function getMediaBaseUrls(): array
    {
        if ($this->mediaBaseUrls !== null) {
            return $this->mediaBaseUrls;
        }

        $urls = [];

        foreach ($this->storeManager->getStores(true) as $store) {
            $urls[] = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/';
        }

        usort($urls, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $this->mediaBaseUrls = $urls;

        return $urls;
    }
}
