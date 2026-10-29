<?php

declare(strict_types=1);

namespace Elme\Theme\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Content Studio type `events` (FE-66) + /fairs-events JSON-LD helpers.
 *
 * Visible events: end date ≥ today in Australia/Melbourne (end-of-day inclusive), sorted by start
 * ascending. Past rows never reach the HTML or the @graph. FE-55 owns #organization / #website.
 */
class Events implements ArgumentInterface
{
    public const PAGE_IDENTIFIER = 'fairs-events';

    public const ENTRY_IDENTIFIER = 'fairs-events-list';

    public const TIMEZONE = 'Australia/Melbourne';

    public const MAX_EVENTS = 30;

    public const DEFAULT_HEADING = 'Upcoming fairs';

    public const DEFAULT_EMPTY_TEXT = 'No fairs scheduled right now';

    public const SHOWROOM_PATH = 'showroom-locator/elme-living-melbourne-showroom';

    public const SHOWROOM_FALLBACK_PATH = 'showroom-locator';

    public const CTA_PATH = 'meet-the-team';

    public const CTA_LABEL = 'Book a time with your rep';

    /** FPC / block cache: Melbourne calendar day so past events rotate off without cache:flush. */
    public const CONTEXT_MELBOURNE_DAY = 'elme_events_melbourne_day';

    /** @var array<string, mixed>|null */
    private ?array $entryContent = null;

    private bool $entryLoaded = false;

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CmsPage $cmsPage,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly ContentPage $contentPage
    ) {
    }

    public function isFairsEventsPage(?string $identifier = null): bool
    {
        $id = $identifier ?? (string)$this->cmsPage->getIdentifier();

        return $id === self::PAGE_IDENTIFIER;
    }

    /**
     * Today's calendar date in Melbourne (Y-m-d).
     */
    public function getToday(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->format('Y-m-d');
    }

    /**
     * @param array<string, mixed> $content
     * @return list<array{
     *     name: string,
     *     start: string,
     *     end: string,
     *     city: string,
     *     venue: string,
     *     stand: string,
     *     text: string,
     *     link: string,
     *     slug: string,
     *     day: string,
     *     month: string,
     *     range_label: string,
     *     on_now: bool
     * }>
     */
    public function getEvents(array $content): array
    {
        $today = $this->getToday();
        $items = [];

        foreach ((array)($content['items'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = $this->text($row, 'name');
            $start = $this->calendarDate($this->text($row, 'start'));
            $end = $this->calendarDate($this->text($row, 'end'));

            if ($name === '' || $start === null || $end === null || $end < $today) {
                continue;
            }

            if ($end < $start) {
                continue;
            }

            $items[] = [
                'name' => $name,
                'start' => $start,
                'end' => $end,
                'city' => $this->text($row, 'city'),
                'venue' => $this->text($row, 'venue'),
                'stand' => $this->text($row, 'stand'),
                'text' => $this->plainText($this->text($row, 'text')),
                'link' => $this->absoluteHttpUrl($this->text($row, 'link')),
                'slug' => $this->eventSlug($name, $start),
                'day' => (new \DateTimeImmutable($start))->format('j'),
                'month' => (new \DateTimeImmutable($start))->format('M'),
                'range_label' => $this->rangeLabel($start, $end),
                'on_now' => $start <= $today && $today <= $end,
            ];
        }

        usort(
            $items,
            static fn (array $a, array $b): int => [$a['start'], $a['name']] <=> [$b['start'], $b['name']]
        );

        return array_slice($items, 0, self::MAX_EVENTS);
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getIntro(array $content): string
    {
        $intro = str_replace('\\/', '/', $this->text($content, 'intro'));

        return trim(strip_tags($intro)) !== '' ? $intro : '';
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getHeading(array $content): string
    {
        $heading = $this->text($content, 'heading');

        return $heading !== '' ? $heading : self::DEFAULT_HEADING;
    }

    /**
     * @param array<string, mixed> $content
     */
    public function getEmptyText(array $content): string
    {
        $text = $this->text($content, 'empty_text');

        return $text !== '' ? $text : self::DEFAULT_EMPTY_TEXT;
    }

    public function getCtaUrl(): string
    {
        return $this->storeManager->getStore()->getUrl(self::CTA_PATH);
    }

    public function getCtaLabel(): string
    {
        return self::CTA_LABEL;
    }

    public function getShowroomUrl(): string
    {
        $store = $this->storeManager->getStore();

        return $store->getUrl(self::SHOWROOM_PATH) ?: $store->getUrl(self::SHOWROOM_FALLBACK_PATH);
    }

    /**
     * Entry content for the page (JSON-LD). Empty array when missing.
     *
     * @return array<string, mixed>
     */
    public function getPageContent(): array
    {
        if ($this->entryLoaded) {
            return $this->entryContent ?? [];
        }

        $this->entryLoaded = true;
        $this->entryContent = [];
        $storeId = (int)$this->storeManager->getStore()->getId();

        foreach ([$storeId, Store::DEFAULT_STORE_ID] as $tryStoreId) {
            try {
                $entry = $this->entryRepository->getByIdentifier(self::ENTRY_IDENTIFIER, $tryStoreId);
                if ($entry->isActive()) {
                    $this->entryContent = $entry->getEntryData();
                    break;
                }
            } catch (NoSuchEntityException) {
                continue;
            }
        }

        return $this->entryContent;
    }

    /**
     * CollectionPage + BreadcrumbList, plus ItemList / Event nodes when upcoming events exist.
     *
     * @return array<string, mixed>
     */
    public function getJsonLdGraph(): array
    {
        $baseUrl = rtrim((string)$this->storeManager->getStore()->getBaseUrl(), '/');
        $pageUrl = $this->contentPage->getCanonicalUrl();
        $events = $this->getEvents($this->getPageContent());

        $webpage = [
            '@type' => ['CollectionPage', 'WebPage'],
            '@id' => $pageUrl . '#webpage',
            'url' => $pageUrl,
            'name' => $this->contentPage->getMetaTitle(),
            'inLanguage' => ContentPage::LANGUAGE,
            'isPartOf' => ['@id' => $baseUrl . '/#website'],
            'about' => ['@id' => $baseUrl . '/#organization'],
            'publisher' => ['@id' => $baseUrl . '/#organization'],
            'breadcrumb' => ['@id' => $pageUrl . '#breadcrumb'],
        ];
        $description = $this->contentPage->getMetaDescription();
        if ($description !== '') {
            $webpage['description'] = $description;
        }
        $updated = (string)$this->cmsPage->getUpdateTime();
        if ($updated !== '') {
            $webpage['dateModified'] = str_replace(' ', 'T', $updated);
        }

        $graph = [
            $webpage,
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
                        'item' => ['@id' => $pageUrl, 'name' => $this->contentPage->getPageTitle()],
                    ],
                ],
            ],
        ];

        if ($events === []) {
            return [
                '@context' => 'https://schema.org',
                '@graph' => $graph,
            ];
        }

        $graph[0]['mainEntity'] = ['@id' => $pageUrl . '#events'];

        $listElements = [];
        $eventNodes = [];
        foreach ($events as $i => $event) {
            $eventId = $pageUrl . '#event-' . $event['slug'];
            $listElements[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'item' => ['@id' => $eventId],
            ];
            $eventNodes[] = $this->eventNode($event, $eventId, $baseUrl);
        }

        $graph[] = [
            '@type' => 'ItemList',
            '@id' => $pageUrl . '#events',
            'name' => 'Upcoming Elme Living fairs and events',
            'numberOfItems' => count($listElements),
            'itemListElement' => $listElements,
        ];

        foreach ($eventNodes as $node) {
            $graph[] = $node;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }

    public function getJsonLdScript(): string
    {
        return (string)json_encode(
            $this->getJsonLdGraph(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }

    /**
     * Stable fragment id from name + start date (survives copy edits).
     */
    public function eventSlug(string $name, string $start): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'event';
        }

        return $slug . '-' . $start;
    }

    /**
     * en-AU written range, e.g. "31 Jul - 4 Aug 2027" or "31 Jul 2027" when same day.
     */
    public function rangeLabel(string $start, string $end): string
    {
        $from = new \DateTimeImmutable($start);
        $to = new \DateTimeImmutable($end);

        if ($start === $end) {
            return $from->format('j M Y');
        }

        if ($from->format('Y') === $to->format('Y') && $from->format('M') === $to->format('M')) {
            return $from->format('j') . ' - ' . $to->format('j M Y');
        }

        if ($from->format('Y') === $to->format('Y')) {
            return $from->format('j M') . ' - ' . $to->format('j M Y');
        }

        return $from->format('j M Y') . ' - ' . $to->format('j M Y');
    }

    /**
     * @param array{
     *     name: string,
     *     start: string,
     *     end: string,
     *     city: string,
     *     venue: string,
     *     stand: string,
     *     text: string,
     *     link: string,
     *     slug: string
     * } $event
     * @return array<string, mixed>
     */
    private function eventNode(array $event, string $eventId, string $baseUrl): array
    {
        $node = [
            '@type' => 'Event',
            '@id' => $eventId,
            'name' => $event['name'],
            'startDate' => $event['start'],
            'endDate' => $event['end'],
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'organizer' => ['@id' => $baseUrl . '/#organization'],
        ];

        if ($event['text'] !== '') {
            $node['description'] = $event['text'];
        }

        $place = ['@type' => 'Place'];
        if ($event['venue'] !== '') {
            $place['name'] = $event['venue'];
        }
        $address = [
            '@type' => 'PostalAddress',
            'addressCountry' => 'AU',
        ];
        if ($event['city'] !== '') {
            $address['addressLocality'] = $event['city'];
        }
        $place['address'] = $address;
        $node['location'] = $place;

        $node['url'] = $event['link'] !== '' ? $event['link'] : $eventId;

        if ($event['stand'] !== '') {
            $node['additionalProperty'] = [
                '@type' => 'PropertyValue',
                'name' => 'Stand',
                'value' => $event['stand'],
            ];
        }

        return $node;
    }

    /**
     * Accept YYYY-MM-DD or an ISO datetime; return Melbourne calendar Y-m-d, or null.
     */
    private function calendarDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})$/', $value, $m)) {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $m[1]);

            return $dt instanceof \DateTimeImmutable ? $dt->format('Y-m-d') : null;
        }

        try {
            $dt = (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone(self::TIMEZONE));
        } catch (\Exception) {
            return null;
        }

        return $dt->format('Y-m-d');
    }

    private function absoluteHttpUrl(string $value): string
    {
        if ($value === '' || !preg_match('#^https?://#i', $value)) {
            return '';
        }

        return $value;
    }

    private function plainText(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
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
