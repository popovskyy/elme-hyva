<?php

declare(strict_types=1);

namespace Elme\Search\Model\OpenSearch;

/**
 * Settings and mapping of elme_products_<store>. The document follows the target product model (PIM field names),
 * so a Search Service adapter maps 1:1. See README "Document".
 */
class IndexSchema
{
    /** Keyword fields that also get a full-text sub field ".text" (searchable and facetable). */
    public const KEYWORD_TEXT_FIELDS = [
        'product_type', 'colour_group', 'color', 'material', 'type_detail', 'style', 'features',
    ];

    /** from + size of a request may not go past this (index setting max_result_window) */
    public const MAX_RESULT_WINDOW = 50000;

    public function getBody(): array
    {
        $keywordText = [
            'type' => 'keyword',
            'fields' => ['text' => ['type' => 'text', 'analyzer' => 'elme_text']],
        ];
        $properties = [
            'id' => ['type' => 'integer'],
            'sku' => [
                'type' => 'keyword',
                'normalizer' => 'elme_lowercase',
                'fields' => [
                    // "VG.228.ML", "vg228ml" and "vg 228 ml" meet here
                    'compact' => ['type' => 'keyword', 'normalizer' => 'elme_sku_compact'],
                ],
            ],
            'name' => [
                'type' => 'text',
                'analyzer' => 'elme_text',
                'fields' => [
                    'sort' => ['type' => 'keyword', 'normalizer' => 'elme_lowercase'],
                    // whole words, not stemmed: autocomplete matches "din" / "dinin" as a prefix of "dining"
                    'prefix' => ['type' => 'text', 'analyzer' => 'elme_words'],
                ],
            ],
            'url_key' => ['type' => 'keyword', 'index' => false],
            'url' => ['type' => 'keyword', 'index' => false],
            'image' => ['type' => 'keyword', 'index' => false],
            'family_code' => ['type' => 'keyword'],
            'family_key' => ['type' => 'keyword'],
            'family_sort_order' => ['type' => 'integer'],
            'seats' => ['type' => 'keyword'],
            'width' => ['type' => 'float'],
            'depth' => ['type' => 'float'],
            'height' => ['type' => 'float'],
            'category_ids' => ['type' => 'integer'],
            'category_direct_ids' => ['type' => 'integer'],
            'category_paths' => ['type' => 'keyword'],
            'category_positions' => [
                'type' => 'nested',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'position' => ['type' => 'integer'],
                ],
            ],
            'price' => ['type' => 'float'],
            'news_from_date' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss||yyyy-MM-dd||strict_date_optional_time'],
            'made_to_order' => ['type' => 'boolean'],
            'condition_code' => ['type' => 'keyword'],
            'in_stock' => ['type' => 'boolean'],
            'visibility' => ['type' => 'integer'],
        ];
        foreach (self::KEYWORD_TEXT_FIELDS as $field) {
            $properties[$field] = $keywordText;
        }

        return [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
                'max_result_window' => self::MAX_RESULT_WINDOW,
                'analysis' => [
                    'char_filter' => [
                        'elme_strip_punctuation' => [
                            'type' => 'pattern_replace',
                            'pattern' => '[^\\p{L}\\p{N}]',
                            'replacement' => '',
                        ],
                    ],
                    'filter' => [
                        'elme_stem' => ['type' => 'stemmer', 'language' => 'minimal_english'],
                    ],
                    'normalizer' => [
                        'elme_lowercase' => ['type' => 'custom', 'filter' => ['lowercase', 'asciifolding']],
                        'elme_sku_compact' => [
                            'type' => 'custom',
                            'char_filter' => ['elme_strip_punctuation'],
                            'filter' => ['lowercase', 'asciifolding'],
                        ],
                    ],
                    'analyzer' => [
                        'elme_words' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'asciifolding'],
                        ],
                        'elme_text' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'asciifolding', 'elme_stem'],
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'dynamic' => false,
                'properties' => $properties,
            ],
        ];
    }
}
