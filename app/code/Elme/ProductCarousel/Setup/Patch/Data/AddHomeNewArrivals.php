<?php
/**
 * FE-17: home "New arrivals": a product carousel entry (source New) placed on the home page after the category
 * tiles. The block hides itself when no product is marked new, so an empty catalog shows nothing. No-op on the page
 * when the widget is already there; the entry is created only when missing.
 */

declare(strict_types=1);

namespace Elme\ProductCarousel\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class AddHomeNewArrivals implements DataPatchInterface
{
    private const IDENTIFIER = 'home-new-arrivals';

    private const AFTER = 'home-category-tiles';

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository
    ) {
    }

    public function apply(): self
    {
        $this->ensureEntry();
        $this->placeOnHome();

        return $this;
    }

    private function ensureEntry(): void
    {
        try {
            $this->entryRepository->getByIdentifier(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $entry = $this->entryFactory->create();
        $entry->setIdentifier(self::IDENTIFIER)
            ->setType('product_carousel')
            ->setTitle('Home: New arrivals')
            ->setIsActive(true)
            ->setEntryData([
                'title' => 'New arrivals',
                'source' => 'new',
                'limit' => 12,
            ])
            ->setStoreIds([Store::DEFAULT_STORE_ID]);
        $this->entryRepository->save($entry);
    }

    private function placeOnHome(): void
    {
        try {
            $page = $this->getPageByIdentifier->execute('home', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $content = (string) $page->getContent();
        if (str_contains($content, 'identifier="' . self::IDENTIFIER . '"')) {
            return;
        }

        $widget = '<div data-content-type="content_studio" data-appearance="default" data-element="main">'
            . '{{widget type="Bart\ContentStudio\Block\Entry" identifier="' . self::IDENTIFIER . '"}}</div>';
        $anchor = 'identifier="' . self::AFTER . '"}}</div>';
        $at = strpos($content, $anchor);
        if ($at === false) {
            return;
        }
        $at += strlen($anchor);
        $page->setContent(substr($content, 0, $at) . $widget . substr($content, $at));
        $this->pageRepository->save($page);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
