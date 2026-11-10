# Pronto item data: what can be read and written

Inventory of everything the Bart repos (pos, m2-bart-group, shaw, data-warehouse, shipment-service)
read or write about Pronto items. Version 0.1, reviewed 26 Oct 2026. There are two Pronto companies:
B2B (Elme) and B2C (JL / SG).

## Protocols

| Name | What it is | Use |
|---|---|---|
| Xi API | Pronto Xi "Connect eCommerce" REST/XML API (POST, XML body, token from login) | reads; writes for orders, payments, customers |
| DWH | Bart data warehouse: read-only Postgres + REST copy of Pronto | richest read source; read-only by construction |
| ODBC proxy | feasibility.cc JSON proxy over Pronto ODBC tables | GET only; not in the target |
| Avenue | Pronto Avenue push into Magento REST | one way, Pronto to Magento; not in the target |

## Writes that exist

Only sales orders, order payments and customers: `SalesOrderInsertSalesOrders`,
`SalesOrderPostPayment`, `SalesOrderInsertSalesOrderPayment`, `SalesOrderPostCancelSalesOrder`,
`SalesOrderPostLineDiscountOverride`, `CusPostNewCustomer`, `CusPostCustomerDetails` (update allows
only 5 tags). Order line allowlist: ItemCode, OrderedQty, ItemPrice (GST inclusive),
LineDescription, TypeCode, ExternalSequenceNo, OtherNotes.

No item write exists anywhere: no item create or update, no price, BOM, carton or dimension write.
An order insert commits stock; that is the only indirect effect on item data.

## Reads

| Operation | Protocol | Fields |
|---|---|---|
| InvGetItems | Xi API | ItemCode, GroupCode, ConditionCode, warehouse quantities (nested list stops at 24 warehouses) |
| InvGetItemPrices | Xi API | WholesalePrice, PriceRegionCode, TypeCode, PromPrice with start and end, LastChangeDate |
| InvGetItemWarehouses | Xi API | AvailableQty, OnHandQty (CommittedQty and BackorderQty are rejected) |
| PurchaseOrderGetPurchaseOrders | Xi API | POOrderNo, ArrivalDate, StatusCode, WarehouseCode, lines with ItemCode, OrderQty, ExpectedDate |
| CusGetCustomers | Xi API | CustomerCode, MarketingCode, TypeCode, CreditLimitAmount, Email |
| /api/v1/items | DWH | itemCode, description, unitDesc, stockGroup, brand, apnNumber, stockStatus, assemblyPrice, packCubicSize, packWeight, packQty, userGroup1 |
| /api/v1/item-dimensions | DWH | noOfCartons, unitsPerCarton, finished-goods L/W/D/weight, cartons 1-12 L/W/D/weight |
| /api/v1/item-prices | DWH | base and sale price inc GST, sale dates, break price 2 (JL / SG national only, no Elme) |
| /api/v1/item-stock | DWH | per item and warehouse: on hand, on order, in transit, back orders, min/max |
| /api/v1/kit-components | DWH | parentSku, childSku, childQty |

## Item field map

| Pronto field | Meaning | Read via | Old M2 attribute |
|---|---|---|---|
| stk5mf8.stock_code / ItemCode | item code = SKU | Xi, DWH | sku (codes with a '#' suffix exist) |
| stk_description (+ lines 2, 3) | description | DWH | none (name is owned outside Pronto) |
| stk_unit_desc | unit (EACH...) | DWH | unit_desc |
| stock_group / GroupCode | stock group | DWH, Xi | stock_group (whether GroupCode equals stock_group is unconfirmed) |
| stk_brand | brand | DWH | - |
| stk_apn_number | APN / EAN-13 barcode | DWH | not mapped |
| stk_stock_status | status, K = kit | DWH | - |
| condition_code | lifecycle A / C / D / I / T | Xi | condition_code |
| only_alpha4_4 | assembly price as text '$120' (4 chars, max $999, no cents) | DWH | assembly_price |
| pack_cubic_size, stk_pack_weight, stk_pack_qty | pack data | DWH | pack_cubic_size, pack_weight |
| stk_user_group_2 | parcel class | not extracted by the DWH | stk_user_group_2 |
| buyer code (column unknown) | buyer bucket | no read we own | buyer_code |
| supplier, lead time (columns unknown) | supplier, lead time | no read we own | supplier_lead_time |
| min sale qty (column unknown) | MOQ | no read we own | stock item min_sale_qty |
| WholesalePrice (+ PriceRegionCode, TypeCode) | base / regional price | Xi | EL price tables |
| PromPrice + dates | promo price | Xi | EL promo price |
| special prices (sp_key A1 / 20 / 25) | contract and marketing prices | ODBC proxy today | pronto_elme_cust_mkt_mapping |
| cartons, finished-goods dimensions (tables unknown) | cartons 1-12, dimensions | DWH item-dimensions | no_of_ctns, units_per_ctn, fg_*, ctn1..10 |
| stk5whl | warehouse stock | Xi, DWH | MSI / store locator tables |
| bom5hdrs / bom5detd | kit BOM | DWH | - |

Buyer code, supplier lead time, minimum sale quantity and parcel class reached the old Magento only
through the Avenue push. With Avenue gone they need a new read path (DWH B2B); until it exists,
MOQ on this instance has no live source.

## Traps

- Not every Connect bundle is licensed on the instance: some return HTTP 503.
- Pronto's published field tables are unreliable. Several "insertable" fields are rejected, and one
  unsupported tag voids the whole post. Every write has to be probed tag by tag on staging.
- There is no usable staging company with current item data.
- Spare-field conventions (assembly price in `only_alpha4_4`) are not enforced by any API.
- Writes go to a different company endpoint than reads.

## Open with the Pronto owner

Whether the Xi Connect licence includes item maintenance; which API an external PIM can use to
create and update items (decided: an API, not files); mandatory stock-master fields with widths;
which table holds cartons and dimensions and whether it can be written; where buyer code, supplier,
lead time and minimum sale quantity live; whether prices can be written or stay in Pronto; whether
item-by-warehouse records and kit BOMs can be created by API; rate limits, token lifetime and
idempotency of writes.
