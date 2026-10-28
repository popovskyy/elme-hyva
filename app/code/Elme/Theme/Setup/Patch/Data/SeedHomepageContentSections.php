<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-16: seed Welcome, Outlet (copy refresh), Curated Catalogues and Stockist USP Content Studio
 * entries, copy theme images into pub/media, and wire them onto the home CMS page after the hero
 * and category tiles.
 */
class SeedHomepageContentSections implements DataPatchInterface
{
    private const MEDIA_DIR = 'bart_content_studio/fe16';

    private const HOME_IDENTIFIERS = [
        'home-hero',
        'home-category-tiles',
        'home-welcome',
        'home-outlet-promo',
        'home-catalogue-covers',
        'home-stockist-usp',
    ];

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        self::syncImagesToMedia();
        $mediaBase = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/' . self::MEDIA_DIR . '/';

        $this->ensureEntry(
            'home-welcome',
            'welcome_cta',
            'Home: Welcome',
            [
                'heading' => 'Wholesale artificial flowers, plants and home decor for Australian retailers',
                'text' => 'Elme Living is a wholesale trade site. Sign in for your Pronto prices (ex GST),'
                    . ' or apply for an ABN trade account to become a stockist.',
                'button_label' => 'Become a Stockist',
                'button_link' => '/become-a-stockist',
            ]
        );

        $this->ensureOrRefreshOutlet($mediaBase);

        $this->ensureEntry(
            'home-catalogue-covers',
            'catalogue_covers',
            'Home: Curated Catalogues',
            [
                'heading' => 'Curated Catalogues',
                'intro' => 'Plan your next buy with our Lookbook and Virtual Catalogues - style edits and'
                    . ' full ranges built for Australian retailers.',
                'cards' => [
                    [
                        'image' => $mediaBase . 'lookbook-cover.jpg',
                        'image_alt' => 'Elme Living Lookbook cover',
                        'title' => 'Lookbook',
                        'link' => '/shop-the-looks',
                    ],
                    [
                        'image' => $mediaBase . 'virtual-catalogues-cover.jpg',
                        'image_alt' => 'Elme Living Virtual Catalogues cover',
                        'title' => 'Virtual Catalogues',
                        'link' => '/virtual-catalogues',
                    ],
                ],
                'see_all_label' => 'See All Catalogues',
                'see_all_link' => '/virtual-catalogues',
            ]
        );

        $this->ensureEntry(
            'home-stockist-usp',
            'usp_strip',
            'Home: Become a Stockist',
            [
                'heading' => 'Become a Stockist',
                'intro' => 'Apply with your ABN for a wholesale trade account. Once approved, you shop'
                    . ' your Pronto prices online - built for Australian retailers, not a consumer gift shop.',
                'image' => $mediaBase . 'become-stockist.jpg',
                'image_alt' => 'Styled Elme Living homewares and artificial flora display',
                'items' => [
                    [
                        'title' => 'ABN trade account',
                        'text' => 'Register with a valid Australian Business Number. We review applications'
                            . ' and open wholesale access for approved retailers.',
                    ],
                    [
                        'title' => 'Prices ex GST',
                        'text' => 'After login you see your Pronto prices ex GST - customer-specific, not'
                            . ' a public retail list.',
                    ],
                    [
                        'title' => 'Australia-wide delivery',
                        'text' => 'Order online for delivery across Australia. Some lines carry a minimum'
                            . ' order quantity (MOQ); steppers jump in those multiples.',
                    ],
                ],
                'button_label' => 'Create An Account',
                'button_link' => '/customer/account/create',
            ]
        );

        $this->wireHomePage();

        return $this;
    }

    /**
     * Theme-tracked originals → pub/media (Content Studio image fields need the media tree).
     */
    public static function syncImagesToMedia(): void
    {
        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/fe16';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        foreach (['lookbook-cover.jpg', 'virtual-catalogues-cover.jpg', 'become-stockist.jpg', 'elme-outlet.jpg'] as $file) {
            $from = $sourceDir . '/' . $file;
            $to = $targetDir . '/' . $file;

            if (is_file($from)) {
                copy($from, $to);
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function ensureEntry(string $identifier, string $type, string $title, array $data): void
    {
        try {
            $this->entryRepository->getByIdentifier($identifier, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $entry = $this->entryFactory->create();
        $entry->setIdentifier($identifier)
            ->setType($type)
            ->setTitle($title)
            ->setIsActive(true)
            ->setEntryData($data)
            ->setStoreIds([Store::DEFAULT_STORE_ID]);
        $this->entryRepository->save($entry);
    }

    private function ensureOrRefreshOutlet(string $mediaBase): void
    {
        $newData = [
            'image' => $mediaBase . 'elme-outlet.jpg',
            'image_alt' => 'Elme Outlet display of vases and homewares',
            'heading' => "Introducing the\nElme Outlet",
            'text' => '<p>End-of-lines, samples and one-off pieces for your floor - at'
                . ' <strong>up to 80% off</strong> - at our Melbourne outlet.</p>'
                . '<p>Stockists use it for samples and clearance fills; the public is welcome too.</p>',
            'button_label' => 'Find Us In-Store',
            'button_link' => '/showroom-locator/elme-living-melbourne-outlet',
        ];

        try {
            $entry = $this->entryRepository->getByIdentifier('home-outlet-promo', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            $entry = $this->entryFactory->create();
            $entry->setIdentifier('home-outlet-promo')
                ->setType('promo_split')
                ->setTitle('Home: Outlet promo')
                ->setIsActive(true)
                ->setStoreIds([Store::DEFAULT_STORE_ID]);
            $entry->setEntryData($newData);
            $this->entryRepository->save($entry);
            return;
        }

        $current = $entry->getEntryData();
        $oldConsumerBeat = 'Design lovers, stylists and bargain hunters';

        if (str_contains((string)($current['text'] ?? ''), $oldConsumerBeat)) {
            // Keep any editor-uploaded image; only replace soft consumer copy.
            $newData['image'] = trim((string)($current['image'] ?? '')) !== ''
                ? (string)$current['image']
                : $newData['image'];
            $newData['image_alt'] = trim((string)($current['image_alt'] ?? '')) !== ''
                ? (string)$current['image_alt']
                : $newData['image_alt'];
            $entry->setEntryData($newData);
            $this->entryRepository->save($entry);
        }
    }

    private function wireHomePage(): void
    {
        try {
            $page = $this->getPageByIdentifier->execute('home', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $widgets = [];
        foreach (self::HOME_IDENTIFIERS as $identifier) {
            $widgets[] = '<div data-content-type="content_studio" data-appearance="default"'
                . ' data-element="main">{{widget type="Bart\ContentStudio\Block\Entry"'
                . ' identifier="' . $identifier . '"}}</div>';
        }

        $content = '<div data-content-type="row" data-appearance="contained" data-element="main">'
            . '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
            . ' data-element="inner" style="display:flex;flex-direction:column;">'
            . implode('', $widgets)
            . '</div></div>';

        $page->setContent($content);
        $this->pageRepository->save($page);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
