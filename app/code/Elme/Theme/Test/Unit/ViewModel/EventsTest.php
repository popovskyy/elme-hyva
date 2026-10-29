<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Elme\Theme\ViewModel\ContentPage;
use Elme\Theme\ViewModel\Events;
use Magento\Cms\Model\Page as CmsPage;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see Events
 */
class EventsTest extends TestCase
{
    private Events $events;

    /** @var CmsPage&MockObject */
    private CmsPage $cmsPage;

    /** @var ContentPage&MockObject */
    private ContentPage $contentPage;

    protected function setUp(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://elme.test/');
        $store->method('getUrl')->willReturnCallback(
            static fn (string $path = ''): string => 'https://elme.test/' . ltrim($path, '/')
        );
        $store->method('getId')->willReturn(1);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->cmsPage = $this->createMock(CmsPage::class);
        $this->cmsPage->method('getIdentifier')->willReturn('fairs-events');
        $this->cmsPage->method('getUpdateTime')->willReturn('2026-10-29 10:00:00');

        $this->contentPage = $this->createMock(ContentPage::class);
        $this->contentPage->method('getCanonicalUrl')->willReturn('https://elme.test/fairs-events');
        $this->contentPage->method('getMetaTitle')->willReturn('Fairs & Events | Elme Living Trade Shows Australia');
        $this->contentPage->method('getMetaDescription')->willReturn('Trade fairs for retailers.');
        $this->contentPage->method('getPageTitle')->willReturn('Fairs & Events');

        $this->events = new Events(
            $storeManager,
            $this->cmsPage,
            $this->createMock(EntryRepositoryInterface::class),
            $this->contentPage
        );
    }

    public function testPastEventsAreFilteredAndUpcomingSortedByStart(): void
    {
        $today = $this->events->getToday();
        $yesterday = (new \DateTimeImmutable($today . ' Australia/Melbourne'))->modify('-1 day')->format('Y-m-d');
        $tomorrow = (new \DateTimeImmutable($today . ' Australia/Melbourne'))->modify('+1 day')->format('Y-m-d');
        $nextMonth = (new \DateTimeImmutable($today . ' Australia/Melbourne'))->modify('+30 days')->format('Y-m-d');
        $nextMonthEnd = (new \DateTimeImmutable($nextMonth))->modify('+3 days')->format('Y-m-d');

        $list = $this->events->getEvents([
            'items' => [
                [
                    'name' => 'Later Fair',
                    'start' => $nextMonth,
                    'end' => $nextMonthEnd,
                    'city' => 'Sydney',
                ],
                [
                    'name' => 'Ended Yesterday',
                    'start' => $yesterday,
                    'end' => $yesterday,
                    'city' => 'Melbourne',
                ],
                [
                    'name' => 'On Now Fair',
                    'start' => $yesterday,
                    'end' => $tomorrow,
                    'city' => 'Melbourne',
                    'venue' => 'MCEC',
                    'stand' => 'B1',
                ],
                [
                    'name' => 'Missing dates',
                    'start' => '',
                    'end' => $tomorrow,
                ],
            ],
        ]);

        $this->assertCount(2, $list);
        $this->assertSame('On Now Fair', $list[0]['name']);
        $this->assertTrue($list[0]['on_now']);
        $this->assertSame('Later Fair', $list[1]['name']);
        $this->assertFalse($list[1]['on_now']);
    }

    public function testEventEndingTodayStillShows(): void
    {
        $today = $this->events->getToday();
        $list = $this->events->getEvents([
            'items' => [
                ['name' => 'Ends Today', 'start' => $today, 'end' => $today],
            ],
        ]);

        $this->assertCount(1, $list);
        $this->assertTrue($list[0]['on_now']);
    }

    public function testRangeLabelFormatsEnAu(): void
    {
        $this->assertSame('31 Jul - 4 Aug 2027', $this->events->rangeLabel('2027-07-31', '2027-08-04'));
        $this->assertSame('31 Jul 2027', $this->events->rangeLabel('2027-07-31', '2027-07-31'));
        $this->assertSame('1 - 3 Aug 2027', $this->events->rangeLabel('2027-08-01', '2027-08-03'));
    }

    public function testSlugIsStableFromNameAndStart(): void
    {
        $this->assertSame(
            'reed-gift-fair-2027-07-31',
            $this->events->eventSlug('Reed Gift Fair', '2027-07-31')
        );
    }

    public function testJsonLdOmitsPastAndIncludesItemList(): void
    {
        $today = $this->events->getToday();
        $tomorrow = (new \DateTimeImmutable($today . ' Australia/Melbourne'))->modify('+1 day')->format('Y-m-d');
        $end = (new \DateTimeImmutable($tomorrow))->modify('+2 days')->format('Y-m-d');
        $yesterday = (new \DateTimeImmutable($today . ' Australia/Melbourne'))->modify('-1 day')->format('Y-m-d');

        // Inject page content via a subclass-style stub: use reflection on private entry cache.
        $ref = new \ReflectionClass($this->events);
        $loaded = $ref->getProperty('entryLoaded');
        $loaded->setAccessible(true);
        $loaded->setValue($this->events, true);
        $content = $ref->getProperty('entryContent');
        $content->setAccessible(true);
        $content->setValue($this->events, [
            'items' => [
                ['name' => 'Past', 'start' => $yesterday, 'end' => $yesterday, 'city' => 'Melbourne'],
                [
                    'name' => 'Reed Gift Fair',
                    'start' => $tomorrow,
                    'end' => $end,
                    'city' => 'Melbourne',
                    'venue' => 'MCEC',
                    'stand' => 'A12',
                    'text' => 'See the new season.',
                    'link' => '',
                ],
            ],
        ]);

        $graph = $this->events->getJsonLdGraph()['@graph'];
        $types = array_map(
            static fn (array $node) => $node['@type'] ?? null,
            $graph
        );

        $this->assertContains(['CollectionPage', 'WebPage'], $types);
        $this->assertContains('BreadcrumbList', $types);
        $this->assertContains('ItemList', $types);
        $this->assertContains('Event', $types);

        $eventNodes = array_values(array_filter(
            $graph,
            static fn (array $node): bool => ($node['@type'] ?? '') === 'Event'
        ));
        $this->assertCount(1, $eventNodes);
        $this->assertSame('Reed Gift Fair', $eventNodes[0]['name']);
        $this->assertSame('A12', $eventNodes[0]['additionalProperty']['value']);
        $this->assertStringContainsString('reed-gift-fair-', $eventNodes[0]['@id']);
    }

    public function testJsonLdEmptyStateHasNoEvents(): void
    {
        $ref = new \ReflectionClass($this->events);
        $loaded = $ref->getProperty('entryLoaded');
        $loaded->setAccessible(true);
        $loaded->setValue($this->events, true);
        $content = $ref->getProperty('entryContent');
        $content->setAccessible(true);
        $content->setValue($this->events, ['items' => []]);

        $types = array_map(
            static fn (array $node) => $node['@type'] ?? null,
            $this->events->getJsonLdGraph()['@graph']
        );

        $this->assertContains(['CollectionPage', 'WebPage'], $types);
        $this->assertContains('BreadcrumbList', $types);
        $this->assertNotContains('ItemList', $types);
        $this->assertNotContains('Event', $types);
    }
}
