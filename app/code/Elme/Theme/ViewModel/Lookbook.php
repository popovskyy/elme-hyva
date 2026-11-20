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
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\CacheInterface;
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
 * Content Studio type `lookbook` (FE-49, FE-77) + /shop-the-looks JSON-LD / OG helpers.
 *
 * Guest HTML: look image, hotspot product name / thumb / PDP URL, sign-in CTA. Never customer prices.
 * Logged-in: MOQ / stock / ATC data (prices still load after render via Elme_LoginOnly placeholders).
 * Configurables carry their options and children, so the card asks for the colour and adds the child.
 * Layout `feature` (home Be Inspired) shows one look, from its own rows or from another lookbook entry.
 */
class Lookbook implements ArgumentInterface
{
    public const PAGE_IDENTIFIER = 'shop-the-looks';

    public const ENTRY_IDENTIFIER = 'shop-the-looks-gallery';

    public const MAX_LOOKS = 60;

    public const MAX_HOTSPOTS = 24;

    public const IMAGE_SIZE = [1440, 960];

    public const PRODUCT_IMAGE_SIZE = [240, 240];

    public const LAYOUT_GALLERY = 'gallery';

    public const LAYOUT_FEATURE = 'feature';

    private const TONE_CACHE_PREFIX = 'elme_lookbook_tone_';

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

    /** @var array<string, array<string, mixed>> */
    private array $entryContentById = [];

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
        private readonly IsSourceItemManagementAllowedForProductTypeInterface $isQtyManagedType,
        private readonly CacheInterface $cache
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
     * @param array<string, mixed> $content
     */
    public function getLayout(array $content): string
    {
        return $this->text($content, 'layout') === self::LAYOUT_FEATURE ? self::LAYOUT_FEATURE : self::LAYOUT_GALLERY;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getHeading(array $content): string
    {
        return $this->text($content, 'heading');
    }

    /**
     * @param array<string, mixed> $content
     * @return array{label: string, url: string}|null
     */
    public function getLink(array $content): ?array
    {
        $label = $this->text($content, 'link_label');
        $url = $this->text($content, 'link');
        if ($label === '' || $url === '') {
            return null;
        }
        if (!preg_match('#^(https?:)?//#i', $url)) {
            $url = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/') . '/' . ltrim($url, '/');
        }

        return ['label' => $label, 'url' => $url];
    }

    /**
     * The one look of the `feature` layout. "Look number" counts the looks that show; out of range = the first.
     *
     * @param array<string, mixed> $content
     * @return list<array<string, mixed>>
     */
    public function getFeatureLooks(array $content): array
    {
        $looks = $this->getLooks($content);
        if ($looks === []) {
            return [];
        }
        $index = max(0, (int) ($content['feature_look'] ?? 1) - 1);

        return [$looks[$index] ?? $looks[0]];
    }

    /**
     * Rooms present in the looks, in the fixed room order, with how many looks each has.
     *
     * @return list<array{code: string, label: string, count: int}>
     */
    public function getRoomFilters(array $looks): array
    {
        $counts = [];
        foreach ($looks as $look) {
            $code = (string) ($look['room'] ?? '');
            if ($code !== '' && isset(self::ROOMS[$code])) {
                $counts[$code] = ($counts[$code] ?? 0) + 1;
            }
        }

        $filters = [];
        foreach (self::ROOMS as $code => $label) {
            if (isset($counts[$code])) {
                $filters[] = ['code' => $code, 'label' => $label, 'count' => $counts[$code]];
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
     *     tone: string,
     *     hotspots: list<array<string, mixed>>,
     *     add_all: list<array{name: string, qty: float, add_url: string, product_id: int, reason: string}>
     * }>
     */
    public function getLooks(array $content): array
    {
        $looks = [];
        $loggedIn = $this->isLoggedIn();

        foreach ($this->lookRows($content) as $row) {
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
            // Every pin goes in: the ones with a reason are named as not added
            $addAll = [];
            if ($loggedIn) {
                foreach ($hotspots as $pin) {
                    $reason = $this->skipReason($pin);
                    $addAll[] = [
                        'name' => (string) $pin['name'],
                        'qty' => (float) $pin['qty_min'],
                        'add_url' => $reason === '' ? (string) $pin['add_url'] : '',
                        'product_id' => (int) $pin['product_id'],
                        'reason' => $reason,
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
                'tone' => $this->tone($image),
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
        $this->entryContent = $this->entryContent(self::ENTRY_IDENTIFIER);

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
            $type = match ((string) $product->getTypeId()) {
                'simple', 'virtual' => 'simple',
                Configurable::TYPE_CODE => 'configurable',
                default => 'other',
            };
            $config = $loggedIn && $type === 'configurable'
                ? $this->configurableOptions($product)
                : ['attributes' => [], 'children' => []];
            $saleable = $loggedIn && $product->isSaleable();
            $canAdd = match ($type) {
                'simple' => $saleable,
                // FE-77: a configurable is bought as its child; the card asks for the options first
                'configurable' => $saleable && $config['attributes'] !== []
                    && array_filter($config['children'], static fn (array $child): bool => $child['saleable']) !== [],
                default => false,
            };
            $qty = $canAdd && $type === 'simple'
                ? $this->qtyRules->getMinAndStep($product)
                : ['min' => 1.0, 'step' => 1.0];
            $isSimple = $loggedIn && $type === 'simple';

            $pins[] = [
                'x' => $x,
                'y' => $y,
                'sku' => $sku,
                'product_id' => $productId,
                'type' => $type,
                'name' => $name,
                'label' => $accessible,
                'url' => $product->getProductUrl(),
                'image' => $this->productImage($product),
                'saleable' => $saleable,
                'can_add' => $canAdd,
                'qty_min' => $qty['min'],
                'qty_step' => $qty['step'],
                'stock_level' => $isSimple ? $this->productTile->getStockLevel($product) : null,
                'stock_qty' => $isSimple ? $this->salableQty($product) : null,
                'attributes' => $config['attributes'],
                'children' => $config['children'],
                'add_url' => $canAdd ? $this->cartHelper->getAddUrl($product, ['useUencPlaceholder' => true]) : '',
            ];

            if (count($pins) === self::MAX_HOTSPOTS) {
                break;
            }
        }

        return $pins;
    }

    /**
     * Options of a configurable and its enabled children, for the card's option picker.
     *
     * @return array{
     *     attributes: list<array{id: int, code: string, label: string, values: list<array{id: int, label: string}>}>,
     *     children: list<array{id: int, sku: string, attrs: array<string, int>, label: string, saleable: bool,
     *         min: float, step: float, stock_qty: float|null}>
     * }
     */
    private function configurableOptions(Product $product): array
    {
        $none = ['attributes' => [], 'children' => []];
        $type = $product->getTypeInstance();
        if (!$type instanceof Configurable) {
            return $none;
        }

        try {
            $rawAttributes = $type->getConfigurableAttributesAsArray($product);
            $usedProducts = $type->getUsedProducts($product);
        } catch (Throwable) {
            return $none;
        }

        $attributes = [];
        foreach ($rawAttributes as $raw) {
            $id = (int) ($raw['attribute_id'] ?? 0);
            $code = (string) ($raw['attribute_code'] ?? '');
            if ($id === 0 || $code === '') {
                continue;
            }
            $values = [];
            foreach ((array) ($raw['values'] ?? []) as $value) {
                $valueId = (int) ($value['value_index'] ?? 0);
                $valueLabel = (string) ($value['store_label'] ?? $value['label'] ?? $value['default_label'] ?? '');
                if ($valueId !== 0 && $valueLabel !== '') {
                    $values[$valueId] = $valueLabel;
                }
            }
            $attributes[$id] = [
                'code' => $code,
                'label' => (string) ($raw['store_label'] ?? $raw['label'] ?? $raw['frontend_label'] ?? $code),
                'values' => $values,
            ];
        }

        $children = [];
        $usedValues = [];
        foreach ($usedProducts as $child) {
            if (!$child instanceof Product || (int) $child->getStatus() !== Status::STATUS_ENABLED) {
                continue;
            }
            $attrs = [];
            $names = [];
            foreach ($attributes as $id => $attribute) {
                $valueId = (int) $child->getData($attribute['code']);
                if (!isset($attribute['values'][$valueId])) {
                    continue 2;
                }
                $attrs[(string) $id] = $valueId;
                $names[] = $attribute['values'][$valueId];
            }
            foreach ($attrs as $id => $valueId) {
                $usedValues[$id][$valueId] = true;
            }
            $rules = $this->qtyRules->getMinAndStep($child);
            $children[] = [
                'id' => (int) $child->getId(),
                'sku' => (string) $child->getSku(),
                'attrs' => $attrs,
                'label' => implode(', ', $names),
                'saleable' => (bool) $child->isSaleable(),
                'min' => (float) $rules['min'],
                'step' => (float) $rules['step'],
                'stock_qty' => $this->salableQty($child),
            ];
        }

        $list = [];
        foreach ($attributes as $id => $attribute) {
            $values = [];
            foreach ($attribute['values'] as $valueId => $valueLabel) {
                if (isset($usedValues[(string) $id][$valueId])) {
                    $values[] = ['id' => $valueId, 'label' => $valueLabel];
                }
            }
            if ($values === []) {
                return $none;
            }
            $list[] = ['id' => $id, 'code' => $attribute['code'], 'label' => $attribute['label'], 'values' => $values];
        }

        return ['attributes' => $list, 'children' => $children];
    }

    /**
     * Why Add this look leaves a pin out; '' = it goes in at its MOQ.
     *
     * @param array<string, mixed> $pin
     */
    private function skipReason(array $pin): string
    {
        if (!$pin['saleable']) {
            return (string) __('out of stock');
        }
        if ($pin['type'] === 'configurable' && $pin['can_add']) {
            $names = array_map(
                static fn (array $attribute): string => mb_strtolower($attribute['label']),
                $pin['attributes']
            );

            return (string) __('choose a %1', implode(' and ', $names));
        }
        if ($pin['type'] !== 'simple') {
            return (string) __('choose options on the product page');
        }

        return $pin['can_add'] ? '' : (string) __('out of stock');
    }

    /**
     * @param array<string, mixed> $content
     * @return list<mixed>
     */
    private function lookRows(array $content): array
    {
        $rows = (array) ($content['looks'] ?? []);
        $source = $this->text($content, 'source');
        if ($rows === [] && $source !== '') {
            $rows = (array) ($this->entryContent($source)['looks'] ?? []);
        }

        return array_values($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function entryContent(string $identifier): array
    {
        if (isset($this->entryContentById[$identifier])) {
            return $this->entryContentById[$identifier];
        }

        $content = [];
        $storeId = (int) $this->storeManager->getStore()->getId();
        foreach ([$storeId, Store::DEFAULT_STORE_ID] as $tryStoreId) {
            try {
                $entry = $this->entryRepository->getByIdentifier($identifier, $tryStoreId);
                if ($entry->isActive()) {
                    $content = (array) $entry->getEntryData();
                    break;
                }
            } catch (NoSuchEntityException) {
                continue;
            }
        }

        return $this->entryContentById[$identifier] = $content;
    }

    /**
     * Average colour of a media image (#RRGGBB), shown behind the photo while it loads; '' when unknown.
     */
    private function tone(string $url): string
    {
        $relative = $this->mediaRelativePath($url);
        if ($relative === null || !function_exists('imagecreatefromstring')) {
            return '';
        }

        try {
            $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            if (!$media->isFile($relative)) {
                return '';
            }
            $path = $media->getAbsolutePath($relative);
            $key = self::TONE_CACHE_PREFIX . md5($relative . '|' . (string) filemtime($path));
            $cached = $this->cache->load($key);
            if (is_string($cached) && $cached !== '') {
                return $cached === '-' ? '' : $cached;
            }

            $tone = '';
            $image = @imagecreatefromstring((string) file_get_contents($path));
            if ($image !== false) {
                $pixel = imagecreatetruecolor(1, 1);
                imagecopyresampled($pixel, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
                $tone = sprintf('#%06X', imagecolorat($pixel, 0, 0) & 0xFFFFFF);
            }
            $this->cache->save($tone !== '' ? $tone : '-', $key, [], 30 * 86400);

            return $tone;
        } catch (Throwable) {
            return '';
        }
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
