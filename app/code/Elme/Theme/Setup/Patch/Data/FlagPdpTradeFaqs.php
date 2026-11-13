<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Elme\Theme\ViewModel\Faq;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-29: the questions a new stockist asks before a first order, shown in the Trade FAQ row of the
 * PDP accordion (field show_on_pdp of the `faqs` entry). Leaves the entry alone once an editor has
 * flagged any row.
 */
class FlagPdpTradeFaqs implements DataPatchInterface
{
    private const ANCHORS = [
        'how-can-i-set-up-an-account',
        'do-you-have-a-minimum-order',
        'what-are-your-freight-charges',
        'delivery-times',
        'can-you-ship-to-a-residential-address',
        'what-if-there-is-a-problem-with-my-order',
    ];

    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository
    ) {
    }

    public function apply(): self
    {
        try {
            $entry = $this->entryRepository->getByIdentifier(Faq::ENTRY_IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return $this;
        }

        $data = $entry->getEntryData();
        $items = (array) ($data['items'] ?? []);
        foreach ($items as $row) {
            if (is_array($row) && !empty($row['show_on_pdp'])) {
                return $this;
            }
        }

        foreach ($items as $i => $row) {
            if (is_array($row)) {
                $items[$i]['show_on_pdp'] = in_array((string) ($row['anchor'] ?? ''), self::ANCHORS, true);
            }
        }
        $data['items'] = $items;
        $entry->setEntryData($data);
        $this->entryRepository->save($entry);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [SeedFaqsAccordion::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
