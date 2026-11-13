<?php

declare(strict_types=1);

namespace Elme\Search\Model;

/**
 * Trade customers search by SKU (many contain "#", others "." or "-"). Engine-neutral helpers.
 *
 * looksLikeSku is only a cheap candidate filter ("l-shaped", "3-seater" also match). Collapse and SKU listing
 * are decided after OpenSearch confirms a SKU or SKU prefix exists (see ProductSearch / Client::hasSkuMatch).
 */
class SkuQuery
{
    /**
     * One token with a digit or SKU punctuation, e.g. "VG.228", "218AREDIN", "107BRE#", "Z-ARDTBL".
     * Not proof it is a SKU: "l-shaped" and "3-seater" also match.
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
