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
use Magento\Store\Model\Store;

/**
 * FE-66: seed /fairs-events CMS page + Content Studio `events` entry.
 *
 * Seeds one past fair (filtered out of HTML/schema) and upcoming Reed Gift Fair 2027 so the page
 * has visible cards. Existing entry and page are left alone.
 */
class CreateFairsEventsPage implements DataPatchInterface
{
    private const ENTRY_IDENTIFIER = 'fairs-events-list';

    private const PAGE_IDENTIFIER = 'fairs-events';

    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly PageInterfaceFactory $pageFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier
    ) {
    }

    public function apply(): self
    {
        $this->ensureEntry();
        $this->ensurePage();

        return $this;
    }

    private function ensureEntry(): void
    {
        try {
            $this->entryRepository->getByIdentifier(self::ENTRY_IDENTIFIER, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $entry = $this->entryFactory->create();
        $entry->setIdentifier(self::ENTRY_IDENTIFIER)
            ->setType('events')
            ->setTitle('Fairs & Events')
            ->setIsActive(true)
            ->setEntryData([
                'intro' => '<p>Elme Living is an Australian B2B wholesaler of artificial flora, homewares'
                    . ' and furniture for retailers. This page lists the trade fairs and showroom events'
                    . ' where you can meet the team and see new ranges.</p>'
                    . '<p>Book a time with your wholesale rep via Meet the Team, or visit the Blackburn'
                    . ' showroom at 1 Apollo Court when no fair is scheduled.</p>',
                'heading' => 'Upcoming fairs',
                'empty_text' => 'No fairs scheduled right now',
                'items' => [
                    [
                        'name' => 'Past Sample Fair',
                        'start' => '2024-03-01',
                        'end' => '2024-03-04',
                        'city' => 'Melbourne',
                        'venue' => 'Melbourne Convention and Exhibition Centre',
                        'stand' => '',
                        'text' => 'Past row — must never appear on the storefront.',
                        'link' => '',
                    ],
                    [
                        'name' => 'Reed Gift Fair',
                        'start' => '2027-07-31',
                        'end' => '2027-08-04',
                        'city' => 'Melbourne',
                        'venue' => 'Melbourne Convention and Exhibition Centre',
                        'stand' => 'A12',
                        'text' => 'Come and chat to our team and see the new season collection.',
                        'link' => '',
                    ],
                ],
            ])
            ->setStoreIds([Store::DEFAULT_STORE_ID]);
        $this->entryRepository->save($entry);
    }

    private function ensurePage(): void
    {
        try {
            $this->getPageByIdentifier->execute(self::PAGE_IDENTIFIER, Store::DEFAULT_STORE_ID);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $content = '<div data-content-type="row" data-appearance="contained" data-element="main">'
            . '<div data-enable-parallax="0" data-parallax-speed="0.5" data-background-images="{}"'
            . ' data-element="inner" style="display:flex;flex-direction:column;">'
            . '<div data-content-type="content_studio" data-appearance="default" data-element="main">'
            . '{{widget type="Bart\ContentStudio\Block\Entry" identifier="'
            . self::ENTRY_IDENTIFIER . '"}}</div>'
            . '</div></div>';

        /** @var PageInterface $page */
        $page = $this->pageFactory->create();
        $page->setIdentifier(self::PAGE_IDENTIFIER)
            ->setTitle('Fairs & Events')
            ->setContentHeading('Fairs & Events')
            ->setMetaTitle('Fairs & Events | Elme Living Trade Shows Australia')
            ->setMetaDescription(
                'See where Elme Living is exhibiting next — Australian gift and design trade fairs for'
                . ' retailers. Book a time with your wholesale rep, or visit the Blackburn showroom.'
            )
            ->setPageLayout('1column')
            ->setContent($content)
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
