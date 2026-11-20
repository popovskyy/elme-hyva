<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * JSON-LD of a CMS page about one store (FE-63 Outlet): the ContentPage graph (WebPage + BreadcrumbList)
 * plus the store node from the page's `store_visit` entry, and FAQPage questions from its `faq_list`
 * entry. Both entries are found in the CMS content, so the schema reads the same entries the page shows.
 * The page node is FAQPage when there are questions, WebPage otherwise; it is `about` the store.
 */
class StorePage implements ArgumentInterface
{
    private const ENTRY_WIDGETS = '/\{\{widget\s+type="Bart\\\\+ContentStudio\\\\+Block\\\\+Entry"[^}]*?identifier="([^"]+)"/';

    public function __construct(
        private readonly ContentPage $contentPage,
        private readonly CmsPage $cmsPage,
        private readonly StoreManagerInterface $storeManager,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly StoreVisit $storeVisit,
        private readonly FaqList $faqList
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getJsonLdGraph(): array
    {
        $visit = $this->findEntryData('store_visit');
        $faq = $this->findEntryData('faq_list');
        $questions = $faq !== null ? $this->faqList->getSchemaQuestions($faq) : [];

        $graph = $this->contentPage->getJsonLdGraph($questions ? 'FAQPage' : 'WebPage');
        $pageUrl = $this->contentPage->getCanonicalUrl();
        $baseUrl = rtrim((string)$this->storeManager->getStore()->getBaseUrl(), '/');

        $store = $visit !== null
            ? $this->storeVisit->getSchemaNode($visit, $pageUrl, $baseUrl, $this->contentPage->getPrimaryImage())
            : null;

        $webpage = $graph['@graph'][0];
        if ($store !== null) {
            $webpage['about'] = ['@id' => $store['@id']];
        }
        if ($questions) {
            $webpage['mainEntity'] = $questions;
        }

        $graph['@graph'][0] = $webpage;
        if ($store !== null) {
            array_splice($graph['@graph'], 1, 0, [$store]);
        }

        return $graph;
    }

    public function getJsonLdScript(): string
    {
        return (string)json_encode(
            $this->getJsonLdGraph(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    /**
     * Data of the first active entry of the given type placed in the page content.
     *
     * @return array<string, mixed>|null
     */
    private function findEntryData(string $type): ?array
    {
        if (!preg_match_all(self::ENTRY_WIDGETS, (string)$this->cmsPage->getContent(), $matches)) {
            return null;
        }

        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
        } catch (Throwable) {
            return null;
        }

        foreach (array_unique($matches[1]) as $identifier) {
            try {
                $entry = $this->entryRepository->getByIdentifier($identifier, $storeId);
            } catch (Throwable) {
                continue;
            }

            if ($entry->isActive() && $entry->getType() === $type) {
                return $entry->getEntryData();
            }
        }

        return null;
    }
}
