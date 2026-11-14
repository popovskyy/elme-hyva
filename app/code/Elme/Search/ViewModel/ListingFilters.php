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

    /** @var array<int, array{code: string, label: string, type: string, chips: array}>|null */
    private ?array $quickGroups = null;

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
     * @param bool $withoutQuickBar leave out the values the quick bar already shows as chips (they are removed there)
     * @return array<int, array{code: string, facet: string, value: string, url: string}>
     */
    public function getChips(bool $withoutQuickBar = false): array
    {
        $selection = $this->getSelection();
        $inQuickBar = $withoutQuickBar ? $this->getQuickSelected() : [];
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
                if (isset($inQuickBar[$code][$value])) {
                    continue;
                }
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
     * One-tap chips of the sticky quick bar, grouped by facet in config order. Only facets marked quick="true" in
     * elme_search_facets.xml, and only what helps in this listing:
     * - multi: the top options (quickLimit), selected ones always; skipped when the listing has fewer than two
     *   options and nothing is selected (a single "Coffee Table" chip filters nothing);
     * - boolean: the one option, skipped when it would match every product and is not selected.
     * Each chip links to the listing with that value toggled (a plain link: no JS, nofollow in the template).
     *
     * @return array<int, array{code: string, label: string, type: string, chips: array<int, array{label: string, value: string, selected: bool, url: string}>}>
     */
    public function getQuickGroups(): array
    {
        if ($this->quickGroups !== null) {
            return $this->quickGroups;
        }
        $groups = [];
        if ($this->searchFacets->isActive()) {
            foreach ($this->getFacets() as $facet) {
                $definition = $this->facetConfig->get($facet->code);
                if ($definition === null || !$definition->quick || $facet->type === Facet::TYPE_RANGE) {
                    continue;
                }
                $chips = [];
                foreach ($this->pickQuickOptions($facet, $definition) as $option) {
                    $chips[] = [
                        'label' => $facet->type === Facet::TYPE_BOOLEAN ? (string)__($facet->label) : $option->label,
                        'value' => $option->value,
                        'selected' => $option->selected,
                        'url' => $this->buildToggleUrl($facet->code, $option->value, $option->selected),
                    ];
                }
                if ($chips !== []) {
                    $groups[] = [
                        'code' => $facet->code,
                        'label' => (string)__($facet->label),
                        'type' => $facet->type,
                        'chips' => $chips,
                    ];
                }
            }
        }
        return $this->quickGroups = $groups;
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
        $parts = $this->buildFacetParts($filters, $ranges);
        $kept = http_build_query($this->getKeptParams(), '', '&', PHP_QUERY_RFC3986);
        if ($kept !== '') {
            $parts[] = $kept;
        }
        return $this->getBaseUrl() . ($parts !== [] ? '?' . implode('&', $parts) : '');
    }

    /**
     * What the toolbar (sort, per page) builds its URLs from, so changing the sort keeps the stable URL form of
     * buildUrl(): the base URL, the facet query of the current selection exactly as buildUrl() writes it, the other
     * parameters (name => value, sorted by name, without "p") and the current page.
     *
     * @return array{base: string, facets: string, kept: array<string, string>, page: int}
     */
    public function getToolbarUrlParts(): array
    {
        $selection = $this->getSelection();
        $kept = [];
        foreach ($this->getKeptInputs() as $input) {
            $kept[$input['name']] = $input['value'];
        }
        $page = $this->getQueryParams()[self::PAGE_PARAM] ?? 1;
        return [
            'base' => $this->getBaseUrl(),
            'facets' => implode('&', $this->buildFacetParts($selection['filters'], $selection['ranges'])),
            'kept' => $kept,
            'page' => is_scalar($page) && ctype_digit((string)$page) ? max(1, (int)$page) : 1,
        ];
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
            return $min . "\u{2013}" . $max;
        }
        return $min !== '' ? (string)__('from %1', $min) : (string)__('up to %1', $max);
    }

    /**
     * @return FacetOption[]
     */
    private function pickQuickOptions(Facet $facet, FacetDefinition $definition): array
    {
        $options = $facet->options;
        $hasSelected = $facet->isSelected();
        if ($facet->type === Facet::TYPE_BOOLEAN) {
            $option = $options[0] ?? null;
            if ($option === null) {
                return [];
            }
            return $option->selected || ($option->count > 0 && $option->count < $this->getTotal()) ? [$option] : [];
        }
        if (!$hasSelected && count($options) < 2) {
            return [];
        }
        $limit = max(1, $definition->quickLimit);
        $picked = [];
        foreach ($options as $index => $option) {
            if ($option->selected) {
                $picked[$index] = true;
            }
        }
        foreach ($options as $index => $option) {
            if (count($picked) >= $limit) {
                break;
            }
            $picked[$index] = true;
        }
        ksort($picked);
        return array_map(static fn (int $index): FacetOption => $options[$index], array_keys($picked));
    }

    /**
     * The current listing with one value of a multi / boolean facet switched on or off.
     */
    private function buildToggleUrl(string $code, string $value, bool $selected): string
    {
        $selection = $this->getSelection();
        $values = $selection['filters'][$code] ?? [];
        $values = $selected
            ? array_values(array_diff($values, [$value]))
            : array_values(array_unique(array_merge($values, [$value])));
        $selection['filters'][$code] = $values;
        return $this->buildUrl($selection['filters'], $selection['ranges']);
    }

    /**
     * Selected values the quick bar shows as chips: [facet code][value] => true.
     *
     * @return array<string, array<string, true>>
     */
    private function getQuickSelected(): array
    {
        $shown = [];
        foreach ($this->getQuickGroups() as $group) {
            foreach ($group['chips'] as $chip) {
                if ($chip['selected']) {
                    $shown[$group['code']][$chip['value']] = true;
                }
            }
        }
        return $shown;
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

    /**
     * Facet parameters in config order: one value as a scalar, several as code[]=, ranges as code=min-max.
     *
     * @param array<string, string[]> $filters
     * @param array<string, array{min: ?float, max: ?float}> $ranges
     * @return string[]
     */
    private function buildFacetParts(array $filters, array $ranges): array
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
        return $parts;
    }

    private function getQueryParams(): array
    {
        if ($this->request instanceof HttpRequest) {
            return $this->request->getQuery()->toArray();
        }
        return [];
    }
}
