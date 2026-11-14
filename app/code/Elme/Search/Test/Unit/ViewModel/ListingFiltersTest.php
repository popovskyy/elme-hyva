<?php

declare(strict_types=1);

namespace Elme\Search\Test\Unit\ViewModel;

use Elme\Search\Api\Data\Facet;
use Elme\Search\Api\Data\FacetOption;
use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Api\Data\SearchResult;
use Elme\Search\Model\Facet\FacetConfig;
use Elme\Search\Model\Storefront\CurrentSearch;
use Elme\Search\ViewModel\ListingFilters;
use Elme\Search\ViewModel\SearchFacets;
use Laminas\Stdlib\Parameters;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Config\DataInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ListingFiltersTest extends TestCase
{
    private const BASE = 'https://elme.test/furniture.html';

    /**
     * @param array<string, mixed> $query current query string
     * @param Facet[] $facets
     */
    private function filters(array $query, array $filters = [], array $ranges = [], array $facets = []): ListingFilters
    {
        $data = $this->createMock(DataInterface::class);
        $data->method('get')->willReturn([
            'colour_group' => ['type' => 'multi', 'label' => 'Colour', 'sortOrder' => '10', 'quick' => 'true',
                'quickLimit' => '2'],
            'type_detail' => ['type' => 'multi', 'label' => 'Type', 'sortOrder' => '30', 'quick' => 'true'],
            'seats' => ['type' => 'multi', 'label' => 'Seats', 'sortOrder' => '60', 'optionSort' => 'label'],
            'width' => ['type' => 'range', 'label' => 'Width (cm)', 'sortOrder' => '70'],
            'in_stock' => ['type' => 'boolean', 'label' => 'In stock', 'sortOrder' => '90', 'quick' => 'true'],
        ]);
        $current = new CurrentSearch();
        $current->set(
            new SearchRequest(1, null, 11, $filters, $ranges, SearchRequest::SORT_POSITION),
            new SearchResult([], 12, $facets)
        );
        $http = $this->createMock(Http::class);
        $http->method('getQuery')->willReturn(new Parameters($query));
        $url = $this->createMock(UrlInterface::class);
        $url->method('getCurrentUrl')->willReturn(
            self::BASE . ($query ? '?' . http_build_query($query) : '')
        );
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string => 'https://elme.test/' . $route . '/?'
                . http_build_query($params['_query'] ?? [])
        );
        return new ListingFilters(new SearchFacets($current), new FacetConfig($data), $http, $url);
    }

    public function testOneValueIsScalarSeveralAreArrayParamsInConfigAndByteOrder(): void
    {
        $url = $this->filters([])->buildUrl(
            ['type_detail' => ['Bed Frame, Storage Bed'], 'colour_group' => ['Green', 'Blue']],
            []
        );
        $this->assertSame(
            self::BASE . '?colour_group[]=Blue&colour_group[]=Green&type_detail=Bed%20Frame%2C%20Storage%20Bed',
            $url
        );
    }

    public function testRangesBooleanAndKeptParamsDropPage(): void
    {
        $filters = $this->filters(['p' => '3', 'product_list_order' => 'name', 'product_list_limit' => '12',
            'width' => '50-120']);
        $url = $filters->buildUrl(['in_stock' => ['1']], ['width' => ['min' => 50.0, 'max' => null]]);
        $this->assertSame(
            self::BASE . '?width=50-&in_stock=1&product_list_limit=12&product_list_order=name',
            $url
        );
        $this->assertSame(self::BASE . '?product_list_limit=12&product_list_order=name', $filters->getClearUrl());
    }

    public function testChipsRemoveOneValueAndCountActiveFilters(): void
    {
        $filters = $this->filters(
            ['type_detail' => ['Buffet', 'Dining Table'], 'width' => '50-120.5'],
            ['type_detail' => ['Buffet', 'Dining Table']],
            ['width' => ['min' => 50.0, 'max' => 120.5]]
        );
        $this->assertSame(3, $filters->getActiveCount());
        $chips = $filters->getChips();
        $this->assertCount(3, $chips);
        $this->assertSame(['Type', 'Buffet'], [$chips[0]['facet'], $chips[0]['value']]);
        $this->assertSame(self::BASE . '?type_detail=Dining%20Table&width=50-120.5', $chips[0]['url']);
        $this->assertSame("50\u{2013}120.5", $chips[2]['value']);
        $this->assertSame(self::BASE . '?type_detail[]=Buffet&type_detail[]=Dining%20Table', $chips[2]['url']);
    }

    public function testEmptyRowsAreDroppedAndLabelSortIsNatural(): void
    {
        $filters = $this->filters([], [], [], [
            new Facet('colour_group', 'Colour', Facet::TYPE_MULTI, [new FacetOption('Green', 'Green', 0)]),
            new Facet('seats', 'Seats', Facet::TYPE_MULTI, [
                new FacetOption('10 Seater', '10 Seater', 1),
                new FacetOption('2 Seater', '2 Seater', 3),
            ]),
            new Facet('width', 'Width (cm)', Facet::TYPE_RANGE, [], 80.0, 80.0),
        ]);
        $facets = $filters->getFacets();
        $this->assertCount(1, $facets);
        $this->assertSame(['2 Seater', '10 Seater'], array_map(static fn ($o) => $o->label, $facets[0]->options));
        $this->assertTrue($filters->isAvailable());
    }

    public function testToolbarPartsKeepTheStableFacetQuery(): void
    {
        $filters = $this->filters(
            ['p' => '2', 'type_detail' => ['Dining Table', 'Bed Frame, Storage Bed'], 'product_list_order' => 'name'],
            ['type_detail' => ['Dining Table', 'Bed Frame, Storage Bed']]
        );
        $this->assertSame([
            'base' => self::BASE,
            'facets' => 'type_detail[]=Bed%20Frame%2C%20Storage%20Bed&type_detail[]=Dining%20Table',
            'kept' => ['product_list_order' => 'name'],
            'page' => 2,
        ], $filters->getToolbarUrlParts());
    }

    public function testCountUrlCarriesCategory(): void
    {
        $this->assertSame(
            'https://elme.test/elme-search/listing/count/?category=11',
            $this->filters([])->getCountUrl()
        );
    }

    public function testQuickBarTakesTopOptionsPerFacetAndTogglesWithPlainLinks(): void
    {
        $filters = $this->filters([], [], [], [
            new Facet('colour_group', 'Colour', Facet::TYPE_MULTI, [
                new FacetOption('Beige', 'Beige', 5),
                new FacetOption('Grey', 'Grey', 4),
                new FacetOption('Green', 'Green', 1),
            ]),
            new Facet('type_detail', 'Type', Facet::TYPE_MULTI, [
                new FacetOption('Buffet', 'Buffet', 7),
                new FacetOption('Coffee Table', 'Coffee Table', 5),
            ]),
            new Facet('seats', 'Seats', Facet::TYPE_MULTI, [
                new FacetOption('2 Seater', '2 Seater', 3),
                new FacetOption('3 Seater', '3 Seater', 2),
            ]),
            new Facet('in_stock', 'In stock', Facet::TYPE_BOOLEAN, [new FacetOption('1', 'In stock', 4)]),
        ]);
        $groups = $filters->getQuickGroups();
        $this->assertSame(['colour_group', 'type_detail', 'in_stock'], array_column($groups, 'code'));
        // quickLimit 2 on Colour, the rest stays in the drawer
        $this->assertSame(['Beige', 'Grey'], array_column($groups[0]['chips'], 'label'));
        $this->assertSame(self::BASE . '?colour_group=Beige', $groups[0]['chips'][0]['url']);
        $this->assertSame(self::BASE . '?type_detail=Buffet', $groups[1]['chips'][0]['url']);
        $this->assertSame('In stock', $groups[2]['chips'][0]['label']);
        $this->assertSame(self::BASE . '?in_stock=1', $groups[2]['chips'][0]['url']);
    }

    public function testQuickChipOfSelectedValueRemovesItAndKeepsOtherFilters(): void
    {
        $filters = $this->filters(
            ['type_detail' => ['Buffet', 'Coffee Table'], 'width' => '50-120', 'product_list_order' => 'name'],
            ['type_detail' => ['Buffet', 'Coffee Table']],
            ['width' => ['min' => 50.0, 'max' => 120.0]],
            [new Facet('type_detail', 'Type', Facet::TYPE_MULTI, [
                new FacetOption('Buffet', 'Buffet', 7, true),
                new FacetOption('Coffee Table', 'Coffee Table', 5, true),
                new FacetOption('Side Table', 'Side Table', 2),
            ])]
        );
        $chips = $filters->getQuickGroups()[0]['chips'];
        $this->assertTrue($chips[0]['selected']);
        $this->assertSame(self::BASE . '?type_detail=Coffee%20Table&width=50-120&product_list_order=name', $chips[0]['url']);
        $this->assertSame(
            self::BASE . '?type_detail[]=Buffet&type_detail[]=Coffee%20Table&type_detail[]=Side%20Table&width=50-120'
                . '&product_list_order=name',
            $chips[2]['url']
        );
        // the two selected types sit in the quick bar; only the range chip is left below the toolbar
        $this->assertSame(3, $filters->getActiveCount());
        $this->assertSame(['width'], array_column($filters->getChips(true), 'code'));
        $this->assertCount(3, $filters->getChips());
    }

    public function testQuickBarSkipsFacetsThatFilterNothing(): void
    {
        $filters = $this->filters([], [], [], [
            // one type only, and in stock matches every product (total is 12)
            new Facet('type_detail', 'Type', Facet::TYPE_MULTI, [new FacetOption('Buffet', 'Buffet', 12)]),
            new Facet('in_stock', 'In stock', Facet::TYPE_BOOLEAN, [new FacetOption('1', 'In stock', 12)]),
            new Facet('colour_group', 'Colour', Facet::TYPE_MULTI, [new FacetOption('Beige', 'Beige', 6)]),
        ]);
        $this->assertSame([], $filters->getQuickGroups());
        $this->assertTrue($filters->isAvailable());
    }

    public function testSelectedValueBeyondTheLimitStillGetsAChip(): void
    {
        $filters = $this->filters(
            ['colour_group' => 'Green'],
            ['colour_group' => ['Green']],
            [],
            [new Facet('colour_group', 'Colour', Facet::TYPE_MULTI, [
                new FacetOption('Beige', 'Beige', 5),
                new FacetOption('Grey', 'Grey', 4),
                new FacetOption('Green', 'Green', 1, true),
            ])]
        );
        $this->assertSame(['Beige', 'Green'], array_column($filters->getQuickGroups()[0]['chips'], 'label'));
    }
}
