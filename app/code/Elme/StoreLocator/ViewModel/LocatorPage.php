<?php

declare(strict_types=1);

namespace Elme\StoreLocator\ViewModel;

use Elme\StoreLocator\Model\Service\LocationQuery;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-51 list page: locations payload, meta, JSON-LD.
 */
class LocatorPage implements ArgumentInterface
{
    public const PAGE_TITLE = 'Showroom locator';

    public const META_TITLE = 'Showroom & Pick-up Locator | Elme Living';

    public const META_DESCRIPTION = 'Find Elme Living trade showrooms and distribution pick-up centres in VIC, NSW and QLD. Search by suburb or postcode, or filter by state.';

    public const INTRO = 'Visit the trade showroom to see the range, or collect an order from a distribution & pick-up centre. Warehouses are not showrooms.';

    /** @var list<string> */
    public const STATES = ['NSW', 'QLD', 'VIC'];

    public function __construct(
        private readonly LocationQuery $locationQuery,
        private readonly LocationPresenter $presenter,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getPresenter(): LocationPresenter
    {
        return $this->presenter;
    }

    /**
     * @return list<\Elme\StoreLocator\Api\Data\LocationInterface>
     */
    public function getLocations(): array
    {
        return $this->locationQuery->list();
    }

    /**
     * @return list<string>
     */
    public function getStates(): array
    {
        return self::STATES;
    }

    public function getPageTitle(): string
    {
        return (string) __(self::PAGE_TITLE);
    }

    public function getMetaTitle(): string
    {
        return (string) __(self::META_TITLE);
    }

    public function getMetaDescription(): string
    {
        return (string) __(self::META_DESCRIPTION);
    }

    public function getIntro(): string
    {
        return (string) __(self::INTRO);
    }

    public function getCanonicalUrl(): string
    {
        return $this->presenter->getListUrl();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getCards(): array
    {
        $cards = [];
        foreach ($this->getLocations() as $location) {
            $cards[] = $this->presenter->toListCard($location);
        }

        return $cards;
    }

    public function getCardsJson(): string
    {
        return (string) json_encode(
            $this->getCards(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonLdGraph(): array
    {
        $baseUrl = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');
        $listUrl = $this->getCanonicalUrl();
        $locations = $this->getLocations();

        $itemListElement = [];
        foreach ($locations as $index => $location) {
            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => $this->presenter->toSchemaPlace($location, false),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => ['CollectionPage', 'WebPage'],
                    '@id' => $listUrl . '#webpage',
                    'url' => $listUrl,
                    'name' => $this->getMetaTitle(),
                    'description' => $this->getMetaDescription(),
                    'inLanguage' => 'en-AU',
                    'isPartOf' => ['@id' => $baseUrl . '/#website'],
                    'about' => ['@id' => $baseUrl . '/#organization'],
                    'publisher' => ['@id' => $baseUrl . '/#organization'],
                    'breadcrumb' => ['@id' => $listUrl . '#breadcrumb'],
                    'mainEntity' => ['@id' => $listUrl . '#location-list'],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $listUrl . '#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'item' => ['@id' => $baseUrl . '/', 'name' => 'Home'],
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'item' => ['@id' => $listUrl, 'name' => 'Showroom locator'],
                        ],
                    ],
                ],
                [
                    '@type' => 'ItemList',
                    '@id' => $listUrl . '#location-list',
                    'name' => 'Elme Living showrooms and pick-up centres',
                    'numberOfItems' => count($locations),
                    'itemListElement' => $itemListElement,
                ],
            ],
        ];
    }

    public function getJsonLdScript(): string
    {
        return (string) json_encode(
            $this->getJsonLdGraph(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }
}
