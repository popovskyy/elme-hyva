<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-63: /outlet-and-clearance-store on the banner + breadcrumbs layout: a `hero` entry that holds the
 * H1, the `store_visit` card (hours), "What You'll Find" as Page Builder text and a `faq_list`. The home
 * Outlet promo button moves from the not yet built store locator entry to this page. Existing entries and
 * an existing page are left alone.
 */
class CreateOutletClearanceStorePage implements DataPatchInterface
{
    private const IDENTIFIER = 'outlet-and-clearance-store';

    private const MEDIA_DIR = 'bart_content_studio/fe63';

    private const IMAGES = ['outlet-hero-desktop.jpg', 'outlet-hero-mobile.jpg'];

    private const OLD_PROMO_LINK = '/showroom-locator/elme-living-melbourne-outlet';

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function apply(): self
    {
        $this->copyImages();
        $mediaBase = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/' . self::MEDIA_DIR . '/';

        $this->ensureEntry('outlet-hero', 'hero', 'Outlet: hero', [
            'is_page_title' => true,
            'autoplay' => false,
            'interval' => 7,
            'slides' => [
                [
                    'image_desktop' => $mediaBase . self::IMAGES[0],
                    'image_mobile' => $mediaBase . self::IMAGES[1],
                    'image_alt' => 'Elme Living furniture, vases and artificial flowers styled in a sunlit room',
                    'heading' => 'Outlet & Clearance Store',
                    'tone' => 'light',
                    'text' => 'Ex-display pieces, one-off samples and end-of-line furniture and homewares at up to'
                        . ' 80% off RRP.',
                ],
            ],
        ]);

        $this->ensureEntry('outlet-visit', 'store_visit', 'Outlet: Visit us', [
            'heading' => 'Visit us',
            'store_name' => 'Elme Living Outlet & Clearance Store',
            'intro' => 'Ex-display, sample and end-of-line furniture, homewares and artificial plants at up to'
                . ' 80% off RRP.',
            'public_note' => 'Open to the public, no trade account needed',
            'schema_type' => 'OutletStore',
            'hours' => [
                ['day_from' => 'Monday', 'day_to' => 'Saturday', 'opens' => '09:00', 'closes' => '17:00'],
                ['day_from' => 'Sunday', 'day_to' => '', 'opens' => '10:00', 'closes' => '17:00'],
            ],
        ]);

        $this->ensureEntry('outlet-faq', 'faq_list', 'Outlet: Good to know', [
            'heading' => 'Good to know',
            'items' => [
                [
                    'question' => 'Is the outlet open to the public?',
                    'answer' => 'Yes. Anyone can shop at the Elme Living Outlet & Clearance Store, no trade'
                        . ' account needed. Our Melbourne Showroom (trade only) at the same address is for'
                        . ' registered retailers.',
                    'anchor' => 'open-to-the-public',
                ],
                [
                    'question' => 'Can I hold an item?',
                    'answer' => 'Give us a call before you make the trip and ask about the piece you are after.'
                        . ' Most outlet pieces are one-offs and sell quickly.',
                    'anchor' => 'hold-an-item',
                ],
                [
                    'question' => 'Is outlet stock on the website?',
                    'answer' => "No. Outlet pieces are sold in store only, and new stock arrives every week.\n\n"
                        . 'All outlet purchases are final sale, with no change-of-mind returns, and items may'
                        . ' include minor imperfections. Your rights under the Australian Consumer Law are not'
                        . ' affected.',
                    'anchor' => 'stock-on-the-website',
                ],
            ],
        ]);

        $this->ensurePage();
        $this->pointHomePromoHere();

        return $this;
    }

    private function copyImages(): void
    {
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        foreach (self::IMAGES as $image) {
            $from = __DIR__ . '/files/outlet/' . $image;
            if (is_file($from)) {
                copy($from, $targetDir . '/' . $image);
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

    private function ensurePage(): void
    {
        try {
            $this->getPageByIdentifier->execute(self::IDENTIFIER, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $page = $this->pageFactory->create();
        $page->setIdentifier(self::IDENTIFIER)
            ->setTitle('Outlet & Clearance Store')
            ->setPageLayout('banner-breadcrumbs')
            ->setMetaTitle('Outlet & Clearance Store, Blackburn VIC | Elme Living')
            ->setMetaDescription(
                'Ex-display furniture, homewares and artificial plants at up to 80% off RRP. Open to the public'
                . ' at 1 Apollo Court, Blackburn VIC, 7 days.'
            )
            ->setContent(
                '<div data-content-type="row" data-appearance="contained" data-element="main">'
                . '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
                . ' data-element="inner" style="display:flex;flex-direction:column;">'
                . $this->widget('outlet-hero')
                . $this->widget('outlet-visit')
                . '<div data-content-type="text" data-appearance="default" data-element="main"'
                . ' style="margin:0 0 40px;">'
                . $this->findText()
                . '</div>'
                . $this->widget('outlet-faq')
                . '</div></div>'
            )
            ->setIsActive(true);
        $page->setData('stores', [Store::DEFAULT_STORE_ID]);
        $this->pageRepository->save($page);
    }

    private function widget(string $identifier): string
    {
        return '<div data-content-type="content_studio" data-appearance="default" data-element="main">'
            . '{{widget type="Bart\ContentStudio\Block\Entry" identifier="' . $identifier . '"}}</div>';
    }

    /**
     * "What You'll Find", "Why People Love the Outlet" and "Perfect For" from production, as H2s and lists.
     */
    private function findText(): string
    {
        return '<h2>What You\'ll Find</h2>'
            . '<p>A curated mix of ex-display pieces, one-off samples, end-of-line ranges and never-to-be-repeated'
            . ' homewares and furniture.</p>'
            . '<ul>'
            . '<li><strong>One-of-a-kind samples:</strong> unique items from our design studio and supplier'
            . ' development.</li>'
            . '<li><strong>Furniture:</strong> chairs, consoles, dining, occasional pieces, shelving, bedroom and'
            . ' more.</li>'
            . '<li><strong>Homewares &amp; decor:</strong> vases, bowls, pots, wall art, framed prints, vessels,'
            . ' throws, trays, baskets and styling accessories.</li>'
            . '<li><strong>Artificial plants &amp; florals:</strong> premium stems, greenery and'
            . ' arrangements.</li>'
            . '<li><strong>Perfectly imperfect pieces:</strong> slight seconds, samples and last ones left.</li>'
            . '</ul>'
            . '<h2>Why People Love the Outlet</h2>'
            . '<ul>'
            . '<li>Up to 80% off RRP</li>'
            . '<li>New stock added weekly</li>'
            . '<li>Open to the public</li>'
            . '<li>Sustainable shopping</li>'
            . '<li>Unique treasures</li>'
            . '</ul>'
            . '<h2>Perfect For</h2>'
            . '<ul>'
            . '<li>Interior designers and property stylists</li>'
            . '<li>New homeowners and renovators</li>'
            . '<li>Airbnb and holiday home set-ups</li>'
            . '<li>Bargain hunters and treasure hunters</li>'
            . '</ul>';
    }

    /**
     * The home Outlet promo linked to the store locator entry (FE-51, not built yet), a 404 here.
     */
    private function pointHomePromoHere(): void
    {
        try {
            $entry = $this->entryRepository->getByIdentifier('home-outlet-promo', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $data = $entry->getEntryData();
        if (($data['button_link'] ?? '') !== self::OLD_PROMO_LINK) {
            return;
        }

        $data['button_link'] = '/' . self::IDENTIFIER;
        $entry->setEntryData($data);
        $this->entryRepository->save($entry);
    }

    public static function getDependencies(): array
    {
        return [SeedHomepageContentSections::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
