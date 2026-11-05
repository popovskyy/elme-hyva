<?php

declare(strict_types=1);

namespace Elme\StoreLocator\ViewModel;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Elme\StoreLocator\Controller\Index\View as ViewController;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-51 location page: current location, hours, CTAs, JSON-LD.
 */
class LocationPage implements ArgumentInterface
{
    public function __construct(
        private readonly Registry $registry,
        private readonly LocationPresenter $presenter,
        private readonly Hours $hours,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function getPresenter(): LocationPresenter
    {
        return $this->presenter;
    }

    public function getHours(): Hours
    {
        return $this->hours;
    }

    public function getLocation(): ?LocationInterface
    {
        $location = $this->registry->registry(ViewController::REGISTRY_KEY);

        return $location instanceof LocationInterface ? $location : null;
    }

    public function getMetaTitle(LocationInterface $location): string
    {
        // Drop "(TRADE ONLY)" so titles stay near 60 chars without cutting mid-word.
        $shortName = trim((string) preg_replace('/\s*\(TRADE ONLY\)\s*/i', '', $location->getName()));
        $cityState = trim($location->getCity() . ' ' . $location->getState());

        // Prefer a shorter label for long warehouse names.
        if (str_contains($shortName, 'Distribution')) {
            $shortName = trim(str_replace(
                ['Elme Living Melbourne Distribution & Pick up Centre', 'Distribution & Pick up Centre'],
                ['Melbourne Distribution Centre', 'Distribution Centre'],
                $shortName
            ));
        }

        return $shortName . ' | ' . $cityState;
    }

    public function getMetaDescription(LocationInterface $location): string
    {
        $type = $this->presenter->isShowroom($location)
            ? 'Trade-only Elme Living showroom'
            : 'Elme Living distribution & pick-up centre';
        $address = sprintf(
            '%s, %s %s %s',
            (string) $location->getStreet(),
            (string) $location->getCity(),
            $location->getState(),
            $location->getPostcode()
        );
        $job = $this->presenter->isShowroom($location)
            ? 'Book an appointment to view wholesale artificial flora, homewares and furniture.'
            : 'Pick-up hours Mon-Fri; this location is not a showroom.';

        return sprintf('%s at %s. %s', $type, $address, $job);
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonLdGraph(LocationInterface $location): array
    {
        $baseUrl = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');
        $pageUrl = $this->presenter->getLocationUrl($location);
        $listUrl = $this->presenter->getListUrl();
        $place = $this->presenter->toSchemaPlace($location, true);

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $pageUrl . '#webpage',
                    'url' => $pageUrl,
                    'name' => $this->getMetaTitle($location),
                    'description' => $this->getMetaDescription($location),
                    'inLanguage' => 'en-AU',
                    'isPartOf' => ['@id' => $baseUrl . '/#website'],
                    'about' => ['@id' => $place['@id']],
                    'publisher' => ['@id' => $baseUrl . '/#organization'],
                    'breadcrumb' => ['@id' => $pageUrl . '#breadcrumb'],
                    'mainEntity' => ['@id' => $place['@id']],
                ],
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
                            'item' => ['@id' => $listUrl, 'name' => 'Showroom locator'],
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 3,
                            'item' => ['@id' => $pageUrl, 'name' => $location->getName()],
                        ],
                    ],
                ],
                $place,
            ],
        ];
    }

    public function getJsonLdScript(LocationInterface $location): string
    {
        return (string) json_encode(
            $this->getJsonLdGraph($location),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }
}
