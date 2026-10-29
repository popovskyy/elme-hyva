<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Elme\LoginOnly\Model\LoginState;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Content Studio type `virtual_catalogues` (FE-50) + page JSON-LD / OG helpers.
 *
 * Cover title + image are always guest-safe. Flipbook (`open_url`) and shop links are only returned
 * when the visitor is logged in (QA 0 / FPC varies by auth HTTP context).
 */
class VirtualCatalogues implements ArgumentInterface
{
    public const PAGE_IDENTIFIER = 'virtual-catalogues';

    public const ENTRY_IDENTIFIER = 'virtual-catalogues-list';

    public const MAX_CATALOGUES = 12;

    public const IMAGE_SIZE = [620, 877];

    private ?string $mediaPath = null;

    /** @var array<string, mixed>|null */
    private ?array $entryContent = null;

    private bool $entryLoaded = false;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        private readonly LoginState $loginState,
        private readonly CmsPage $cmsPage,
        private readonly EntryRepositoryInterface $entryRepository
    ) {
    }

    public function isVirtualCataloguesPage(?string $identifier = null): bool
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
     * Guest-safe cards always; open_url / shop_url only when logged in.
     *
     * @param array<string, mixed> $content
     * @return list<array{
     *     title: string,
     *     description: string,
     *     image_alt: string,
     *     image: array{src: string, width: int, height: int},
     *     open_url: string,
     *     shop_url: string,
     *     shop_label: string
     * }>
     */
    public function getCatalogues(array $content): array
    {
        $includePrivate = $this->isLoggedIn();
        $items = [];

        foreach ((array) ($content['catalogues'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = $this->text($row, 'title');
            $image = $this->text($row, 'image');

            if ($title === '' || $image === '') {
                continue;
            }

            $alt = $this->text($row, 'image_alt');
            $shopLabel = $this->text($row, 'shop_label');

            $items[] = [
                'title' => $title,
                'description' => $this->text($row, 'description'),
                'image_alt' => $alt !== '' ? $alt : sprintf('Elme Living %s catalogue cover', $title),
                'image' => $this->image($image),
                'open_url' => $includePrivate ? $this->text($row, 'open_url') : '',
                'shop_url' => $includePrivate ? $this->text($row, 'shop_url') : '',
                'shop_label' => $shopLabel !== '' ? $shopLabel : (string) __('Shop this range'),
            ];

            if (count($items) === self::MAX_CATALOGUES) {
                break;
            }
        }

        return $items;
    }

    /**
     * Entry content for the page (JSON-LD / OG). Empty array when missing.
     *
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
     * Guest-safe catalogue list for schema (never includes open_url / shop_url).
     *
     * @return list<array{title: string, description: string, image_alt: string, image: array{src: string, width: int, height: int}}>
     */
    public function getGuestCataloguesForSchema(): array
    {
        $items = [];
        foreach ((array) ($this->getPageContent()['catalogues'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = $this->text($row, 'title');
            $image = $this->text($row, 'image');
            if ($title === '' || $image === '') {
                continue;
            }
            $alt = $this->text($row, 'image_alt');
            $items[] = [
                'title' => $title,
                'description' => $this->text($row, 'description'),
                'image_alt' => $alt !== '' ? $alt : sprintf('Elme Living %s catalogue cover', $title),
                'image' => $this->image($image),
            ];
            if (count($items) === self::MAX_CATALOGUES) {
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
        $covers = $this->getGuestCataloguesForSchema();

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
                        'item' => ['@id' => $pageUrl, 'name' => 'Virtual Catalogues'],
                    ],
                ],
            ],
        ];

        if ($covers) {
            $first = $covers[0]['image']['src'];
            $webpage['mainEntity'] = ['@id' => $pageUrl . '#catalogue-list'];
            $webpage['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $first,
            ];
            $graph[0] = $webpage;

            $listElements = [];
            foreach ($covers as $i => $cover) {
                $item = [
                    '@type' => 'CreativeWork',
                    'name' => $cover['title'],
                    'image' => $cover['image']['src'],
                    'brand' => ['@id' => $baseUrl . '/#organization'],
                ];
                if ($cover['description'] !== '') {
                    $item['description'] = $cover['description'];
                }
                $listElements[] = [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'item' => $item,
                ];
            }

            $graph[] = [
                '@type' => 'ItemList',
                '@id' => $pageUrl . '#catalogue-list',
                'name' => 'Elme Living virtual catalogues',
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
        $covers = $this->getGuestCataloguesForSchema();

        return $covers[0]['image']['src'] ?? '';
    }

    public function getLoginUrl(): string
    {
        return $this->storeManager->getStore()->getUrl('customer/account/login');
    }

    public function getStockistUrl(): string
    {
        return $this->storeManager->getStore()->getUrl('become-a-stockist');
    }

    private function canonicalUrl(): string
    {
        return rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/')
            . '/' . self::PAGE_IDENTIFIER;
    }

    /**
     * @return array{src: string, width: int, height: int}
     */
    private function image(string $url): array
    {
        [$width, $height] = $this->readSize($url) ?? self::IMAGE_SIZE;

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
