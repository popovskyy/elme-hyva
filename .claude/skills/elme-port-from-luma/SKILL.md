---
name: elme-port-from-luma
description: Use when porting a page, block, widget or template of the old Elme Living site (Adobe Commerce, Luma-based theme Bart/el) to the Hyvä theme Elme/default in this project - "перенеси блок", "port the header/footer/PLP/PDP/minicart", "make it look like the live site", "rebuild this Elme widget in Hyvä", or when converting LESS/Knockout/RequireJS/jQuery code from m2-bart-group into Tailwind + Alpine.
---

# Port an old Elme block to Hyvä

There is no new design: the live site **is** the design. A port is done when the Hyvä version matches
the old one at mobile, tablet and desktop, uses only theme tokens, and works without jQuery/RequireJS/
Knockout. UI/UX improvements are welcome but are listed and committed separately from the parity work.

## 1. Find every source of the old block

The old theme inherits, so the block can live in four places. Look in this order and take the first hit
for each file (`m2-bart-group` = `~/Documents/BartGroup/m2-bart-group`):

1. `app/design/frontend/Bart/el/<Module>/` - Elme overrides
2. `app/design/frontend/Bart/jl/<Module>/` - parent (James Lane): anything Elme does not override
3. `app/design/frontend/Overdose/base/` and `vendor/overdose/*` - agency base
4. the module itself: `app/code/ElmeLiving/*`, `app/code/Bart/*` (often with `isElWebsite()` branches),
   `vendor/*/view/frontend`

Collect: layout XML (`<handle>.xml`), templates (`.phtml`), styles (`web/css/source/**/*.less`, grep
the block's CSS class), JS (`web/js`, `requirejs-config.js`, Knockout `.html` templates), and the
ViewModel / Block PHP that feeds the template. CMS-driven content (banners, tiles, the dusty-blue top
bar) lives in the DB, not in code - check Content > Blocks/Pages and Page Builder on the old admin.

## 2. Look at the live block

Open https://www.elmeliving.com/ with Playwright at **375, 768, 1440 and 1920** wide (close the
Klaviyo popup first). Screenshot the block, and read computed styles (`getComputedStyle`) for font,
size, line-height, colours, spacing and borders - the LESS often differs from what ships. Log in as an
Elme test customer when the block shows prices, stock or MOQ (see the `elme-business` skill): guests see
a different page.

## 3. Rebuild it in Hyvä

- **Where:** override a Hyvä template by copying it from `vendor/hyva-themes/magento2-default-theme/<Module>/templates/...`
  into `app/design/frontend/Elme/default/<Module>/templates/...`; new blocks go in a module under
  `app/code/Elme/*` (use the `hyva-create-module` skill) with templates and a ViewModel.
- **Data:** ViewModels (`$viewModels->require(...)`), not `$block->helper()` and not ObjectManager.
  Port PHP logic from the old Block/ViewModel; keep business rules identical (`elme-business`).
- **Styles:** Tailwind classes using **tokens only** - colours `bg-primary`, `text-ink-muted`,
  `border-border`…, fonts `font-sans` / `font-heading`, breakpoints `md:` (tablet 768) / `lg:`
  (desktop 1025) / `3xl:` (1920). No hex values, no arbitrary px in templates: if a value is missing,
  add a token to `web/tailwind/hyva.config.json` → `tokens.values`. Map old LESS variables with
  `references/tokens-map.md`. Write mobile first.
- **JS:** Alpine, CSP-compatible (`hyva-alpine-component` skill). jQuery widgets, `x-magento-init`,
  Knockout and RequireJS do not exist here. Private content (customer, cart) comes from Hyvä's
  `private-content-loaded` event, not `customer-data.js`.
- **Images:** `hyva-render-media-image` skill (responsive `<picture>`).
- **Third-party modules** (Algolia, Aheadworks, Magefan, Amasty, Klaviyo, Yotpo…) need a Hyvä compat
  module or a rewrite - check the Hyvä compatibility tracker before porting their templates by hand.
- Build with `npm run build` in `web/tailwind` (or `hyva-compile-tailwind-css`), then
  `bin/clinotty bash -c "bin/magento cache:flush"`.

## 4. Verify

- Side-by-side screenshots old vs new at 375 / 768 / 1440 / 1920 (Playwright, real host
  `https://elme-hyva.local`). Bump `pub/static/deployed_version.txt` (no trailing newline) or clear the
  browser cache, otherwise the old `styles.css` is served.
- No console errors, no horizontal scroll on mobile, keyboard focus visible.
- Logged-in and guest states if the block depends on them.
- In the hand-over, list: what matches, what intentionally differs (UX improvements), and what is
  still missing (data or integrations not on this instance yet).
