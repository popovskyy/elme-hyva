---
name: elme-product-data
description: Use when a ticket reads, shows, stores, imports or filters product data on the new Elme Living instance - PDP (gallery, name, subtitle, specs, dimensions, care, labels), PLP tile, filters and facets, search index, structured data, product attributes and attribute sets, catalog import, anything that asks "where does this field come from" (PIM, Pronto, data warehouse, shipment service), "which attribute do I create", or what the PIM / Pronto item API can and cannot do. Also when an old template reads an M2 attribute and you need its replacement.
---

# Elme product data: where every field comes from

**This is the planned picture, not what runs today.** The PIM does not exist as a service yet, and
neither do its feeds into Magento, the DWH B2B reads or the Search Service. The backend team's
documents (PIM data specification and Pronto item API inventory, state of 9 Nov 2026, figures from
the prod dump of 15 Oct 2026) describe how it will look. `elme-business` still holds the customer,
price, order and delivery rules.

How to use it now:

- Today product data on this instance is ordinary Magento catalog data (admin, data patches, test
  fixtures). That is fine and nothing here blocks a ticket.
- Use the plan to choose the shape of what you build, so it does not have to be redone when the PIM
  arrives: field names and meaning, lists instead of numbered attributes, what may be empty, what
  will never be stored in Magento.
- Do not build a client, importer or sync for the PIM, DWH or Search Service unless a ticket asks
  for it, and do not wait for them either.
- Parts of the plan are working assumptions, marked **(assumption)**. Where one changes what a
  ticket builds, ask.

Below, "is" and "receives" describe the target.

## The systems

- **PIM** - the only place product data is created or edited. One record per item for all channels
  (EL = B2B, JL and SG = B2C). Nothing writes product data back from Magento.
- **This instance = "M2 B2B"** - will receive EL listings from the PIM: on publish, on every change
  to a published item, and as a full re-send on request. Once that runs, catalog data here is a
  copy, so do not design a feature around editing product fields in the Magento admin.
- **Pronto B2B** (Elme company) and **Pronto B2C** (JL/SG) - commercial and operational data. It does
  not pass through the PIM. This instance reads it from the data warehouse **DWH B2B**: price lists,
  item groups, contract and marketing prices, promo prices, MOQ / order rules, condition code.
- **Shipment Service** - stock, purchase orders, labels, quantities, ETA. **No stock data is stored in
  Magento**: the storefront asks the Shipment Service live. It also sends stock to the Search Service.
- **Search Service** - builds the search index from Magento (product data) and the Shipment Service
  (stock). Feeds, Klaviyo, Yotpo and other tools get product data from Magento **(assumption)**.
- **Cloudinary** - stays the media store. The PIM keeps the reference, roles, alt text and order.
- **Not in the target:** Pronto Avenue (the push that set status, MOQ, pack and carton fields in the
  old Magento) and the feasibility.cc ODBC proxy (old special-price cron). Do not port code that
  depends on either.

Shared items (the same SKU on EL and JL/SG, about 16,300 of the 25,293 EL products) live in Pronto
B2C only, so for them this instance reads prices, MOQ and condition code from **DWH B2C**; the exact
list is open. The Shipment Service adds JL distribution-centre stock to Elme stock for EL availability.

## Rules for anything you build here

They keep today's work compatible with the plan; none of them needs the PIM to exist.

1. **Use the PIM field, not the old attribute.** 377 old M2 attributes collapse into 74 fields.
   Look the old code up in `references/m2-attribute-map.csv` (verdict Keep / Merge / Derive /
   Not PIM / Drop and the field it maps to), then read the field in `references/fields.md`.
2. **Do not create an EAV attribute per spec row.** Display-only specs arrive as a key/value list
   whose rows and order depend on the product type (`specs.*`, `measurements`). Cartons are one
   ordered list, not `ctn1..ctn10`. Repeating groups are lists.
3. **Carry only what is filled.** An old attribute with no values in prod is not migrated even when
   an old template reads it (`details`, `faq`, `brochure_and_manuals`, `mattress_features`, `awards`,
   `art_style`, `video_url`, `viewa_id`). Do not port the block that showed it.
4. **Do not type what is derived.** `colour_group` (from colour), size bands (from dimensions),
   number of cartons, carton package type, the display dimension string and stock labels are computed.
5. **Global or per channel.** Global only: sku, name, images and their order, variant structure,
   dimensions, cartons, logistics facts. Scopable (can hold an EL value): description, short
   description, SEO fields, URL key, labels, visibility, launch date. Status is always per channel.
   Which fields are scopable is PIM configuration, so never hardcode that list.
6. **Status is not stock.** A product can be enabled with no stock, or have stock and be disabled.
   The Pronto condition code (A / C / D / I / T) drives availability and labels, never the status.
7. **Every field can be empty.** Only sku, name, product type, brand, description, dimensions,
   cartons and parcel class are required for an EL listing (proposal). Images are optional. Templates
   must render without a subtitle, short description, images, care text or any single spec row.
8. **URL keys are data.** 30% of visible products have a URL key that is not the slug of the name
   and it carries SEO history. Never regenerate a URL key from the name.

## What the EL storefront shows

| Page part | Field(s) | Notes |
|---|---|---|
| PDP title, PLP tile name | `name` | global, same as JL |
| Under the name | `subtitle` | filled on 40% of EL products |
| PDP gallery, PLP tile | `images` | ordered list with roles (base, small, thumbnail, hover, swatch) and alt text; average 6, maximum 23 per item; no image is possible |
| PDP gallery | `videos` | Cloudinary mp4 as gallery entries, 4% of EL products |
| PDP dimensions | `diagram_image` | the dimension drawing is its own field, not a gallery slide by rule |
| Badges | `labels` | per channel; EL uses Clearance today; New and Sale can become rules (`launch_date`, promo price) |
| Short description, Details | `short_description`, `description` | 82% filled; lower on beds (47%) |
| Care Instructions | `care_instructions` | 99% |
| Spec table | `sku`, `range`, `colour`, `size`, `material`, `assembly_info`, `specs.*`, `product_note`, `disclaimer` | rows per product type, see `references/spec-fill-el.md` |
| Dimensions | `dimensions`, `measurements` | one convention: width x depth x height (cm) + net weight (kg). The old EL data was stored as L x W x H: do not trust the old order |
| Colour siblings | `colour_super_sku` | colour is not a configurable axis on EL: colour variants are separate products grouped by this key |
| Size and other options | `size`, `variant_axes` | configurable axes on EL: size 1,516; chaise side 493; comfort (feel) 13 |
| Filters | `colour_group`, `material`, `type_detail`, `style`, `comfort`, `features`, `size_bands` | `size_bands` derived from dimensions |
| Reviews | `review_group` | SKUs that share Yotpo reviews |
| Price, MOQ | not PIM | DWH B2B, per customer, logged in only (`elme-business`) |
| Stock label, qty, ETA | not PIM, not in Magento | live request to the Shipment Service |

Not on EL: warranty tab, assembly service, click and collect, interstate flags, recycling code,
mattress facets and bundles, the carton tab (cartons reach this instance for freight rating only).

## Catalogue size

25,293 products are assigned to EL in prod: 23,267 simple and 2,024 configurable. About 76% are
made to order. 60 attribute sets (product types), top: Sofa 8,204; Bed 3,393; Artificial Plant
2,484; Sofa Cover 1,741; Bedhead 1,331; Sofa with Chaise 1,238; Vase 1,136. 3,766 products have a
minimum quantity above 1. Older notes in this repo that say "about 8,300 products, 147
configurables, axes colour and size" came from a staging snapshot and are superseded by these.

## Pronto item API in one paragraph

Today nothing can write an item to Pronto: the only proven API writes are sales orders, payments and
customers. Whether an item create/update API exists or is licensed is an open question for the Pronto
owner, so "the PIM creates the item in Pronto" has no route yet. Reads exist through the Pronto
Xi API and, richest, the data warehouse. Details, field map and traps (assembly price stored as
`'$120'` text, field tables that are wrong, one bad tag voids a whole post):
`references/pronto-item-api.md`.

## Decision and question ids

The backend documents use the replatform decision log (their D2, D14...D26) and question ids
(Q-031...Q-174). **Those D numbers are not the D1-D15 of `docs/frontend-tickets.md`.** Here their
D14 means "Avenue is not in the target" while ours means the Quick Order cart. Write
"replatform D14" when you quote one.

Open on their side and able to change a storefront ticket: dimension convention for old data;
which spec rows stay filterable on EL; who owns categories (probably Magento rules); made-to-order
label and lead time (flat 98 days or per supplier); the target label list; colour as separate
products or a real axis; which Pronto data shared items need from DWH B2C.

## References

- `references/fields.md` - the 74 fields: meaning, type, scope, EL fill, source for this instance,
  the old attributes each replaces, where the old sites show it.
- `references/m2-attribute-map.csv` - every old M2 product attribute: fill on EL, old PDP / filter
  use, verdict, PIM field.
- `references/spec-fill-el.md` - per product type, which spec attributes are filled on EL.
- `references/pronto-item-api.md` - what can be read from and written to Pronto.
- Originals from the backend team (not in git): `EL Replatform - PIM Data Specification.docx`,
  `EL Replatform - PIM Product Data.xlsx`, `EL Replatform - Pronto Item API.docx`. When a new
  version arrives, regenerate the three generated references from the sheet and re-read this file
  against the specification.
