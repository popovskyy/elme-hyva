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
 * FE-49: seed /shop-the-looks CMS page + Content Studio lookbook gallery entry.
 */
class CreateShopTheLooksPage implements DataPatchInterface
{
    private const MEDIA_DIR = 'bart_content_studio/fe49';

    private const ENTRY_IDENTIFIER = 'shop-the-looks-gallery';

    private const PAGE_IDENTIFIER = 'shop-the-looks';

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
        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/sample-images';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        $map = [
            'room-styled.jpg' => 'look-living.jpg',
            'vessels.jpg' => 'look-dining.jpg',
            'orchids-wide.jpg' => 'look-bedroom.jpg',
        ];

        foreach ($map as $fromName => $toName) {
            $from = $sourceDir . '/' . $fromName;
            $to = $targetDir . '/' . $toName;
            if (is_file($from)) {
                copy($from, $to);
            }
        }
    }

    private function ensureEntry(string $mediaBase): void
    {
        $data = [
            'intro' => 'Elme Living wholesale styled looks for Australian retailers.'
                . ' Tap the hotspots to see products in the scene, then sign in for your Pronto prices'
                . ' (ex GST), or apply for a trade account with an ABN.',
            'looks' => [
                [
                    'title' => 'Living room vignette',
                    'room' => 'living',
                    'image' => $mediaBase . 'look-living.jpg',
                    'image_alt' => 'Elme Living showroom look with vessels and flora',
                    'description' => 'A retail floor set with vessels and stems',
                    'hotspots' => [
                        ['x' => 32, 'y' => 48, 'sku' => 'FE53-VASE', 'label' => ''],
                        ['x' => 58, 'y' => 40, 'sku' => 'FE53-LAMP', 'label' => ''],
                        ['x' => 72, 'y' => 62, 'sku' => 'FE19-MOQ6', 'label' => ''],
                    ],
                ],
                [
                    'title' => 'Dining table edit',
                    'room' => 'dining',
                    'image' => $mediaBase . 'look-dining.jpg',
                    'image_alt' => 'Elme Living dining look with vessels',
                    'description' => 'Tabletop vessels for a window display',
                    'hotspots' => [
                        ['x' => 40, 'y' => 55, 'sku' => 'FE53-LAMP', 'label' => ''],
                        ['x' => 62, 'y' => 42, 'sku' => 'FE19-QTY50', 'label' => ''],
                    ],
                ],
                [
                    'title' => 'Bedroom flora',
                    'room' => 'bedroom',
                    'image' => $mediaBase . 'look-bedroom.jpg',
                    'image_alt' => 'Elme Living bedroom look with orchids',
                    'description' => 'Soft flora for a bedroom floor set',
                    'hotspots' => [
                        ['x' => 45, 'y' => 50, 'sku' => 'FE53-VASE', 'label' => ''],
                        ['x' => 68, 'y' => 38, 'sku' => 'TEST-SOFA-1', 'label' => ''],
                    ],
                ],
            ],
        ];

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
            ->setEntryData($data)
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
