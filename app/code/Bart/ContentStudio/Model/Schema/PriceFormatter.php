<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Schema;

/**
 * `$3,999` mask: dollar, integer digits, thousands commas.
 *
 * The admin input (`view/base/web/js/editor/lib/price.js`) and the storefront
 * template must emit the same string from the same stored value. Cents are
 * dropped on purpose - `\D` alone would turn `3999.00` into `$399,900`.
 */
class PriceFormatter
{
    /**
     * A trailing separator plus one or two digits. That is the shape cents
     * take - and also the shape a thousands group takes while it is being
     * typed, or half-deleted.
     */
    private const CENTS_PATTERN = '/^(.*)([.,])(\d{1,2})$/';

    /** What the mask itself emits: 1-3 digits, then groups of exactly three. */
    private const GROUPED_PATTERN = '/^\d{1,3}(?:,\d{3})*$/';

    /**
     * @param mixed $raw Stored or typed value. Non-scalars are treated as empty.
     * @return string
     */
    public static function format(mixed $raw): string
    {
        $source = is_scalar($raw) ? (string)$raw : '';
        $withoutCents = self::dropCents($source);
        $digits = preg_replace('/\D+/', '', $withoutCents) ?? '';
        $digits = preg_replace('/^0+(?=\d)/', '', $digits) ?? $digits;

        if ($digits === '') {
            return '';
        }

        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $digits) ?? $digits;

        return '$' . $grouped;
    }

    /**
     * `.` is always the decimal point here - the mask never emits one, so
     * nothing carrying it came from us. A comma is genuinely ambiguous:
     * `3999,00` is a pasted European price, while `$1,25` is `$1,250` with its
     * last digit just deleted. Reading the second as cents ate the entire
     * thousands group on a single Backspace (`$1,250` became `$1`), so a comma
     * counts as a decimal point only when what precedes it is not already
     * grouped.
     *
     * @param string $source
     * @return string
     */
    private static function dropCents(string $source): string
    {
        if (preg_match(self::CENTS_PATTERN, $source, $match) !== 1) {
            return $source;
        }

        $head = preg_replace('/[^\d,]/', '', $match[1]) ?? '';

        if ($match[2] === ',' && preg_match(self::GROUPED_PATTERN, $head) === 1) {
            return $source;
        }

        return $match[1];
    }
}
