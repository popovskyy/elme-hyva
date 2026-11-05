# Elme_StoreLocator (BE-15 + FE-51)

Showroom / distribution locations: admin + import (BE-15), storefront list and location pages (FE-51).

## Data

Table `elme_store_locator_location`: name, type (`showroom` | `distribution_pickup` | `trade_only`), street, city, state, postcode, lat/lng, phone, opening hours (JSON), holiday hours (JSON), url key, description, parking instructions, active, sort order.

- `opening_hours`: `{"monday": {"from": "09:00", "to": "17:00"}, ..., "sunday": {"from": "", "to": ""}}`. Empty from/to = closed.
- `holiday_hours`: `[{"date": "2026-12-25", "from": "", "to": "", "name": "Christmas Day", "label": "Back on 5 Jan"}]`. A row replaces the weekday hours for that date; `name` and `label` are optional and show under the day.

The admin form checks both JSON fields on save. The CSV importer takes the same optional `holiday_hours` and `parking_instructions` columns.

Seed CSV: `files/elme_locations.csv` (Blackburn trade-only showroom, Tarneit / St Marys / Richlands distribution centres).

## Admin

Content > Showroom Locations. Grid + edit form. Re-import:

```bash
bin/clinotty bash -c "bin/magento elme:store-locator:import app/code/Elme/StoreLocator/files/elme_locations.csv"
# wipe then reload:
bin/clinotty bash -c "bin/magento elme:store-locator:import app/code/Elme/StoreLocator/files/elme_locations.csv --replace"
```

## Frontend service

`Elme\StoreLocator\Model\Service\LocationQuery`:

- `list(?string $state)`: active locations, optional state filter (`VIC`, `NSW`, ...)
- `nearestToPostcode(string $postcode, ?string $state)`: origin = lat/lng of the location that shares that postcode (so `3130` gives Blackburn first)
- `nearestToCoordinates(float $lat, float $lng, ?string $state)`

Repository: `Elme\StoreLocator\Api\LocationRepositoryInterface` (`getById`, `getByUrlKey`, `save`, `delete`).

## Storefront (FE-51)

| URL | Handle |
| --- | --- |
| `/showroom-locator` | `elme_storelocator_index_index` |
| `/showroom-locator/{url_key}` | `elme_storelocator_index_view` |

Custom router keeps production paths. Templates under `view/frontend/templates/location/`. JSON-LD in `list-jsonld.phtml` / `view-jsonld.phtml`. Search, state filter and distance sort run client-side (CSP Alpine); all four locations stay in the initial HTML for crawlers. Location page hours: five weeks from this Monday with holiday rows applied, week switcher, today highlighted. Both pages sit in full-page cache, so "today" (location page row, list card "Today" hours) is recomputed in the browser in the store timezone.

Map: keyless Google embed by default. Stores > Configuration > Elme > Showroom Locator > Google Maps API Key switches to the Maps Embed API; set it only where the key's referrer rules allow the domain (production).

Unknown / inactive `url_key` gives HTTP 404.
