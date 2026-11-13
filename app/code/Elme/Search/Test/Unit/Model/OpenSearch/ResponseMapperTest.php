<?php

declare(strict_types=1);

namespace Elme\Search\Test\Unit\Model\OpenSearch;

use Elme\Search\Api\Data\Facet;
use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Model\Facet\FacetDefinition;
use Elme\Search\Model\OpenSearch\ResponseMapper;
use PHPUnit\Framework\TestCase;

class ResponseMapperTest extends TestCase
{
    private ResponseMapper $mapper;

    /** @var FacetDefinition[] */
    private array $facets;

    protected function setUp(): void
    {
        $this->mapper = new ResponseMapper();
        $this->facets = [
            'colour_group' => new FacetDefinition('colour_group', Facet::TYPE_MULTI, 'Colour', 10),
            'seats' => new FacetDefinition('seats', Facet::TYPE_MULTI, 'Seats', 20, 50, FacetDefinition::SORT_LABEL),
            'style' => new FacetDefinition('style', Facet::TYPE_MULTI, 'Style', 30),
            'width' => new FacetDefinition('width', Facet::TYPE_RANGE, 'Width (cm)', 40),
            'depth' => new FacetDefinition('depth', Facet::TYPE_RANGE, 'Depth (cm)', 50),
            'in_stock' => new FacetDefinition('in_stock', Facet::TYPE_BOOLEAN, 'In stock', 60),
        ];
    }

    public function testHitsKeepOrderAndTotalCountsFamiliesWhenCollapsed(): void
    {
        $result = $this->mapper->map($this->response(), new SearchRequest(1, null, 14), []);

        $this->assertSame([70, 22, 57], $result->getProductIds());
        $this->assertSame(['107BRELBDSINCI#', '153ALDOCCCHACHS', '218AREDINW210B#'], $result->getSkus());
        $this->assertSame('BRENTWOOD-LIFT', $result->hits[0]->familyCode);
        $this->assertNull($result->hits[1]->familyCode);
        $this->assertSame(41, $result->total, 'cardinality of family_key, not the 50 member hits');
    }

    public function testTotalFromHitsWhenNotCollapsed(): void
    {
        $request = new SearchRequest(1, '218AREDIN', null, [], [], 'relevance', null, 1, 24, false);

        $this->assertSame(50, $this->mapper->map($this->response(), $request, [])->total);
    }

    public function testMultiFacetCountsItemsSortsByCountAndMarksSelection(): void
    {
        $request = new SearchRequest(1, null, 14, ['colour_group' => ['Green', 'Pink']]);
        $facet = $this->mapper->map($this->response(), $request, $this->facets)->getFacet('colour_group');

        $this->assertSame('Colour', $facet->label);
        $this->assertSame(Facet::TYPE_MULTI, $facet->type);
        $rows = array_map(static fn ($o) => [$o->value, $o->count, $o->selected], $facet->options);
        $this->assertSame([
            ['Natural', 7, false],   // 9 members, 7 tiles
            ['Black', 3, false],     // equal counts: by label
            ['Green', 3, true],
            ['Pink', 0, true],       // selected, no items left: kept so it can be unselected
        ], $rows);
        $this->assertTrue($facet->isSelected());
    }

    public function testLabelSortedFacet(): void
    {
        $facet = $this->mapper->map($this->response(), new SearchRequest(1, null, 14), $this->facets)->getFacet('seats');

        $this->assertSame(['1', '2', '10'], array_map(static fn ($o) => $o->value, $facet->options));
    }

    public function testFacetWithNothingInTheResultIsLeftOut(): void
    {
        $result = $this->mapper->map($this->response(), new SearchRequest(1, null, 14), $this->facets);

        $this->assertNull($result->getFacet('style'));
        $this->assertNull($result->getFacet('depth'));
        $codes = array_map(static fn ($f) => $f->code, $result->facets);
        $this->assertSame(['colour_group', 'seats', 'width', 'in_stock'], $codes, 'config order kept');
    }

    public function testRangeFacet(): void
    {
        $request = new SearchRequest(1, null, 14, [], ['width' => ['min' => 50.0, 'max' => null]]);
        $facet = $this->mapper->map($this->response(), $request, $this->facets)->getFacet('width');

        $this->assertSame(Facet::TYPE_RANGE, $facet->type);
        $this->assertSame([34.0, 240.0], [$facet->min, $facet->max]);
        $this->assertSame([50.0, null], [$facet->selectedMin, $facet->selectedMax]);
        $this->assertSame([], $facet->options);
        $this->assertTrue($facet->isSelected());
    }

    public function testBooleanFacetHasOneOptionCountingInStockItems(): void
    {
        $request = new SearchRequest(1, null, 14, ['in_stock' => ['1']]);
        $facet = $this->mapper->map($this->response(), $request, $this->facets)->getFacet('in_stock');

        $this->assertCount(1, $facet->options);
        $this->assertSame(['1', 'In stock', 40, true], [
            $facet->options[0]->value, $facet->options[0]->label, $facet->options[0]->count, $facet->options[0]->selected,
        ]);
    }

    public function testEmptyResponse(): void
    {
        $result = $this->mapper->map(['hits' => ['total' => ['value' => 0], 'hits' => []]], new SearchRequest(1, 'x'), $this->facets);

        $this->assertSame([], $result->hits);
        $this->assertSame(0, $result->total);
        $this->assertSame([], $result->facets);
    }

    private function response(): array
    {
        $cardinality = static fn (int $n) => ['items' => ['value' => $n]];
        return [
            'hits' => [
                'total' => ['value' => 50, 'relation' => 'eq'],
                'hits' => [
                    ['_id' => '70', '_source' => ['id' => 70, 'sku' => '107BRELBDSINCI#', 'family_code' => 'BRENTWOOD-LIFT']],
                    ['_id' => '22', '_source' => ['id' => 22, 'sku' => '153ALDOCCCHACHS']],
                    ['_id' => '57', '_source' => ['id' => 57, 'sku' => '218AREDINW210B#', 'family_code' => 'ARES-DT']],
                ],
            ],
            'aggregations' => [
                '_total' => ['doc_count' => 50, 'items' => ['value' => 41]],
                'facet_colour_group' => ['values' => ['buckets' => [
                    ['key' => 'Natural', 'doc_count' => 9] + $cardinality(7),
                    ['key' => 'Green', 'doc_count' => 3] + $cardinality(3),
                    ['key' => 'Black', 'doc_count' => 4] + $cardinality(3),
                ]]],
                'facet_seats' => ['values' => ['buckets' => [
                    ['key' => '10', 'doc_count' => 1] + $cardinality(1),
                    ['key' => '1', 'doc_count' => 1] + $cardinality(1),
                    ['key' => '2', 'doc_count' => 2] + $cardinality(2),
                ]]],
                'facet_style' => ['values' => ['buckets' => []]],
                'facet_width' => ['values' => ['count' => 45, 'min' => 34.0, 'max' => 240.0]],
                'facet_depth' => ['values' => ['count' => 0, 'min' => null, 'max' => null]],
                'facet_in_stock' => ['values' => ['buckets' => [
                    ['key' => 1, 'key_as_string' => 'true', 'doc_count' => 45] + $cardinality(40),
                    ['key' => 0, 'key_as_string' => 'false', 'doc_count' => 5] + $cardinality(1),
                ]]],
            ],
        ];
    }
}
