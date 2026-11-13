<?php

declare(strict_types=1);

namespace Elme\Search\Api\Data;

/**
 * Autocomplete answer (FE-22).
 */
final class SuggestResult
{
    /**
     * @param SuggestProduct[] $products
     * @param SuggestCategory[] $categories
     * @param string[] $queries
     */
    public function __construct(
        public readonly array $products = [],
        public readonly array $categories = [],
        public readonly array $queries = []
    ) {
    }
}
