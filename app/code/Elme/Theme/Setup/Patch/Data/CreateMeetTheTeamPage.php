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
 * FE-65: /meet-the-team on cms-full-width with a Content Studio `team` entry (contacts from production
 * HTML comments / ops — no invented addresses). People without email+phone on live are omitted.
 */
class CreateMeetTheTeamPage implements DataPatchInterface
{
    private const IDENTIFIER = 'meet-the-team';

    private const MEDIA_DIR = 'bart_content_studio/fe65';

    private const ENTRY = 'meet-the-team';

    /** @var list<array{file: string, name: string, role: string, email: string, phone: string, region: string}> */
    private const PEOPLE = [
        [
            'file' => 'christopher-torner.jpg',
            'name' => 'Christopher Torner',
            'role' => 'CEO',
            'email' => 'christopher@elmeliving.com.au',
            'phone' => '+61 417 155 522',
            'region' => 'head_office',
        ],
        [
            'file' => 'amanda-langstone.jpg',
            'name' => 'Amanda Langstone',
            'role' => 'Head of Sales',
            'email' => 'amanda@elmeliving.com',
            'phone' => '+61 419 361 373',
            'region' => 'head_office',
        ],
        [
            'file' => 'ainsley-moore.jpg',
            'name' => 'Ainsley Moore',
            'role' => 'Operation Manager',
            'email' => 'ainsley@elmeliving.com',
            'phone' => '+61 408 548 778',
            'region' => 'head_office',
        ],
        [
            'file' => 'wayne-pringle.jpg',
            'name' => 'Wayne Pringle',
            'role' => 'National Sales Manager',
            'email' => 'wayne@elmeliving.com',
            'phone' => '+61 415 840 641',
            'region' => 'head_office',
        ],
        [
            'file' => 'barb-stephenson.jpg',
            'name' => 'Barb Stephenson',
            'role' => 'Showroom Business Development Manager',
            'email' => 'barb@elmeliving.com',
            'phone' => '03 7067 9725',
            'region' => 'head_office',
        ],
        [
            'file' => 'helena-yetsenga.jpg',
            'name' => 'Helena Yetsenga',
            'role' => 'VIC Business Development Manager',
            'email' => 'helena.yetsenga@elmeliving.com',
            'phone' => '+61 404 383 155',
            'region' => 'VIC',
        ],
        [
            'file' => 'greg-morcombe.jpg',
            'name' => 'Greg Morcombe',
            'role' => 'Victorian Regional Account Manager',
            'email' => 'greg.morcombe@elmeliving.com',
            'phone' => '03 7067 9725',
            'region' => 'VIC',
        ],
        [
            'file' => 'donna-snelling.jpg',
            'name' => 'Donna Snelling',
            'role' => 'New South Wales Account Manager',
            'email' => 'donna.snelling@elmeliving.com',
            'phone' => '+61 466 577 937',
            'region' => 'NSW',
        ],
        [
            'file' => 'karen-gibson.jpg',
            'name' => 'Karen Gibson',
            'role' => 'Queensland Account Manager',
            'email' => 'karen.gibson@elmeliving.com',
            'phone' => '+61 405 184 864',
            'region' => 'QLD',
        ],
        [
            'file' => 'david-milella.jpg',
            'name' => 'David Milella',
            'role' => 'Victorian Regional and Tasmanian Account Manager',
            'email' => 'david.milella@elmeliving.com',
            'phone' => '+61 433 460 067',
            'region' => 'SA_TAS',
        ],
    ];

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

        $people = [];
        foreach (self::PEOPLE as $row) {
            $people[] = [
                'photo' => $mediaBase . $row['file'],
                'name' => $row['name'],
                'role' => $row['role'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'region' => $row['region'],
                'is_visible' => true,
            ];
        }

        $this->ensureEntry(self::ENTRY, 'team', 'Meet the Team', [
            'title' => 'Meet the Team',
            'intro' => '<p>Elme Living is an Australian B2B wholesaler of artificial flora, homewares and'
                . ' furniture for retailers. This page lists our sales, showroom and operations contacts by'
                . ' region — Head office, VIC, NSW, QLD, WA and SA-TAS.</p>'
                . '<p>Trade prices are on your account after you'
                . ' <a href="/become-a-stockist">become a stockist</a>.'
                . ' Email or call your rep for wholesale support.</p>',
            'heading' => 'Our account managers',
            'people' => $people,
        ]);

        $this->ensurePage();

        return $this;
    }

    private function copyImages(): void
    {
        $fromDir = BP . '/app/design/frontend/Elme/default/web/images/sample-images';
        $targetDir = BP . '/pub/media/' . self::MEDIA_DIR;

        if (!is_dir($fromDir) || (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir))) {
            return;
        }

        foreach (self::PEOPLE as $row) {
            $from = $fromDir . '/' . $row['file'];
            if (is_file($from)) {
                copy($from, $targetDir . '/' . $row['file']);
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

        $widget = '<div data-content-type="content_studio" data-appearance="default" data-element="main">'
            . '{{widget type="Bart\ContentStudio\Block\Entry" identifier="' . self::ENTRY . '"}}</div>';

        $page = $this->pageFactory->create();
        $page->setIdentifier(self::IDENTIFIER)
            ->setTitle('Meet the Team')
            ->setPageLayout('cms-full-width')
            ->setMetaTitle('Meet the Team | Elme Living Wholesale Account Managers')
            ->setMetaDescription(
                'Meet Elme Living’s wholesale account managers by state — Head office, VIC, NSW, QLD, WA'
                . ' and SA-TAS. Email or call your rep for trade support.'
            )
            ->setContent(
                '<div data-content-type="row" data-appearance="contained" data-element="main">'
                . '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
                . ' data-element="inner" style="justify-content:flex-start;display:flex;flex-direction:column;'
                . 'background-position:left top;background-size:cover;background-repeat:no-repeat;'
                . 'background-attachment:scroll">'
                . $widget
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
