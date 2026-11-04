# Elme_StoreLocator (BE-15)

Showroom / distribution locations for the FE-51 locator.

## Data

Table `elme_store_locator_location`: name, type (`showroom` | `distribution_pickup` | `trade_only`), street, city, state, postcode, lat/lng, phone, opening hours (JSON), url key, description, active, sort order.

Seed CSV: `files/elme_locations.csv` (Blackburn trade-only showroom, Tarneit / St Marys / Richlands distribution centres — the four locations on the live Elme locator).

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
