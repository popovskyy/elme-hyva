<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * FE-61: seed /about-us (banner-breadcrumbs), Content Studio hero + proof pillars + stockist CTA,
 * welcome prose with catalogue links, About-specific meta.
 */
class CreateAboutUsPage implements DataPatchInterface
{
    private const MEDIA_DIR = 'bart_content_studio/fe61';

    private const PAGE_IDENTIFIER = 'about-us';

    private const ENTRY_HERO = 'about-us-hero';

    private const ENTRY_PROOF = 'about-us-proof';

    private const ENTRY_CTA = 'about-us-stockist-cta';

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
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

        $this->upsertEntry(
            self::ENTRY_HERO,
            'hero',
            'About us: Hero',
            [
                'is_page_title' => true,
                'autoplay' => false,
                'slides' => [
                    [
                        'image_desktop' => $mediaBase . 'about-hero-desktop.jpg',
                        'image_mobile' => $mediaBase . 'about-hero-mobile.jpg',
                        'image_alt' => 'Styled Elme Living artificial flora and homewares display',
                        'heading' => 'Welcome to Elme Living',
                        'tone' => 'dark',
                        'text' => 'Australian wholesale artificial flowers, plants and homewares for retailers.',
                    ],
                ],
            ]
        );

        $this->upsertEntry(
            self::ENTRY_PROOF,
            'proof_pillars',
            'About us: Why stockists choose Elme',
            [
                'heading' => 'Why stockists choose Elme',
                'image' => $mediaBase . 'about-pillars.jpg',
                'image_alt' => 'Furniture, vases and artificial flora styled for a retail floor',
                'items' => [
                    [
                        'title' => 'New and exclusive lines',
                        'text' => 'Fresh artificial flora and homewares arrivals, including Elme Living exclusives'
                            . ' you will not find elsewhere.',
                        'link_label' => 'Shop New',
                        'link' => '/new.html',
                    ],
                    [
                        'title' => 'Advice from our BDMs',
                        'text' => 'Talk to the business development team about ranges, showroom visits and'
                            . ' what works on Australian retail floors.',
                        'link_label' => 'Meet the Team',
                        'link' => '/meet-the-team',
                    ],
                    [
                        'title' => 'Australia-wide wholesale',
                        'text' => 'Open a trade account with your ABN and order online for delivery across'
                            . ' Australia once approved.',
                        'link_label' => 'Become a Stockist',
                        'link' => '/become-a-stockist',
                    ],
                ],
            ]
        );

        $this->upsertEntry(
            self::ENTRY_CTA,
            'stockist_cta',
            'About us: Become a stockist CTA',
            [
                'heading' => 'Become a stockist',
                'text' => 'Apply with your ABN for wholesale access, or talk to the team about your store.',
                'primary_label' => 'Create an Account',
                'primary_link' => '/customer/account/create',
                'secondary_label' => 'Talk to our team',
                'secondary_link' => '/meet-the-team',
            ]
        );

        $this->upsertCmsPage();

        return $this;
    }

    /**
     * Theme-tracked originals → pub/media.
     */
    public static function syncImagesToMedia(): void
    {
        $sourceDir = BP . '/app/design/frontend/Elme/default/web/images/sample-images';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return;
        }

        foreach (['about-hero-desktop.jpg', 'about-hero-mobile.jpg', 'about-pillars.jpg'] as $file) {
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
    private function upsertEntry(string $identifier, string $type, string $title, array $data): void
    {
        try {
            $entry = $this->entryRepository->getByIdentifier($identifier, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            $entry = $this->entryFactory->create();
            $entry->setIdentifier($identifier)
                ->setType($type)
                ->setStoreIds([Store::DEFAULT_STORE_ID]);
        }

        $entry->setTitle($title)
            ->setIsActive(true)
            ->setEntryData($data);
        $this->entryRepository->save($entry);
    }

    private function upsertCmsPage(): void
    {
        try {
            $page = $this->getPageByIdentifier->execute(self::PAGE_IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            /** @var PageInterface $page */
            $page = $this->pageFactory->create();
            $page->setIdentifier(self::PAGE_IDENTIFIER);
            $page->setData('stores', [Store::DEFAULT_STORE_ID]);
        }

        $welcome = $this->loadWelcomeHtml();
        $widgets = [
            $this->widget(self::ENTRY_HERO),
            $welcome,
            $this->widget(self::ENTRY_PROOF),
            $this->widget(self::ENTRY_CTA),
        ];

        $content = '<div data-content-type="row" data-appearance="contained" data-element="main">'
            . '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
            . ' data-element="inner" style="display:flex;flex-direction:column;">'
            . implode('', $widgets)
            . '</div></div>';

        $page->setTitle('About Us')
            ->setContentHeading('About Us')
            ->setMetaTitle('About Elme Living | Australian Wholesale Flora & Homewares')
            ->setMetaDescription(
                'Elme Living is an Australian B2B wholesaler of artificial flowers, plants and homewares'
                . ' for retailers. See our ranges, meet the team and apply for a trade account.'
            )
            ->setPageLayout('banner-breadcrumbs')
            ->setContent($content)
            ->setIsActive(true);

        $this->pageRepository->save($page);
    }

    private function widget(string $identifier): string
    {
        return '<div data-content-type="content_studio" data-appearance="default"'
            . ' data-element="main">{{widget type="Bart\ContentStudio\Block\Entry"'
            . ' identifier="' . $identifier . '"}}</div>';
    }

    private function loadWelcomeHtml(): string
    {
        $path = __DIR__ . '/files/about/welcome.html';
        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException('Missing About us welcome seed: ' . $path);
        }

        return trim($html);
    }

    public static function getDependencies(): array
    {
        return [CreateSharedCmsBlocks::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
