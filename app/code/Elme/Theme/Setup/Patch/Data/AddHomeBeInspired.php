<?php
/**
 * FE-77: home "Be Inspired": a lookbook entry in the Feature layout that shows the first look of the Shop the
 * Looks gallery (no copy of its looks), placed on the home page after the category tiles. No-op on the page when
 * the widget is already there; the entry is created only when missing.
 */

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class AddHomeBeInspired implements DataPatchInterface
{
    private const IDENTIFIER = 'home-be-inspired';

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
            ->setType('lookbook')
            ->setTitle('Home: Be Inspired')
            ->setIsActive(true)
            ->setEntryData([
                'layout' => 'feature',
                'heading' => 'Be Inspired',
                'feature_look' => 1,
                'link_label' => 'Shop the Looks',
                'link' => '/shop-the-looks',
                'source' => 'shop-the-looks-gallery',
                'looks' => [],
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
        return [SeedHomepageContentSections::class, CreateShopTheLooksPage::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
