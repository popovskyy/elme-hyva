<?php

declare(strict_types=1);

namespace Elme\Search\Model;

/**
 * Trade customers search by SKU (8,503 SKUs contain "#", others "." or "-"). Engine-neutral helpers.
 */
class SkuQuery
{
    /**
     * One token with a digit or SKU punctuation, e.g. "VG.228", "218AREDIN", "107BRE#".
     */
    public static function looksLikeSku(?string $value): bool
    {
        $value = trim((string)$value);
        return $value !== ''
            && !preg_match('/\s/', $value)
            && mb_strlen($value) >= 2
            && (bool)preg_match('/[0-9.#\-\/_]/', $value);
    }

    /**
     * "VG.228.ML" -> "vg228ml": letters and digits only, lower case.
     */
    public static function compact(string $value): string
    {
        return mb_strtolower((string)preg_replace('/[^\p{L}\p{N}]+/u', '', $value));
    }
}
