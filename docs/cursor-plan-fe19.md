# Plan for Cursor (local machine): finish FE-19 product card + hero 4K + login check

Written 2026-10-31 for a coding agent with access to the local site https://elme-hyva.local.
**Follow the steps in order. Do not skip a step, do not invent results.** Every step has a "Done when"
line: if you cannot show that output, the step is NOT done. Stop and report instead of guessing.

## 0. Hard rules (read first, they override your habits)

1. Read `CLAUDE.md` in the repo root before anything else. It wins over this file if they disagree.
2. Magento/PHP/composer commands run ONLY in the container:
   `bin/clinotty bash -c "bin/magento …"`. Never run `bin/magento`, `php` or `composer` on the host.
3. NEVER run `setup:static-content:deploy`. After template/layout changes: `bin/clinotty bash -c "bin/magento cache:flush"`.
4. No hex colours, no px sizes in templates or CSS. Use tokens from
   `app/design/frontend/Elme/default/web/tailwind/hyva.config.json` → `tokens.values`
   (CSS vars `--color-*`, `--spacing(n)`, `--text-*`). If a token is missing, add it there and run `npx hyva-tokens`.
5. Alpine must be CSP-compatible: `x-data="initSomething"` registered via `Alpine.data(...)`,
   no inline expressions with assignments/arguments in attributes, methods only.
   See `.claude/skills/hyva-alpine-component/SKILL.md`.
6. Do not edit files in `vendor/`. Do not edit `magento-docker/php/Dockerfile`.
7. Do not remove the `btn-atc` class from the Add to Cart button (FE-70 animation depends on it).
8. Do not touch the PLP grid (FE-18 / FE-20). Only the tile (FE-19).
9. If a command fails, paste the real error and fix the cause. Never write "should work now" without re-running it.
10. When unsure what a file contains, open it. Do not assume class names, method names or paths.

## 1. Get the code

```bash
git fetch origin
git checkout main && git pull origin main
git checkout -B fe-19-verify origin/wip/fe-19-product-card
git merge origin/main          # expected: no conflicts (checked 2026-10-31)
```

**Done when:** `git status` is clean and `git log --oneline -3` shows the merge on top of `a3cf480`.

What the branch contains (do not rewrite it, verify and fix it):

| File | What |
|---|---|
| `app/code/Elme/Theme/ViewModel/ProductTile.php` | `getBadge()` sale/new, `getStockLevel()` via MSI salable qty (≥10 `in`, 1–9 `low`, 0 → null), `getQtyRules()` (MOQ = `min_sale_qty`, step = MOQ or `qty_increments`), `isQuickOrderEnabled()` (config `elme_catalog/plp/quick_order`, default 1) |
| `app/code/Elme/Theme/etc/adminhtml/system.xml`, `etc/config.xml` | switch "Quick order on PLP" |
| `app/code/Elme/Theme/Test/Unit/ViewModel/ProductTileTest.php` | unit tests |
| `app/design/frontend/Elme/default/Magento_Catalog/templates/product/list/item.phtml` | new tile. Price via FE-53 PriceRenderPlugin (guest sees "Sign in to see pricing"); logged in: qty stepper + Add to Cart `.btn-atc`; compare and ratings removed |
| `.../Magento_Catalog/templates/product/list/js/qty-stepper.phtml` | CSP Alpine `initElmeQtyStepper` |
| `app/design/frontend/Elme/default/web/tailwind/elme/product-card.css` | tile styles, imported in `elme.css` |
| `app/design/frontend/Elme/default/web/tailwind/elme/hero.css` | `.elme-hero__copy` width from md: `calc(var(--hero-pad) + (100% - 2 * var(--hero-pad)) * 0.58)` + `padding-right: 0` |

## 2. Make Magento pick it up

```bash
bin/clinotty bash -c "bin/magento setup:upgrade && bin/magento cache:flush"
bin/clinotty bash -c "bin/magento setup:di:compile"     # catches DI/constructor errors; then back to developer mode is automatic
bin/clinotty bash -c "vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Elme/Theme/Test/Unit/ViewModel/ProductTileTest.php"
cd app/design/frontend/Elme/default/web/tailwind && npm ci && npm run build && cd -
```

**Done when:** all four commands exit 0. PHPUnit prints `OK (N tests…)`. If `setup:di:compile` leaves
`generated/` stale for developer mode, run `bin/clinotty bash -c "rm -rf generated/code/* && bin/magento cache:flush"`.

## 3. Test data (script lives OUTSIDE the repo or in `var/`, never committed)

Create in Admin (Catalog → Products) or with a PHP script run via `bin/clinotty`. Two categories
("FE19 Test A", "FE19 Test B"), anchor, visible in menu. Products (simple, visible Catalog+Search, enabled, price 10):

| SKU | Category | Setup | Expected on tile (logged in) |
|---|---|---|---|
| FE19-QTY0 | A | qty 0, stock status "Out of stock" with backorders off | no stock label |
| FE19-QTY5 | A | qty 5 | "Low Stock" |
| FE19-QTY50 | A | qty 50 | "In Stock" |
| FE19-MOQ6 | A | qty 100, Advanced Inventory → Minimum Qty Allowed in Shopping Cart = 6 | stepper starts at 6, steps 6/12/18 |
| FE19-SALE | B | qty 50, Special Price 8, special_from_date = yesterday, special_to_date = +30 days | badge "Sale" |
| FE19-NEW | B | qty 50, "Set Product as New From" = yesterday, "to" = +30 days | badge "New" |
| FE19-LONG | B | qty 50, name 120+ chars ("Artificial Peony Arrangement in Ribbed Ceramic Vase with Trailing Eucalyptus and Seasonal Foliage, Extra Large Display Size") | title clamps, card height equal to neighbours |

Plus a customer: `fe19@example.com` / `Fe19Test!2026`, account confirmed, website Main.
Then: `bin/clinotty bash -c "bin/magento indexer:reindex && bin/magento cache:flush"`.

**Done when:** both category URLs return 200 and list the products (check in the browser, not by assumption).

## 4. Browser checks (Playwright, `ignoreHTTPSErrors: true`)

Use `node .claude/skills/elme-qa/scripts/browse.mjs --viewports 390,768,1025,1440,1920,2560,3840 <path>`
or your own Playwright script. **Open and look at every screenshot.** Save them under `var/qa/fe19/`.

### 4a. Hero (home `/`)
At 390, 768, 1025, 1440, 1920, 2560, 3000, 3840:
- [ ] heading fully visible, not under the header, not cut at the top
- [ ] copy starts on the container line (same left edge as the logo)
- [ ] heading wraps into 2–4 lines on desktop, not a narrow column of 6+ lines
- [ ] on 390 the copy is under/over the photo as before, nothing overflows horizontally
  (check `document.documentElement.scrollWidth <= window.innerWidth`)

### 4b. FE-19 tile, logged OUT (category A and B)
- [ ] no price, no stepper, no Add to Cart; the price slot says "Sign in to see pricing"
- [ ] clicking "Sign in" goes to `/customer/account/login/`, after login you land BACK on the same category URL
- [ ] Sale/New badges visible on FE19-SALE / FE19-NEW
- [ ] no compare link, no rating stars

### 4c. FE-19 tile, logged IN as fe19@example.com
- [ ] price visible on every tile
- [ ] stock labels: QTY0 none, QTY5 "Low Stock", QTY50 "In Stock"
- [ ] FE19-MOQ6: stepper shows 6; + goes 12, 18; − never below 6; type 7 and blur → snaps UP to 12; type 0 → 6
- [ ] normal product: stepper starts at 1, steps by 1
- [ ] Add to Cart on MOQ6 adds 6 (check `/checkout/cart/` qty), mini-cart counter updates without reload,
      the `.btn-atc` animation plays
- [ ] cards in one row have equal height with FE19-LONG in that row (compare `getBoundingClientRect().height`
      of the tiles in the row, difference ≤ 1)
- [ ] keyboard: Tab reaches −, input, +, Add to Cart; visible focus ring; buttons have aria-labels
- [ ] Admin: Stores → Configuration → Elme → Catalog → Product card (FE-19) → "Quick order on PLP" = No → flush cache → tile has no
      stepper/Add to Cart; set back to Yes
- [ ] browser console: zero errors, zero CSP violations (listen to `page.on('console')` and `pageerror`)

### 4d. Login and registration (`/customer/account/login/`, `/customer/account/create/`)
Main already has a fix (2026-10-31): Hyvä's `#customer-login-container` added 32px padding above and below
the split layout (white strip under the header and above the footer) and two columns from 768px.
At 390, 768, 1025, 1440, 1920, 2560, 3840:
- [ ] photo starts directly under the header (no white strip), ends directly above the footer section
- [ ] 390 and 768: photo band on top, form under it, one column
- [ ] from 1025: photo on the left, form column on the right (32–44rem, 48rem from 1440), form centred in it
- [ ] no horizontal scrollbar
- [ ] register page: photo stays in view (sticky) while the long form scrolls

Fix every failed box. Each fix: smallest change, tokens only, rebuild Tailwind, re-run the same check and
keep the "after" screenshot.

## 5. QA skill

```bash
python3 .claude/skills/elme-qa/scripts/scope.py
bash .claude/skills/elme-qa/scripts/smoke.sh / /customer/account/login/ /customer/account/create/ <category A URL> <category B URL>
```
Read every lint hit from `scope.py` and fix or justify it. Write the TASK / Change log / Steps For QA
block as `.claude/skills/elme-qa/SKILL.md` describes.

**Done when:** smoke.sh reports no failures and scope.py has no unexplained hits.

## 6. Finish

1. `cd app/design/frontend/Elme/default/web/tailwind && npm run build` one last time; commit `web/css/styles.css`.
2. In `docs/frontend-tickets.md` set FE-19 Status to `Done` (only if every box in 4b/4c passed;
   otherwise `In progress` and list what is open). Note in the report that the PLP grid (FE-18/FE-20) was not touched.
3. Commit with a clear message (what changed and why, one bullet per fix).
4. `git checkout main && git merge --no-ff fe-19-verify && git push origin main`
5. Report back: list of checks with pass/fail, screenshots folder, commits, anything still open.
   Never claim a check passed if you did not run it.
