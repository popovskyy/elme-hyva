<?php

declare(strict_types=1);

namespace Elme\Search\Model\OpenSearch;

use Elme\Search\Api\Data\Facet;
use Elme\Search\Api\Data\FacetOption;
use Elme\Search\Api\Data\SearchHit;
use Elme\Search\Api\Data\SearchRequest;
use Elme\Search\Api\Data\SearchResult;
use Elme\Search\Model\Facet\FacetDefinition;

/**
 * OpenSearch response (of a QueryBuilder::build() body) -> SearchResult. Pure.
 *
 * Facets without any option in the current result are left out (unless something in them is selected), so a
 * vase search does not offer bed or rug facets: facets follow what is in the result.
 */
class ResponseMapper
{
    /**
     * @param FacetDefinition[] $facets
     */
    public function map(array $response, SearchRequest $request, array $facets): SearchResult
    {
        $hits = [];
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $source = $hit['_source'] ?? [];
            $id = (int)($source['id'] ?? $hit['_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $family = isset($source['family_code']) && $source['family_code'] !== ''
                ? (string)$source['family_code'] : null;
            $hits[] = new SearchHit($id, (string)($source['sku'] ?? ''), $family);
        }

        $aggs = $response['aggregations'] ?? [];
        if ($request->collapseFamilies && isset($aggs[QueryBuilder::AGG_TOTAL][QueryBuilder::AGG_ITEMS]['value'])) {
            $total = (int)$aggs[QueryBuilder::AGG_TOTAL][QueryBuilder::AGG_ITEMS]['value'];
        } else {
            $total = $response['hits']['total'] ?? 0;
            $total = (int)(is_array($total) ? ($total['value'] ?? 0) : $total);
        }

        $result = [];
        foreach ($facets as $definition) {
            $facetAgg = $aggs[QueryBuilder::AGG_PREFIX . $definition->code] ?? null;
            $agg = $facetAgg['values'] ?? null;
            if ($agg === null) {
                continue;
            }
            $facet = $definition->type === Facet::TYPE_RANGE
                ? $this->mapRange($definition, $agg, $request)
                : $this->mapOptions(
                    $definition,
                    $agg,
                    $request,
                    $facetAgg[QueryBuilder::AGG_SELECTED]['buckets'] ?? []
                );
            if ($facet !== null) {
                $result[] = $facet;
            }
        }

        return new SearchResult($hits, $total, $result);
    }

    /**
     * @param array $selectedBuckets OpenSearch terms buckets forced for the current selection
     */
    private function mapOptions(
        FacetDefinition $definition,
        array $agg,
        SearchRequest $request,
        array $selectedBuckets = []
    ): ?Facet {
        $isBoolean = $definition->type === Facet::TYPE_BOOLEAN;
        $selected = array_map('strval', $request->filters[$definition->code] ?? []);
        if ($isBoolean) {
            $selected = array_values(array_intersect(array_map('strtolower', $selected), ['1', 'true', 'yes']))
                ? ['1'] : [];
        }
        $label = (string)__($definition->label);

        $options = [];
        foreach (array_merge($agg['buckets'] ?? [], $selectedBuckets) as $bucket) {
            $count = (int)($bucket[QueryBuilder::AGG_ITEMS]['value'] ?? $bucket['doc_count'] ?? 0);
            if ($isBoolean) {
                $key = (string)($bucket['key_as_string'] ?? $bucket['key']);
                if (!in_array($key, ['true', '1'], true)) {
                    continue;
                }
                $value = '1';
                $optionLabel = $label;
            } else {
                $value = (string)$bucket['key'];
                $optionLabel = $value;
            }
            if ($count <= 0) {
                continue;
            }
            // selected agg may repeat a top-size value: keep the first (same count)
            if (!isset($options[$value])) {
                $options[$value] = new FacetOption($value, $optionLabel, $count, in_array($value, $selected, true));
            }
        }
        // A selected value that has no items any more stays visible, so it can be unselected.
        foreach ($selected as $value) {
            if ($value !== '' && !isset($options[$value])) {
                $options[$value] = new FacetOption($value, $isBoolean ? $label : $value, 0, true);
            }
        }
        if ($options === []) {
            return null;
        }
        $options = array_values($options);
        if (!$isBoolean) {
            usort($options, $definition->optionSort === FacetDefinition::SORT_LABEL
                ? static fn (FacetOption $a, FacetOption $b): int => strnatcasecmp($a->label, $b->label)
                : static fn (FacetOption $a, FacetOption $b): int
                    => [$b->count, $a->label] <=> [$a->count, $b->label]);
        }
        return new Facet($definition->code, $label, $definition->type, $options);
    }

    private function mapRange(FacetDefinition $definition, array $agg, SearchRequest $request): ?Facet
    {
        $range = $request->ranges[$definition->code] ?? [];
        $selectedMin = isset($range['min']) && is_numeric($range['min']) ? (float)$range['min'] : null;
        $selectedMax = isset($range['max']) && is_numeric($range['max']) ? (float)$range['max'] : null;
        $count = (int)($agg['count'] ?? 0);
        if ($count === 0 && $selectedMin === null && $selectedMax === null) {
            return null;
        }
        return new Facet(
            $definition->code,
            (string)__($definition->label),
            Facet::TYPE_RANGE,
            [],
            isset($agg['min']) ? (float)$agg['min'] : null,
            isset($agg['max']) ? (float)$agg['max'] : null,
            $selectedMin,
            $selectedMax
        );
    }
}
