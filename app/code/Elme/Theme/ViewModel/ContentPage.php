<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\MediaUrlRebaser;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Head and JSON-LD of a public CMS content page (help pages, Careers, About...): one @graph with the
 * WebPage node and the BreadcrumbList, plus Open Graph / Twitter. FE-55 owns #organization and
 * #website; they are linked by @id only. The page title is the second crumb, as in the visible trail
 * (Magento\Cms\Block\Page), so the layout sets `skip_ld_json_schema` on `breadcrumbs`.
 *
 * When the page content opens with a Content Studio `hero`, its first slide's desktop photo is the
 * primaryImageOfPage and og:image.
 */
class ContentPage implements ArgumentInterface
{
    public const OG_TYPE = 'website';

    public const OG_LOCALE = 'en_AU';

    public const LANGUAGE = 'en-AU';

    private const ENTRY_WIDGET = '/\{\{widget\s+type="Bart\\\\+ContentStudio\\\\+Block\\\\+Entry"[^}]*?identifier="([^"]+)"/';

    /** @var array{src: string, width: int, height: int}|false|null */
    private array|false|null $primaryImage = null;

    public function __construct(
        private readonly CmsPage $cmsPage,
        private readonly StoreManagerInterface $storeManager,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly MediaUrlRebaser $mediaUrlRebaser,
        private readonly Hero $hero
    ) {
    }

    public function getMetaTitle(): string
    {
        $title = trim((string)$this->cmsPage->getMetaTitle());

        return $title !== '' ? $title : $this->getPageTitle();
    }

    public function getMetaDescription(): string
    {
        return trim((string)$this->cmsPage->getMetaDescription());
    }

    public function getPageTitle(): string
    {
        return trim((string)$this->cmsPage->getTitle());
    }

    /**
     * Absolute self URL without a query string.
     */
    public function getCanonicalUrl(): string
    {
        $store = $this->storeManager->getStore();
        $identifier = (string)$this->cmsPage->getIdentifier();

        if ($identifier !== '') {
            return rtrim((string)$store->getBaseUrl(), '/') . '/' . ltrim($identifier, '/');
        }

        return rtrim((string)$store->getCurrentUrl(false), '/');
    }

    /**
     * Desktop photo of the first slide of the hero the page content opens with, or null.
     *
     * @return array{src: string, width: int, height: int}|null
     */
    public function getPrimaryImage(): ?array
    {
        if ($this->primaryImage === null) {
            $this->primaryImage = $this->findHeroImage() ?? false;
        }

        return $this->primaryImage ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonLdGraph(string $schemaType = 'WebPage'): array
    {
        $baseUrl = rtrim((string)$this->storeManager->getStore()->getBaseUrl(), '/');
        $pageUrl = $this->getCanonicalUrl();

        $webpage = [
            '@type' => $schemaType,
            '@id' => $pageUrl . '#webpage',
            'url' => $pageUrl,
            'name' => $this->getMetaTitle(),
        ];
        $description = $this->getMetaDescription();
        if ($description !== '') {
            $webpage['description'] = $description;
        }
        $webpage += [
            'inLanguage' => self::LANGUAGE,
            'isPartOf' => ['@id' => $baseUrl . '/#website'],
            'about' => ['@id' => $baseUrl . '/#organization'],
            'publisher' => ['@id' => $baseUrl . '/#organization'],
            'breadcrumb' => ['@id' => $pageUrl . '#breadcrumb'],
        ];
        $image = $this->getPrimaryImage();
        if ($image !== null) {
            $webpage['primaryImageOfPage'] = [
                '@type' => 'ImageObject',
                'url' => $image['src'],
                'width' => $image['width'],
                'height' => $image['height'],
            ];
        }
        $updated = (string)$this->cmsPage->getUpdateTime();
        if ($updated !== '') {
            $webpage['dateModified'] = str_replace(' ', 'T', $updated);
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
                            'item' => ['@id' => $baseUrl . '/', 'name' => 'Home'],
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'item' => ['@id' => $pageUrl, 'name' => $this->getPageTitle()],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getJsonLdScript(string $schemaType = 'WebPage'): string
    {
        return (string)json_encode(
            $this->getJsonLdGraph($schemaType),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    /**
     * @return array{src: string, width: int, height: int}|null
     */
    private function findHeroImage(): ?array
    {
        if (!preg_match(self::ENTRY_WIDGET, (string)$this->cmsPage->getContent(), $match)) {
            return null;
        }

        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            $entry = $this->entryRepository->getByIdentifier($match[1], $storeId);
        } catch (Throwable) {
            return null;
        }

        if (!$entry->isActive() || $entry->getType() !== 'hero') {
            return null;
        }

        $content = $entry->getEntryData();
        foreach ((array)($content['slides'] ?? []) as $i => $row) {
            if (is_array($row) && is_string($row['image_desktop'] ?? null)) {
                $content['slides'][$i]['image_desktop'] = $this->mediaUrlRebaser->rebase(trim($row['image_desktop']));
            }
        }

        $slides = $this->hero->getSlides($content);

        return $slides ? $slides[0]['desktop'] : null;
    }
}
