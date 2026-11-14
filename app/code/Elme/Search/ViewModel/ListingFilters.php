<?php

declare(strict_types=1);

namespace Elme\Search\ViewModel;

use Elme\Search\Api\Data\Facet;
use Elme\Search\Api\Data\FacetOption;
use Elme\Search\Model\Facet\FacetConfig;
use Elme\Search\Model\Facet\FacetDefinition;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Filters drawer, chips and Filters button of a listing (FE-21; FE-23 reuses it on search results).
 *
 * Reads SearchFacets, so call it after the product collection has loaded (from the toolbar or the list template).
 *
 * URLs follow the BE-24 contract and are stable, so one selection always gives one URL: facet parameters in config
 * order, values of one facet sorted A-Z (byte order), one value as a scalar (?material=Iron%2C%20MDF), several as
 * code[]= (?type_detail[]=a&type_detail[]=b), ranges as ?width=50-120; then the other parameters (sort, limit,
 * search text) sorted by name. Changing a filter drops the page number "p".
 */
class ListingFilters implements ArgumentInterface
{
    public const COUNT_ROUTE = 'elme-search/listing/count';

    /** Options shown before "Show all" in a long facet */
    public const VISIBLE_OPTIONS = 8;

    private const PAGE_PARAM = 'p';

    /** @var Facet[]|null */
    private ?array $facets = null;

    public function __construct(
        private readonly SearchFacets $searchFacets,
        private readonly FacetConfig $facetConfig,
        private readonly RequestInterface $request,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * False with engine = magento (no facets, D2: no layered navigation fallback): hide the Filters button.
     */
    public function isAvailable(): bool
    {
        return $this->searchFacets->isActive() && ($this->getFacets() !== [] || $this->getActiveCount() > 0);
    }

    /**
     * Facets to render, in config order, without empty rows: a multi / boolean facet needs an option with items
     * (or a selected one), a range needs a span (or a selection).
     *
     * @return Facet[]
     */
    public function getFacets(): array
    {
        if ($this->facets !== null) {
            return $this->facets;
        }
        $facets = [];
        foreach ($this->searchFacets->getFacets() as $facet) {
            if ($facet->type === Facet::TYPE_RANGE) {
                $hasSpan = $facet->min !== null && $facet->max !== null && $facet->max > $facet->min;
                if ($hasSpan || $facet->isSelected()) {
                    $facets[] = $facet;
                }
                continue;
            }
            $options = array_values(array_filter(
                $facet->options,
                static fn (FacetOption $option): bool => $option->count > 0 || $option->selected
            ));
            if ($options === []) {
                continue;
            }
            if ($this->facetConfig->get($facet->code)?->optionSort === FacetDefinition::SORT_LABEL) {
                // "2 Seater" before "10 Seater"
                usort($options, static fn (FacetOption $a, FacetOption $b): int => strnatcasecmp($a->label, $b->label));
            }
            $facets[] = new Facet(
                $facet->code,
                $facet->label,
                $facet->type,
                $options,
                $facet->min,
                $facet->max,
                $facet->selectedMin,
                $facet->selectedMax
            );
        }
        return $this->facets = $facets;
    }

    public function getTotal(): int
    {
        return $this->searchFacets->getTotal();
    }

    /**
     * Number of selected values: each value of a multi facet, one per range, one for a boolean.
     */
    public function getActiveCount(): int
    {
        $selection = $this->getSelection();
        $count = count($selection['ranges']);
        foreach ($selection['filters'] as $values) {
            $count += count($values);
        }
        return $count;
    }

    /**
     * @return array<int, array{code: string, facet: string, value: string, url: string}>
     */
    public function getChips(): array
    {
        $selection = $this->getSelection();
        $chips = [];
        foreach ($this->facetConfig->getFacets() as $code => $definition) {
            $label = (string)__($definition->label);
            if (isset($selection['ranges'][$code])) {
                $without = $selection;
                unset($without['ranges'][$code]);
                $chips[] = [
                    'code' => $code,
                    'facet' => $label,
                    'value' => $this->formatRange($selection['ranges'][$code]),
                    'url' => $this->buildUrl($without['filters'], $without['ranges']),
                ];
                continue;
            }
            foreach ($selection['filters'][$code] ?? [] as $value) {
                $without = $selection;
                $without['filters'][$code] = array_values(array_diff($selection['filters'][$code], [$value]));
                $chips[] = [
                    'code' => $code,
                    'facet' => $label,
                    'value' => $definition->type === Facet::TYPE_BOOLEAN ? '' : $this->getOptionLabel($code, $value),
                    'url' => $this->buildUrl($without['filters'], $without['ranges']),
                ];
            }
        }
        return $chips;
    }

    /**
     * The listing without any facet parameter and without "p"; sort and per page stay.
     */
    public function getClearUrl(): string
    {
        return $this->buildUrl([], []);
    }

    /**
     * Current URL without its query string: the drawer form posts (GET) here.
     */
    public function getBaseUrl(): string
    {
        return strtok($this->url->getCurrentUrl(), '?') ?: $this->url->getCurrentUrl();
    }

    /**
     * Query parameters that are not facets and not the page number (sort, limit, search text), sorted by name.
     *
     * @return array<string, string|array>
     */
    public function getKeptParams(): array
    {
        $codes = array_keys($this->facetConfig->getFacets());
        $kept = [];
        foreach ($this->getQueryParams() as $name => $value) {
            if ($name === self::PAGE_PARAM || in_array($name, $codes, true)) {
                continue;
            }
            $kept[(string)$name] = $value;
        }
        ksort($kept);
        return $kept;
    }

    /**
     * Kept parameters flattened to name => scalar pairs for hidden inputs (array values become name[key]).
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function getKeptInputs(): array
    {
        $inputs = [];
        foreach ($this->getKeptParams() as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    if (is_scalar($item)) {
                        $inputs[] = ['name' => $name . '[' . $key . ']', 'value' => (string)$item];
                    }
                }
            } elseif (is_scalar($value)) {
                $inputs[] = ['name' => $name, 'value' => (string)$value];
            }
        }
        return $inputs;
    }

    /**
     * Endpoint for the live "Show N products" count, with the listing's category or search text.
     */
    public function getCountUrl(): string
    {
        $request = $this->searchFacets->getRequest();
        $params = [];
        if ($request?->categoryId !== null) {
            $params['category'] = $request->categoryId;
        }
        if ($request?->hasQuery()) {
            $params['q'] = $request->query;
        }
        return $this->url->getUrl(self::COUNT_ROUTE, ['_query' => $params]);
    }

    /**
     * @param array<string, string[]> $filters
     * @param array<string, array{min: ?float, max: ?float}> $ranges
     */
    public function buildUrl(array $filters, array $ranges): string
    {
        $parts = [];
        foreach ($this->facetConfig->getFacets() as $code => $definition) {
            if ($definition->type === Facet::TYPE_RANGE) {
                if (isset($ranges[$code])) {
                    $value = $this->formatRangeParam($ranges[$code]);
                    if ($value !== '') {
                        $parts[] = rawurlencode($code) . '=' . rawurlencode($value);
                    }
                }
                continue;
            }
            $values = $this->sortValues(array_values(array_unique($filters[$code] ?? [])));
            if (count($values) === 1) {
                $parts[] = rawurlencode($code) . '=' . rawurlencode($values[0]);
            } else {
                foreach ($values as $value) {
                    $parts[] = rawurlencode($code) . '[]=' . rawurlencode($value);
                }
            }
        }
        $kept = http_build_query($this->getKeptParams(), '', '&', PHP_QUERY_RFC3986);
        if ($kept !== '') {
            $parts[] = $kept;
        }
        return $this->getBaseUrl() . ($parts !== [] ? '?' . implode('&', $parts) : '');
    }

    public function isOptionHidden(int $index, Facet $facet): bool
    {
        return $index >= self::VISIBLE_OPTIONS && !$this->hasSelectedBeyondVisible($facet);
    }

    public function hasMoreOptions(Facet $facet): bool
    {
        return count($facet->options) > self::VISIBLE_OPTIONS && !$this->hasSelectedBeyondVisible($facet);
    }

    public function formatNumber(?float $number): string
    {
        if ($number === null) {
            return '';
        }
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }

    /**
     * @param array{min: ?float, max: ?float} $range
     */
    public function formatRange(array $range): string
    {
        $min = $this->formatNumber($range['min'] ?? null);
        $max = $this->formatNumber($range['max'] ?? null);
        if ($min !== '' && $max !== '') {
            return $min . '–' . $max;
        }
        return $min !== '' ? (string)__('from %1', $min) : (string)__('up to %1', $max);
    }

    /**
     * @return array{filters: array<string, string[]>, ranges: array<string, array{min: ?float, max: ?float}>}
     */
    private function getSelection(): array
    {
        return $this->searchFacets->getSelection();
    }

    private function hasSelectedBeyondVisible(Facet $facet): bool
    {
        foreach (array_slice($facet->options, self::VISIBLE_OPTIONS) as $option) {
            if ($option->selected) {
                return true;
            }
        }
        return false;
    }

    private function getOptionLabel(string $code, string $value): string
    {
        foreach ($this->getFacets() as $facet) {
            if ($facet->code !== $code) {
                continue;
            }
            foreach ($facet->options as $option) {
                if ($option->value === $value) {
                    return $option->label;
                }
            }
        }
        return $value;
    }

    /**
     * Values of one facet sorted by plain byte order (the drawer script sorts the same way), so the URL does
     * not depend on the order of ticking or on counts.
     *
     * @param string[] $values
     * @return string[]
     */
    private function sortValues(array $values): array
    {
        sort($values, SORT_STRING);
        return $values;
    }

    /**
     * @param array{min: ?float, max: ?float} $range
     */
    private function formatRangeParam(array $range): string
    {
        $min = $this->formatNumber($range['min'] ?? null);
        $max = $this->formatNumber($range['max'] ?? null);
        return $min === '' && $max === '' ? '' : $min . '-' . $max;
    }

    private function getQueryParams(): array
    {
        if ($this->request instanceof HttpRequest) {
            return $this->request->getQuery()->toArray();
        }
        return [];
    }
}
