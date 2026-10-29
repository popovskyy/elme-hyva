<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-67 Become a Stockist main column: How it works steps, UX-02 facts, CTAs, and HowTo JSON-LD.
 * Visible HTML and schema share the same step strings.
 */
class BecomeStockist implements ArgumentInterface
{
    public const HOW_TO_HEADING = 'How it works';

    public const HOW_TO_SCHEMA_NAME = 'How to become an Elme Living stockist';

    public const HOW_TO_SCHEMA_DESCRIPTION = 'Elme Living is trade-only. Apply with a valid Australian Business Number (ABN) to open a wholesale account.';

    public const FACTS_HEADING = 'Trade account facts';

    /**
     * @var list<array{name: string, text: string}>
     */
    private const STEPS = [
        [
            'name' => 'Apply with your ABN',
            'text' => 'Submit a trade account application with your valid Australian Business Number (ABN).',
        ],
        [
            'name' => 'We review and approve',
            'text' => "We'll email you when your wholesale account is approved.",
        ],
        [
            'name' => 'Log in to see your prices and order',
            'text' => 'Sign in to see your trade prices (ex GST), stock as a number, and use Quick Order.',
        ],
    ];

    /**
     * UX-02 facts: ABN, minimum order with no amount, Australia-wide delivery.
     *
     * @var list<string>
     */
    private const FACTS = [
        'A valid Australian Business Number (ABN) is required.',
        'A minimum order applies.',
        'Delivery is available Australia-wide.',
    ];

    public function __construct(
        private readonly ContentPage $contentPage,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * Short intro paragraphs (plain text) for the main column.
     *
     * @return list<string>
     */
    public function getIntroParagraphs(): array
    {
        return [
            (string) __(
                'Elme Living is Australia\'s trade-only wholesaler of artificial flora, homewares and decor.'
                . ' Retailers apply with a valid Australian Business Number (ABN) to open a wholesale account.'
            ),
            (string) __(
                'Once approved, you sign in to see your trade prices (ex GST), stock as a number, and order'
                . ' online — including Quick Order for bulk SKU entry.'
            ),
        ];
    }

    /**
     * @return list<array{position: int, name: string, text: string}>
     */
    public function getSteps(): array
    {
        $steps = [];
        foreach (self::STEPS as $i => $step) {
            $steps[] = [
                'position' => $i + 1,
                'name' => (string) __($step['name']),
                'text' => (string) __($step['text']),
            ];
        }

        return $steps;
    }

    /**
     * @return list<string>
     */
    public function getFacts(): array
    {
        return array_map(
            static fn (string $fact): string => (string) __($fact),
            self::FACTS
        );
    }

    public function getHowToHeading(): string
    {
        return (string) __(self::HOW_TO_HEADING);
    }

    public function getFactsHeading(): string
    {
        return (string) __(self::FACTS_HEADING);
    }

    public function getCreateAccountUrl(): string
    {
        return $this->urlBuilder->getUrl('customer/account/create');
    }

    public function getLoginUrl(): string
    {
        return $this->urlBuilder->getUrl('customer/account/login');
    }

    public function getMeetTheTeamUrl(): string
    {
        return $this->pageUrl('meet-the-team');
    }

    public function getQuickOrderUrl(): string
    {
        return $this->pageUrl('quick-order');
    }

    public function getShopUrl(): string
    {
        return rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/') . '/';
    }

    /**
     * WebPage + BreadcrumbList from ContentPage, plus HowTo as mainEntity.
     *
     * @return array<string, mixed>
     */
    public function getJsonLdGraph(): array
    {
        $graph = $this->contentPage->getJsonLdGraph('WebPage');
        $pageUrl = $this->contentPage->getCanonicalUrl();

        if (!isset($graph['@graph'][0]) || !is_array($graph['@graph'][0])) {
            return $graph;
        }

        $graph['@graph'][0]['mainEntity'] = ['@id' => $pageUrl . '#howto'];
        $graph['@graph'][] = $this->getHowToNode($pageUrl);

        return $graph;
    }

    public function getJsonLdScript(): string
    {
        return (string) json_encode(
            $this->getJsonLdGraph(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    /**
     * HowTo steps use the same English strings as the visible HTML (en-AU storefront).
     *
     * @return array<string, mixed>
     */
    private function getHowToNode(string $pageUrl): array
    {
        $steps = [];
        foreach (self::STEPS as $i => $step) {
            $position = $i + 1;
            $item = [
                '@type' => 'HowToStep',
                'position' => $position,
                'name' => $step['name'],
                'text' => $step['text'],
            ];
            if ($position === 1) {
                $item['url'] = $this->getCreateAccountUrl();
            }
            $steps[] = $item;
        }

        return [
            '@type' => 'HowTo',
            '@id' => $pageUrl . '#howto',
            'name' => self::HOW_TO_SCHEMA_NAME,
            'description' => self::HOW_TO_SCHEMA_DESCRIPTION,
            'step' => $steps,
        ];
    }

    private function pageUrl(string $identifier): string
    {
        return rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/') . '/' . ltrim($identifier, '/');
    }
}
