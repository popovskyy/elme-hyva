<?php

declare(strict_types=1);

namespace Elme\Search\Test\Unit\Model\Storefront;

use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Model\Facet\FacetConfig;
use Elme\Search\Model\Storefront\RequestFactory;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\Search\SearchCriteria;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Config\DataInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class RequestFactoryTest extends TestCase
{
    private function factory(array $params): RequestFactory
    {
        $http = $this->createMock(RequestInterface::class);
        $http->method('getParam')->willReturnCallback(static fn ($name) => $params[$name] ?? null);
        $data = $this->createMock(DataInterface::class);
        $data->method('get')->willReturn([
            'colour_group' => ['code' => 'colour_group', 'type' => 'multi', 'sortOrder' => '10'],
            'width' => ['code' => 'width', 'type' => 'range', 'sortOrder' => '20'],
            'depth' => ['code' => 'depth', 'type' => 'range', 'sortOrder' => '30'],
            'in_stock' => ['code' => 'in_stock', 'type' => 'boolean', 'sortOrder' => '40'],
        ]);
        return new RequestFactory($http, new FacetConfig($data));
    }

    private function criteria(array $filters, array $orders, int $currentPage, ?int $pageSize): SearchCriteria
    {
        $group = new FilterGroup();
        $group->setFilters(array_map(static function ($field) use ($filters) {
            $filter = new Filter();
            $filter->setField($field);
            $filter->setValue($filters[$field]);
            return $filter;
        }, array_keys($filters)));
        $criteria = new SearchCriteria();
        $criteria->setFilterGroups([$group]);
        $criteria->setSortOrders($orders);
        $criteria->setCurrentPage($currentPage);
        if ($pageSize !== null) {
            $criteria->setPageSize($pageSize);
        }
        return $criteria;
    }

    public function testCategoryPage(): void
    {
        $criteria = $this->criteria(
            ['category_ids' => '14', 'visibility' => [2, 4]],
            ['position' => 'ASC', 'entity_id' => 'DESC'],
            1,
            24
        );
        $request = $this->factory([
            'colour_group' => [' Green', 'Blue', ''],
            'width' => '50-120',
            'depth' => '-80',
            'in_stock' => '1',
            'p' => '2',
        ])->fromCriteria($criteria, 1);

        $this->assertSame(1, $request->storeId);
        $this->assertSame(14, $request->categoryId);
        $this->assertNull($request->query);
        $this->assertSame(SearchRequest::SORT_POSITION, $request->sort);
        $this->assertSame('asc', $request->direction);
        $this->assertSame(2, $request->page, 'criteria page is 0-based');
        $this->assertSame(24, $request->pageSize);
        $this->assertSame(['colour_group' => ['Green', 'Blue'], 'in_stock' => ['1']], $request->filters);
        $this->assertSame([
            'width' => ['min' => 50.0, 'max' => 120.0],
            'depth' => ['min' => null, 'max' => 80.0],
        ], $request->ranges);
        $this->assertTrue($request->collapseFamilies);
    }

    public function testScalarFacetValueIsOneValueEvenWithAComma(): void
    {
        $criteria = $this->criteria(['category_ids' => '14'], [], 0, 24);
        $request = $this->factory(['colour_group' => 'Bed Frame, Storage Bed'])->fromCriteria($criteria, 1);

        $this->assertSame(['colour_group' => ['Bed Frame, Storage Bed']], $request->filters);
    }

    public function testArrayFacetValuesKeepCommas(): void
    {
        $criteria = $this->criteria(['category_ids' => '14'], [], 0, 24);
        $request = $this->factory([
            'colour_group' => ['Engineered Wood Frame, Metal Frame', 'Oak', ['nested']],
        ])->fromCriteria($criteria, 1);

        $this->assertSame(['colour_group' => ['Engineered Wood Frame, Metal Frame', 'Oak']], $request->filters);
    }

    public function testSearchPageRelevanceIsAlwaysBestFirst(): void
    {
        $criteria = $this->criteria(['search_term' => ' sofa '], ['relevance' => 'ASC'], 0, 12);
        $request = $this->factory([])->fromCriteria($criteria, 1);

        $this->assertSame('sofa', $request->query);
        $this->assertSame(SearchRequest::SORT_RELEVANCE, $request->sort);
        $this->assertSame('desc', $request->direction);
        $this->assertSame(1, $request->page);
        $this->assertSame([], $request->filters);
    }

    public function testSkuSearchDoesNotCollapseFamilies(): void
    {
        $criteria = $this->criteria(['search_term' => 'VG.228'], [], 0, 24);

        $this->assertFalse($this->factory([])->fromCriteria($criteria, 1)->collapseFamilies);
    }

    public function testToolbarSortsMapAndUnknownFallsBack(): void
    {
        $byName = $this->factory([])->fromCriteria($this->criteria(['category_ids' => 3], ['name' => 'DESC'], 0, 24), 1);
        $unknown = $this->factory([])->fromCriteria($this->criteria(['category_ids' => 3], ['color' => 'ASC'], 0, 24), 1);
        $newest = $this->factory([])->fromCriteria($this->criteria(['category_ids' => 3], ['news_from_date' => 'DESC'], 0, null), 1);

        $this->assertSame([SearchRequest::SORT_NAME, 'desc'], [$byName->sort, $byName->direction]);
        $this->assertSame(SearchRequest::SORT_POSITION, $unknown->sort);
        $this->assertSame(SearchRequest::SORT_NEWEST, $newest->sort);
        $this->assertSame(10000, $newest->pageSize, 'no page size = everything (capped)');
    }
}
