# Elme_CustomerPricing

Per-customer prices from the Pronto price mappings, ex GST. Implements `Elme\LoginOnly\Api\CustomerPriceProviderInterface`
(di.xml preference), so the price endpoint `/customer-price/prices` (PLP, PDP, search, Quick Order) and the cart use the
same prices. Ported from `ElmeLiving_CustomPricing` and `Bart\Pronto\Model\Price\ElmePriceService` on the old site.

Customer prices never go into cacheable HTML: the storefront renders a placeholder and fetches them (FE-53). The PDP
Open Graph tag `product:price:amount` is dropped for logged-in customers too (`catalog_product_view.xml`).

## Rules

`Model\PriceCalculator`, per SKU. Every source that has a value is a candidate, the lowest wins, rounded to cents:

| Source | Table | Applies to |
| --- | --- | --- |
| List price | `pronto_elme_price_list.price` | everyone; also the "was" price (`regular`) |
| List promo | `pronto_elme_price_list.prom_price` when `is_prom_price_active = 1` | everyone |
| Region price and promo | `pronto_elme_price_list_mapping` | the customer's `region_group` |
| A1 | `pronto_elme_cust_mkt_mapping`, `discount_type = A1`, `price` | the customer's Pronto id |
| 20 | `discount_type = 20`: `price` (fixed) or else `discount` (% off the list price) | the customer's `marketing_flag` |
| 25 | `discount_type = 25`: `discount` (% off the list price) for an `item_group` | the customer's `marketing_flag`, SKUs in `pronto_elme_sku_group_mapping` |

- A mapping never raises the price above the list price (the list price is a candidate too).
- A SKU without a price list row takes Magento's price for the customer group (price, special price, catalog rules) as
  its list price and promo, so the catalogue keeps prices before the Pronto feed fills the list. Without any price the
  product is left out (no price shown, Magento's price in the cart).
- A configurable costs its cheapest enabled simple product; a configurable cart line costs the simple product chosen.
- Differences from the old resolver: the Pronto customer id is a string (it was cast to int); a SKU in several item
  groups gets the best group discount (the old code used only one of them); region price and region promo are both
  candidates (the old cart used the promo whenever it was active, even when higher).

## Customer link

Customer attribute `pronto_customer_id` (admin customer form and grid, not visible to the customer). The Pronto
customer's `marketing_flag` and `region_group` are in `pronto_elme_customer_mapping`, keyed by the Pronto id. A customer
without the attribute, or without mappings, pays the list price.

## Cart

`Observer\ApplyCustomerPrices` on `sales_quote_collect_totals_before` (storefront, REST, SOAP, GraphQL) sets each line's
`custom_price` to the customer price on every totals collection: add to cart, qty change, reorder, Quick Order and
checkout all reprice from the current mappings. Admin order creation keeps Magento's or the admin's price.

## Test data

No Pronto feed here (separate integration ticket). Load CSVs whose header names the table columns; rows are upserted on
the table's unique key, `--replace` empties the table first:

```bash
bin/clinotty bash -c "php app/code/Elme/CustomerPricing/Test/_files/seed-customers.php"
for t in customer price-list region-price sku-group cust-mkt customer-link; do
  bin/clinotty bash -c "bin/magento elme:customer-pricing:import $t app/code/Elme/CustomerPricing/Test/_files/$t.csv --replace"
done
```

| Type | Columns (required in bold) |
| --- | --- |
| `customer` | **pronto_customer_id**, marketing_flag, region_group |
| `price-list` | **sku**, **price**, prom_price, is_prom_price_active |
| `region-price` | **sku**, **region_group**, **price**, prom_price, is_prom_price_active |
| `sku-group` | **sku**, **group_code** |
| `cust-mkt` | **discount_type**, sku, item_group, pronto_customer_id, marketing_flag, discount, price |
| `customer-link` | **email**, pronto_customer_id (empty removes the link) |

The sample files give (password `Be07Prices!2026`):

| SKU | be07-a (C100, MKA, VIC) | be07-b (C200, MKB) | be07-none |
| --- | --- | --- | --- |
| FE53-LAMP (list 450) | 380 (A1) | 405 (20, 10%) | 450 |
| TEST-SOFA-1 (list 1299) | 1199 (region VIC) | 1039.20 (25, SOFAS 20%) | 1299 |
| FE53-VASE configurable | 99 (20, SAGE fixed) | 110 (SAND list promo) | 110 |
| BE01-100 (no Pronto row) | 100 (Magento) | 100 | 100 |

## Tests

`vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Elme/CustomerPricing/Test/Unit`
