<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-45 curated help side nav (no VersionsCms on Open Source). Head and JSON-LD come from ContentPage.
 */
class HelpPages implements ArgumentInterface
{
    /**
     * Task-first order (idea review): Contact → Shipping → FAQs → Stockist → Terms → Trade → Privacy.
     *
     * @var list<array{
     *     identifier: string,
     *     label: string,
     *     on_this_page: bool,
     *     banner?: bool
     * }>
     */
    public const PAGES = [
        [
            'identifier' => 'contact-us',
            'label' => 'Contact Us',
            'on_this_page' => false,
        ],
        [
            'identifier' => 'shipping-and-returns',
            'label' => 'Shipping & Returns',
            'on_this_page' => false,
        ],
        [
            'identifier' => 'faqs',
            'label' => 'FAQs',
            'on_this_page' => false,
        ],
        [
            'identifier' => 'become-a-stockist',
            'label' => 'Become a Stockist',
            'on_this_page' => false,
            'banner' => true,
        ],
        [
            'identifier' => 'website-terms-conditions',
            'label' => 'Terms & Conditions',
            'on_this_page' => true,
        ],
        [
            'identifier' => 'terms-conditions-of-trade',
            'label' => 'Trade Terms & Conditions',
            'on_this_page' => true,
        ],
        [
            'identifier' => 'privacy-policy',
            'label' => 'Privacy Policy',
            'on_this_page' => true,
        ],
    ];

    public function __construct(
        private readonly CmsPage $cmsPage,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return list<string>
     */
    public function getIdentifiers(): array
    {
        return array_column(self::PAGES, 'identifier');
    }

    public function isHelpPage(?string $identifier = null): bool
    {
        $id = $identifier ?? $this->getCurrentIdentifier();
        return $id !== '' && in_array($id, $this->getIdentifiers(), true);
    }

    public function hasBanner(?string $identifier = null): bool
    {
        $id = $identifier ?? $this->getCurrentIdentifier();
        foreach (self::PAGES as $page) {
            if ($page['identifier'] === $id) {
                return !empty($page['banner']);
            }
        }
        return false;
    }

    public function getCurrentIdentifier(): string
    {
        return (string) $this->cmsPage->getIdentifier();
    }

    public function getCurrentTitle(): string
    {
        $heading = trim((string) $this->cmsPage->getContentHeading());
        if ($heading !== '') {
            return $heading;
        }

        $meta = $this->currentPageMeta();
        if ($meta !== null) {
            return $meta['label'];
        }

        return trim((string) $this->cmsPage->getTitle());
    }

    /**
     * @return list<array{identifier: string, label: string, url: string, current: bool}>
     */
    public function getMenuItems(): array
    {
        $current = $this->getCurrentIdentifier();
        $items = [];
        foreach (self::PAGES as $page) {
            $items[] = [
                'identifier' => $page['identifier'],
                'label' => $page['label'],
                'url' => $this->pageUrl($page['identifier']),
                'current' => $page['identifier'] === $current,
            ];
        }
        return $items;
    }

    public function showsOnThisPage(): bool
    {
        $meta = $this->currentPageMeta();
        return $meta !== null && !empty($meta['on_this_page']);
    }

    /**
     * In-page jump links from H2s that already have an id (Privacy & Terms seed content).
     *
     * @return list<array{id: string, label: string}>
     */
    public function getOnThisPageLinks(): array
    {
        if (!$this->showsOnThisPage()) {
            return [];
        }

        $content = (string) $this->cmsPage->getContent();
        if ($content === '' || !preg_match_all(
            '/<h2[^>]*\bid=(["\'])([^"\']+)\1[^>]*>(.*?)<\/h2>/is',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            return [];
        }

        $links = [];
        foreach ($matches as $match) {
            $label = trim(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($label === '') {
                continue;
            }
            $links[] = [
                'id' => $match[2],
                'label' => $label,
            ];
        }
        return $links;
    }

    /**
     * @return array{identifier: string, label: string, on_this_page: bool, banner?: bool}|null
     */
    private function currentPageMeta(): ?array
    {
        $current = $this->getCurrentIdentifier();
        foreach (self::PAGES as $page) {
            if ($page['identifier'] === $current) {
                return $page;
            }
        }
        return null;
    }

    private function pageUrl(string $identifier): string
    {
        return rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/') . '/' . ltrim($identifier, '/');
    }
}
