# PIM fields (target product data model)

One row per field the PIM holds. `M2 B2B gets it from`: PIM = sent by the PIM on publish; `per channel` = the EL value of a scopable field; DWH B2B = this instance reads it from the Pronto data warehouse, not from the PIM; `-` = this instance does not receive it. EL fill = share of the 25,293 EL products with a value (prod dump 2026-10-15). The M2 attribute each field lands in on this instance: `m2-target/attributes.csv` (column `PIM field`).


## Identity

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `sku` | Item code = Pronto stock code | string, unique | Global | n/a | No | PIM | sku | EL: PDP spec row "SKU" and on the PLP tile (guest). |
| `name` | Product name | text | Global | 100% | Allowed (rare today) | PIM, per channel | name | All sites: PDP title, PLP tile. |
| `brand` | Brand | option (controlled list) | Global | 99% | No | PIM | brand, facebook_brand |  |
| `barcode` | EAN-13 / APN | string | Global | n/a | No | PIM | (not in M2) |  |
| `product_type` | What the item is (Armchair, Bed - Base, Rug ...); = today's attribute sets 1:1, each with legacy_attribute_set_id | option (controlled list, 90; codes in `m2-target/product-types.csv`) | Global | 84% | No | PIM | product_set |  |
| `product_structure` | Simple only: no configurable products in either instance (replatform 2026-11-12); kits are a Pronto BOM | structure | Global | n/a | No | PIM | type_id, super links, is_kit_component | JL: family configurator on the PDP. |
| `channel_assignment` | Which channels sell the item (EL / JL / SG) and enabled flag per channel | list of {channel, enabled} | Global | 100% | Yes (by definition) | PIM (EL listing) | status, website_ids |  |
| `visibility` | Catalog / search / not visible individually | option | Global | 100% | Yes | PIM, per channel | visibility |  |
| `url_key` | URL key | string | Global | 100% | Yes (store overrides: JL 2,631, SG 247, EL 138 rows) | PIM, per channel | url_key | All sites: product URLs. |
| `supplier_sku` | Supplier item code; for DS142 twins the EL item code (temporary) | string | Global | 4% | No | - | supplier_sku |  |
| `launch_date` | Date the item goes live / counts as New | date | Global | 8% | Allowed | PIM, per channel | publishing_date |  |

## Content

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `description` | Long description | rich text | Global | 82% | Yes (15.7k shared SKUs differ) | PIM, per channel | description | All sites: PDP "Details". Prod fill is low on Bed sets (EL 47%, JL/SG 32%). |
| `short_description` | Short description / overview | text | Global | 82% | Yes (15.7k shared SKUs differ) | PIM, per channel | short_description |  |
| `subtitle` | Subtitle under the name | text | Global | 40% | Allowed | PIM, per channel | subtitle |  |
| `care_instructions` | Care instructions | rich text | Global | 99% | No | PIM | special_care | EL: PDP tab "Care Instructions"; JL/SG: tab "Care". |
| `warranty` | Warranty text | rich text | B2C | 89% | No | - | warranty | JL/SG: PDP tab "Warranty". EL: no warranty tab (confirmed). |
| `disclaimer` | Disclaimer | text | Global | 65% | No | PIM | disclaimer |  |
| `product_note` | Product note (label today: Product Note) | text | Global | 12% | No | PIM | postage_handling | EL: PDP spec row "Product Note". |
| `seo` | Meta title / meta description | {title, description} | Global | 16% | Yes | PIM, per channel | meta_title, meta_description |  |

## Media

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `images` | Ordered gallery with roles (base, small, thumbnail, hover, swatch) and alt text | list of {asset, roles[], position, alt} | Global | 100% | Yes (order differs on 1,342 shared SKUs) | PIM, per channel | image, small_image, thumbnail, hover_image, swatch_image, *_label, media_gallery | All sites: PDP gallery and PLP tile. |
| `diagram_image` | Dimension diagram image | asset | Global | n/a | Allowed | PIM | image_diagram |  |
| `videos` | Product videos | list of asset / URL | Global | 4% | Allowed | PIM | media gallery entries of type external-video (Cloudinary mp4) | Videos: shown in the PDP gallery, e.g. JL willow-bed-view-steel-adjustable-base.html (Cloudinary mp4, checked 2026-10-28). |

## Variants & family

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `colour` | Colour name (one controlled list) | option (~760) | Global | 99% | No | PIM | color, colour | All sites: PDP spec "Colour"; JL/SG PLP tile swatches. |
| `colour_group` | Colour group for filters (Grey, Blue ...) | option (derived from colour) | Global | 90% | No | PIM | colour_group | All sites: category filter colour_group and Algolia facet. |
| `colour_super_sku` | Groups the variants of one design over every axis; lands in M2 as `family_code` (replaces colour_super_sku and mattress_super_sku) | string | Global | 65% | No | PIM | colour_super_sku |  |
| `size` | Size (configurable axis) | option (~1,250) | Global | 35% | No | PIM | size, rug_size | JL/SG: category filter, PLP tile size label (SG "Various sizes available"); EL: PDP spec "Size". |
| `variant_axes` | Ordered axis codes of a family (M2 `family_axes`, e.g. color,feel,chaise_side) + family member order (M2 `family_sort_order`); the option per item lands in `feel`, `chaise_side`, `orientation` | list of axis codes per family + option per item | Global | 38% | No | PIM | feel, chaise_side, orientation | JL/SG: family configurator on PDP (size/feel/chaise side). |
| `range` | Range / collection name | option | Global | 88% | No | PIM | range | EL: PDP spec "Range"; JL: range name on the PLP tile. |
| `review_group` | Groups SKUs that share reviews | string | Global | 46% | No | PIM | yotpo_group | JL: PDP tab "Reviews" (Yotpo); SG: Yotpo stars on the PLP tile. |
| `bundle_links` | Mattress bundle / upsell links (super SKU, upsell SKU + qty) | list of {sku, qty, role} | B2C | <1% | No | - | mattress_super_sku, upsell_sku_1, upsell_qty_1 | JL: mattress options on PDP; SG: "Add extras". Mattress PDP (SG/JL): size and comfort grid from mattress_super_sku (197 items), upsell_sku_1 / upsell_qty_1 (154), CMS blocks from mattress_pdp_cms_blocks (120); Template fields with no values in prod (upsell_sku_2 / upsell_qty_2, awards, size_super_sku, size_skus) are not carried (D24). |

## Facets (filter / search)

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `material` | Main material (one list for all product types) | multi-option | Global | 83% | No | PIM | material, sofa_style, casegood_material, rug_material, bed_type | EL: category filter "Material" = casegood_material (furniture), rug_material on rugs; PDP spec "Material". JL/SG: filters casegood_material, rug_material, sofa_style. |
| `type_detail` | Sub-type for filters (sofa type, bed type, mirror type, plant type, vessel type, art type) | multi-option, list per product_type | Global | 49% | No | PIM | sofa_type, bed_category, mirror_type, plant_type, vessel_type, art_type | Filters: JL sofa_type, art_type, plant_type; SG bed_category, bed_type; EL plant_type. EL PDP spec: Mirror Type, Plant Type, Vessel Type. |
| `style` | Style / aesthetic | multi-option | Global | <1% | No | PIM | art_style_elme, rug_style, aesthetic | EL: category filter art_style_elme (label "Style"); JL: rug_style. art_style has no values in prod: not carried (D24). |
| `shape` | Shape | option | Global | <1% | No | PIM | shape, coffee_shape, rug_shape | JL: filters coffee_shape, rug_shape; EL: PDP spec "Shape" on rugs. |
| `features` | Yes/no features shown or filtered: removable covers, recliner, stackable, watertight, waterproof, handmade, hypoallergenic, FSC timber, GoodWeave | multi-option (feature codes) | Global | 71% | No | PIM | removable_covers, recliner, stackable, watertight, waterproof, handmade, hypoallergenic, fcs_timber, goodweave (x2) | EL: filter watertight; JL: filters removable_covers, goodweave; EL PDP spec: Hypoallergenic, Goodweave, Handmade, Microwave Safe, Dishwasher Safe, UV Resistant, Fire Retardant. |
| `seats` | Number of seats | integer | Global | <1% | No | PIM | seats, number_of_seats | JL: category filter seats (options used on EL 4, JL 5). Stores = EL, JL. |
| `comfort` | Comfort / firmness level | option | Global | 38% | No | PIM | feel | EL: filter feel (label "Comfort"); JL/SG: filter feel. feel option ids are referenced in config (bart_catalog/pdp comfort_colors, mattress_comfort_callout): a vocabulary clean-up must remap these ids. |
| `mattress_facets` | Mattress brand and range | {brand, range} | B2C | <1% | No | - | mattress_brand, mattress_range | JL/SG: filters mattress_range, mattress_brand. |
| `size_bands` | Width / depth bands for filters (Search Service only, not in M2) | derived from dimensions | Global | <1% | No | derived | length_range, width_range_cm | EL/JL: filters length_range ("Width (cm)") and width_range_cm ("Depth (cm)"); width_range_cm uses 8 of 32 options, none on SG. AW also has numeric filters range/depth. |

## Specifications (display only)

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `specs.composition` | Composition / fabric make-up | text | Global | 79% | No | PIM | composition |  |
| `specs.fabric` | Fabric name | text | Global | 73% | No | PIM | fabric |  |
| `specs.upholstery` | Leg material, leg colour, frame colour, frame, construction, cushion filling, filling, suspension, bed slats | key/value list (template per product_type) | Global | 47% | No | PIM | leg_material, leg_colour, frame_colour, frame, construction, cushion_filling, filling, suspension, bed_slat, adjustability | EL: PDP spec "Construction"; JL: seat dimensions in the Dimensions tab. |
| `specs.mattress` | Mattress build: ticking, quilt layer, comfort layer, spring unit / count, zoning, pillow top, edge support, cooling fabric, wall fabrics, skirt, ActiFresh, Ultrafresh | key/value list (template) | B2C | <1% | No | - | ticking, quilt_layer, comfort_layer, spring_unit, spring_count, zoning, pillow_top, edge_support, cooling_fabric, side/bottom_wall_fabric, skirt, actifresh, ultrafresh, waterproof_membrane, fire_retardant | SG: PDP spec Comfort Layer, Weight limit, Slat height from base, Internal Storage. SG and JL mattresses use their own PDP layout (handle catalog_product_view_mattress, simple products in mattress attribute sets 57, 419, 422, 425, 428, 431, 434). Live SG spec rows differ per mattress (e.g. Art & Science Windsor: Comfort, Quilt layer, ActiFresh, Edge support; Dreamsuite Flex: Comfort Layer, no Quilt layer). mattress_features (feature icons) is read by the template but has no values in prod: not carried (D24). |
| `specs.lighting` | Fitting type, bulb included, max wattage, switch, cord length | key/value list (template) | Global | <1% | No | PIM | fitting_type, bulb_included, max_wattage, switch, cord_length | EL: PDP spec Bulb included, Cord Length, Fitting type, Max wattage, Switch. |
| `specs.decor_other` | Plant height, pot dimensions, hand painted, glassmaking, cutting method, quality, scent, drainage hole, hanging info, food-safe flags | key/value list (template) | Global | 2% | No | PIM | plant_height, pot_dimensions, hand_painted, glassmaking_technique, cutting_method, quality, scent, drainage_hole, hanging_info, microwave_safe, dishwasher_safe, oven_safe, cuttable_tearable, uv_resistant, double_sided, detachable_sections, drawer_side, open_centre_compartment, pile_height | EL: PDP spec Pot Dimensions, Plant Height, Hand Painted, Frame colour, Orientation, Drawer Dimensions; rugs: Aesthetic, Pile height. |
| `assembly_info` | Assembly level shown on PDP (Fully assembled, Leg assembly required ...) | option | Global | 96% | No | PIM | assembly | EL: PDP spec "Assembly"; JL/SG: also drives the assembly service. |

## Dimensions

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `dimensions` | Assembled product size, one convention: width = left-right (Pronto fgLength), depth = front-back (fgWidth), height = vertical (fgDepth), cm; weight kg. M2 `width`, `depth`, `height` (decimal), `weight` | {width, depth, height, weight} numbers | Global | 91% | No | PIM | width, depth, height, length, weight, dimension, fg_length, fg_width, fg_depth, fg_weight | All sites: PDP tab "Dimensions" (config depth, height, length, weight, width). EL also shows the free text "dimension" in the spec table (29 of 53 sampled PDPs): derive it from the structured fields, do not type it. Second PDP list in config bart_catalog/pdp/dimension_attribute_codes = height_clearance, pile_height, plant_height, seat_depth, seat_height, seat_width, seats, weight. |
| `measurements` | Extra measurements: seat height / depth / width, weight limit, height clearance, between legs, internal storage, drawer, shelf spacing, slat height | list of {name, value, unit} | Global | 36% | No | PIM | seat_height, seat_depth, seat_width, weight_limit, height_clearance, dimensions_between_the_legs, internal_storage, drawer_dimensions, shelf_spacing, slat_height_from_base | JL: Dimensions tab seat depth/height/width; SG: Weight limit, Slat height. |

## Logistics

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `cartons` | Shipping cartons: ordered list; per carton length x width x height (cm), weight (kg); package type (Carton / Pallet, limit 430 cm) is derived by the receiver. M2: one read-only JSON attribute `cartons` | list of {length, width, height, weight} | Global | 97% | No | PIM | no_of_ctns, ctn1_* .. ctn10_* (40 attributes), fusedship_product_cartons (JSON copy) | ALSO ON THE FE: JL/SG PDP tab "Packaging Dimensions" (Number of Cartons, Carton 1..n); SG spec Cartons 1-5. Not shown on EL. |
| `units_per_carton` | Units per carton | integer | Global | 72% | No | PIM (feeds) | units_per_ctn |  |
| `pack` | Pack weight and cubic size (Pronto pack) | {weight, cubic} | Global | 89% | No | - | pack_weight, pack_cubic_size |  |
| `unit_of_measure` | Unit of measure (EACH, SET ...) | option | Global | 91% | No | - | unit_desc |  |
| `parcel_class` | Parcel classification 1-7 (drives rating, assembly, recycling) | option | Global | 92% | No | - | stk_user_group_2 |  |
| `buyer_code` | Buyer code (stock traffic-light thresholds) | option | Global | 89% | No | - | buyer_code |  |
| `stock_group` | Stock group (delivery and recycling rules) | option | Global | 69% | No | - | stock_group |  |
| `lead_time_class` | Lead-time class used by ETA | option | Global | 88% | No | - | (range today) |  |
| `made_to_order` | Made to order | yes/no | Global | 100% | No | PIM (label) | made_to_order |  |
| `supplier_lead_time` | Supplier lead time (days) | integer | Global | <1% | No | - | supplier_lead_time |  |
| `condition_code` | Lifecycle code A / C / D / I / T | option | Global | 41% | No | DWH B2B | condition_code |  |
| `order_rules` | MOQ / pack multiple and backorders allowed | {min_qty, qty_step, backorder_allowed} | Global | 12% | Yes (EL B2B MOQ) | DWH B2B | stock item min_sale_qty, qty_increments, backorders; freedom_price | EL: MOQ shown only when logged in. |
| `kit_bom` | Kit components and quantities | list of {sku, qty} | Global | 69% | No | PIM | is_kit_component |  |
| `shipping_flags` | Live carrier rates flag, HS code, country of origin | {use_live_rates, hs_code, country_of_origin} | Global | 94% | No | - | fusedship_product_data, country_of_manufacture |  |
| `dropship` | Dropship flags: live approved, made to order (temporary until the Supplier Portal). elme_dropship is dropped (never set) | {approved, mto} | Global | 95% | No | - | dropship_live_approved, dropship_made_to_order, elme_dropship |  |

## B2C services

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `assembly_service` | Assembly service offered + code + price + free assembly | {offered, code, price, free} | B2C | 100% | No | - | assembly_code, assembly_price, free_assembly |  |
| `fulfilment_options` | Click & collect allowed, stores excluded, interstate NSW / QLD / VIC | {cnc, cnc_excluded_stores, interstate{}} | B2C | 100% | Yes (C&C per storefront) | - | is_click_and_collect, disable_stores_cnc, available_interstate_nsw/qld/vic | JL/SG: PLP tile delivery options (Home Delivery, Click & Collect). |
| `recycling_code` | Old-goods recycling code | option | B2C | <1% | No | - | recycling_code |  |

## Pricing

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `list_price` | Base / list price per channel | money per channel | Global | 51% | Yes | DWH B2B (price tables) | price | EL: price only when logged in; JL/SG: PLP tile and PDP price, SG from/was. |
| `promo_price` | Promo price with dates | {price, from, to} | B2C | 10% | Yes | - | special_price, special_from_date, special_to_date |  |
| `b2c_promo_display` | Multibuy range, discounted price, promo text and colour | set of fields | B2C | 63% | Yes | - | multibuy_price_min/max, discounted_price, promo_text, promo_text_color | JL: promo box on the tile; SG: promo text. |
| `contract_prices` | Customer and marketing contract prices (B2B) | table, not an attribute | B2B | n/a | n/a | DWH B2B | (pronto_elme_* tables) |  |
| `item_group` | Pronto item group (drives type-25 discounts) | option | B2B | n/a | No | DWH B2B | (not an attribute) |  |

## Compliance & labels

| Field | Meaning | Type | Scope | EL fill | Channel value | M2 B2B gets it from | Old M2 attributes | Old sites show it as |
|---|---|---|---|---|---|---|---|---|
| `warnings` | Show material popup, show toppling warning | flags | Global | 100% | No | PIM | show_material_popup, show_toppling_warning | JL/SG: PDP tab "DANGER Product Safety Warning" (toppling). |
| `labels` | Badges (New, Sale, Clearance ...) | multi-option per channel | Global | 31% | Yes (3,731 shared SKUs differ) | PIM, per channel | product_label | EL: Clearance; JL/SG: New, Sale, Clearance, Online Exclusive, Best Value, Most Popular. |
| `feed_mapping` | Google product category | option | Global | <1% | No | PIM | google_product_category |  |
