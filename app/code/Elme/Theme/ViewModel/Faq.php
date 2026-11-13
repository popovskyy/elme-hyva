<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Elme\LoginOnly\Model\LoginState;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Content Studio type `faq` (etc/content_types.xml, FE-47): topics, anchors, next-step links, FAQPage entities.
 * Page-level FAQPage JSON-LD wraps ContentPage (same pattern as BecomeStockist / StorePage).
 */
class Faq implements ArgumentInterface
{
    public const ENTRY_IDENTIFIER = 'faqs';

    public const TOPIC_ORDER = [
        'Location',
        'Orders',
        'Payments',
        'Shipping',
        'Marketing',
    ];

    /**
     * Trade aliases retailers type in Find a question that may not appear verbatim in copy.
     * Key = substring in topic/question/answer (lowercase); value = tokens appended to search_text.
     *
     * @var array<string, list<string>>
     */
    private const SEARCH_SYNONYMS = [
        'minimum order' => ['moq', 'mov', 'minimum order quantity', 'minimum order value'],
        'minimum pack' => ['moq', 'pack size'],
        'australian consumer law' => ['acl', 'returns'],
        'proforma' => ['pro forma', 'upfront'],
        'pro forma' => ['proforma', 'upfront'],
        'american express' => ['amex'],
        'distribution warehouse' => ['dc', 'warehouse', 'tarneit'],
        'tarneit' => ['dc', 'warehouse'],
    ];

    /**
     * @var array<string, array{label: string, guest_path: string, customer_path: string|null}>
     */
    private const NEXT_STEPS = [
        'become_stockist' => [
            'label' => 'Become a Stockist',
            'guest_path' => 'become-a-stockist',
            'customer_path' => null,
        ],
        'quick_order' => [
            'label' => 'Quick Order',
            'guest_path' => 'customer/account/login',
            'customer_path' => 'quickorder',
        ],
        'shipping_returns' => [
            'label' => 'Shipping & Returns',
            'guest_path' => 'shipping-and-returns',
            'customer_path' => 'shipping-and-returns',
        ],
        'contact' => [
            'label' => 'Contact Us',
            'guest_path' => 'contact-us',
            'customer_path' => 'contact-us',
        ],
    ];

    public function __construct(
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly LoginState $loginState,
        private readonly ContentPage $contentPage
    ) {
    }

    /**
     * @param array<string, mixed> $content Block\Entry::getContent()
     * @return list<array{
     *     topic: string,
     *     topic_slug: string,
     *     question: string,
     *     anchor: string,
     *     answer_html: string,
     *     answer_text: string,
     *     search_text: string,
     *     next_step: array{label: string, href: string}|null,
     *     show_on_pdp: bool
     * }>
     */
    public function getItems(array $content): array
    {
        $items = [];
        $usedAnchors = [];

        foreach ((array)($content['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $question = $this->text($row, 'question');
            $answerHtml = trim((string)($row['answer'] ?? ''));
            $topic = $this->normaliseTopic($this->text($row, 'topic'));

            if ($question === '' || $answerHtml === '' || $topic === '') {
                continue;
            }

            $anchor = $this->text($row, 'anchor');
            if ($anchor === '') {
                $anchor = $this->slugify($question);
            } else {
                $anchor = $this->slugify($anchor);
            }

            if ($anchor === '') {
                continue;
            }

            $baseAnchor = $anchor;
            $suffix = 2;
            while (isset($usedAnchors[$anchor])) {
                $anchor = $baseAnchor . '-' . $suffix;
                $suffix++;
            }
            $usedAnchors[$anchor] = true;

            $nextStep = $this->resolveNextStep($this->text($row, 'next_step'));
            $answerWithNext = $this->appendNextStep($answerHtml, $nextStep);
            $answerText = $this->plainText($answerWithNext);
            $baseSearch = mb_strtolower($topic . ' ' . $question . ' ' . $answerText, 'UTF-8');

            $items[] = [
                'topic' => $topic,
                'topic_slug' => $this->slugify($topic),
                'question' => $question,
                'anchor' => $anchor,
                'answer_html' => $answerWithNext,
                'answer_text' => $answerText,
                'search_text' => $this->withSearchSynonyms($baseSearch),
                'next_step' => $nextStep,
                'show_on_pdp' => !empty($row['show_on_pdp']),
            ];
        }

        return $items;
    }

    /**
     * Append synonym tokens when the matching phrase appears in the item copy.
     */
    private function withSearchSynonyms(string $searchText): string
    {
        $extra = [];
        foreach (self::SEARCH_SYNONYMS as $needle => $aliases) {
            if (!str_contains($searchText, $needle)) {
                continue;
            }
            foreach ($aliases as $alias) {
                if (!str_contains($searchText, $alias) && !isset($extra[$alias])) {
                    $extra[$alias] = true;
                }
            }
        }

        if ($extra === []) {
            return $searchText;
        }

        return $searchText . ' ' . implode(' ', array_keys($extra));
    }

    /**
     * @param list<array{topic: string, topic_slug: string, question: string, anchor: string, answer_html: string, answer_text: string, search_text: string, next_step: array{label: string, href: string}|null}> $items
     * @return list<array{label: string, slug: string, items: list<array{topic: string, topic_slug: string, question: string, anchor: string, answer_html: string, answer_text: string, search_text: string, next_step: array{label: string, href: string}|null}>}>
     */
    public function groupByTopic(array $items): array
    {
        $buckets = [];
        foreach (self::TOPIC_ORDER as $label) {
            $buckets[$label] = [];
        }

        foreach ($items as $item) {
            $topic = $item['topic'];
            if (!isset($buckets[$topic])) {
                $buckets[$topic] = [];
            }
            $buckets[$topic][] = $item;
        }

        $groups = [];
        foreach ($buckets as $label => $groupItems) {
            if ($groupItems === []) {
                continue;
            }
            $groups[] = [
                'label' => $label,
                'slug' => $this->slugify($label),
                'items' => $groupItems,
            ];
        }

        return $groups;
    }

    /**
     * @param list<array{label: string, slug: string, items: list}> $groups
     * @return list<array{label: string, slug: string}>
     */
    public function getTopicLinks(array $groups): array
    {
        $links = [];
        foreach ($groups as $group) {
            $links[] = [
                'label' => $group['label'],
                'slug' => $group['slug'],
            ];
        }

        return $links;
    }

    /**
     * FE-29: questions flagged "Show on product pages", for the Trade FAQ row of the PDP accordion.
     * Plain HTML there (no FAQPage markup: /faqs is the one marked-up copy).
     *
     * @return list<array{question: string, anchor: string, answer_html: string, url: string}>
     */
    public function getPdpItems(): array
    {
        $content = $this->loadPublishedContent();
        if ($content === null) {
            return [];
        }

        $pageUrl = $this->faqsPageUrl();
        $items = [];
        foreach ($this->getItems($content) as $item) {
            if (!$item['show_on_pdp']) {
                continue;
            }
            $items[] = [
                'question' => $item['question'],
                'anchor' => $item['anchor'],
                'answer_html' => $item['answer_html'],
                'url' => $pageUrl . '#' . $item['anchor'],
            ];
        }

        return $items;
    }

    /**
     * FAQPage mainEntity from the published Content Studio entry (same order as storefront HTML).
     *
     * @return list<array<string, mixed>>
     */
    public function getSchemaMainEntity(?string $pageUrl = null): array
    {
        $content = $this->loadPublishedContent();
        if ($content === null) {
            return [];
        }

        $items = $this->getItems($content);
        if ($items === []) {
            return [];
        }

        $pageUrl = $pageUrl ?? $this->faqsPageUrl();
        $entities = [];
        foreach ($items as $item) {
            $entities[] = [
                '@type' => 'Question',
                '@id' => $pageUrl . '#' . $item['anchor'],
                'name' => $item['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['answer_text'],
                ],
            ];
        }

        return $entities;
    }

    /**
     * WebPage/FAQPage + BreadcrumbList from ContentPage, with FAQ mainEntity when questions exist.
     *
     * @return array<string, mixed>
     */
    public function getJsonLdGraph(): array
    {
        $pageUrl = $this->contentPage->getCanonicalUrl();
        $entities = $this->getSchemaMainEntity($pageUrl);
        $graph = $this->contentPage->getJsonLdGraph($entities !== [] ? 'FAQPage' : 'WebPage');

        if ($entities === [] || !isset($graph['@graph'][0]) || !is_array($graph['@graph'][0])) {
            return $graph;
        }

        $graph['@graph'][0]['mainEntity'] = $entities;

        return $graph;
    }

    public function getJsonLdScript(): string
    {
        return (string) json_encode(
            $this->getJsonLdGraph(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadPublishedContent(): ?array
    {
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            $entry = $this->entryRepository->getByIdentifier(self::ENTRY_IDENTIFIER, $storeId);
        } catch (NoSuchEntityException) {
            try {
                $entry = $this->entryRepository->getByIdentifier(
                    self::ENTRY_IDENTIFIER,
                    Store::DEFAULT_STORE_ID
                );
            } catch (NoSuchEntityException) {
                return null;
            }
        }

        if (!$entry->isActive()) {
            return null;
        }

        return $entry->getEntryData();
    }

    private function faqsPageUrl(): string
    {
        return rtrim((string)$this->storeManager->getStore()->getBaseUrl(), '/') . '/faqs';
    }

    /**
     * @return array{label: string, href: string}|null
     */
    private function resolveNextStep(string $code): ?array
    {
        if ($code === '' || $code === 'none' || !isset(self::NEXT_STEPS[$code])) {
            return null;
        }

        $step = self::NEXT_STEPS[$code];
        $loggedIn = $this->loginState->isLoggedIn();

        if ($code === 'become_stockist' && $loggedIn) {
            return null;
        }

        if ($code === 'quick_order' && !$loggedIn) {
            return [
                'label' => (string)__('Sign in'),
                'href' => $this->urlBuilder->getUrl('customer/account/login'),
            ];
        }

        $path = $loggedIn && $step['customer_path'] !== null
            ? $step['customer_path']
            : $step['guest_path'];

        if (str_contains($path, '/')) {
            $href = $this->urlBuilder->getUrl($path);
        } else {
            $href = rtrim((string)$this->storeManager->getStore()->getBaseUrl(), '/') . '/' . ltrim($path, '/');
        }

        return [
            'label' => (string)__($step['label']),
            'href' => $href,
        ];
    }

    /**
     * @param array{label: string, href: string}|null $nextStep
     */
    private function appendNextStep(string $answerHtml, ?array $nextStep): string
    {
        if ($nextStep === null) {
            return $answerHtml;
        }

        $label = htmlspecialchars($nextStep['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $href = htmlspecialchars($nextStep['href'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return rtrim($answerHtml)
            . "\n"
            . '<p class="elme-faq__next-step"><a href="' . $href . '">' . $label . '</a></p>';
    }

    private function normaliseTopic(string $topic): string
    {
        $map = [
            'location' => 'Location',
            'orders' => 'Orders',
            'payments' => 'Payments',
            'shipping' => 'Shipping',
            'delivery' => 'Shipping',
            'marketing' => 'Marketing',
            'marketing opportunities' => 'Marketing',
        ];

        $key = mb_strtolower(trim($topic), 'UTF-8');

        return $map[$key] ?? '';
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
