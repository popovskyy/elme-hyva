# Elme_Search (BE-24)

Product search, listings and facets come through one interface of our own, `Elme\Search\Api\ProductSearchInterface`.
For now our own OpenSearch index answers it. Later the backend **Search Service** answers it, and nothing else in
the storefront changes.

```
storefront (category page, search page, FE-21/22/23)
        |  SearchRequest / SearchResult / SuggestResult (plain PHP objects)
ProductSearchInterface  --di.xml preference-->  Model\OpenSearch\ProductSearch   (today)
                                               Model\SearchService\ProductSearch (later: new class + 1 line)
```

## Switch it on

`Stores > Configuration > Elme > Product Search > Listings and search from` (`elme_search/general/engine`):

| Value | Behaviour |
|---|---|
| `magento` (default) | Magento catalog search. Nothing changes compared with no module. |
| `opensearch` | The category page and the search results page take product ids, order and total from `ProductSearchInterface`. Facets are available through `ViewModel\SearchFacets`. |

First build the index with `bin/magento elme:search:reindex`, then switch the setting and run `bin/magento cache:flush`.

The config value only says *whether* the storefront uses `ProductSearchInterface`. *Which* engine answers it is set
in `etc/di.xml` only:

```xml
<preference for="Elme\Search\Api\ProductSearchInterface" type="Elme\Search\Model\OpenSearch\ProductSearch"/>
```

## The contract

`search(SearchRequest $request): SearchResult` and `suggest(string $query, int $limit, int $storeId): SuggestResult`.
If the engine cannot answer, both throw `SearchUnavailableException`.

### SearchRequest

| Field | Type | Meaning |
|---|---|---|
| `storeId` | int | store view (one index per store) |
| `query` | ?string | search text; empty = category listing |
| `categoryId` | ?int | only products in this category (anchor categories include their children) |
| `filters` | `array<code, string[]>` | multi / boolean facet values; OR inside a facet, AND between facets. `in_stock => ['1']` |
| `ranges` | `array<code, {min: ?float, max: ?float}>` | range facets (`width`, `depth`), cm, bounds included |
| `sort` | `relevance` \| `position` \| `name` \| `price` \| `newest` | `position` = category position, `newest` = `news_from_date` |
| `direction` | ?`asc`\|`desc` | null = default of the sort: relevance desc, position asc, name asc, price asc, newest desc |
| `page`, `pageSize` | int | 1-based page |
| `collapseFamilies` | bool | default true: one item per `family_code`. The storefront sends false for SKU-like queries (see Relevance) |

### SearchResult

| Field | Type | Meaning |
|---|---|---|
| `hits` | `SearchHit[]` (`productId`, `sku`, `familyCode`) | one page, in order. For a family, the member shown is the best one for the sort and always one that passes the filters |
| `total` | int | number of items over all pages (families count once) |
| `facets` | `Facet[]` | in config order; a facet with nothing in this result is left out |

`Facet`: `code`, `label`, `type` (`multi` \| `range` \| `boolean`), `options` (`FacetOption`: `value`, `label`,
`count`, `selected`), `min` / `max` (range: bounds of the current result), `selectedMin` / `selectedMax`.
Counts are disjunctive: each facet is counted with the other facets' selections applied, not its own, so the
options of a facet keep their counts while one of them is selected. Counts count items (families once).

### SuggestResult (FE-22)

`products` (`id`, `sku`, `name`, `url`, `image`; no price: Elme prices are per customer), `categories`
(`id`, `name`, `url`: categories that hold matching products), `queries` (a spelling correction, then the types of
the matching products). Autocomplete is typed letter by letter, so the last word also matches as a prefix of a
name word: "din" finds "Dining", "ares b" finds "Ares Buffet".

### Relevance (UX-05)

1. Exact SKU (`VG.228.ML`, `218AREDINW210B#`), then the same SKU without punctuation (`vg228ml`, `zardtbl` for
   `Z-ARDTBL`), then a SKU prefix
   (only for one-word queries with a digit or `. # - / _`).
2. Then what the item is: `product_type` > `type_detail` > `name`, then colour, material, style, features. All
   words must match (`operator: and`). For several words, the exact phrase in the name gets a boost.
3. Typo tolerance only for longer words: no edits up to 4 letters, 1 edit for 5-7, 2 from 8 (`AUTO:5,8`). So
   "vase" never matches "Vale"; "tabel" finds "table".
4. Visibility: search uses "Search" + "Catalog, Search", listings use "Catalog" + "Catalog, Search".
5. SKU-like queries are not collapsed, so a trade customer who types `218AREDIN` sees every matching SKU.
6. Facets follow the result: only facets and options that occur in it (a vase search shows no bed facets).

## Mapping for a future Search Service adapter

The document of our index already uses the target field names (`elme-product-data`), so the Search Service adapter
should only rename parameters. The Search Service column is a proposal for the backend team to review.

| ProductSearchInterface | OpenSearch adapter today | Search Service (proposal) |
|---|---|---|
| `storeId` | index alias `elme_products_<storeId>` | `channel=EL` (+ store / locale) |
| `query` | bool query: SKU term / prefix + multi_match (see Relevance) | `q` |
| `categoryId` | `term category_ids` (anchor ids in the doc) | `category` (Magento category id; or PIM leaf category + tree) |
| `filters[code]` | `terms <code>` in `post_filter` | `filter[<code>][]=v1&filter[<code>][]=v2` (labels contain commas) |
| `filters[in_stock]` | `term in_stock` (Magento stock today) | `filter[in_stock]=1` (Shipment Service stock) |
| `ranges[code]` | `range <code> gte/lte` | `range[<code>]=min..max` (or size bands) |
| `sort` / `direction` | `_score`; nested `category_positions.position`; `name.sort`; `price`; `news_from_date` | `sort=relevance\|position\|name\|price\|newest`, `dir` |
| `page` / `pageSize` | `from` / `size` | `page` / `size` |
| `collapseFamilies` | `collapse.field = family_key` | `collapse=family` |
| `SearchResult.hits` | `hits.hits[]._source.id / sku / family_code` | `items[].id / sku / family_code` |
| `SearchResult.total` | `aggs._total.items` (cardinality of `family_key`) or `hits.total` | `total` (families collapsed) |
| `Facet` multi / boolean | `aggs.facet_<code>.values` terms (+ cardinality per bucket) | `facets[]` with `options[].value / count` |
| `Facet` range | `aggs.facet_<code>.values` stats | `facets[]` with `min / max` |
| `suggest()` | same query, size = limit, terms on `category_direct_ids` and `type_detail`, term suggester on `name`; plus `match_bool_prefix` on `name.prefix` (last word as a prefix) | `/suggest?q=&limit=` |

Facet values are option **labels** today (`Green`, `Dining Table`). If the Search Service uses option codes, the
adapter returns codes as `value` and labels as `label`; the URLs change, the contract does not.

### Document (`elme_products_<store>`)

One document per enabled, visible product of the store's website (`Model\Indexer\DocumentBuilder`). Families are
**not** merged in the index; they collapse at query time.

| Field | Type | Source today (Magento) | Target source |
|---|---|---|---|
| `id`, `sku` (+ `sku.compact`) | integer, keyword | entity | PIM `sku` |
| `name` (+ `name.sort`, `name.prefix`) | text, keyword, text not stemmed | `name` | PIM |
| `url_key`, `url` | not indexed | `url_key`, URL rewrite | PIM / Magento |
| `image` | not indexed | `small_image` | Cloudinary via PIM |
| `product_type` | keyword + text | `product_type` (not created yet) | PIM |
| `family_code`, `family_sort_order` | keyword, integer | FE-28 attributes | PIM |
| `family_key` | keyword | `f:<family_code>` or `p:<id>` | collapse key |
| `color`, `colour_group` | keyword + text | `color`; `colour_group` falls back to the colour label until the attribute exists | PIM (colour_group derived) |
| `material`, `type_detail`, `style`, `features` | keyword + text (arrays for multiselect) | attributes (style / features not created yet) | PIM facets |
| `seats` | keyword | `seats` (not created yet) | PIM |
| `width`, `depth`, `height` | float, cm | first number of the attribute | PIM |
| `category_ids`, `category_direct_ids`, `category_paths`, `category_positions[{id, position}]` | integer / keyword / nested | `catalog_category_product_index_store<N>` | Magento category trees + PIM leaf categories |
| `price` | float | `price`, else the guest index price (sort only, never a customer price) | DWH B2B list price |
| `news_from_date` | date | `news_from_date` | PIM |
| `made_to_order` | boolean | `made_to_order` (not created yet) | PIM |
| `condition_code` | keyword | `condition_code` (not created yet) | DWH (Pronto) |
| `in_stock` | boolean | Magento stock status, one private method (`getStockStatuses`) | Shipment Service (BE-23 `StockProviderInterface`) |
| `visibility` | integer | `visibility` | PIM channel assignment |

Field => attribute code mapping is in `etc/di.xml` (`DocumentBuilder` argument `fieldMap`). Attributes that do
not exist yet are skipped and appear once they are created.

## Facets: `etc/elme_search_facets.xml`

Which facets, their labels, type, order, size and option order. Other modules can add or change facets with their
own `etc/elme_search_facets.xml` (merged by `code`, `disabled="true"` removes one). The adapter has no facet code
in it. Start set: colour_group, material, type_detail, style, features, seats, width / depth ranges, in stock.

## Storefront wiring

`Plugin\UseProductSearch` (frontend only) wraps `Magento\Search\Api\SearchInterface::search()` for the requests
`catalog_view_container` (category) and `quick_search_container` (search page):

1. `Model\Storefront\RequestFactory` builds the `SearchRequest` from Magento's criteria (category, query text,
   toolbar sort, page, page size) plus the facet parameters of the URL:
   - select / multiselect: the option label as it is, one value per parameter: `?colour_group=Green`, several
     values as an array `?colour_group[]=Green&colour_group[]=Blue`. A scalar value is never split: labels
     contain commas ("Bed Frame, Storage Bed"), so `?type_detail=Bed%20Frame%2C%20Storage%20Bed` is one value.
   - boolean: `?in_stock=1`
   - range: `?width=50-120` (`50-` / `-120` for open bounds).
2. The answer goes back to Magento as its own search result (page ids + total). The product collection loads those
   ids in that order, so the toolbar, pager and product list templates stay as they are.
   A page past the end (`?p=99`) is asked again for the last page, so it lists the last page like Magento.
3. Magento's layered navigation gets empty buckets (it shows no filters). Facets come from
   `Elme\Search\ViewModel\SearchFacets` (`isActive()`, `getFacets()`, `getTotal()`, `getSelection()`); render it
   after the product list has loaded (FE-21 / FE-23).
4. Errors: if `ProductSearchInterface` throws (OpenSearch down, index missing), the page uses Magento catalog search
   and one error per request goes to `var/log/system.log` (`Elme_Search: ProductSearchInterface failed ...`).
   When the whole `elme_opensearch` container is down, Magento's own catalog search fails too (core behaviour).

## Indexing

- Indexer `elme_search_products` (`bin/magento indexer:info`): full, by ids, mview (`etc/mview.xml` subscribes to
  product entity tables, websites, category products, stock items).
- `bin/magento elme:search:reindex [--store=1]`: builds `elme_products_<store>_v<time>`, swaps the alias
  `elme_products_<store>`, deletes the old index. The storefront never reads a half-built index.
- "Update by Schedule" is the mode on every instance (data patch `SetIndexerScheduled`): the `index` cron runs the
  mview. Keep it: only the mview sees stock after an order, imports and price reindexes.
  "Update on Save" still works for admin edits: product save / delete, category save (products and positions) and
  mass actions update the affected documents right away. A failure while saving is logged, never thrown.
- Category moved, enabled / disabled, anchor changed: after Magento's "Category Products" indexer has run,
  `Plugin\InvalidateOnCategoryReindex` marks our indexer invalid and the cron rebuilds it (within a minute or two).
  In "Update by Schedule" that is after every category save.
- An update during a full rebuild is written to the live index and to the one being built, so it survives the swap.
- A page past `max_result_window` (50,000 items) asks OpenSearch for no hits, so the storefront lists the last page.
- Connection: Magento's catalog search connection unless `elme_search/opensearch/hostname` / `port` are set;
  timeout `elme_search/opensearch/timeout` (5 s, connect 2 s); index prefix `elme_search/opensearch/index_prefix`.

## Adding the Search Service

1. `Model/SearchService/ProductSearch.php implements ProductSearchInterface`: map the request and response with the
   table above. Throw `SearchUnavailableException` on any transport or response error.
2. Change the preference in `etc/di.xml`.
3. Remove the indexer (`etc/indexer.xml`, `etc/mview.xml`, `Model/Indexer`, observers, mass-action plugin), the
   OpenSearch adapter and `elme:search:reindex`: the Search Service builds its own index from the PIM.
4. The storefront wiring, `elme_search_facets.xml` (labels, order) and `SearchFacets` stay.

## Debugging and tests

```
bin/clinotty bash -c "bin/magento elme:search:query 'vase'"
bin/clinotty bash -c "bin/magento elme:search:query --category=11 --filter='type_detail=Dining Table' --range=width=200-220"
bin/clinotty bash -c "bin/magento elme:search:query 'VG.228' --suggest"
bin/clinotty bash -c "vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Elme/Search/Test/Unit"
```

Unit tests cover the query builder (filters, ranges, sorts, SKU boosts, collapse, disjunctive facets), the response
mapper (hits, totals, facets) and the storefront request mapping.

## Known gaps

- `product_type`, `colour_group`, `style`, `features`, `seats`, `made_to_order`, `condition_code` do not exist on
  this instance yet; their facets stay empty until the attributes are created and the index is rebuilt.
- Stock is Magento's stock status until BE-23 replaces `DocumentBuilder::getStockStatuses()`.
- Facet values are labels (one store view, English).
- No synonyms, no merchandising rules, no sort by sales (Search Service features).
