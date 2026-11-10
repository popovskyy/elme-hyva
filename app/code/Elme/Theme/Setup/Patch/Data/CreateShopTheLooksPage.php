<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
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
 * FE-49 / FE-77: seed /shop-the-looks CMS page + Content Studio lookbook gallery entry.
 *
 * Look frames live in the theme at web/images/lookbook/ (full-size lifestyle JPEGs from the
 * live Shop the Looks set). Never seed from web/images/sample-images — those are 400px soft
 * placeholders that stretch badly in the editorial layout.
 */
class CreateShopTheLooksPage implements DataPatchInterface
{
    public const MEDIA_DIR = 'bart_content_studio/fe49';

    public const ENTRY_IDENTIFIER = 'shop-the-looks-gallery';

    private const PAGE_IDENTIFIER = 'shop-the-looks';

    /** Theme-relative lookbook frames copied into pub/media on install/update. */
    private const IMAGE_FILES = [
        'look-living.jpg',
        'look-dining.jpg',
        'look-bedroom.jpg',
    ];

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        self::syncImagesToMedia();
        $mediaBase = self::mediaBaseUrl($this->storeManager);

        $this->ensureEntry($mediaBase);
        $this->upsertPage();

        return $this;
    }

    public static function syncImagesToMedia(): void
    {
        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/lookbook';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        foreach (self::IMAGE_FILES as $fileName) {
            $from = $sourceDir . '/' . $fileName;
            $to = $targetDir . '/' . $fileName;
            if (is_file($from)) {
                copy($from, $to);
            }
        }
    }

    public static function mediaBaseUrl(StoreManagerInterface $storeManager): string
    {
        return rtrim(
            $storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/' . self::MEDIA_DIR . '/';
    }

    /**
     * Gallery payload used on create and when FE-77 refreshes an existing entry.
     *
     * @return array{intro: string, looks: list<array<string, mixed>>}
     */
    public static function galleryData(string $mediaBase): array
    {
        return [
            'intro' => 'Elme Living wholesale styled looks for Australian retailers.'
                . ' Tap the hotspots to see products in the scene, then sign in for your Pronto prices'
                . ' (ex GST), or apply for a trade account with an ABN.',
            'looks' => [
                [
                    'title' => 'Living room vignette',
                    'room' => 'living',
                    'image' => $mediaBase . 'look-living.jpg',
                    'image_alt' => 'Styled living room with a cream modular sofa, side table and lamp',
                    'description' => 'A showroom living set with seating and lighting',
                    'hotspots' => [
                        ['x' => 78, 'y' => 52, 'sku' => 'FE53-VASE', 'label' => ''],
                        ['x' => 80, 'y' => 22, 'sku' => 'FE53-LAMP', 'label' => ''],
                        ['x' => 42, 'y' => 82, 'sku' => 'FE19-MOQ6', 'label' => ''],
                    ],
                ],
                [
                    'title' => 'Dining table edit',
                    'room' => 'dining',
                    'image' => $mediaBase . 'look-dining.jpg',
                    'image_alt' => 'Dining table set with bronze vessels, bowls and a lit candlestick',
                    'description' => 'A dark dining vignette for a window display',
                    'hotspots' => [
                        ['x' => 55, 'y' => 30, 'sku' => 'FE53-LAMP', 'label' => ''],
                        ['x' => 24, 'y' => 70, 'sku' => 'FE19-QTY50', 'label' => ''],
                    ],
                ],
                [
                    'title' => 'Bedroom flora',
                    'room' => 'bedroom',
                    'image' => $mediaBase . 'look-bedroom.jpg',
                    'image_alt' => 'Cream sofa against a terracotta wall with a marble side table and vessel',
                    'description' => 'A soft seating vignette with a table vessel',
                    'hotspots' => [
                        ['x' => 60, 'y' => 48, 'sku' => 'FE53-VASE', 'label' => ''],
                        ['x' => 45, 'y' => 50, 'sku' => 'TEST-SOFA-1', 'label' => ''],
                    ],
                ],
            ],
        ];
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
            ->setType('lookbook')
            ->setTitle('Shop the Looks')
            ->setIsActive(true)
            ->setEntryData(self::galleryData($mediaBase))
            ->setStoreIds([Store::DEFAULT_STORE_ID]);
        $this->entryRepository->save($entry);
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

        $page->setTitle('Shop the Looks')
            ->setContentHeading('Shop the Looks')
            ->setMetaTitle('Shop the Looks | Elme Living Wholesale Inspiration')
            ->setMetaDescription(
                'Browse Elme Living styled looks for Australian retailers.'
                . ' Tap hotspots to see products, sign in for Pronto prices, or apply for a trade account.'
            )
            ->setMetaKeywords('')
            ->setPageLayout('1column')
            ->setContent($content)
            ->setIsActive(true);

        $this->pageRepository->save($page);
    }

    public static function getDependencies(): array
    {
        return [CreateVirtualCataloguesPage::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
