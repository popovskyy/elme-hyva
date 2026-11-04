# Elme_StoreLocator (BE-15 + FE-51)

Showroom / distribution locations: admin + import (BE-15), storefront list and location pages (FE-51).

## Data

Table `elme_store_locator_location`: name, type (`showroom` | `distribution_pickup` | `trade_only`), street, city, state, postcode, lat/lng, phone, opening hours (JSON), url key, description, active, sort order.

Seed CSV: `files/elme_locations.csv` (Blackburn trade-only showroom, Tarneit / St Marys / Richlands distribution centres).

## Admin

Content → Showroom Locations. Grid + edit form. Re-import:

```bash
bin/clinotty bash -c "bin/magento elme:store-locator:import app/code/Elme/StoreLocator/files/elme_locations.csv"
# wipe then reload:
bin/clinotty bash -c "bin/magento elme:store-locator:import app/code/Elme/StoreLocator/files/elme_locations.csv --replace"
```

## Frontend service

`Elme\StoreLocator\Model\Service\LocationQuery`:

- `list(?string $state)` — active locations, optional state filter (`VIC`, `NSW`, …)
- `nearestToPostcode(string $postcode, ?string $state)` — origin = lat/lng of the location that shares that postcode (so `3130` → Blackburn first)
- `nearestToCoordinates(float $lat, float $lng, ?string $state)`

Repository: `Elme\StoreLocator\Api\LocationRepositoryInterface` (`getById`, `getByUrlKey`, `save`, `delete`).

## Storefront (FE-51)

| URL | Handle |
| --- | --- |
| `/showroom-locator` | `elme_storelocator_index_index` |
| `/showroom-locator/{url_key}` | `elme_storelocator_index_view` |

Custom router keeps production paths. Templates under `view/frontend/templates/location/`. JSON-LD in `list-jsonld.phtml` / `view-jsonld.phtml`. Search, state filter and distance sort run client-side (CSP Alpine); all four locations stay in the initial HTML for crawlers. Unknown / inactive `url_key` → HTTP 404.
