<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Home page, Calm gallery: hero (page H1), intro with the three trade facts, category grid,
 * Outlet + Lookbook + Virtual Catalogues as three photo cards, Become a Stockist collage.
 * Originals live in the theme (web/images/sample-images) and are copied to pub/media.
 * The Outlet promo entry is seeded for other pages and is not placed on the home page.
 */
class SeedHomepageContentSections implements DataPatchInterface
{
    /** @var array<string, list<string>> */
    private const IMAGE_FILES = [
        'fe14' => [
            'peonies-desktop-4k.webp',
            'peonies-mobile-hd.webp',
            'olive-wide.webp',
            'olive-mobile.webp',
            'vases-wide.webp',
            'vases-mobile.webp',
            'eucalyptus-wide.webp',
            'eucalyptus-mobile.webp',
        ],
        'fe15' => [
            'vessels.jpg',
            'decor.jpg',
            'floral-stems.jpg',
            'bouquets.jpg',
            'coffee-tables.jpg',
            'arrangements.jpg',
            'textiles.jpg',
            'wall-decor.jpg',
        ],
        'fe16' => [
            'lookbook-cover.jpg',
            'virtual-catalogues-cover.jpg',
            'become-stockist.jpg',
            'collage-vases.jpg',
            'collage-arrangements.jpg',
        ],
        'fe57' => [
            'elme-outlet.jpg',
            'outlet-planters.jpg',
        ],
    ];

    private const HOME_IDENTIFIERS = [
        'home-hero',
        'home-welcome',
        'home-category-tiles',
        'home-catalogue-covers',
        'home-stockist-usp',
    ];

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        self::syncImagesToMedia();
        $media = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/bart_content_studio/';

        $this->ensureEntry('home-hero', 'hero', 'Home: hero', $this->heroData($media));
        $this->ensureEntry('home-welcome', 'welcome_cta', 'Home: Welcome', $this->welcomeData());
        $this->ensureEntry(
            'home-category-tiles',
            'category_tiles',
            'Home: category tiles',
            $this->categoryTilesData($media)
        );
        $this->ensureEntry('home-outlet-promo', 'promo_split', 'Home: Outlet promo', $this->outletData($media));
        $this->ensureEntry(
            'home-catalogue-covers',
            'catalogue_covers',
            'Home: Curated Catalogues',
            $this->catalogueData($media)
        );
        $this->ensureEntry('home-stockist-usp', 'usp_strip', 'Home: Become a Stockist', $this->stockistData($media));
        $this->wireHomePage();

        return $this;
    }

    public static function syncImagesToMedia(): void
    {
        foreach (self::IMAGE_FILES as $folder => $files) {
            $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/sample-images';
            $targetDir = BP . '/pub/media/bart_content_studio/' . $folder;

            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                continue;
            }

            foreach ($files as $file) {
                if (is_file($sourceDir . '/' . $file)) {
                    copy($sourceDir . '/' . $file, $targetDir . '/' . $file);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function heroData(string $media): array
    {
        $slide = static function (
            string $media,
            string $photo,
            string $desktop,
            string $mobile,
            string $alt,
            string $heading,
            string $text,
            string $label,
            string $link,
            string $tone
        ): array {
            return [
                'image_desktop' => $media . 'fe14/' . $photo . $desktop,
                'image_mobile' => $media . 'fe14/' . $photo . $mobile,
                'image_alt' => $alt,
                'heading' => $heading,
                'text' => $text,
                'button_label' => $label,
                'button_link' => $link,
                'tone' => $tone,
            ];
        };

        return [
            'is_page_title' => '1',
            'autoplay' => true,
            'interval' => 7,
            'slides' => [
                $slide(
                    $media,
                    'peonies-',
                    'desktop-4k.webp',
                    'mobile-hd.webp',
                    'Blush peonies and roses with eucalyptus and trailing ivy in a charcoal vase against an aubergine wall',
                    "Artificial Flowers\n& Plants for\nYour Store",
                    'Lifelike orchids, stems and potted plants, sold wholesale to retailers. Open a trade account with your ABN to see prices and order online.',
                    'Shop Flowers & Plants',
                    '/flowers-plants.html',
                    'dark'
                ),
                $slide(
                    $media,
                    'olive-',
                    'wide.webp',
                    'mobile.webp',
                    'Olive tree in a textured charcoal planter against a dark plaster wall',
                    "Lifelike Trees\n& Greenery\nfor Retail",
                    'Trees, potted plants and greenery, sold wholesale to Australian retailers. Open a trade account with your ABN to see prices.',
                    'Shop Trees',
                    '/flowers-plants/trees.html',
                    'dark'
                ),
                $slide(
                    $media,
                    'vases-',
                    'wide.webp',
                    'mobile.webp',
                    'Three ceramic vases with pampas and dried stems on a dark timber table against an olive wall',
                    "Vessels & Vases\nfor Styled\nDisplays",
                    'Vases and vessels for your shop floor, sold wholesale to Australian retailers. Open a trade account with your ABN to see prices.',
                    'Shop Vessels',
                    '/homewares/vessels.html',
                    'dark'
                ),
                $slide(
                    $media,
                    'eucalyptus-',
                    'wide.webp',
                    'mobile.webp',
                    'Eucalyptus branches in a sand-coloured ceramic vessel on a travertine plinth against a pale wall',
                    "See the Range in\nOur Melbourne\nShowroom",
                    'Browse furniture, homewares and florals in person, by appointment for trade customers.',
                    'Visit Our Showroom',
                    '/showroom-locator',
                    'light'
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function welcomeData(): array
    {
        return [
            'heading' => 'Wholesale artificial flowers, plants and *home decor* for Australian retailers',
            'text' => 'Elme Living is a wholesale trade site. Log in to see your trade prices (ex GST),'
                . ' or apply for a trade account with your ABN.',
            'button_label' => 'Become a Stockist',
            'button_link' => '/become-a-stockist',
            'look' => 'intro',
            'facts' => $this->tradeFacts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryTilesData(string $media): array
    {
        $tiles = [
            ['/homewares/vessels.html', 'Vessels', 'vessels.jpg'],
            ['/homewares/decor.html', 'Decor', 'decor.jpg'],
            ['/flowers-plants/floral-stems.html', 'Floral Stems', 'floral-stems.jpg'],
            ['/flowers-plants/bouquets-bundles.html', 'Bouquets & Bundles', 'bouquets.jpg'],
            [
                '/furniture/living-room-furniture/coffee-and-side-tables/coffee-tables.html',
                'Coffee Tables',
                'coffee-tables.jpg',
            ],
            ['/flowers-plants/arrangements.html', 'Arrangements', 'arrangements.jpg'],
            ['/homewares/textiles.html', 'Textiles', 'textiles.jpg'],
            ['/homewares/wall-decor.html', 'Wall Decor', 'wall-decor.jpg'],
        ];

        return [
            'heading' => '',
            'layout' => 'grid',
            'tiles' => array_map(
                static fn (array $tile): array => [
                    'link' => $tile[0],
                    'label' => $tile[1],
                    'image' => $media . 'fe15/' . $tile[2],
                ],
                $tiles
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outletData(string $media): array
    {
        return [
            'image' => $media . 'fe57/elme-outlet.jpg',
            'image_alt' => 'Vases in silhouette on red: Elme Outlet now open',
            'heading' => "Introducing the\nElme Outlet",
            'text' => '<p>End-of-lines, samples and one-off pieces for your floor at our Melbourne outlet.</p>'
                . '<p>Stockists use it for samples and clearance fills; the public is welcome too.</p>',
            'button_label' => 'Find Us In-Store',
            'button_link' => '/outlet-and-clearance-store',
            'tone' => 'dark',
            'highlight' => 'Up to 80% off',
            'ticker' => "End-of-lines\nSamples\nOne-off pieces\nUp to 80% off\nMelbourne outlet",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogueData(string $media): array
    {
        return [
            'heading' => 'From the showroom',
            'intro' => '',
            'look' => 'cards',
            'cards' => [
                [
                    'image' => $media . 'fe57/outlet-planters.jpg',
                    'image_alt' => 'White woven planters with a peace lily against a cream panelled wall',
                    'title' => 'Elme Outlet',
                    'text' => 'End-of-lines and samples, up to 80% off',
                    'link' => '/outlet-and-clearance-store',
                ],
                [
                    'image' => $media . 'fe16/lookbook-cover.jpg',
                    'image_alt' => 'Elme Living Lookbook cover',
                    'title' => 'Lookbook',
                    'text' => 'Styled rooms and seasonal edits',
                    'link' => '/shop-the-looks',
                ],
                [
                    'image' => $media . 'fe16/virtual-catalogues-cover.jpg',
                    'image_alt' => 'Elme Living Virtual Catalogues cover',
                    'title' => 'Virtual Catalogues',
                    'text' => 'Full ranges to browse page by page',
                    'link' => '/virtual-catalogues',
                ],
            ],
            'see_all_label' => 'See All Catalogues',
            'see_all_link' => '/virtual-catalogues',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockistData(string $media): array
    {
        return [
            'heading' => 'Become a Stockist',
            'intro' => 'Apply with your ABN for a wholesale trade account. Once approved, you shop'
                . ' your own trade prices online, built for Australian retailers.',
            'image' => $media . 'fe16/become-stockist.jpg',
            'image_alt' => 'Styled Elme Living homewares and artificial flora display',
            'image_2' => $media . 'fe16/collage-vases.jpg',
            'image_3' => $media . 'fe16/collage-arrangements.jpg',
            'items' => $this->tradeFacts(),
            'button_label' => 'Create An Account',
            'button_link' => '/customer/account/create',
            'show_items' => '0',
        ];
    }

    /**
     * @return list<array{title: string, text: string}>
     */
    private function tradeFacts(): array
    {
        return [
            [
                'title' => 'ABN trade account',
                'text' => 'Register with a valid Australian Business Number. We review applications'
                    . ' and open wholesale access for approved retailers.',
            ],
            [
                'title' => 'Prices ex GST',
                'text' => 'After login you see your own trade prices ex GST, not a public retail list.',
            ],
            [
                'title' => 'Australia-wide delivery',
                'text' => 'Order online for delivery across Australia. Some lines have a'
                    . ' minimum order quantity.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ensureEntry(string $identifier, string $type, string $title, array $data): void
    {
        try {
            $this->entryRepository->getByIdentifier($identifier, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $entry = $this->entryFactory->create();
        $entry->setIdentifier($identifier)
            ->setType($type)
            ->setTitle($title)
            ->setIsActive(true)
            ->setEntryData($data)
            ->setStoreIds([Store::DEFAULT_STORE_ID]);
        $this->entryRepository->save($entry);
    }

    private function wireHomePage(): void
    {
        try {
            $page = $this->getPageByIdentifier->execute('home', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $widgets = [];
        foreach (self::HOME_IDENTIFIERS as $identifier) {
            $widgets[] = '<div data-content-type="content_studio" data-appearance="default"'
                . ' data-element="main">{{widget type="Bart\ContentStudio\Block\Entry"'
                . ' identifier="' . $identifier . '"}}</div>';
        }

        $content = '<div data-content-type="row" data-appearance="contained" data-element="main">'
            . '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
            . ' data-element="inner" style="display:flex;flex-direction:column;">'
            . implode('', $widgets)
            . '</div></div>';

        $page->setContent($content);
        $page->setMetaTitle('Wholesale Artificial Flowers, Plants and Home Decor');
        $page->setMetaDescription(
            'Wholesale artificial flowers, plants and home decor for Australian retailers.'
            . ' Sign in with your ABN for trade prices, stock and Quick Order.'
        );
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
