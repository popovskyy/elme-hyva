<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-89: seed /elme-living-three-birds-maison-soleil CMS page + Content Studio collaboration entry.
 *
 * Products are local catalog SKUs (production collab SKUs are not on this instance). Editors replace
 * the SKU list in Content Studio for the live campaign; the next collab is a new entry, no code.
 */
class CreateCollaborationPage implements DataPatchInterface
{
    public const MEDIA_DIR = 'bart_content_studio/fe89';

    public const ENTRY_IDENTIFIER = 'three-birds-maison-soleil';

    public const PAGE_IDENTIFIER = 'elme-living-three-birds-maison-soleil';

    private const COLLAGE_FILE = 'collage.jpg';

    private const SEED_PRODUCT_LIMIT = 12;

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductCollectionFactory $productCollectionFactory
    ) {
    }

    public function apply(): self
    {
        self::syncImagesToMedia();
        $mediaBase = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/' . self::MEDIA_DIR . '/';

        $this->ensureEntry($mediaBase);
        $this->upsertPage();

        return $this;
    }

    public static function syncImagesToMedia(): void
    {
        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/sample-images/fe89';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        $from = $sourceDir . '/' . self::COLLAGE_FILE;
        $to = $targetDir . '/' . self::COLLAGE_FILE;
        if (is_file($from)) {
            copy($from, $to);
        }
    }

    private function ensureEntry(string $mediaBase): void
    {
        try {
            $this->entryRepository->getByIdentifier(self::ENTRY_IDENTIFIER, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $entry = $this->entryFactory->create();
        $entry->setIdentifier(self::ENTRY_IDENTIFIER)
            ->setType('collaboration')
            ->setTitle('Three Birds Renovations Maison Soleil')
            ->setIsActive(true)
            ->setEntryData($this->entryData($mediaBase))
            ->setStoreIds([Store::DEFAULT_STORE_ID]);
        $this->entryRepository->save($entry);
    }

    /**
     * @return array<string, mixed>
     */
    private function entryData(string $mediaBase): array
    {
        $products = [];
        foreach ($this->seedSkus() as $sku) {
            $products[] = ['sku' => $sku];
        }

        return [
            'banner_desktop' => '',
            'banner_mobile' => '',
            'banner_alt' => '',
            'heading' => 'THREE BIRDS RENOVATIONS MAISON SOLEIL',
            'is_page_title' => true,
            'intro' => 'Explore the Elme Living pieces featured in Three Birds Renovations Maison Soleil.'
                . ' Warm tones, sculptural forms and layered textures come together to create a refined,'
                . ' collected look with a relaxed French influence. Discover the furniture, homewares'
                . ' and styling pieces used throughout the project.',
            'collage' => $mediaBase . self::COLLAGE_FILE,
            'collage_alt' => 'Three Birds Renovations Maison Soleil styled rooms with Elme Living pieces',
            'section_heading' => 'Shop the look',
            'products' => $products,
        ];
    }

    /**
     * @return list<string>
     */
    private function seedSkus(): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('sku')
            ->addAttributeToFilter('status', 1)
            ->setPageSize(self::SEED_PRODUCT_LIMIT)
            ->setCurPage(1);

        $skus = [];
        foreach ($collection as $product) {
            $sku = trim((string) $product->getSku());
            if ($sku !== '') {
                $skus[] = $sku;
            }
        }

        return $skus;
    }

    private function upsertPage(): void
    {
        $content = '<div data-content-type="row" data-appearance="contained" data-element="main">'
            . '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
            . ' data-element="inner" style="display:flex;flex-direction:column;">'
            . '<div data-content-type="content_studio" data-appearance="default" data-element="main">'
            . '{{widget type="Bart\ContentStudio\Block\Entry" identifier="'
            . self::ENTRY_IDENTIFIER . '"}}</div>'
            . '</div></div>';

        try {
            $page = $this->getPageByIdentifier->execute(self::PAGE_IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            /** @var PageInterface $page */
            $page = $this->pageFactory->create();
            $page->setIdentifier(self::PAGE_IDENTIFIER);
            $page->setData('stores', [Store::DEFAULT_STORE_ID]);
        }

        $page->setTitle('Three Birds Renovations - Maison Soleil')
            ->setContentHeading('')
            ->setMetaTitle('Elme Living & Three Birds: Maison Soleil')
            ->setMetaDescription(
                'Explore Elme Living pieces featured in Three Birds Renovations Maison Soleil.'
                . ' Shop the look: furniture, homewares and styling for Australian retailers.'
            )
            ->setPageLayout('1column')
            ->setContent($content)
            ->setIsActive(true);

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
