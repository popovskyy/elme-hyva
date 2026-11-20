---
name: elme-product-data
description: Use when a ticket reads, shows, stores, imports or filters product data on the new Elme Living instance - PDP (gallery, name, subtitle, specs, dimensions, care, labels, product family swatches), PLP tile, filters and facets, search index, structured data, product attributes, attribute sets and groups, product type, catalog import, anything that asks "where does this field come from" (PIM, Pronto, data warehouse, shipment service), "which attribute do I create" or "what is its code and type", or what the PIM / Pronto item API can and cannot do. Also when an old template reads an M2 attribute and you need its replacement.
---

# Elme product data: where every field comes from

**This is the planned picture, not what runs today.** The PIM does not exist as a service yet, and
neither do its feeds into Magento, the DWH B2B reads or the Search Service. The backend team's
documents describe how it will look, in three layers:

1. PIM fields (product data model, 74 fields) - `references/fields.md`.
2. M2 target attributes: which EAV attributes, sets and groups this instance gets, and where each of
   the 377 old attributes goes - `references/m2-target/` (state of 12 Nov 2026).
3. Pronto item API - `references/pronto-item-api.md`.

Figures come from the prod dump of 15 Oct 2026. `elme-business` still holds the customer, price,
order and delivery rules.

How to use it now:

- Today product data on this instance is ordinary Magento catalog data (admin, data patches, test
  fixtures). That is fine and nothing here blocks a ticket.
- Use the plan to choose the shape of what you build, so it does not have to be redone when the PIM
  arrives: attribute codes and types, lists as JSON instead of numbered attributes, what may be
  empty, what will never be stored in Magento.
- When a ticket needs an attribute, create it with the target code, type and group from
  `references/m2-target/attributes.csv` (column `EL OS (B2B)` = Yes), not with the old code.
- Do not build a client, importer or sync for the PIM, DWH or Search Service unless a ticket asks
  for it, and do not wait for them either.
- Parts of the plan are working assumptions, marked **(assumption)**. Where one changes what a
  ticket builds, ask.

Below, "is" and "receives" describe the target.

## The systems

- **PIM** - the only place product data is created or edited. One record per item for all channels
  (EL = B2B, JL and SG = B2C). Nothing writes product data back from Magento.
- **This instance = "EL OS" / "M2 B2B"** - will receive EL listings from the PIM (event + pull, the
  consumer keeps its sync state in a table, not in EAV). Once that runs, catalog data here is a copy
  and **the PIM attributes are read-only in the Magento admin**, so do not design a feature around
  editing product fields there.
- **Pronto B2B** (Elme company) and **Pronto B2C** (JL/SG) - commercial and operational data. It does
  not pass through the PIM. This instance reads it from the data warehouse **DWH B2B**: list price,
  special price, item group, contract and marketing prices, MOQ / order rules, condition code.
- **Shipment Service** - stock, purchase orders, labels, quantities, ETA, freight rating (with the
  cartons from the PIM), click and collect. **No stock data is stored in Magento**: the storefront
  asks the Shipment Service live.
- **Search Service** - owns search, listings and **filters**. It reads the PIM directly, gets stock
  from the Shipment Service and collapses product families on listings. Size bands, stock filters
  and sort by sales exist only there. Feeds, Klaviyo, Yotpo and other tools get product data from
  Magento **(assumption)**.
- **Cloudinary** - stays the media store. The PIM keeps the reference, roles, alt text and order.
- **Not in the target:** Pronto Avenue (the push that set status, MOQ, pack and carton fields in the
  old Magento) and the feasibility.cc ODBC proxy (old special-price cron). Do not port code that
  depends on either.

Shared items (the same SKU on EL and JL/SG, about 16,300 of the 25,293 EL products) live in Pronto
B2C only, so for them this instance reads prices, MOQ and condition code from **DWH B2C**; the exact
list is open. The Shipment Service adds JL distribution-centre stock to Elme stock for EL availability.

## The M2 model on this instance (EL OS)

About 60 EAV attributes in **one attribute set, `Product`**, 12 groups. EL has one website, so every
attribute is global here. Principle: OOTB attribute first, then the old code when the meaning is
unchanged, a new code only when the meaning changes.

| Group | Attributes (EL OS) |
|---|---|
| Product Details | sku, name, status, visibility, price, tax_class_id |
| Classification | product_type, brand, range, barcode, news_from_date |
| Content | description, short_description, subtitle, special_care, disclaimer, product_note |
| Images | image / small_image / thumbnail / swatch_image, hover_image, media_gallery |
| Search Engine Optimization | url_key, meta_title, meta_description |
| Family & Variants | family_code, family_axes, color, colour_group, size, feel, chaise_side, orientation, family_sort_order |
| Facets | material, type_detail, style, shape, features, seats |
| Specifications | specifications, composition, fabric, assembly |
| Dimensions & Packaging | weight, width, depth, height, measurements, cartons, units_per_ctn |
| Fulfilment & Logistics | made_to_order, condition_code, stk_user_group_2, country_of_manufacture |
| Pricing & Promotions | special_price / special_from_date / special_to_date, item_group |
| Labels & Compliance | product_label, show_material_popup, google_product_category |

Code, type, source and the old attributes each one replaces: `references/m2-target/attributes.csv`.
What matters for a ticket:

- **`product_type` (select) is the classification, not the attribute set.** 90 types = today's
  attribute sets 1:1 (67 used on EL), each option keeps `legacy_attribute_set_id`. PDP template
  choice, spec labels, GA4 `item_category` and rules key on it. Codes:
  `references/m2-target/product-types.csv`.
- **No configurable products.** Every item is a simple product. `family_code` groups the variants of
  one design over every axis (colour, size, comfort, chaise side, orientation); `family_axes` is the
  ordered list of axis codes, the same on every member (e.g. `color,feel,chaise_side`);
  `family_sort_order` orders the members. Swatches navigate to the sibling product.
- **JSON attributes** (text, read-only): `specifications` = ordered `[{code, label, value}]`, about
  70 old display-only attributes, rows from the PIM template of the product type; `measurements` =
  `[{name, value, unit}]` (seat height, weight limit ...); `cartons` = ordered
  `[{length, width, height, weight}]`. Render rows from the JSON; do not create an attribute per row.
- **Facets are select / multiselect, not filterable.** They exist for the PDP spec table and as a
  fallback; filters come from the Search Service. Do not configure layered navigation on them.
- **Dimensions, one standard:** `width` = left-right, `depth` = front-back, `height` = vertical, all
  decimal cm; `weight` = assembled product, kg. Pronto names: width = fgLength, depth = fgWidth,
  height = fgDepth. The display string and labels (e.g. "Length" for rugs) come from the product
  type template. 27% of old items match no Pronto value (Q-194): do not trust the old order.
- **Repeated texts** (`special_care`, `disclaimer`) arrive as resolved plain text from a PIM snippet
  library.
- **Rules on `product_type` in config, not per item:** toppling warning (dressers, buffets, TV units,
  tallboys, consoles, bookcases, bar cabinets).
- **Not EAV here:** MOQ / qty step / backorders = stock item fields from DWH B2B (3,766 EL SKUs have
  MOQ > 1); contract and customer prices = tables (DWH B2B); categories = the PIM sends leaf
  categories per channel, the trees are owned by Magento, New / Sale / Clearance are rules; website
  assignment from the PIM channel assignment; stock, C&C, pack, buyer code, lead time = Shipment
  Service.
- B2C only (not on this instance): warranty, supplier_sku, dropship flags, assembly service,
  bundle_links, mattress facets, discounted / multibuy prices, warehouse_cost, items_sold,
  yotpo_group, pdp_cms_blocks.

## Rules for anything you build here

They keep today's work compatible with the plan; none of them needs the PIM to exist.

1. **Use the target attribute, not the old one.** Look the old code up in
   `references/m2-attribute-map.csv`: `m2_el_os` is the attribute it becomes here (`-` = not on
   this instance), `m2_how` says how (Same code, Merged, Key in specifications JSON, Not carried ...),
   `pim_field` points into `references/fields.md`.
2. **Do not create an EAV attribute per spec row** or per carton (`ctn1..ctn10`): use the JSON
   attributes above.
3. **Carry only what is filled.** An old attribute with no values in prod is not migrated even when
   an old template reads it (`details`, `faq`, `brochure_and_manuals`, `mattress_features`, `awards`,
   `art_style`, `video_url`, `viewa_id`, `shipping_and_returns`). Do not port the block that showed it.
4. **Do not type what is derived.** `colour_group` (from colour), size bands (Search Service),
   number of cartons, carton package type, the display dimension string and stock labels are computed.
5. **Status is not stock.** A product can be enabled with no stock, or have stock and be disabled.
   The Pronto condition code (A / C / D / I / T) drives availability and labels, never the status.
6. **Every field can be empty.** Only sku, name, status, visibility, price, tax class and
   product_type are required in M2. Templates must render without a subtitle, short description,
   images, care text, any measurement or any single spec row.
7. **URL keys are data.** 30% of visible products have a URL key that is not the slug of the name
   and it carries SEO history. Never regenerate a URL key from the name; empty = Magento builds it.
8. **Meta fields** have a fallback template when empty; `meta_keyword` is not carried.

## What the EL storefront shows

| Page part | M2 attribute(s) | Notes |
|---|---|---|
| PDP title, PLP tile name | `name` | global, same as JL |
| Under the name | `subtitle` | filled on 40% of EL products |
| PDP gallery, PLP tile | `media_gallery`, image roles, `hover_image` | average 6, maximum 23 per item; no image is possible; alt text per gallery entry; videos (Cloudinary mp4, 4%) and the dimension diagram are gallery entries with a role |
| Badges | `product_label` | EL uses Clearance today; New, Sale, Clearance are rules (`news_from_date`, special price, condition code) |
| Short description, Details | `short_description`, `description` | 82% filled; lower on beds (47%) |
| Care Instructions | `special_care` | 99% |
| Spec table | `sku`, `range`, `color`, `size`, `material`, `assembly`, `composition`, `fabric`, facets, `specifications`, `product_note`, `disclaimer` | rows per product type, see `references/spec-fill-el.md` |
| Dimensions | `width`, `depth`, `height`, `weight`, `measurements` | cm / kg, see the standard above |
| Family swatches | `family_code`, `family_axes`, `color`, `size`, `feel`, `chaise_side`, `orientation`, `family_sort_order` | every variant is its own simple product |
| Material popup | `show_material_popup` | 45% (upholstered types) |
| Toppling warning | rule on `product_type` | config list |
| Filters | not M2 | Search Service: colour group, material, type, style, comfort, features, size bands, stock |
| Price, MOQ | not PIM | DWH B2B, per customer, logged in only (`elme-business`) |
| Stock label, qty, ETA | not in Magento | live request to the Shipment Service |

Not on EL: warranty tab, assembly service, click and collect, interstate flags, recycling, mattress
facets and bundles, the Packaging Dimensions tab (on EL `cartons` is used for the order
"Product specifications" CSV and freight rating only), reviews (Yotpo is off on EL).

## Catalogue size

25,293 products are assigned to EL in prod: 23,267 simple and 2,024 configurable today. In the target
they are all simple products grouped by `family_code`. About 76% are made to order. 67 product types
on EL, top: Sofa 8,204; Bed 3,393; Artificial Plant 2,484; Sofa Cover 1,741; Bedhead 1,331; Sofa
with Chaise 1,238; Vase 1,136. 3,766 products have a minimum quantity above 1. Older notes that say
"about 8,300 products, 147 configurables, axes colour and size" came from a staging snapshot.

## Pronto item API in one paragraph

Today nothing can write an item to Pronto: the only proven API writes are sales orders, payments and
customers. Whether an item create/update API exists or is licensed is an open question for the Pronto
owner, so "the PIM creates the item in Pronto" has no route yet. Reads exist through the Pronto
Xi API and, richest, the data warehouse. Details, field map and traps (assembly price stored as
`'$120'` text, field tables that are wrong, one bad tag voids a whole post):
`references/pronto-item-api.md`.

## Decision and question ids

The backend documents use the replatform decision log (their D2, D14...D29), simplifications
S-01...S-22 and question ids (Q-031...Q-196). **Those D numbers are not the D1-D15 of
`docs/frontend-tickets.md`.** Here their D14 means "Avenue is not in the target" while ours means the
Quick Order cart. Write "replatform D14" when you quote one. The reasoning behind each M2 change is in
`references/m2-target/simplifications.csv` (S-xx) and `logic-check.csv` (every removed attribute, the
code that read it and the decision).

Open on their side and able to change a storefront ticket: Q-194 which dimension copy wins for the
27% that conflict; Q-193 product_set clean-up by the Data Team; made-to-order label and lead time
(flat 98 days or per supplier); the target label list; which Pronto data shared items need from
DWH B2C.

## References

- `references/fields.md` - the 74 PIM fields: meaning, type, scope, EL fill, source for this
  instance, the old attributes each replaces, where the old sites show it.
- `references/m2-target/` - the M2 target model, one CSV per tab of the backend sheet:
  `attributes.csv` (target attributes, then non-EAV data), `attribute-sets.csv`,
  `attribute-groups.csv`, `product-types.csv` (old set id -> product_type code, counts per site),
  `simplifications.csv`, `migration-map.csv` (every old attribute -> target per instance),
  `logic-check.csv`, `readme.csv` (decisions of 12 Nov 2026).
- `references/m2-attribute-map.csv` - every old M2 product attribute: fill on EL, old PDP / filter
  use, verdict, PIM field, target attribute on this instance.
- `references/spec-fill-el.md` - per product type, which old spec attributes are filled on EL.
- `references/pronto-item-api.md` - what can be read from and written to Pronto.
- Originals from the backend team: Google Sheet "EL Replatform - M2 Target Attribute Model"
  (https://docs.google.com/spreadsheets/d/1X2dp0HQf5ULmHMJi7fuF9lZl4H98WBEmQgDzcyDL2Yo), and, not
  in git, `EL Replatform - PIM Data Specification.docx`, `EL Replatform - PIM Product Data.xlsx`,
  `EL Replatform - Pronto Item API.docx`. When a new version arrives: export the sheet as xlsx,
  write each tab to `references/m2-target/<tab>.csv` (ASCII only), refresh the `m2_el_os` /
  `m2_how` columns of `m2-attribute-map.csv` from `migration-map.csv`, and re-read this file.
