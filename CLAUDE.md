# Elme Living - Magento Open Source 2.4.9 + Hyvä

New Elme Living storefront (moving off the shared Adobe Commerce instance `m2-bart-group`, theme `Bart/el`). No new design: port the old Elme look and blocks to Hyvä, then improve UI/UX.

## Environment

- Everything PHP/Magento runs in the container: `bin/clinotty bash -c "bin/magento ..."` (or `docker exec -it -u magento elme_php bash`). Never run `bin/magento` or `composer` on the host.
- Site https://elme-hyva.local, admin `/admin`. Stack in `docker-compose.yml`, see README.md.
- Never run `setup:static-content:deploy` locally (developer mode). After template/layout changes: `bin/magento cache:flush`.

## Theme: `app/design/frontend/Elme/default` (child of `Hyva/default`, Tailwind v4)

- **Design tokens live in one place: `web/tailwind/hyva.config.json` -> `tokens.values`** (colours, type scale, breakpoints, container, radius, form, button, easing, durations). `npx hyva-tokens` turns them into `generated/hyva-tokens.css` (`--color-primary` -> `bg-primary`, `text-primary`, ...). Do not hardcode hex values or px sizes in templates; add a token instead.
- Fonts: `web/tailwind/hyva.config.json` -> `fonts` (Figtree = `font-sans`, body; `font-heading` = Figtree Medium registered at weight 400, so headings written as `font-normal` render medium). Files in `web/fonts/`.
- Breakpoints: mobile = no prefix, tablet = `md:` (768px), desktop = `lg:` (1025px), `xl:` 1280, `2xl:` 1440, `3xl:` 1920. Container is fluid up to 1920px. Write mobile first.
- **Leaf corners:** everything you press has leaf corners, top-right and bottom-left rounded (the leaf in the logo). Radius tokens `--radius-leaf` (16px, 50-56px controls), `--radius-leaf-sm` (10px, 40-48px controls, text fields, floating panels), `--radius-leaf-xs` (4px, rows inside a panel, 32px mini swatches), `--radius-leaf-lg` (32px, the white rows of the PLP list view); utilities `corner-leaf` / `corner-leaf-sm` / `corner-leaf-xs` / `corner-leaf-lg` (`web/tailwind/elme/foundation/leaf.css`). `.btn` has it built in (`--btn-radius`); text fields get it from the `form.radius` token.
  - leaf: buttons, enhanced selects, qty steppers, header and mobile menu search fields, hero carousel arrows.
  - leaf-sm: text inputs and textareas (incl. native select fallback), chips (quick filters, help tabs, contact shortcuts), pager squares, icon buttons (`.elme-ib`, heart, save), mobile menu tiles, chevrons and close, PLP view switch (from md), family swatches and size chips, slider arrows (`.elme-snap-carousel__arrow`), hero carousel control bar, select list panel and header autocomplete panel (they float 4px under the field as their own leaf).
  - leaf-xs: option rows in the select panel / phone sheet and in the autocomplete.
  - Stay square: checkboxes, filter colour tiles, cards (incl. checkout option cards; the PLP list view rows are the one leaf card), images and gallery thumbs, badges, drawer / dialog / bottom sheet shells, accordion rows, copy SKU, footer newsletter field (underline only). Round marks (radios, dots, number circles, counters, switches) stay round.
  - Promo band: `.elme-band-leaves` (sage with leaf art), one or two blocks per page.
  - Text over a photo: `.elme-glass` + `--ink` / `--light` / `--olive` (`web/tailwind/elme/foundation/glass.css`): a leaf-shaped blurred panel instead of a gradient fade. Hero slides pick it per slide (field "Text panel"), the category banner from Stores > Configuration > Elme > Catalog > Category banner. Use it for any new block with copy on an image.
- Elme-specific CSS: `web/tailwind/elme.css` only imports partials from `web/tailwind/elme/<area>/`, one folder per area (`foundation`, `layout`, `home`, `plp`, `product`, `cart-checkout`, `account`, `content`, `help`; the list is in the header of `elme.css`). Add a new file in the folder it belongs to and import it in that folder's block of `elme.css`; a new area gets a new folder. Do not grow one stylesheet. Edited Hyvä copies are marked with an `Elme:` comment (`components/button.css`, `components/wrapper.css`).
- Build: `cd app/design/frontend/Elme/default/web/tailwind && npm run build` (or `npm run watch`). Commit `web/css/styles.css`.
- Checkout is our own module `Elme_Checkout` on this theme (decision D12, tickets BE-01...BE-05 in `docs/frontend-tickets.md` Epic 6). No Luma fallback checkout, no Hyva Checkout. How to add a section or a place-order validator: `app/code/Elme/Checkout/README.md`.
- Alpine components must be CSP-compatible (see the `hyva-alpine-component` skill).

## Skills

- `elme-business` - who Elme's customers are and the B2B rules (login-only, per-customer Pronto prices, ex GST, MOQ, minimum order value, Quick Order, stock, freight). Read it before building anything that shows prices, stock or touches cart/checkout.
- `elme-product-data` - the planned source of every product field (a PIM that is not built yet, Pronto through the data warehouse, Shipment Service; no stock in Magento, no Avenue), the 74 PIM fields that replace 377 old attributes, what the Pronto item API can do. Read it before a ticket that shows, stores, imports or filters product data (PDP, PLP tile, filters, search, structured data, attributes).
- `elme-port-from-luma` - how to port a block from the old `Bart/el` theme: where its sources are, how to check the live site, Hyvä rules, verification. `references/tokens-map.md` maps every old LESS variable to a token.
- `elme-qa` - run when a change is done: `scope.py` (diff + Hyvä lint) + `smoke.sh` + `browse.mjs` (new vs old site screenshots), ends with the TASK / Change log / Steps For QA block.
- `elme-look-options` - before restyling a block: a standalone HTML page in `docs/design/` with two or three looks (phone and desktop). The user picks, then the theme changes. Do not edit templates in the same step.
- Frontend polish: `web-interface-guidelines` (UI review). Motion is CSS/Tailwind + Alpine `x-transition`, no Motion/GSAP/React (rules in `.claude/skills/README.md`).
- Plugins from `.claude/settings.json`: `superpowers` (brainstorm -> plan -> TDD -> review), `frontend-design`, `playwright`.

`.claude/skills/hyva-*` are copied from github.com/hyva-themes/hyva-ai-tools (commit in `HYVA_AI_TOOLS_VERSION`). The Hyvä CMS / Hyvä UI skills are left out on purpose: those are paid products we don't use.

## Git

The base branch is `production` (Bitbucket `bartgroup/m2-elmeliving`). Cut every ticket branch from a fresh `production` (`git fetch && git switch -c fe-xx-slug origin/production`), name it after the ticket and merge it back with a `Merge FE-xx` commit.

## Roadmap

Frontend tickets (FE-01...FE-58, UX-01...UX-12, epics, dependencies, QA checks, decisions D1-D12): `docs/frontend-tickets.md`. Update the Status column and decisions there when a ticket or decision changes.

## Source of truth for the old site

the `m2-bart-group` repository (ask a teammate for access; examples below assume it is checked out next to this project), theme `app/design/frontend/Bart/el` (parent `Bart/jl` -> `Overdose/base`), modules `app/code/ElmeLiving/*` and Elme parts of `app/code/Bart/*`. Live: https://www.elmeliving.com/
