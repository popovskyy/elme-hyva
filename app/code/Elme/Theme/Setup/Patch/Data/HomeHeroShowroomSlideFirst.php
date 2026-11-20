<?php
/**
 * Home hero: the "See the Range in Our Melbourne Showroom" slide (eucalyptus photo) opens the
 * slider. Moves that slide to the front of the existing home-hero entry and keeps the order of
 * the others. No-op when the entry is missing or the slide is already first.
 */

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class HomeHeroShowroomSlideFirst implements DataPatchInterface
{
    private const IDENTIFIER = 'home-hero';
    private const SLIDE_PHOTO = '/fe14/eucalyptus-';

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
        $slides = array_values((array) ($data['slides'] ?? []));

        foreach ($slides as $index => $slide) {
            if (!str_contains((string) ($slide['image_desktop'] ?? ''), self::SLIDE_PHOTO)) {
                continue;
            }
            if ($index === 0) {
                return $this;
            }
            unset($slides[$index]);
            array_unshift($slides, $slide);
            $data['slides'] = array_values($slides);
            $entry->setEntryData($data);
            $this->entryRepository->save($entry);

            return $this;
        }

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
