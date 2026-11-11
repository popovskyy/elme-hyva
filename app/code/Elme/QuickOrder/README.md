# Elme_QuickOrder

The Quick Order cart is the only cart (BE-16, decision D14). The working cart is a list row per customer, sku + qty,
not the Magento quote: edits are instant and cheap, and nothing collects quote totals until the customer opens
checkout. There is no Magento cart page or mini-cart.

## Data

Table `quick_order_list`: `customer_id`, `name`, `items` (JSON `[{"sku": "...", "qty": 6}]`), one row per customer and
name. The cart is the list named `cart_list` (`Model\CartList::NAME`); named lists (BE-12) use the same table. Only sku
and qty are stored: name, image, price, stock and MOQ are read live, so catalogue changes never leave stale data.

## The pieces

| Class | Does |
| --- | --- |
| `Model\CartList` | Reads and writes a list as `sku => qty` |
| `Model\CartLines` | The one place that reads live row data: product, customer price (`CustomerPriceProviderInterface`), stock, MOQ (`Elme_QtyRules`) and why a row cannot be ordered now |
| `Model\CartEditor` | Every change: `add()` (add to cart) and `save()` (replace or merge). New or changed qty is checked; a refused row keeps its old qty |
| `Model\CartData` | The cart as JSON for the browser, with subtotal, GST, total (ex GST prices), `minimum_order` (BE-08) and `can_checkout` (rows available and MOV met) |
| `Model\QuoteBuilder` | Rebuilds the quote from `cart_list` |
| `CustomerData\QuickOrder` | Section `quick_order`: `summary_count` (sum of qty, the header badge), `items_count`, `lists_item_count` and `lists` (`[{id, name, item_count, skus}]`, for the hearts) |

## Flows

- **Add to cart** (tile, PDP, sticky bar): `Plugin\AddToCartListPlugin` takes over `/checkout/cart/add` for logged-in
  customers and adds to `cart_list`. The answer keeps core's shape, so FE-70's button works unchanged: ajax gets
  JSON, the result is in the messages section. A configurable stores the chosen simple product's sku; no qty is the
  MOQ.
- **Endpoints**, all login only, `no-store`, never a 200 on an error, guests get 401:
  - `GET /quick-order/cart/get`: `{success, cart: {items, totals, summary_count, items_count, can_checkout, minimum_order}}`
  - `POST /quick-order/cart/save`, JSON `{form_key, mode: "replace"|"merge", items: [{sku, qty}]}`:
    `{success, errors: {sku: message}, cart}`
  - `POST /quick-order/cart/availability`, JSON `{form_key, items}` (max 40): `{success, availability: {sku: {...}}}`
- **Browser**: `templates/js/cart-client.phtml` (on every page for logged-in customers) is `window.elmeQuickOrderCart`:
  `load()`, `setQty()` (saved 1 s after the last edit, the whole cart in one request), `remove()`, `flush()`. It
  dispatches `elme-qo-cart` and `elme-qo-count`. The header panel (FE-32) and the cart page (FE-33) build on it.
- **Cart page** (`/quick-order/cart`, FE-33): item table, MOV notice, totals (no freight), Proceed to checkout,
  empty state. `/checkout/cart` redirects here (`Observer\RedirectCartPage`). Head is `noindex, nofollow` with a
  self canonical. Coupon place is still open in D14 (see Not here yet).
- **Checkout**: one rule. `Plugin\RebuildQuoteOnCheckoutPlugin` rebuilds the quote from `cart_list` every time
  `/checkout` opens. Refused rows send the customer back to the cart page with their reason. No merge, no
  "hydrate if empty", no skip flag. Elme_Checkout then works on the quote as before. `Model\Validator\QuoteMatchesCart`
  refuses place order when the cart changed since checkout opened (another tab).
- **After the order**: `Observer\ClearCartAfterOrder` empties `cart_list` on `sales_model_service_quote_submit_success`.
  A failed payment or an abandoned checkout keeps it.

## Cache

The endpoint URLs are the same for every customer, and a shared cache (Varnish, Fastly) keys on the customer group,
not the customer. Every JSON answer carries `Cache-Control: private, no-store` and `Surrogate-Control: private,
no-store`; the cart page block is `cacheable="false"`. With Fastly, also pass `^/quick-order` in `vcl_recv`, as the old
site does (`ElmeLiving_QuickOrder/docs/fastly-no-cache.md` in m2-bart-group).

## Shopping lists (FE-73, Bart wishlist remake)

Magento wishlist stays off. Named rows in `quick_order_list` (any name except `cart_list`) are the customer's
shopping lists.

| Piece | Does |
| --- | --- |
| `Model\SavedLists` | List / create / rename / delete / merge named lists; preview thumbnails for My Shopping List |
| `GET /quick-order/list/getall` | `{success, lists, saved_skus, total_item_count}`, each list with `skus` and `preview` |
| `POST /quick-order/list/save` | JSON `{form_key, name, mode, items}` or legacy `list[name]` form body. `mode`: `merge` (default), `replace` (whole list), `add` (existing list, SKUs checked: "SKU not found."), `create` (empty list, a taken name is refused), `remove` (take the SKUs off an existing list). Answers with the getall payload |
| `POST /quick-order/list/rename` | JSON `{form_key, name, new_name}`, same duplicate rule as `create` |
| `POST /quick-order/list/delete` | JSON `{form_key, id}` |

List names are unique per customer with case ignored (unique key on `customer_id, name`); `create` and `rename`
never touch another list.
| `/quick-order/template` | My Shopping List page (account + header) |
| `templates/js/lists-store.phtml` | `window.elmeLists` (FE-78): the lists from section `quick_order`, shared by the hearts, the picker and the header badge; paints every `[data-elme-heart]` |
| List picker (`templates/add-to-list/modal.phtml`) | `elme:open-add-to-list-modal` or a click on `[data-elme-qo-add-to-list][data-sku]`. One step: a row saves, a ticked row removes, New list at the bottom |
| `/wishlist/*` | Redirects to `/quick-order/template` |

Section `quick_order` also exposes `lists_item_count` and `lists`, so a page knows the customer's lists without a
request. Opening a list into the Quick Order page is FE-40 / FE-44.

## Not here yet

- Reorder (`/sales/order/reorder`) still fills the quote, which `/checkout` replaces with `cart_list`: BE-14.
- Coupon: D14 left the field's place open (cart vs checkout). There is no quote until `/checkout` opens, so a cart
  coupon needs a stored code applied in `QuoteBuilder` (BE). Do not invent a cart coupon UI until Roman picks.

## Tests

- Unit: `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Elme/QuickOrder/Test/Unit`
- End to end: `app/code/Elme/Checkout/Test/Playwright/quick-order-cart.spec.mjs` (with the other checkout specs)
