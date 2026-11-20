<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Model;

/**
 * Builds the swatch axes of one product family. Pure logic, no Magento calls.
 *
 * For every value of an axis it picks the member to link to: the member with that value that keeps
 * the current product's value on every other axis. When no member keeps all of them, the value is
 * "unavailable" (shown crossed out) and links to the member that keeps the most of them.
 *
 * Input shapes:
 *  - $axes:    list of ['code', 'label', 'visual' => bool, 'labels' => [value => label], 'rank' => [value => int]]
 *              ('rank' = the attribute's option order, used after family_sort_order)
 *  - $members: [memberId => ['sku' => string, 'sort' => ?int, 'values' => [axisCode => string]]]
 */
class AxisBuilder
{
    /**
     * @param array<int, array{code: string, label: string, visual: bool, labels: array<string, string>, rank: array<string, int>}> $axes
     * @param array<int, array{sku: string, sort: ?int, values: array<string, string>}> $members
     * @return array<int, array{
     *     code: string,
     *     label: string,
     *     visual: bool,
     *     selectedLabel: string,
     *     options: array<int, array{value: string, label: string, selected: bool, available: bool, memberId: int, missing: string[]}>
     * }>
     */
    public function build(array $axes, array $members, int $currentId): array
    {
        if (!isset($members[$currentId]) || count($members) < 2) {
            return [];
        }

        $current = $members[$currentId]['values'];
        $codes = array_column($axes, 'code');
        $result = [];

        foreach ($axes as $axis) {
            $code = $axis['code'];
            $values = $this->valuesOf($code, $members);
            if (count($values) < 2) {
                continue;
            }

            $otherCodes = array_values(array_filter(
                $codes,
                static fn (string $other): bool => $other !== $code && ($current[$other] ?? '') !== ''
            ));

            $options = [];
            foreach ($this->orderValues($values, $members, $code, $axis) as $value) {
                $options[] = $this->buildOption($code, $value, $axis, $members, $currentId, $current, $otherCodes);
            }

            $selected = $current[$code] ?? '';
            $result[] = [
                'code' => $code,
                'label' => $axis['label'],
                'visual' => $axis['visual'],
                'selectedLabel' => $selected !== '' ? ($axis['labels'][$selected] ?? $selected) : '',
                'options' => $options,
            ];
        }

        return $result;
    }

    /**
     * @param string[] $otherCodes axes the current product has a value on, except this one
     * @param array<string, string> $current
     * @return array{value: string, label: string, selected: bool, available: bool, memberId: int, missing: string[]}
     */
    private function buildOption(
        string $code,
        string $value,
        array $axis,
        array $members,
        int $currentId,
        array $current,
        array $otherCodes
    ): array {
        $label = $axis['labels'][$value] ?? $value;

        if (($current[$code] ?? '') === $value) {
            return [
                'value' => $value,
                'label' => $label,
                'selected' => true,
                'available' => true,
                'memberId' => $currentId,
                'missing' => [],
            ];
        }

        $bestId = null;
        $bestScore = -1;
        $bestMissing = [];
        foreach ($members as $id => $member) {
            if (($member['values'][$code] ?? '') !== $value) {
                continue;
            }
            $missing = [];
            foreach ($otherCodes as $other) {
                if (($member['values'][$other] ?? '') !== $current[$other]) {
                    $missing[] = $other;
                }
            }
            $score = count($otherCodes) - count($missing);
            if ($score > $bestScore || ($score === $bestScore && $this->comesFirst($member, $members[$bestId]))) {
                $bestId = (int) $id;
                $bestScore = $score;
                $bestMissing = $missing;
            }
        }

        return [
            'value' => $value,
            'label' => $label,
            'selected' => false,
            'available' => $bestMissing === [],
            'memberId' => (int) $bestId,
            'missing' => $bestMissing,
        ];
    }

    /**
     * @return string[] distinct non-empty values of one axis
     */
    private function valuesOf(string $code, array $members): array
    {
        $values = [];
        foreach ($members as $member) {
            $value = (string) ($member['values'][$code] ?? '');
            if ($value !== '') {
                $values[$value] = $value;
            }
        }
        return array_values($values);
    }

    /**
     * family_sort_order first (lowest member order carrying the value), then colours by name and the
     * other axes by the attribute's option order (sizes Single, King Single, Double ... stay in order).
     *
     * @param string[] $values
     * @return string[]
     */
    private function orderValues(array $values, array $members, string $code, array $axis): array
    {
        $memberOrder = [];
        foreach ($members as $member) {
            $value = (string) ($member['values'][$code] ?? '');
            if ($value === '' || $member['sort'] === null) {
                continue;
            }
            $memberOrder[$value] = min($memberOrder[$value] ?? PHP_INT_MAX, $member['sort']);
        }

        usort($values, static function (string $a, string $b) use ($memberOrder, $axis): int {
            $bySort = ($memberOrder[$a] ?? PHP_INT_MAX) <=> ($memberOrder[$b] ?? PHP_INT_MAX);
            if ($bySort !== 0) {
                return $bySort;
            }
            $labelA = $axis['labels'][$a] ?? $a;
            $labelB = $axis['labels'][$b] ?? $b;
            if (!$axis['visual']) {
                $byRank = ($axis['rank'][$a] ?? PHP_INT_MAX) <=> ($axis['rank'][$b] ?? PHP_INT_MAX);
                if ($byRank !== 0) {
                    return $byRank;
                }
            }
            return strnatcasecmp($labelA, $labelB);
        });

        return $values;
    }

    /**
     * Tie-break between two members that keep as many axes: lower family_sort_order, then SKU.
     */
    private function comesFirst(array $candidate, array $best): bool
    {
        $sortA = $candidate['sort'] ?? PHP_INT_MAX;
        $sortB = $best['sort'] ?? PHP_INT_MAX;
        if ($sortA !== $sortB) {
            return $sortA < $sortB;
        }
        return strcmp($candidate['sku'], $best['sku']) < 0;
    }
}
