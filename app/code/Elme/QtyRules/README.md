# Elme_QtyRules

Minimum and step quantity (MOQ) of a product: one service for templates and Alpine steppers, and one server-side check
behind every way into the cart.

## Where the MOQ is stored

Native stock item fields, per product, as on the old site. No Elme attribute:

- **MOQ**: `min_sale_qty` (admin: Advanced Inventory > Minimum Qty Allowed in Shopping Cart). The step is the MOQ, so
  MOQ 6 means 6, 12, 18.
- **Step other than the MOQ**, rare: `enable_qty_increments` + `qty_increments` win over the MOQ as the step.

## Reading the rules

`Model\QtyRules` is also a view model. Templates never read the stock item themselves:

```php
$qtyRules = $viewModels->require(\Elme\QtyRules\Model\QtyRules::class);
```

| Method | Returns | Use |
| --- | --- | --- |
| `getMinAndStep($product)` | `min`, `step` | steppers in block cached HTML |
| `getRules($product)` | `min`, `step`, `max`, `salable` | live data, it reads the salable qty: not for cached HTML |
| `getRulesForQuoteItem($item)` | as `getRules()` | cart lines; a configurable uses its simple product |
| `getRefusalMessage($id, $qty)` | message or `null` | any new check that has to say why |
| `formatQty($qty)` | `"6"`, not `"6.0000"` | showing a qty |

`max` is the highest valid multiple right now, `null` when nothing limits it. `salable` is false when no valid qty can
be ordered, for example 4 in stock with MOQ 6.

## Enforcement

`Plugin\EnforceQtyRulesPlugin` sits on `StockStateInterface::checkQuoteItemQty()`, which every quote item qty change goes
through: add, cart update, mini cart update, REST, GraphQL, reorder, and any new batch add that uses the quote (Quick
Order). The customer sees `This product is sold in multiples of 6`. Only a product whose own step differs from its MOQ
can also answer `The minimum order for this product is 6`. Admin orders are exempt, because the core validator ignores
the check in super mode.

Some paths need more, because core changes or saves the cart around the error:

- `Plugin\RefuseQtyBeforeAddPlugin`: refuses a requested qty before `Quote::addProduct()` touches the quote. Reorder saves
  the cart after an error, so without it a refused line stays in the cart or is added to a line already there.
- `Plugin\RefuseCartUpdatePlugin`: the cart page form rounds the typed qty and saves without a message.
- `Plugin\ShowReorderErrorsPlugin`: the reorder controller drops the errors, so a refused line would just be missing.

A line that became invalid after a MOQ change stays in the cart with the message and blocks checkout until corrected.

## Importing the values

No data patch: the values belong to the catalogue import and there is no Pronto feed yet. Whichever import loads the
~3,600 MOQ products sets these columns of the native product CSV (System > Import > Products):

```csv
sku,use_config_min_sale_qty,min_cart_qty,use_config_enable_qty_inc,enable_qty_increments
ABC-123,0,6,0,0
```

`min_cart_qty` is the import's name for `min_sale_qty`.

Read the values from the old site's `cataloginventory_stock_item` for Elme products with `min_sale_qty > 1`.

## Tests

- Unit: `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Elme/QtyRules/Test/Unit`
- End to end: `app/code/Elme/Checkout/Test/Playwright/qty-rules.spec.mjs`, on its own seed data (`seed-qty-rules.php`)
