<?php

declare(strict_types=1);

namespace Elme\Theme\Setup\Patch\Data;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\EntryFactory;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * FE-47: Content Studio `faq` entry + wire the faqs CMS page to the widget (replaces placeholder).
 */
class SeedFaqsAccordion implements DataPatchInterface
{
    public function __construct(
        private readonly EntryFactory $entryFactory,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository
    ) {
    }

    public function apply(): self
    {
        $items = require __DIR__ . '/files/help/faqs-items.php';
        $this->upsertEntry($items);
        $this->wireFaqsPage();

        return $this;
    }

    /**
     * @param list<array{topic: string, question: string, anchor: string, answer: string, next_step?: string}> $items
     */
    private function upsertEntry(array $items): void
    {
        $data = ['items' => $items];

        try {
            $entry = $this->entryRepository->getByIdentifier('faqs', Store::DEFAULT_STORE_ID);
            $entry->setTitle('FAQs accordion')
                ->setType('faq')
                ->setIsActive(true)
                ->setEntryData($data);
            $this->entryRepository->save($entry);
            return;
        } catch (NoSuchEntityException) {
            // create below
        }

        $entry = $this->entryFactory->create();
        $entry->setIdentifier('faqs')
            ->setType('faq')
            ->setTitle('FAQs accordion')
            ->setIsActive(true)
            ->setEntryData($data)
            ->setStoreIds([Store::DEFAULT_STORE_ID]);
        $this->entryRepository->save($entry);
    }

    private function wireFaqsPage(): void
    {
        try {
            $page = $this->getPageByIdentifier->execute('faqs', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $content = trim((string)file_get_contents(__DIR__ . '/files/help/faqs.html'));
        $page->setContent($content);
        $this->pageRepository->save($page);
    }

    public static function getDependencies(): array
    {
        return [CreateHelpCmsPages::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
