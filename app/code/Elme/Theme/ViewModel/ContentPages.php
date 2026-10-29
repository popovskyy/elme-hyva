<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-61: shared JSON-LD / Open Graph builder for marketing CMS pages (About, Careers, Outlet).
 * Help pages (FE-45) keep HelpPages for the side nav and call {@see buildGraph()} / {@see encodeGraph()}.
 */
class ContentPages implements ArgumentInterface
{
    /**
     * @var array<string, array{
     *     schema_type: string,
     *     breadcrumb: string,
     *     hero_entry?: string
     * }>
     */
    public const PAGES = [
        'about-us' => [
            'schema_type' => 'AboutPage',
            'breadcrumb' => 'About Us',
            'hero_entry' => 'about-us-hero',
        ],
    ];

    public function __construct(
        private readonly CmsPage $cmsPage,
        private readonly StoreManagerInterface $storeManager,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly Hero $hero
    ) {
    }

    /**
     * @return list<string>
     */
    public function getIdentifiers(): array
    {
        return array_keys(self::PAGES);
    }

    public function isContentPage(?string $identifier = null): bool
    {
        $id = $identifier ?? $this->getCurrentIdentifier();

        return $id !== '' && isset(self::PAGES[$id]);
    }

    public function getCurrentIdentifier(): string
    {
        return (string) $this->cmsPage->getIdentifier();
    }

    public function getSchemaType(): string
    {
        return $this->pageMeta()['schema_type'] ?? 'WebPage';
    }

    public function getBreadcrumbLabel(): string
    {
        $meta = $this->pageMeta();
        if ($meta !== null && ($meta['breadcrumb'] ?? '') !== '') {
            return $meta['breadcrumb'];
        }

        $heading = trim((string) $this->cmsPage->getContentHeading());
        if ($heading !== '') {
            return $heading;
        }

        return trim((string) $this->cmsPage->getTitle());
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

    public function getCanonicalUrl(): string
    {
        $identifier = $this->getCurrentIdentifier();
        if ($identifier !== '') {
            return $this->pageUrl($identifier);
        }

        return rtrim((string) $this->storeManager->getStore()->getCurrentUrl(false), '/');
    }

    public function getOgType(): string
    {
        return 'website';
    }

    public function getOgLocale(): string
    {
        return 'en_AU';
    }

    /**
     * Hero slide 1 desktop image for OG / primaryImageOfPage. Null when no hero entry or no image.
     *
     * @return array{url: string, width: int, height: int}|null
     */
    public function getPrimaryImage(): ?array
    {
        $meta = $this->pageMeta();
        $heroId = (string) ($meta['hero_entry'] ?? '');
        if ($heroId === '') {
            return null;
        }

        try {
            $entry = $this->entryRepository->getByIdentifier($heroId, (int) $this->storeManager->getStore()->getId());
        } catch (NoSuchEntityException) {
            try {
                $entry = $this->entryRepository->getByIdentifier($heroId, Store::DEFAULT_STORE_ID);
            } catch (NoSuchEntityException) {
                return null;
            }
        }

        $slides = $this->hero->getSlides($entry->getEntryData());
        if ($slides === []) {
            return null;
        }

        $desktop = $slides[0]['desktop'];

        return [
            'url' => $desktop['src'],
            'width' => $desktop['width'],
            'height' => $desktop['height'],
        ];
    }

    public function getTwitterCard(): string
    {
        return $this->getPrimaryImage() !== null ? 'summary_large_image' : 'summary';
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonLdGraph(): array
    {
        $updated = (string) $this->cmsPage->getUpdateTime();

        return $this->buildGraph(
            $this->getSchemaType(),
            $this->getCanonicalUrl(),
            $this->getMetaTitle(),
            $this->getMetaDescription(),
            $this->getBreadcrumbLabel(),
            $updated !== '' ? $updated : null,
            $this->getPrimaryImage()
        );
    }

    public function getJsonLdScript(): string
    {
        return $this->encodeGraph($this->getJsonLdGraph());
    }

    /**
     * Shared builder for marketing pages and help pages (FE-45).
     *
     * @param array{url: string, width?: int, height?: int}|null $primaryImage
     * @return array<string, mixed>
     */
    public function buildGraph(
        string $schemaType,
        string $pageUrl,
        string $name,
        string $description,
        string $breadcrumbLabel,
        ?string $dateModified = null,
        ?array $primaryImage = null
    ): array {
        $store = $this->storeManager->getStore();
        $baseUrl = rtrim((string) $store->getBaseUrl(), '/');

        $webpage = [
            '@type' => $schemaType,
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

        if ($primaryImage !== null && ($primaryImage['url'] ?? '') !== '') {
            $image = [
                '@type' => 'ImageObject',
                'url' => $primaryImage['url'],
            ];
            if (!empty($primaryImage['width'])) {
                $image['width'] = (int) $primaryImage['width'];
            }
            if (!empty($primaryImage['height'])) {
                $image['height'] = (int) $primaryImage['height'];
            }
            $webpage['primaryImageOfPage'] = $image;
        }

        if ($dateModified !== null && $dateModified !== '') {
            $webpage['dateModified'] = str_replace(' ', 'T', $dateModified);
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $webpage,
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $pageUrl . '#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'item' => [
                                '@id' => $baseUrl . '/',
                                'name' => 'Home',
                            ],
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'item' => [
                                '@id' => $pageUrl,
                                'name' => $breadcrumbLabel,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $graph
     */
    public function encodeGraph(array $graph): string
    {
        return (string) json_encode(
            $graph,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    /**
     * @return array{schema_type: string, breadcrumb: string, hero_entry?: string}|null
     */
    private function pageMeta(): ?array
    {
        $current = $this->getCurrentIdentifier();

        return self::PAGES[$current] ?? null;
    }

    private function pageUrl(string $identifier): string
    {
        return rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/') . '/' . ltrim($identifier, '/');
    }
}
