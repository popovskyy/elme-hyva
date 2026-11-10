---
name: elme-business
description: Use when building, porting or explaining any Elme Living feature that carries business rules - who the customers are (B2B retailers with an ABN), login-only shopping, per-customer prices from Pronto, ex-GST display, MOQ and minimum order value, "N in stock" and stock labels, Quick Order, recently ordered, account credit payment, Elme freight and the delivery date calendar, ERP/Pronto and other integrations - and when deciding what an old Elme module did on the Adobe Commerce site and what its replacement here must do.
---

# Elme Living business logic

## What Elme is

Elme Living is the **wholesale (B2B)** brand of Bart Group: artificial flora, homewares and furniture
sold to businesses, mostly retail shops. It ran as website 5 (`elme_living`, store 5, theme `Bart/el`)
inside the shared Adobe Commerce instance `~/Documents/BartGroup/m2-bart-group`, next to the retail
brands James Lane and Sleeping Giant. This project moves Elme to its **own Magento Open Source
instance**. Nothing below exists here yet: it is what the old site does and what has to be rebuilt.

**Status of the facts:** verified in the old codebase and a staging DB snapshot (2026-09/10).
Business rules are the target behaviour; module/table names are where to read the old implementation.
When a rule depends on a config switch, check the switch on the old site before relying on it.

**Where the data comes from on the new instance** (PIM, Pronto through the data warehouse, Shipment
Service, no Avenue, no stock in Magento) is in the `elme-product-data` skill. Where this file names
an old source (Avenue, feasibility.cc, MSI sources, Pronto hourly sync), that is how the old site
worked, not what to build.

## Customers

- Businesses registered **with an ABN** (ABN Lookup validation at registration, admin notification
  email) - `ElmeLiving_Customer`.
- Customer groups in use (2026-09-24): **Retailer 7,315** · General 439 · Wholesale 55 · Trade 7.
- **Shopping needs a login**: guest access to cart/checkout pages is redirected (`ElmeLiving_Checkout`
  `DisableGuestPage` / `DisableGuestAction`), blocks can be hidden for guests or for logged-in users
  (`ElmeLiving_ElmeLiving` `HideForGuest` / `HideForLoggedIn`). The full page cache varies by group, so
  test every price/stock UI logged in.
- The account area is split into B2B / B2C sections with own breadcrumbs
  (`ElmeLiving_CustomerAccountNavigation`).
- No trade-account sign-up (that is a James Lane feature), no Click & Collect, no POS.

## Prices

- **Shown ex GST** ("ex.GST" suffix in templates); cart subtotal display setting differs from retail.
  Australian prices are otherwise GST-inclusive, so every integration must say which one it sends.
- **Per-customer prices from the ERP (Pronto)**: tables `pronto_elme_cust_mkt_mapping` (sku or item
  group, Pronto customer id, discount type, discount, price) plus price lists
  (`pronto_elme_price_list*`, `pronto_elme_sku_group_mapping`, `pronto_elme_customer_mapping`).
  Applied by `ElmeLiving_CustomPricing` in the cart (quote item processor plugin), on the configurable
  PDP JSON, and by ajax `/custom_pricing/price/get` for search hits (prices load after render).
  **Catalog/FPC HTML never contains the customer's price** - it is fetched per customer.
- Special prices on the old site: daily cron from `api.feasibility.cc/sync/special-prices`; full
  Pronto price sync off. Target: price lists, contract, marketing and promo prices are read from the
  data warehouse DWH B2B; feasibility.cc is not used.
- **No tier prices, no multibuy "buy 2+" promo** on Elme (the multibuy is JL/SG only).

## Ordering rules

- **MOQ** (minimum sale qty > 1) on 3,766 products: qty steppers must jump in MOQ multiples. The old
  site got the value from the Avenue push; the target source is DWH B2B.
- **Minimum order value per customer**: customer attribute `minimum_order_value`, enforced before
  place order; Quick Order shows the remaining amount.
- **Quick Order** (`ElmeLiving_QuickOrder`, 56 theme files): bulk SKU entry, its own mini-cart, saved
  lists, "already ordered" history, minimum-order-value UI, batch add-to-cart validation. A core B2B
  page - customers reorder by SKU.
- **Recently ordered** (`ElmeLiving_RecentlyOrdered`): reorder page from past orders, MOQ aware.
- **Account credit** (`ElmeLiving_AccountCredit`): an "on account" payment method limited by the
  customer's balance (customer attribute `outstanding_balance`).

## Stock

Target: Magento stores no stock. Labels, quantity and ETA come from a live request to the Shipment
Service, which combines Elme stock with James Lane DC stock for shared items. The rules below are
what the customer must see; the MSI sources are the old implementation.

- Elme sells from its own warehouse **VTA** (Melbourne, VIC); **VBL** is the showroom (a store, not
  stock for sale).
- Shown as a **number**: "N in stock" = sellable qty (MSI, stock "B2B Stock" with sources `dropship`,
  `b2b`, `elme_hold`). `b2b` = VTA qty **plus James Lane DC stock for products also sold on JL**;
  `elme_hold` = qty held for open orders. Labels: ≥10 In Stock, 1-9 Low Stock, 0 = none.
- Stock and dock (incoming) dates come from **Pronto hourly** (separate Elme Pronto instance, not the
  JL/SG one). Hold stock comes from the data warehouse.
- Labels / ETA / delivery options on the old site come from the shared **shipment service**
  (storefront code `elme`), with Magento as fallback.
- Made-to-order lead time default 126 days on Elme. Elme listings DO show stock labels
  (JL/SG hide them).

## Delivery

- Carriers: `elmeliving_freight` (**freight = a percentage of the subtotal**, Amasty shipping rules),
  Fusedship live rates (Machship), free shipping. No retail carriers (Freight Metro/CAF), no assembly.
- **Preferred delivery date calendar** at checkout (Elme only): earliest date = slowest home-delivery
  lead time for the entered postcode (shipment service).

## Catalogue and search

- 25,293 products assigned to EL in prod (15 Oct 2026): 23,267 simple, 2,024 configurable. Configurable
  axes are size, chaise side and comfort. Colour is not an axis: colour variants are separate
  products grouped by `colour_super_sku`, and clicking a colour navigates to the sibling product
  (Bart_ConfigurableByAttribute). An earlier staging snapshot gave about 8,300 products and 147
  configurables; tickets that quote it describe that snapshot.
- Search and listings on the old site: Algolia (+ Algolia Recommend widgets: frequently bought
  together, trending, related - `ElmeLiving_AlgoliaRecommend`), Aheadworks layered navigation, Mirasvit
  SEO. Dropship partner range is off on Elme.

## Adobe Commerce features the old Elme relied on

Rebuild or drop each one deliberately - Open Source does not have them:
Content Staging (scheduled CMS/catalog changes), Customer Segments, Customer custom attributes UI
(ABN, minimum order value, outstanding balance are customer attributes), Page Builder **is** in Open
Source, dynamic blocks (Banner) are not, RMA, gift cards, reward points, store credit.

## Integrations to plan for

PIM (all product data, see `elme-product-data`), data warehouse DWH B2B (Pronto prices, MOQ,
condition code; DWH B2C for items shared with JL), Pronto B2B (orders, customers), Shipment Service
(stock, labels, ETA, rates, calendar), Search Service, Cloudinary, Klaviyo, Triple Whale, Zendesk
chat. Old-site sources that are gone: Pronto Avenue, feasibility.cc, the hourly Pronto stock sync.

## Where to read more

- Old code: `m2-bart-group/app/code/ElmeLiving/*`, Elme branches in `app/code/Bart/*`
  (`ScopeResolver::isElWebsite()` / `isB2BWebsite()`), theme `app/design/frontend/Bart/el`.
- Full cross-brand reference: the `bart` skill in `m2-bart-group/.claude/skills/bart`
  (`pricing.md`, `inventory.md`, `delivery.md`, `shipment-service.md`).
