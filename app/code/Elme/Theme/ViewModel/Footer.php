<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-10 footer: CMS-driven link columns, social links, payment icons.
 *
 * Blocks: footer_links, footer_social, footer_payment_block (see CreateFooterCmsBlocks).
 */
class Footer implements ArgumentInterface
{
    private const LINKS_BLOCK = 'footer_links';

    private const SOCIAL_BLOCK = 'footer_social';

    private const PAYMENT_BLOCK = 'footer_payment_block';

    /** @var list<array{title: string, links: list<array{label: string, href: string, external: bool}>}>|null */
    private ?array $columns = null;

    /** @var list<array{network: string, label: string, href: string}>|null */
    private ?array $social = null;

    /** @var list<array{src: string, alt: string, width: ?int, height: ?int}>|null */
    private ?array $payments = null;

    public function __construct(
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly FilterProvider $filterProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly AssetRepository $assetRepository
    ) {
    }

    /**
     * @return list<array{title: string, links: list<array{label: string, href: string, external: bool}>}>
     */
    public function getColumns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $html = $this->filteredBlockHtml(self::LINKS_BLOCK);
        $parsed = $html !== '' ? $this->parseColumns($html) : [];
        $this->columns = $parsed !== [] ? $parsed : $this->defaultColumns();

        return $this->columns;
    }

    /**
     * @return list<array{network: string, label: string, href: string}>
     */
    public function getSocialLinks(): array
    {
        if ($this->social !== null) {
            return $this->social;
        }

        $html = $this->filteredBlockHtml(self::SOCIAL_BLOCK);
        $parsed = $html !== '' ? $this->parseSocial($html) : [];
        $this->social = $parsed !== [] ? $parsed : $this->defaultSocial();

        return $this->social;
    }

    /**
     * Accessible name for a social icon link, e.g. "Elme Living on Instagram".
     */
    public function getSocialAriaLabel(string $network, string $fallbackLabel): string
    {
        $names = [
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'pinterest' => 'Pinterest',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
        ];
        $networkKey = strtolower($network);
        $name = $names[$networkKey] ?? ($fallbackLabel !== '' ? $fallbackLabel : ucfirst($networkKey));

        return (string) __('Elme Living on %1', $name);
    }

    /**
     * @return list<array{src: string, alt: string, width: ?int, height: ?int}>
     */
    public function getPaymentIcons(): array
    {
        if ($this->payments !== null) {
            return $this->payments;
        }

        $html = $this->filteredBlockHtml(self::PAYMENT_BLOCK);
        $parsed = $html !== '' ? $this->parsePayments($html) : [];
        $this->payments = $parsed !== [] ? $parsed : $this->defaultPayments();

        return $this->payments;
    }

    public function getLogoUrl(): string
    {
        return $this->assetRepository->getUrl('images/footer-logo.svg');
    }

    private function filteredBlockHtml(string $identifier): string
    {
        try {
            $storeId = (int) $this->storeManager->getStore()->getId();
            $block = $this->getBlockByIdentifier->execute($identifier, $storeId);
        } catch (NoSuchEntityException) {
            return '';
        }

        if (!(bool) $block->getIsActive()) {
            return '';
        }

        $html = (string) $block->getContent();
        if ($html === '') {
            return '';
        }

        try {
            return (string) $this->filterProvider->getBlockFilter()->filter($html);
        } catch (\Throwable) {
            return $html;
        }
    }

    /**
     * @return list<array{title: string, links: list<array{label: string, href: string, external: bool}>}>
     */
    private function parseColumns(string $html): array
    {
        $dom = $this->loadDom($html, 'elme-footer-links-root');
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $sections = $xpath->query('//*[@data-footer-column]');
        if ($sections === false || $sections->length === 0) {
            // Accept production Magento-collapsible markup (.box-links)
            $sections = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " box-links ")]');
        }
        if ($sections === false || $sections->length === 0) {
            return [];
        }

        $columns = [];
        foreach ($sections as $section) {
            if (!$section instanceof DOMElement) {
                continue;
            }
            $titleNode = $xpath->query('.//h3|.//h2|.//*[@data-footer-column-title]', $section)->item(0);
            $title = $titleNode ? trim($titleNode->textContent ?? '') : '';
            $anchors = $xpath->query('.//a[@href]', $section);
            $links = [];
            if ($anchors !== false) {
                foreach ($anchors as $anchor) {
                    if (!$anchor instanceof DOMElement) {
                        continue;
                    }
                    $href = trim($anchor->getAttribute('href'));
                    $label = trim($anchor->textContent ?? '');
                    if ($href === '' || $label === '') {
                        continue;
                    }
                    $links[] = [
                        'label' => $label,
                        'href' => $href,
                        'external' => $this->isExternalHref($href, $anchor),
                    ];
                }
            }
            if ($title === '' && $links === []) {
                continue;
            }
            $columns[] = [
                'title' => $title !== '' ? $title : (string) __('Links'),
                'links' => $links,
            ];
        }

        return $columns;
    }

    /**
     * @return list<array{network: string, label: string, href: string}>
     */
    private function parseSocial(string $html): array
    {
        $dom = $this->loadDom($html, 'elme-footer-social-root');
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $anchors = $xpath->query('//a[@href]');
        if ($anchors === false || $anchors->length === 0) {
            return [];
        }

        $items = [];
        foreach ($anchors as $anchor) {
            if (!$anchor instanceof DOMElement) {
                continue;
            }
            $href = trim($anchor->getAttribute('href'));
            if ($href === '') {
                continue;
            }
            $network = strtolower(trim($anchor->getAttribute('data-network')));
            if ($network === '') {
                $network = $this->guessNetwork($href, $anchor->getAttribute('class'), $anchor->getAttribute('title'));
            }
            $label = trim($anchor->textContent ?? '');
            if ($label === '') {
                $label = trim($anchor->getAttribute('title'));
            }
            $items[] = [
                'network' => $network,
                'label' => $label,
                'href' => $href,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{src: string, alt: string, width: ?int, height: ?int}>
     */
    private function parsePayments(string $html): array
    {
        $dom = $this->loadDom($html, 'elme-footer-payments-root');
        if ($dom === null) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $images = $xpath->query('//img[@src]');
        if ($images === false || $images->length === 0) {
            return [];
        }

        $items = [];
        foreach ($images as $img) {
            if (!$img instanceof DOMElement) {
                continue;
            }
            $src = trim($img->getAttribute('src'));
            if ($src === '') {
                continue;
            }
            $width = $img->getAttribute('width');
            $height = $img->getAttribute('height');
            $items[] = [
                'src' => $src,
                'alt' => trim($img->getAttribute('alt')) ?: 'Payment',
                'width' => $width !== '' ? (int) $width : null,
                'height' => $height !== '' ? (int) $height : null,
            ];
        }

        return $items;
    }

    private function loadDom(string $html, string $rootId): ?DOMDocument
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="' . $rootId . '">' . $html . '</div>';
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $loaded ? $dom : null;
    }

    private function isExternalHref(string $href, DOMElement $anchor): bool
    {
        $target = strtolower(trim($anchor->getAttribute('target')));
        if ($target === '_blank' || $target === 'blank') {
            return true;
        }

        if (!preg_match('#^https?://#i', $href)) {
            return false;
        }

        try {
            $baseHost = parse_url($this->urlBuilder->getBaseUrl(), PHP_URL_HOST);
            $linkHost = parse_url($href, PHP_URL_HOST);
        } catch (\Throwable) {
            return true;
        }

        if (!$baseHost || !$linkHost) {
            return false;
        }

        return strcasecmp((string) $baseHost, (string) $linkHost) !== 0;
    }

    private function guessNetwork(string $href, string $class, string $title): string
    {
        $haystack = strtolower($href . ' ' . $class . ' ' . $title);
        foreach (['facebook', 'instagram', 'pinterest', 'linkedin', 'tiktok', 'tik-tok'] as $key) {
            if (str_contains($haystack, $key)) {
                return $key === 'tik-tok' ? 'tiktok' : $key;
            }
        }

        return 'social';
    }

    private function directUrl(string $path): string
    {
        return $this->urlBuilder->getUrl('', ['_direct' => $path]);
    }

    /**
     * @return list<array{title: string, links: list<array{label: string, href: string, external: bool}>}>
     */
    private function defaultColumns(): array
    {
        return [
            [
                'title' => (string) __('Shop'),
                'links' => [
                    ['label' => (string) __('Quick Order'), 'href' => $this->directUrl('quick-order'), 'external' => false],
                    ['label' => (string) __('Furniture'), 'href' => $this->directUrl('furniture.html'), 'external' => false],
                    ['label' => (string) __('Plants'), 'href' => $this->directUrl('flowers-plants.html'), 'external' => false],
                    ['label' => (string) __('Homewares'), 'href' => $this->directUrl('homewares.html'), 'external' => false],
                    ['label' => (string) __('Shop the Looks'), 'href' => $this->directUrl('shop-the-looks'), 'external' => false],
                    ['label' => (string) __('Virtual Catalogues'), 'href' => $this->directUrl('virtual-catalogues'), 'external' => false],
                    ['label' => (string) __('Shop Instagram'), 'href' => $this->directUrl('shoppable-instagram'), 'external' => false],
                    ['label' => (string) __('Style Sourcebook'), 'href' => $this->directUrl('style-sourcebook'), 'external' => false],
                    [
                        'label' => (string) __('Outlet & Clearance Store'),
                        'href' => $this->directUrl('outlet-and-clearance-store'),
                        'external' => false,
                    ],
                ],
            ],
            [
                'title' => (string) __('Service'),
                'links' => [
                    ['label' => (string) __('About Us'), 'href' => $this->directUrl('about-us'), 'external' => false],
                    ['label' => (string) __('Meet the Team'), 'href' => $this->directUrl('meet-the-team'), 'external' => false],
                    [
                        'label' => (string) __('Showroom Locator'),
                        'href' => $this->directUrl('showroom-locator'),
                        'external' => false,
                    ],
                    [
                        'label' => (string) __('Become a Stockist'),
                        'href' => $this->urlBuilder->getUrl('customer/account/create'),
                        'external' => false,
                    ],
                    ['label' => (string) __('Fairs & Events'), 'href' => $this->directUrl('fairs-events'), 'external' => false],
                    [
                        'label' => (string) __('Careers'),
                        'href' => $this->directUrl('career-opportunities'),
                        'external' => false,
                    ],
                ],
            ],
            [
                'title' => (string) __('Help'),
                'links' => [
                    ['label' => (string) __('Contact'), 'href' => $this->directUrl('contact-us'), 'external' => false],
                    [
                        'label' => (string) __('Shipping & Returns'),
                        'href' => $this->directUrl('shipping-and-returns'),
                        'external' => false,
                    ],
                    [
                        'label' => (string) __('Terms & Conditions'),
                        'href' => $this->directUrl('website-terms-conditions'),
                        'external' => false,
                    ],
                    [
                        'label' => (string) __('Privacy Policy'),
                        'href' => $this->directUrl('privacy-policy'),
                        'external' => false,
                    ],
                    ['label' => (string) __('FAQs'), 'href' => $this->directUrl('faqs'), 'external' => false],
                    [
                        'label' => (string) __('Submit a Product Claim'),
                        'href' => 'https://help.elmeliving.com/hc/en-au/requests/new?ticket_form_id=14703613453199',
                        'external' => true,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array{network: string, label: string, href: string}>
     */
    private function defaultSocial(): array
    {
        return [
            [
                'network' => 'facebook',
                'label' => 'Facebook',
                'href' => 'https://www.facebook.com/profile.php?id=100086007120442',
            ],
            [
                'network' => 'instagram',
                'label' => 'Instagram',
                'href' => 'https://www.instagram.com/elmeliving/',
            ],
            [
                'network' => 'pinterest',
                'label' => 'Pinterest',
                'href' => 'https://www.pinterest.com.au/elmeliving/',
            ],
            [
                'network' => 'linkedin',
                'label' => 'LinkedIn',
                'href' => 'https://www.linkedin.com/company/elme-living/',
            ],
            [
                'network' => 'tiktok',
                'label' => 'TikTok',
                'href' => 'https://www.tiktok.com/@elmeliving/',
            ],
        ];
    }

    /**
     * @return list<array{src: string, alt: string, width: ?int, height: ?int}>
     */
    private function defaultPayments(): array
    {
        return [
            [
                'src' => $this->assetRepository->getUrl('images/footer/visa.svg'),
                'alt' => 'Visa',
                'width' => 48,
                'height' => 16,
            ],
            [
                'src' => $this->assetRepository->getUrl('images/footer/mastercard.svg'),
                'alt' => 'Mastercard',
                'width' => 34,
                'height' => 22,
            ],
        ];
    }
}
