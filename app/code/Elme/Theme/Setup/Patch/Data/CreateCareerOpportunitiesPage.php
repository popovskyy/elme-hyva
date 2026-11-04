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
 * FE-62: /career-opportunities on the banner + breadcrumbs layout: a `hero` entry that holds the H1 and
 * a `vacancies` entry (intro, open roles, careers address). Starts with no open roles, so the page shows
 * the one no-vacancies message. Existing entries and an existing page are left alone.
 */
class CreateCareerOpportunitiesPage implements DataPatchInterface
{
    private const IDENTIFIER = 'career-opportunities';

    private const MEDIA_DIR = 'bart_content_studio/fe62';

    private const HERO_IMAGE = 'careers-hero.jpg';

    private const ENTRIES = ['careers-hero', 'careers-vacancies'];

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
        $this->copyHeroImage();
        $mediaBase = rtrim(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        ) . '/' . self::MEDIA_DIR . '/';

        $this->ensureEntry('careers-hero', 'hero', 'Careers: hero', [
            'is_page_title' => true,
            'autoplay' => false,
            'interval' => 7,
            'slides' => [
                [
                    'image_desktop' => $mediaBase . self::HERO_IMAGE,
                    'image_alt' => 'White ceramic vases and dried stems on a marble table in the Elme Living showroom',
                    'heading' => 'Career Opportunities',
                    'tone' => 'light',
                    'text' => 'Join the team behind Elme Living, the Australian wholesale homewares, decor and'
                        . ' furniture brand.',
                ],
            ],
        ]);

        $this->ensureEntry('careers-vacancies', 'vacancies', 'Careers: open roles', [
            'intro' => '<p>Elme Living is an Australian wholesale homewares, decor and furniture brand. We supply'
                . ' artificial flowers and plants, vases, decor and furniture to retailers across Australia.</p>'
                . '<p>We hire for our sales, customer service, showroom and warehouse teams, at our Blackburn VIC'
                . ' showroom and our Tarneit VIC distribution centre. If you love design and looking after'
                . ' customers, we would like to hear from you.</p>',
            'heading' => 'Current vacancies',
            'email' => 'hello@elmeliving.com',
            'empty_text' => 'We have no vacancies right now, but we are always glad to hear from people who love'
                . ' design and service.',
            'roles' => [],
        ]);

        $this->ensurePage();

        return $this;
    }

    private function copyHeroImage(): void
    {
        $from = BP . '/app/design/frontend/Elme/default/web/images/sample-images/vases-desktop.jpg';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_file($from) || (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir))) {
            return;
        }

        copy($from, $targetDir . '/' . self::HERO_IMAGE);
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

        $widgets = '';
        foreach (self::ENTRIES as $identifier) {
            $widgets .= '<div data-content-type="content_studio" data-appearance="default" data-element="main">'
                . '{{widget type="Bart\ContentStudio\Block\Entry" identifier="' . $identifier . '"}}</div>';
        }

        $page = $this->pageFactory->create();
        $page->setIdentifier(self::IDENTIFIER)
            ->setTitle('Career Opportunities')
            ->setPageLayout('banner-breadcrumbs')
            ->setMetaTitle('Careers at Elme Living | Homewares & Decor Jobs in Australia')
            ->setMetaDescription(
                'Join Elme Living, the Australian wholesale homewares and decor brand. See open sales, service'
                . ' and warehouse roles and apply by email with your resume.'
            )
            ->setContent(
                '<div data-content-type="row" data-appearance="contained" data-element="main">'
                . '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
                . ' data-element="inner" style="display:flex;flex-direction:column;">'
                . $widgets
                . '</div></div>'
            )
            ->setIsActive(true);
        $page->setData('stores', [Store::DEFAULT_STORE_ID]);
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
