<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * First home-hero photo, so the browser can fetch it from <head> instead of waiting
 * until the parser reaches the hero (the mobile menu HTML sits in front of it).
 */
class HomeHeroPreload implements ArgumentInterface
{
    private const IDENTIFIER = 'home-hero';

    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly Hero $hero,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return list<array{href: string, media: string}>
     */
    public function getLinks(): array
    {
        try {
            $entry = $this->entryRepository->getByIdentifier(
                self::IDENTIFIER,
                (int)$this->storeManager->getStore()->getId()
            );
        } catch (NoSuchEntityException) {
            return [];
        }

        $slides = $this->hero->getSlides($entry->getEntryData());
        if ($slides === []) {
            return [];
        }

        $desktop = $slides[0]['desktop']['src'];
        $mobile = $slides[0]['mobile']['src'] ?? '';

        if ($mobile !== '' && $mobile !== $desktop) {
            return [
                ['href' => $mobile, 'media' => '(max-width: 767px)'],
                ['href' => $desktop, 'media' => '(min-width: 768px)'],
            ];
        }

        return [['href' => $desktop, 'media' => '']];
    }
}
