<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Elme\LoginOnly\Model\LoginState;
use Elme\QtyRules\Model\QtyRules;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Checkout\Helper\Cart as CartHelper;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Content Studio type `lookbook` (FE-49) + /shop-the-looks JSON-LD / OG helpers.
 *
 * Guest HTML: look image, hotspot product name / thumb / PDP URL, sign-in CTA. Never customer prices.
 * Logged-in: MOQ / stock / ATC data (prices still load after render via Elme_LoginOnly placeholders).
 */
class Lookbook implements ArgumentInterface
{
    public const PAGE_IDENTIFIER = 'shop-the-looks';

    public const ENTRY_IDENTIFIER = 'shop-the-looks-gallery';

    public const MAX_LOOKS = 60;

    public const MAX_HOTSPOTS = 24;

    public const IMAGE_SIZE = [1440, 960];

    public const PRODUCT_IMAGE_SIZE = [240, 240];

    /** @var array<string, string> */
    public const ROOMS = [
        'dining' => 'Dining',
        'living' => 'Living',
        'bedroom' => 'Bedroom',
        'bathroom' => 'Bathroom',
        'kitchen' => 'Kitchen',
        'outdoors' => 'Outdoors',
    ];

    private ?string $mediaPath = null;

    /** @var array<string, mixed>|null */
    private ?array $entryContent = null;

    private bool $entryLoaded = false;

    /** @var array<string, Product|false> */
    private array $productsBySku = [];

    /** @var array<string, int> */
    private array $stockIds = [];

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        private readonly LoginState $loginState,
        private readonly CmsPage $cmsPage,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ImageHelper $imageHelper,
        private readonly CartHelper $cartHelper,
        private readonly QtyRules $qtyRules,
        private readonly ProductTile $productTile,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly StockResolverInterface $stockResolver,
        private readonly IsSourceItemManagementAllowedForProductTypeInterface $isQtyManagedType
    ) {
    }

    public function isShopTheLooksPage(?string $identifier = null): bool
    {
        $id = $identifier ?? (string) $this->cmsPage->getIdentifier();

        return $id === self::PAGE_IDENTIFIER;
    }

    public function isLoggedIn(): bool
    {
        return $this->loginState->isLoggedIn();
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getIntro(array $content): string
    {
        return $this->text($content, 'intro');
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function getRoomFilters(array $looks): array
    {
        $present = [];
        foreach ($looks as $look) {
            $code = (string) ($look['room'] ?? '');
            if ($code !== '' && isset(self::ROOMS[$code])) {
                $present[$code] = self::ROOMS[$code];
            }
        }

        $filters = [];
        foreach (self::ROOMS as $code => $label) {
            if (isset($present[$code])) {
                $filters[] = ['code' => $code, 'label' => $label];
            }
        }

        return $filters;
    }

    /**
     * Looks ready for the template. Empty image or zero resolved hotspots → omitted (UX-01).
     *
     * @param array<string, mixed> $content
     * @return list<array{
     *     title: string,
     *     room: string,
     *     room_label: string,
     *     description: string,
     *     image_alt: string,
     *     image: array{src: string, width: int, height: int},
     *     hotspots: list<array<string, mixed>>,
     *     add_all: list<array{product_id: int, sku: string, qty: float, add_url: string}>
     * }>
     */
    public function getLooks(array $content): array
    {
        $looks = [];
        $loggedIn = $this->isLoggedIn();

        foreach ((array) ($content['looks'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = $this->text($row, 'title');
            $image = $this->text($row, 'image');
            if ($title === '' || $image === '') {
                continue;
            }

            $room = strtolower($this->text($row, 'room'));
            if (!isset(self::ROOMS[$room])) {
                $room = 'living';
            }

            $hotspots = $this->resolveHotspots((array) ($row['hotspots'] ?? []), $loggedIn);
            if ($hotspots === []) {
                continue;
            }

            $alt = $this->text($row, 'image_alt');
            $addAll = [];
            if ($loggedIn) {
                foreach ($hotspots as $pin) {
                    if (!(bool) ($pin['can_add'] ?? false)) {
                        continue;
                    }
                    $addAll[] = [
                        'product_id' => (int) $pin['product_id'],
                        'sku' => (string) $pin['sku'],
                        'qty' => (float) $pin['qty_min'],
                        'add_url' => (string) $pin['add_url'],
                    ];
                }
            }

            $looks[] = [
                'title' => $title,
                'room' => $room,
                'room_label' => self::ROOMS[$room],
                'description' => $this->text($row, 'description'),
                'image_alt' => $alt !== '' ? $alt : $title,
                'image' => $this->image($image, self::IMAGE_SIZE),
                'hotspots' => $hotspots,
                'add_all' => $addAll,
            ];

            if (count($looks) === self::MAX_LOOKS) {
                break;
            }
        }

        return $looks;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPageContent(): array
    {
        if ($this->entryLoaded) {
            return $this->entryContent ?? [];
        }

        $this->entryLoaded = true;
        $this->entryContent = [];
        $storeId = (int) $this->storeManager->getStore()->getId();

        foreach ([$storeId, Store::DEFAULT_STORE_ID] as $tryStoreId) {
            try {
                $entry = $this->entryRepository->getByIdentifier(self::ENTRY_IDENTIFIER, $tryStoreId);
                if ($entry->isActive()) {
                    $this->entryContent = $entry->getEntryData();
                    break;
                }
            } catch (NoSuchEntityException) {
                continue;
            }
        }

        return $this->entryContent;
    }

    /**
     * Guest-safe looks for schema (images + titles only; no products / prices).
     *
     * @return list<array{title: string, description: string, image_alt: string, image: array{src: string, width: int, height: int}}>
     */
    public function getGuestLooksForSchema(): array
    {
        $items = [];
        foreach ((array) ($this->getPageContent()['looks'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = $this->text($row, 'title');
            $image = $this->text($row, 'image');
            if ($title === '' || $image === '') {
                continue;
            }
            $hasPin = false;
            foreach ((array) ($row['hotspots'] ?? []) as $pin) {
                if (is_array($pin) && $this->text($pin, 'sku') !== '') {
                    $hasPin = true;
                    break;
                }
            }
            if (!$hasPin) {
                continue;
            }
            $alt = $this->text($row, 'image_alt');
            $items[] = [
                'title' => $title,
                'description' => $this->text($row, 'description'),
                'image_alt' => $alt !== '' ? $alt : $title,
                'image' => $this->image($image, self::IMAGE_SIZE),
            ];
            if (count($items) === self::MAX_LOOKS) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonLdGraph(): array
    {
        $store = $this->storeManager->getStore();
        $baseUrl = rtrim((string) $store->getBaseUrl(), '/');
        $pageUrl = $this->canonicalUrl();
        $name = $this->getMetaTitle();
        $description = $this->getMetaDescription();
        $looks = $this->getGuestLooksForSchema();

        $webpage = [
            '@type' => ['CollectionPage', 'WebPage'],
            '@id' => $pageUrl . '#webpage',
            'url' => $pageUrl,
            'name' => $name,
            'inLanguage' => 'en-AU',
            'isPartOf' => ['@id' => $baseUrl . '/#website'],
            'about' => ['@id' => $baseUrl . '/#organization'],
            'publisher' => ['@id' => $baseUrl . '/#organization'],
            'breadcrumb' => ['@id' => $pageUrl . '#breadcrumb'],
        ];
        if ($description !== '') {
            $webpage['description'] = $description;
        }

        $graph = [
            $webpage,
            [
                '@type' => 'BreadcrumbList',
                '@id' => $pageUrl . '#breadcrumb',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'item' => ['@id' => $baseUrl . '/', 'name' => 'Home'],
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'item' => ['@id' => $pageUrl, 'name' => 'Shop the Looks'],
                    ],
                ],
            ],
        ];

        if ($looks) {
            $first = $looks[0]['image']['src'];
            $webpage['mainEntity'] = ['@id' => $pageUrl . '#look-list'];
            $webpage['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $first,
            ];
            $graph[0] = $webpage;

            $listElements = [];
            foreach ($looks as $i => $look) {
                $item = [
                    '@type' => 'ImageObject',
                    'name' => $look['title'],
                    'contentUrl' => $look['image']['src'],
                    'url' => $look['image']['src'],
                    'creator' => ['@id' => $baseUrl . '/#organization'],
                ];
                if ($look['description'] !== '') {
                    $item['description'] = $look['description'];
                }
                $listElements[] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'item' => $item,
                ];
            }

            $graph[] = [
                '@type' => 'ItemList',
                '@id' => $pageUrl . '#look-list',
                'name' => 'Elme Living shoppable looks',
                'numberOfItems' => count($listElements),
                'itemListElement' => $listElements,
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    public function getJsonLdScript(): string
    {
        return (string) json_encode(
            $this->getJsonLdGraph(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    public function getCanonicalUrl(): string
    {
        return $this->canonicalUrl();
    }

    public function getMetaTitle(): string
    {
        $title = trim((string) $this->cmsPage->getMetaTitle());

        return $title !== '' ? $title : trim((string) $this->cmsPage->getTitle());
    }

    public function getMetaDescription(): string
    {
        return trim((string) $this->cmsPage->getMetaDescription());
    }

    public function getOgType(): string
    {
        return 'website';
    }

    public function getOgLocale(): string
    {
        return 'en_AU';
    }

    public function getOgImageUrl(): string
    {
        $looks = $this->getGuestLooksForSchema();

        return $looks[0]['image']['src'] ?? '';
    }

    public function getLoginUrl(): string
    {
        return $this->storeManager->getStore()->getUrl('customer/account/login');
    }

    public function getStockistUrl(): string
    {
        return $this->storeManager->getStore()->getUrl('become-a-stockist');
    }

    /**
     * @param list<mixed> $rows
     * @return list<array<string, mixed>>
     */
    private function resolveHotspots(array $rows, bool $loggedIn): array
    {
        $pins = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $sku = $this->text($row, 'sku');
            if ($sku === '') {
                continue;
            }

            $product = $this->productBySku($sku);
            if ($product === null) {
                continue;
            }

            $x = $this->percent($row['x'] ?? null);
            $y = $this->percent($row['y'] ?? null);
            if ($x === null || $y === null) {
                continue;
            }

            $name = (string) $product->getName();
            $label = $this->text($row, 'label');
            $accessible = $label !== '' ? $label : $name;
            $productId = (int) $product->getId();
            $canAdd = $loggedIn && $product->isSaleable() && $product->getTypeId() === 'simple';
            $qty = $canAdd ? $this->qtyRules->getMinAndStep($product) : ['min' => 1.0, 'step' => 1.0];
            $stockLevel = $loggedIn ? $this->productTile->getStockLevel($product) : null;
            $stockQty = $loggedIn ? $this->salableQty($product) : null;

            $pins[] = [
                'x' => $x,
                'y' => $y,
                'sku' => $sku,
                'product_id' => $productId,
                'name' => $name,
                'label' => $accessible,
                'url' => $product->getProductUrl(),
                'image' => $this->productImage($product),
                'can_add' => $canAdd,
                'qty_min' => $qty['min'],
                'qty_step' => $qty['step'],
                'stock_level' => $stockLevel,
                'stock_qty' => $stockQty,
                'add_url' => $canAdd ? $this->cartHelper->getAddUrl($product, ['useUencPlaceholder' => true]) : '',
            ];

            if (count($pins) === self::MAX_HOTSPOTS) {
                break;
            }
        }

        return $pins;
    }

    private function productBySku(string $sku): ?Product
    {
        $key = strtolower($sku);
        if (array_key_exists($key, $this->productsBySku)) {
            $cached = $this->productsBySku[$key];

            return $cached instanceof Product ? $cached : null;
        }

        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            /** @var Product $product */
            $product = $this->productRepository->get($sku, false, $storeId);
        } catch (NoSuchEntityException) {
            $this->productsBySku[$key] = false;

            return null;
        }

        if ((int) $product->getStatus() !== Status::STATUS_ENABLED) {
            $this->productsBySku[$key] = false;

            return null;
        }

        $visibility = (int) $product->getVisibility();
        if ($visibility === Visibility::VISIBILITY_NOT_VISIBLE) {
            $this->productsBySku[$key] = false;

            return null;
        }

        $this->productsBySku[$key] = $product;

        return $product;
    }

    /**
     * @return array{src: string, width: int, height: int}
     */
    private function productImage(Product $product): array
    {
        try {
            $helper = $this->imageHelper->init($product, 'category_page_grid');
            $src = (string) $helper->getUrl();
            $width = (int) $helper->getWidth() ?: self::PRODUCT_IMAGE_SIZE[0];
            $height = (int) $helper->getHeight() ?: self::PRODUCT_IMAGE_SIZE[1];

            return ['src' => $src, 'width' => $width, 'height' => $height];
        } catch (Throwable) {
            return [
                'src' => '',
                'width' => self::PRODUCT_IMAGE_SIZE[0],
                'height' => self::PRODUCT_IMAGE_SIZE[1],
            ];
        }
    }

    private function salableQty(Product $product): ?float
    {
        if (!$this->isQtyManagedType->execute((string) $product->getTypeId())) {
            return null;
        }

        try {
            return $this->getProductSalableQty->execute((string) $product->getSku(), $this->stockId());
        } catch (Throwable) {
            return null;
        }
    }

    private function stockId(): int
    {
        $websiteCode = (string) $this->storeManager->getWebsite()->getCode();

        return $this->stockIds[$websiteCode] ??= (int) $this->stockResolver
            ->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode)
            ->getStockId();
    }

    private function percent(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $n = (float) $value;
        if ($n < 0 || $n > 100) {
            return null;
        }

        return round($n, 2);
    }

    private function canonicalUrl(): string
    {
        return rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/')
            . '/' . self::PAGE_IDENTIFIER;
    }

    /**
     * @param array{0: int, 1: int} $fallback
     * @return array{src: string, width: int, height: int}
     */
    private function image(string $url, array $fallback): array
    {
        [$width, $height] = $this->readSize($url) ?? $fallback;

        return ['src' => $url, 'width' => $width, 'height' => $height];
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function readSize(string $url): ?array
    {
        $relative = $this->mediaRelativePath($url);
        if ($relative === null) {
            return null;
        }

        try {
            $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            if (!$media->isFile($relative)) {
                return null;
            }
            $size = getimagesize($media->getAbsolutePath($relative));
        } catch (Throwable) {
            return null;
        }

        if (!$size || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        return [(int) $size[0], (int) $size[1]];
    }

    private function mediaRelativePath(string $url): ?string
    {
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $mediaPath = $this->mediaPath();
        if ($mediaPath === '' || !str_starts_with($path, $mediaPath)) {
            return null;
        }
        $relative = substr($path, strlen($mediaPath));

        return $relative === '' || str_contains($relative, '..') ? null : $relative;
    }

    private function mediaPath(): string
    {
        if ($this->mediaPath === null) {
            try {
                $base = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
                $this->mediaPath = rtrim((string) parse_url($base, PHP_URL_PATH), '/') . '/';
            } catch (Throwable) {
                $this->mediaPath = '';
            }
        }

        return $this->mediaPath;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
