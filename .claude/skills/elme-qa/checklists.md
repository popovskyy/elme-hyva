# elme-qa checklists

Work through the sections the diff touches. Items marked (old) are traps carried over from the old
Adobe Commerce codebase that apply here too.

## Blast radius

- **Consumers of every changed PHP class** - `scope.py` lists them. Open each one and decide whether
  its behaviour changes. A plugin or preference on a shared class reaches every user of it (old).
- **Wiring lives in XML, not only PHP.** Before concluding "nothing uses X", grep `di.xml`, layout XML,
  `ui_component` XML, `hyva.config.json` and `tailwind-source.css` (old).
- **Theme overrides.** A file in `Elme/default` replaces the Hyvä parent's copy (`scope.py` says
  `overrides Hyvä ...`). Diff it against `vendor/hyva-themes/magento2-default-theme/...`: anything the
  parent does that the copy dropped (an escaper, an aria attribute, a ViewModel call) is a finding.
- **Module template shadowed by the theme** (`scope.py` says `OVERRIDDEN`): the storefront never sees
  the module's version.
- **Tailwind reach.** Classes count only where Tailwind scans: theme `.phtml` / `.xml` and the module
  paths in `generated/hyva-source.css` (from `hyva.config.json` → `tailwind.include` and compat modules).
  A class in a module template outside that list renders unstyled.
- **Checkout is Luma.** `/checkout` and the paths in `hyva_theme_fallback/general/list_part_of_url` run
  on `Magento/luma` (Hyvä Luma checkout fallback): Tailwind and Alpine do nothing there. A change meant
  for checkout is LESS/Knockout in a Luma child, or it does not reach checkout at all.
- **Scope** - trace every hunk back to the ticket. An untraceable hunk is a finding, even when it is an
  improvement (old).

## Business rules (read `elme-business` for the area)

Most B2B rules are not built on this instance yet. For each one the change touches, say whether it is
in scope of the ticket, built, or still missing - never assume the old site's behaviour exists here.

- Logged out: no price, no qty input, no add to cart in the HTML; "Sign in to see pricing".
- Logged in: price ex GST; per-customer price loads after render (placeholder, no flash of the wrong
  price); MOQ steps in multiples; minimum order value message.
- Private data (customer name, cart count, prices) never ends up in a full-page-cached HTML. Check with
  Varnish on: load the page as customer A, then as guest; nothing of A's may show.

## Senior backend architect

- **Declarative schema**: column in `db_schema.xml` *and* `db_schema_whitelist.json`; a sensible default
  for existing rows; `smoke.sh` confirms nothing is pending (old).
- **New module**: its `'Vendor_Module' => 1` line is in `app/etc/config.php`, otherwise the next
  `module:disable` of anything writes it as 0 and `setup:upgrade` drops its tables (old).
- **DI and constructors**: developer mode generates DI on the fly and hides errors that
  `setup:di:compile` catches. If the diff adds constructor arguments, plugins or preferences, run the
  compile (SKILL.md, step 4).
- **Caching**: storefront output behind Varnish needs cache identities or an explicit clean when admin
  data changes; customer-specific output goes through Hyvä private content (section data), not FPC.
- **Config**: website/store defaults go in the module's `etc/config.xml`, not in a `config:set` the next
  environment never gets. A new external integration defaults OFF.
- **Security**: `$escaper->escapeHtml/escapeHtmlAttr/escapeUrl/escapeJs` on every template output;
  `ADMIN_RESOURCE` on admin controllers; POST actions implement `HttpPostActionInterface` and check the
  form key (old).
- **PHP 8.4**: an undefined array key is a warning that Magento turns into an exception on web
  requests; typed properties; `declare(strict_types=1)` in new files.
- **No Adobe Commerce APIs**: no `row_id`, Staging, CustomerBalance, GiftCard, TargetRule, Banner classes.
  Code copied from `m2-bart-group` often carries them.
- **Unit tests**: if the module has `Test/Unit`, a feature adds a test; otherwise say none was run.

## Senior frontend (Hyvä)

- **Look at every screenshot.** An unstyled page is 200 with no console error.
- **Tokens only**: colours, fonts, sizes, radius from `hyva.config.json` → `tokens.values` (`scope.py`
  lints hex and arbitrary px in templates). A missing value becomes a token, not a one-off.
- **Breakpoints**: mobile (no prefix), tablet `md:` 768, desktop `lg:` 1025, `xl:` 1280, `2xl:` 1440,
  `3xl:` 1920. A JS `matchMedia` uses the same numbers. Check 375 and 1440 always; 768, 1025 and 1920
  when the layout changes.
- **Alpine CSP**: no inline object in `x-data`, no assignments, negations or arguments inside `@click`
  etc., no `x-model` (`scope.py` lints these). Components are registered functions.
- **No Luma JS**: no jQuery, RequireJS, `x-magento-init`, `data-mage-init`, Knockout in Hyvä templates.
- **Private content**: customer and cart data from the `private-content-loaded` event; reserve its space
  so the layout does not shift when it arrives.
- **Parity** for a ported block: `browse.mjs both:/path` at the same viewports and compare old vs new.
  Differences are either a listed UX improvement or a finding.
- **Polish**: `web-interface-guidelines` skill on the changed templates (focus, labels, forms, motion);
  for motion `review-animations`. Every animation has a `motion-reduce:` fallback - check with
  `browse.mjs --reduced-motion`.
- **CSS rebuilt and committed**: `smoke.sh` compares `styles.css` with a fresh build.
- **Known noise, not regressions**: third-party script errors (Klaviyo, Zendesk, GTM) are counted as
  noise; on `old:` targets every error is the old site's own.

## Admin UI

- `browse.mjs --admin` logs in and resolves secret keys. A 2FA screen means the 2FA modules got
  re-enabled: `bin/clinotty bash -c "bin/magento module:disable Magento_TwoFactorAuth Magento_AdminAdobeImsTwoFactorAuth"`.
- Toggles are Knockout checkboxes: click `[data-index="<field>"] .admin__actions-switch-label`; setting
  `input.value` saves nothing (old).
- Grid filters persist in `ui_bookmark` across sessions: clear them before and after a check (old).
- Grid rows arrive by XHR: wait before reading them.

## Test data

- This instance holds test data only (a fresh install plus what developers added). Record every row you
  create or change and remove or restore it before reporting.
- Create products and customers through the admin or REST (`/rest/V1/...` with an admin token), so
  indexes and URL rewrites follow; reindex and flush caches after direct SQL.
- Never submit forms, log in or add to cart on the old live site (`old:` targets are read-only).
