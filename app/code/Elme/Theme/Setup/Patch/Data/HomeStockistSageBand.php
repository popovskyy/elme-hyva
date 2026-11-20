<?php
/**
 * Home "Become a Stockist" strip on the sage band with leaf art (usp_strip field `ground`).
 * No-op when the entry is missing or already set.
 */

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class HomeStockistSageBand implements DataPatchInterface
{
    private const IDENTIFIER = 'home-stockist-usp';
    private const GROUND = 'sage_leaves';

    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository
    ) {
    }

    public function apply(): self
    {
        try {
            $entry = $this->entryRepository->getByIdentifier(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return $this;
        }

        $data = $entry->getEntryData();
        if (($data['ground'] ?? null) === self::GROUND) {
            return $this;
        }
        $data['ground'] = self::GROUND;
        $entry->setEntryData($data);
        $this->entryRepository->save($entry);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [SeedHomepageContentSections::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
