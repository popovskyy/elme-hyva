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
| `Model\CartData` | The cart as JSON for the browser, with subtotal, GST and total from the server (ex GST prices) |
| `Model\QuoteBuilder` | Rebuilds the quote from `cart_list` |
| `CustomerData\QuickOrder` | Section `quick_order`: `summary_count` (sum of qty, the header badge) and `items_count` |

## Flows

- **Add to cart** (tile, PDP, sticky bar): `Plugin\AddToCartListPlugin` takes over `/checkout/cart/add` for logged-in
  customers and adds to `cart_list`. The answer keeps core's shape, so FE-70's button works unchanged: ajax gets
  JSON, the result is in the messages section. A configurable stores the chosen simple product's sku; no qty is the
  MOQ.
- **Endpoints**, all login only, `no-store`, never a 200 on an error, guests get 401:
  - `GET /quick-order/cart/get`: `{success, cart: {items, totals, summary_count, items_count, can_checkout}}`
  - `POST /quick-order/cart/save`, JSON `{form_key, mode: "replace"|"merge", items: [{sku, qty}]}`:
    `{success, errors: {sku: message}, cart}`
  - `POST /quick-order/cart/availability`, JSON `{form_key, items}` (max 40): `{success, availability: {sku: {...}}}`
- **Browser**: `templates/js/cart-client.phtml` (on every page for logged-in customers) is `window.elmeQuickOrderCart`:
  `load()`, `setQty()` (saved 1 s after the last edit, the whole cart in one request), `remove()`, `flush()`. It
  dispatches `elme-qo-cart` and `elme-qo-count`. The header panel (FE-32) and the cart page (FE-33) build on it;
  `templates/cart/page.phtml` is the plain page until FE-33.
- **Cart page**: `/checkout/cart` redirects to `/quick-order/cart` (`Observer\RedirectCartPage`).
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

## Not here yet

- Reorder (`/sales/order/reorder`) and wishlist "add to cart" still fill the quote, which `/checkout` replaces with
  `cart_list`: BE-14 and FE-44 move them to the cart.
- Coupon: applied in checkout on the quote, as on the old site.

## Tests

- Unit: `vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Elme/QuickOrder/Test/Unit`
- End to end: `app/code/Elme/Checkout/Test/Playwright/quick-order-cart.spec.mjs` (with the other checkout specs)
