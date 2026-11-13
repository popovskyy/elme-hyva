<?php

declare(strict_types=1);

namespace Elme\Search\Test\Unit\Model\OpenSearch;

use Elme\Search\Api\Data\Facet;
use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Model\Facet\FacetDefinition;
use Elme\Search\Model\OpenSearch\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    private QueryBuilder $builder;

    /** @var FacetDefinition[] */
    private array $facets;

    protected function setUp(): void
    {
        $this->builder = new QueryBuilder();
        $this->facets = [
            'colour_group' => new FacetDefinition('colour_group', Facet::TYPE_MULTI, 'Colour', 10),
            'seats' => new FacetDefinition('seats', Facet::TYPE_MULTI, 'Seats', 20, 30, FacetDefinition::SORT_LABEL),
            'width' => new FacetDefinition('width', Facet::TYPE_RANGE, 'Width', 30),
            'in_stock' => new FacetDefinition('in_stock', Facet::TYPE_BOOLEAN, 'In stock', 40),
        ];
    }

    public function testCategoryListingFiltersCategoryAndCatalogVisibilityAndSortsByPosition(): void
    {
        $body = $this->builder->build(new SearchRequest(1, null, 14, [], [], SearchRequest::SORT_POSITION), $this->facets);

        $this->assertSame(
            [['terms' => ['visibility' => [2, 4]]], ['term' => ['category_ids' => 14]]],
            $body['query']['bool']['filter']
        );
        $this->assertArrayNotHasKey('must', $body['query']['bool']);
        $position = $body['sort'][0]['category_positions.position'];
        $this->assertSame('asc', $position['order']);
        $this->assertSame(['term' => ['category_positions.id' => 14]], $position['nested']['filter']);
        $this->assertSame(['id' => ['order' => 'asc']], end($body['sort']));
    }

    public function testPaginationUsesFromAndSize(): void
    {
        $body = $this->builder->build(new SearchRequest(1, null, 14, [], [], 'position', null, 3, 24), []);

        $this->assertSame(48, $body['from']);
        $this->assertSame(24, $body['size']);
        $this->assertTrue($body['track_total_hits']);
    }

    public function testPagePastTheResultWindowAsksForNoHitsInsteadOfAnError(): void
    {
        // from 49992 + size 24 > max_result_window 50000
        $body = $this->builder->build(new SearchRequest(1, null, 14, [], [], 'position', null, 2084, 24), $this->facets);

        $this->assertSame(0, $body['from']);
        $this->assertSame(0, $body['size']);
        $this->assertArrayHasKey('aggs', $body);

        $last = $this->builder->build(new SearchRequest(1, null, 14, [], [], 'position', null, 2083, 24), []);
        $this->assertSame(49968, $last['from']);
        $this->assertSame(24, $last['size']);
    }

    public function testSearchUsesSearchVisibilityAndRanksExactSkuFirst(): void
    {
        $body = $this->builder->build(new SearchRequest(1, 'VG.228.ML'), []);

        $this->assertSame(['terms' => ['visibility' => [3, 4]]], $body['query']['bool']['filter'][0]);
        $should = $body['query']['bool']['must'][0]['bool']['should'];
        $this->assertSame(['term' => ['sku' => ['value' => 'vg.228.ml', 'boost' => 1000]]], $should[0]);
        $this->assertSame(['term' => ['sku.compact' => ['value' => 'vg228ml', 'boost' => 800]]], $should[1]);
        $this->assertSame(['prefix' => ['sku' => ['value' => 'vg.228.ml', 'boost' => 200]]], $should[2]);
        $this->assertSame(['prefix' => ['sku.compact' => ['value' => 'vg228ml', 'boost' => 200]]], $should[3]);
        $this->assertSame(1, $body['query']['bool']['must'][0]['bool']['minimum_should_match']);
        $this->assertSame(['_score' => ['order' => 'desc']], $body['sort'][0]);
    }

    public function testSkuWithHashKeepsTheHashForTheExactMatch(): void
    {
        $should = $this->builder->buildTextQuery('218AREDINW210B#')['bool']['should'];

        $this->assertSame('218aredinw210b#', $should[0]['term']['sku']['value']);
        $this->assertSame('218aredinw210b', $should[1]['term']['sku.compact']['value']);
    }

    public function testPlainWordHasNoSkuPrefixAndNoTypoToleranceForShortWords(): void
    {
        $should = $this->builder->buildTextQuery('vase')['bool']['should'];
        $types = array_map(static fn (array $clause): string => (string)array_key_first($clause), $should);

        $this->assertNotContains('prefix', $types);
        $this->assertNotContains('match_phrase', $types, 'phrase boost only for several words');
        $fuzzy = $this->findClause($should, 'best_fields');
        // AUTO:5,8 = no edits up to 4 letters, so "vase" never matches "Vale"
        $this->assertSame('AUTO:5,8', $fuzzy['fuzziness']);
        $this->assertSame('and', $fuzzy['operator']);
        $this->assertSame('product_type.text^6', $fuzzy['fields'][0]);
        $cross = $this->findClause($should, 'cross_fields');
        $this->assertArrayNotHasKey('fuzziness', $cross);
    }

    public function testSeveralWordsAddNamePhrase(): void
    {
        $should = $this->builder->buildTextQuery('ginger jar')['bool']['should'];

        $this->assertContains(['match_phrase' => ['name' => ['query' => 'ginger jar', 'boost' => 20]]], $should);
    }

    public function testFacetFiltersGoToPostFilterAndEachFacetAggregationSeesTheOthersOnly(): void
    {
        $request = new SearchRequest(
            1,
            null,
            14,
            ['colour_group' => ['Green', 'Blue', ''], 'in_stock' => ['1'], 'unknown' => ['x']],
            ['width' => ['min' => 50.0, 'max' => null]]
        );
        $body = $this->builder->build($request, $this->facets);

        $colour = ['terms' => ['colour_group' => ['Green', 'Blue']]];
        $width = ['range' => ['width' => ['gte' => 50.0]]];
        $stock = ['term' => ['in_stock' => true]];
        $this->assertSame(['bool' => ['filter' => [$colour, $width, $stock]]], $body['post_filter']);
        // the category / visibility filters stay in the query, facet selections do not
        $this->assertCount(2, $body['query']['bool']['filter']);

        $this->assertSame(['bool' => ['filter' => [$width, $stock]]], $body['aggs']['facet_colour_group']['filter']);
        $this->assertSame(['bool' => ['filter' => [$colour, $stock]]], $body['aggs']['facet_width']['filter']);
        $this->assertSame(['bool' => ['filter' => [$colour, $width, $stock]]], $body['aggs']['facet_seats']['filter']);
        $this->assertSame(['bool' => ['filter' => [$colour, $width, $stock]]], $body['aggs']['_total']['filter']);
    }

    public function testFacetAggregationTypes(): void
    {
        $aggs = $this->builder->build(new SearchRequest(1, null, 14), $this->facets)['aggs'];

        $this->assertEquals(['match_all' => (object)[]], $aggs['facet_colour_group']['filter']);
        $terms = $aggs['facet_colour_group']['aggs']['values'];
        $this->assertSame(['field' => 'colour_group', 'size' => 50], $terms['terms']);
        $this->assertSame('family_key', $terms['aggs']['items']['cardinality']['field']);
        $this->assertSame(['_key' => 'asc'], $aggs['facet_seats']['aggs']['values']['terms']['order']);
        $this->assertSame(['stats' => ['field' => 'width']], $aggs['facet_width']['aggs']['values']);
        $this->assertSame(2, $aggs['facet_in_stock']['aggs']['values']['terms']['size']);
    }

    public function testRangeWithOnlyMaxAndBooleanFalse(): void
    {
        $filters = $this->builder->buildFacetFilters(
            new SearchRequest(1, null, null, ['in_stock' => ['0']], ['width' => ['min' => null, 'max' => 120]]),
            $this->facets
        );

        $this->assertSame(['range' => ['width' => ['lte' => 120.0]]], $filters['width']);
        $this->assertSame(['term' => ['in_stock' => false]], $filters['in_stock']);
    }

    public function testFamiliesCollapseAndTotalCountsFamilies(): void
    {
        $body = $this->builder->build(new SearchRequest(1, null, 14), []);

        $this->assertSame(['field' => 'family_key'], $body['collapse']);
        $this->assertSame(
            ['cardinality' => ['field' => 'family_key', 'precision_threshold' => 40000]],
            $body['aggs']['_total']['aggs']['items']
        );
    }

    public function testNoCollapseWhenTurnedOff(): void
    {
        $request = new SearchRequest(1, '218AREDIN', null, [], [], 'relevance', null, 1, 24, false);
        $body = $this->builder->build($request, $this->facets);

        $this->assertArrayNotHasKey('collapse', $body);
        $this->assertArrayNotHasKey('_total', $body['aggs']);
        $this->assertArrayNotHasKey('aggs', $body['aggs']['facet_colour_group']['aggs']['values']);
    }

    #[DataProvider('sortProvider')]
    public function testSorts(SearchRequest $request, array $expectedFirst): void
    {
        $this->assertSame($expectedFirst, $this->builder->buildSort($request)[0]);
    }

    public static function sortProvider(): array
    {
        return [
            'name asc' => [new SearchRequest(1, null, 14, [], [], 'name'), ['name.sort' => ['order' => 'asc']]],
            'price desc' => [
                new SearchRequest(1, null, 14, [], [], 'price', 'desc'),
                ['price' => ['order' => 'desc', 'missing' => '_last']],
            ],
            'newest default desc' => [
                new SearchRequest(1, null, 14, [], [], 'newest'),
                ['news_from_date' => ['order' => 'desc', 'missing' => '_last']],
            ],
            'relevance without query = position' => [
                new SearchRequest(1, null, 14, [], [], 'relevance'),
                ['category_positions.position' => [
                    'order' => 'asc',
                    'missing' => '_last',
                    'nested' => [
                        'path' => 'category_positions',
                        'filter' => ['term' => ['category_positions.id' => 14]],
                    ],
                ]],
            ],
            'position without category = relevance' => [
                new SearchRequest(1, 'sofa', null, [], [], 'position'),
                ['_score' => ['order' => 'desc']],
            ],
        ];
    }

    public function testSortByNameOnSearchKeepsScoreAsTieBreaker(): void
    {
        $sort = $this->builder->buildSort(new SearchRequest(1, 'sofa', null, [], [], 'name', 'desc'));

        $this->assertSame([['name.sort' => ['order' => 'desc']], ['_score' => ['order' => 'desc']], ['id' => ['order' => 'asc']]], $sort);
    }

    public function testSuggestCollapsesWordsButNotSkus(): void
    {
        $words = $this->builder->buildSuggest('marble', 6);
        $sku = $this->builder->buildSuggest('VG.228', 6);

        $this->assertSame(6, $words['size']);
        $this->assertSame(['field' => 'family_key'], $words['collapse']);
        $this->assertArrayNotHasKey('collapse', $sku);
        $this->assertSame('category_direct_ids', $words['aggs']['categories']['terms']['field']);
        $this->assertSame([['terms' => ['visibility' => [3, 4]]]], $words['query']['bool']['filter']);
    }

    public function testSuggestMatchesTheLastWordAsAPrefixButSearchDoesNot(): void
    {
        $suggest = $this->builder->buildSuggest('ares b', 6)['query']['bool']['must'][0]['bool']['should'];
        $search = $this->builder->buildTextQuery('ares b')['bool']['should'];
        $prefix = ['match_bool_prefix' => ['name.prefix' => ['query' => 'ares b', 'operator' => 'and', 'boost' => 3]]];

        $this->assertContains($prefix, $suggest);
        $this->assertNotContains($prefix, $search);
    }

    public function testAllLetterSkuAlsoMatchesTheCompactSku(): void
    {
        $should = $this->builder->buildTextQuery('zardtbl')['bool']['should'];

        // "Z-ARDTBL" is stored as sku.compact "zardtbl"
        $this->assertContains(['term' => ['sku.compact' => ['value' => 'zardtbl', 'boost' => 800]]], $should);
    }

    private function findClause(array $should, string $type): array
    {
        foreach ($should as $clause) {
            if (($clause['multi_match']['type'] ?? null) === $type) {
                return $clause['multi_match'];
            }
        }
        $this->fail('No multi_match of type ' . $type);
    }
}
