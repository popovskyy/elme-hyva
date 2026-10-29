# Elme Living — Magento Open Source 2.4.9 + Hyvä

New Elme Living storefront (moving off the shared Adobe Commerce instance `m2-bart-group`, theme `Bart/el`). No new design: port the old Elme look and blocks to Hyvä, then improve UI/UX.

## Environment

- Everything PHP/Magento runs in the container: `bin/clinotty bash -c "bin/magento …"` (or `docker exec -it -u magento elme_php bash`). Never run `bin/magento` or `composer` on the host.
- Site https://elme-hyva.local, admin `/admin`. Stack in `docker-compose.yml`, see README.md.
- Never run `setup:static-content:deploy` locally (developer mode). After template/layout changes: `bin/magento cache:flush`.

## Theme: `app/design/frontend/Elme/default` (child of `Hyva/default`, Tailwind v4)

- **Design tokens live in one place: `web/tailwind/hyva.config.json` → `tokens.values`** (colours, type scale, breakpoints, container, radius, form, button, easing, durations). `npx hyva-tokens` turns them into `generated/hyva-tokens.css` (`--color-primary` → `bg-primary`, `text-primary`, …). Do not hardcode hex values or px sizes in templates; add a token instead.
- Fonts: `web/tailwind/hyva.config.json` → `fonts` (Karla = `font-sans`, body; DM Serif Display = `font-heading`, headings). Files in `web/fonts/`.
- Breakpoints: mobile = no prefix, tablet = `md:` (768px), desktop = `lg:` (1025px), `xl:` 1280, `2xl:` 1440, `3xl:` 1920. Container is fluid up to 1920px. Write mobile first.
- Elme-specific CSS: `web/tailwind/elme.css` only imports partials in `web/tailwind/elme/` (typography, prose, header, mobile menu, mega menu, footer). Add a new file there instead of growing one stylesheet. Edited Hyvä copies are marked with an `Elme:` comment (`components/button.css`, `components/wrapper.css`).
- Build: `cd app/design/frontend/Elme/default/web/tailwind && npm run build` (or `npm run watch`). Commit `web/css/styles.css`.
- Checkout is the free Luma fallback (`hyva-themes/magento2-luma-checkout`): `/checkout` renders with `Magento/luma`, not with this theme.
- Alpine components must be CSP-compatible (see the `hyva-alpine-component` skill).

## Skills

- `elme-business` - who Elme's customers are and the B2B rules (login-only, per-customer Pronto prices, ex GST, MOQ, minimum order value, Quick Order, stock, freight). Read it before building anything that shows prices, stock or touches cart/checkout.
- `elme-port-from-luma` - how to port a block from the old `Bart/el` theme: where its sources are, how to check the live site, Hyvä rules, verification. `references/tokens-map.md` maps every old LESS variable to a token.
- `elme-qa` - run when a change is done: `scope.py` (diff + Hyvä lint) + `smoke.sh` + `browse.mjs` (new vs old site screenshots), ends with the TASK / Change log / Steps For QA block.
- `elme-look-options` - before restyling a block: a standalone HTML page in `docs/design/` with two or three looks (phone and desktop). The user picks, then the theme changes. Do not edit templates in the same step.
- Frontend polish: `web-interface-guidelines` (UI review), Emil Kowalski's `emil-design-eng` and `review-animations`. Their examples are React/Motion: here motion is CSS/Tailwind + Alpine `x-transition` (see `.claude/skills/README.md`).
- Plugins from `.claude/settings.json`: `superpowers` (brainstorm → plan → TDD → review), `frontend-design`, `playwright`.

`.claude/skills/hyva-*` are copied from github.com/hyva-themes/hyva-ai-tools (commit in `HYVA_AI_TOOLS_VERSION`). The Hyvä CMS / Hyvä UI skills are left out on purpose: those are paid products we don't use.

## Roadmap

Frontend tickets (FE-01…FE-58, UX-01…UX-12, epics, dependencies, QA checks, decisions D1–D11): `docs/frontend-tickets.md`. Update the Status column and decisions there when a ticket or decision changes.

## Source of truth for the old site

`~/Documents/BartGroup/m2-bart-group`, theme `app/design/frontend/Bart/el` (parent `Bart/jl` → `Overdose/base`), modules `app/code/ElmeLiving/*` and Elme parts of `app/code/Bart/*`. Live: https://www.elmeliving.com/
