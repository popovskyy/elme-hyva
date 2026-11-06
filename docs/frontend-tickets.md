# Elme Hyvä — Frontend Tickets

58 frontend tickets to port elmeliving.com to the new Magento 2.4.9 + Hyvä storefront in 11 epics, plus 12 UX tickets (Epic 11) that fix weak spots of the current site. Logged-out pages were checked on production on 22 Oct 2026; logged-in features (prices, cart, quick order, account) come from the old theme Bart/el and modules ElmeLiving/\*, because we had no trade login.

## How to use

Each ticket below is ready to copy into Jira: **Scope** (what the dev builds), **Ref** (production URL and old code in m2-bart-group, theme `Bart/el`), **QA** (checks QA ticks off). A ticket goes to QA only when it also meets the Definition of Done.

**Status** (Overview table): **Done** = merged into `main` from its own `Merge FE-xx` branch; **In review** = the code is in `main` but it was not merged as its own ticket or not yet checked on a running store (QA it, then set Done); **To do** = not started; **Split** = replaced by the tickets named in its title. Last synced with git history on 31 Oct 2026.

**Definition of Done (every ticket)**

- Matches production look at 390 px (mobile), 768 px (tablet), 1440 px and 1920 px (desktop); mobile-first, no horizontal scroll.
- Chrome, Safari (macOS + iOS), Firefox, Edge — latest versions.
- Logged-out and logged-in (trade customer) states both checked where the page differs.
- No console errors, no CSP violations; Alpine components CSP-compatible.
- Keyboard: every control reachable with Tab, visible focus, Esc closes drawers and modals.
- Colours, fonts and sizes come from design tokens; no hardcoded hex or px in templates.
- `styles.css` rebuilt and committed; `cache:flush` noted in the ticket if layout XML changed.

## Overview

58 FE + 12 UX tickets. **Depends on** lists tickets to finish first and open decisions (D1-D12, last section) that change the scope.

| ID | Ticket | Epic | Depends on | Status |
| --- | --- | --- | --- | --- |
| FE-01 | Design tokens, fonts, base typography | 0 Foundation | — | Done |
| FE-02 | UI kit: buttons, form fields, qty stepper, badges, breadcrumbs, pager + style-guide page | 0 Foundation | FE-01 | In review |
| FE-03 | Global feedback: messages/toasts, modal, drawer, loaders, empty states | 0 Foundation | FE-01 | In review |
| FE-04 | Page layouts and container (1 column, 2 columns left, banner + breadcrumbs, full width) | 0 Foundation | FE-01 | In review |
| FE-05 | Top bar (USP text, Become a Stockist, Inspiration) | 1 Header & nav | FE-02 | Done |
| FE-06 | Header desktop + sticky header | 1 Header & nav | FE-02, FE-03 | Done |
| FE-07 | Header mobile | 1 Header & nav | FE-06 | Done |
| FE-08 | Mega menu desktop | 1 Header & nav | FE-06, D3 | In review |
| FE-09 | Mobile menu drawer | 1 Header & nav | FE-07, FE-08 | Done |
| FE-10 | Footer | 2 Footer & blocks | FE-02 | Done |
| FE-11 | Newsletter subscribe (footer) + signup popup | 2 Footer & blocks | FE-02, FE-03, D7 | Done |
| FE-12 | "As styled by you" Instagram slider block | 2 Footer & blocks | FE-02, D10 | Done |
| FE-13 | Shared CMS blocks: "Our Difference", "Need assistance?" | 2 Footer & blocks | FE-02 | Done |
| FE-14 | Homepage hero / banner slider | 3 Homepage | FE-04, FE-57 | Done |
| FE-15 | Category tiles carousel | 3 Homepage | FE-02, FE-57 | Done |
| FE-16 | Homepage content sections (Outlet promo, Curated Catalogues, Become a Stockist, Welcome) | 3 Homepage | FE-02, FE-14, FE-57 | Done |
| FE-17 | Product carousel widget (trending / new / related) | 3 Homepage | FE-19, D1 | To do |
| FE-18 | Category page: banner, title, breadcrumbs, description | 4 PLP & search | FE-04 | To do |
| FE-19 | Product card (shared tile) | 4 PLP & search | FE-02, FE-53 | Done |
| FE-20 | PLP toolbar: sort, per page, pagination | 4 PLP & search | FE-18 | To do |
| FE-21 | Layered navigation (filters) | 4 PLP & search | FE-18, D2 | To do |
| FE-22 | Search autocomplete in header | 4 PLP & search | FE-06, D1 | To do |
| FE-23 | Search results page + no-results state | 4 PLP & search | FE-19, FE-21, D1 | To do |
| FE-24 | Recently viewed categories / products | 4 PLP & search | FE-19 | To do |
| FE-25 | PDP layout + image gallery | 5 PDP | FE-04 | To do |
| FE-26 | PDP buy box, logged out | 5 PDP | FE-25 | To do |
| FE-27 | PDP buy box, logged in (price, MOQ, add to cart/wishlist, sticky bar) | 5 PDP | FE-25, FE-53, BE-06 | To do |
| FE-28 | Configurable products: colour/size swatches to sibling products | 5 PDP | FE-27 | To do |
| FE-29 | PDP details accordion + CMS blocks + FAQ modal | 5 PDP | FE-25 | To do |
| FE-30 | PDP recommendations (frequently bought together, related) + recently viewed | 5 PDP | FE-17, FE-24 | To do |
| FE-31 | PDP stock and delivery info (stock number, dock date, delivery estimate) | 5 PDP | FE-27, D6 | To do |
| FE-32 | Header cart panel (Quick Order cart, replaces the default mini-cart) | 6 Cart & checkout | FE-06, FE-03, BE-16 | To do |
| FE-33 | Cart page (/quick-order/cart) | 6 Cart & checkout | FE-02, FE-32, BE-16 | To do |
| FE-34 | Checkout look pass + order success page | 6 Cart & checkout | BE-04, BE-05 | To do |
| FE-35 | Login, forgot password, reset password | 7 Account | FE-02 | In review |
| FE-36 | Registration: "Apply for an account" (B2B, ABN) | 7 Account | FE-02 | In review |
| FE-37 | Account layout, navigation (B2B/B2C), dashboard | 7 Account | FE-04 | To do |
| FE-38 | Account info, password, newsletter (address book in FE-69) | 7 Account | FE-37 | To do |
| FE-39 | Orders: history, order view, invoices/shipments, print (reorder + CSV in BE-14) | 7 Account | FE-37 | To do |
| FE-40 | Quick Order page | 8 B2B | FE-02, FE-03, FE-53 | To do |
| FE-41 | "Saved" toast + "Add to list" modal | 8 B2B | FE-40, FE-06, FE-32 | To do |
| FE-42 | Order templates (saved lists) | 8 B2B | FE-40 | To do |
| FE-43 | Recently ordered page | 8 B2B | FE-37, FE-19 | To do |
| FE-44 | Wishlist (ajax add + wishlist page) | 8 B2B | FE-19, FE-37 | To do |
| FE-45 | Help page template with side nav | 9 CMS | FE-04 | Done |
| FE-46 | Contact Us form | 9 CMS | FE-45 | Done |
| FE-47 | FAQs accordion | 9 CMS | FE-45, FE-57 | Done |
| FE-48 | Content pages: About, Careers, Outlet, Style Sourcebook, Meet the Team, Fairs & Events (split into FE-61...FE-67) | 9 CMS | FE-04, FE-57 | Split |
| FE-49 | Shop the Looks / lookbook with product hotspots | 9 CMS | FE-19, FE-57 | To do |
| FE-50 | Virtual Catalogues (login only) (Shoppable Instagram in FE-68) | 9 CMS | FE-53 | Done |
| FE-51 | Showroom locator (list + location page) | 9 CMS | FE-04 | In review |
| FE-52 | 404 page | 9 CMS | FE-04 | Done |
| FE-53 | Login-only rules: price hiding, guest redirects, per-customer data | 10 Cross-cutting | — | In review |
| FE-54 | Third-party scripts: Zendesk chat, Klaviyo, GTM/GA4, Triple Whale | 10 Cross-cutting | FE-01 | Done |
| FE-55 | SEO markup: structured data, breadcrumbs, meta, canonical | 10 Cross-cutting | FE-18, FE-25 | To do |
| FE-56 | Final QA pass: devices, accessibility, performance, visual compare | 10 Cross-cutting | all | To do |
| FE-57 | Content Studio for Open Source + Hyva, with a Page Builder content type | 0 Foundation | FE-01, FE-02, D11 | Done |
| FE-58 | Theme preview image (media/preview.png) in Elme + Hyvä style | 0 Foundation | FE-01 | To do |
| FE-61 | About us page | 9 CMS | FE-04, FE-57 | Done |
| FE-62 | Career opportunities page | 9 CMS | FE-04, FE-57 | Done |
| FE-63 | Outlet & clearance store page | 9 CMS | FE-04, FE-57 | Done |
| FE-64 | Style Sourcebook page | 9 CMS | FE-04, FE-57 | Done |
| FE-65 | Meet the Team page | 9 CMS | FE-04, FE-57 | Done |
| FE-66 | Fairs & Events page | 9 CMS | FE-04, FE-57 | Done |
| FE-67 | Become a Stockist page | 9 CMS | FE-04, FE-57, FE-16 | Done |
| FE-68 | Shoppable Instagram page (split from FE-50) | 9 CMS | FE-12, D10 | To do |
| FE-69 | Address book, same form as checkout (split from FE-38) | 7 Account | FE-37, BE-02 | To do |
| FE-60 | Mobile header hides on scroll down, shows on scroll up (+ button ripple) | 1 Header & nav | FE-07 | Done |
| FE-70 | Animated Add to Cart button (AJAX add, sending / Added states) | 0 Foundation | FE-02, FE-03 | In review |
| UX-01 | No empty or broken blocks | 11 UX | FE-12, FE-16, FE-17 | To do |
| UX-02 | Guest path to a trade account | 11 UX | FE-06, FE-07, FE-19, FE-26 | To do |
| UX-03 | Products above the fold on mobile PLP | 11 UX | FE-18, FE-19, FE-20 | To do |
| UX-04 | Filters quality | 11 UX | FE-21 | To do |
| UX-05 | Search relevance | 11 UX | FE-22, FE-23, D1 | To do |
| UX-06 | Chat button placement | 11 UX | FE-54, FE-21, FE-27, FE-32 | To do |
| UX-07 | Forms that autofill | 11 UX | FE-35, FE-36, FE-46 | To do |
| UX-08 | Accessibility baseline | 11 UX | FE-02, FE-03 | To do |
| UX-09 | Performance budget | 11 UX | FE-08, FE-54 | To do |
| UX-10 | Readable text | 11 UX | FE-10, FE-13, FE-15 | To do |
| UX-11 | PDP essentials first | 11 UX | FE-25, FE-29 | To do |
| UX-12 | Faster reordering for trade customers | 11 UX | FE-19, FE-32, FE-37, FE-40 | To do |

### Backend tickets

Most B2B pages need backend work the old site did in `ElmeLiving_*` modules. Each FE ticket that needs it depends on the BE ticket; checkout is BE-01...BE-05 (Epic 6).

| ID | Ticket | Needed by | Depends on |
| --- | --- | --- | --- |
| BE-06 | MOQ rules: minimum and step quantity for every add-to-cart path | FE-19, FE-27, FE-32, FE-33, FE-43, FE-44, BE-11, BE-13, BE-14 | FE-53 |
| BE-07 | Per-customer prices (Pronto price mappings) behind `CustomerPriceProviderInterface` | BE-11, price QA everywhere | FE-53 |
| BE-08 | Minimum order value per customer (attribute, service, checkout validator) | FE-33, FE-40, UX-12 | BE-04 |
| BE-09 | B2B account application backend: attributes, ABN check, emails, approval | FE-35, FE-36 | FE-53 |
| BE-10 | Account credit: outstanding balance + "on account" payment method | FE-37 (balance widget) | BE-04 |
| BE-11 | Quick Order backend: SKU search, availability, batch add to cart | FE-40 | BE-06, BE-07, BE-16 |
| BE-12 | Saved order lists (order templates) backend | FE-41, FE-42, FE-44 | BE-11, BE-16 |
| BE-13 | Recently ordered backend | FE-40, FE-43 | BE-06 |
| BE-14 | Reorder (MOQ aware) and order CSV export | FE-39 buttons | BE-06, FE-39 |
| BE-15 | Showroom locations data: module, admin, import | FE-51 | FE-04 |
| BE-16 | Quick Order cart as the only cart (replaces default mini-cart and cart page) | FE-32, FE-33, FE-40, BE-11, BE-12 | BE-06, FE-53 |

Data feeds from Pronto (prices, stock, balances, customers) are integration tickets that come later.

## Epic 0 — Foundation and global UI

Everything else builds on these four. Ship them first, so other tickets reuse the components instead of copying markup.

### FE-01 Design tokens, fonts, base typography

**Scope:** colour palette, type scale (body 15/20 Karla, headings DM Serif Display), breakpoints, container up to 1920 px, square corners, form and button metrics in `hyva.config.json` → `tokens.values`; self-hosted fonts. Mostly done.

**Ref:** `Bart/el/web/css/source/variables/*`; computed styles on https://www.elmeliving.com/

**QA:**

- [ ] H1–H6, paragraph, link on any CMS page match production font, size and line height (mobile and desktop)
- [ ] Page background warm off-white, body text charcoal, same as production
- [ ] Fonts load from our domain (Network tab), no Google Fonts request, no flash of fallback font on reload

### FE-02 UI kit + style-guide page

**Scope:** buttons (primary, secondary, outline, link, disabled, loading), text input, select, textarea, checkbox, radio, qty stepper that steps in MOQ multiples, badges (Sale, New, In Stock, Low Stock), breadcrumbs, pagination. A CMS page `/styleguide` (noindex, not in menu) shows every state so QA tests them once.

**Ref:** `Bart/el/web/css/source/variables/overrides/_buttons.less`, `_forms.less`; `Overdose_Theme/templates/styleguide`

**QA:**

- [ ] Every component on `/styleguide` has hover, focus, active, disabled and error states
- [ ] Inputs 50 px high, 1 px border; buttons square, 50 px min height
- [ ] Qty stepper with MOQ 6: +/− go 6 → 12 → 18; typing 7 rounds to a valid multiple with a message
- [ ] Focus outline visible on every control

### FE-03 Global feedback components

**Scope:** page messages (success, error, notice, warning) and toasts, modal, side drawer (used by filters, mini-cart, mobile menu), loading spinner and skeleton, empty-state block.

**QA:**

- [ ] Message after an action (e.g. login failure) shows, can be closed, does not cover the header on mobile
- [ ] Modal and drawer: open, close by ×, by overlay click and by Esc; focus stays inside while open; page does not scroll behind
- [ ] Screen reader announces messages (role=alert / aria-live)

### FE-04 Page layouts and container

**Scope:** the layouts production uses: `1column`, `2columns-left` (help pages), banner + breadcrumbs (About, Careers, Outlet), full-width CMS (Meet the Team), full-width category. Side gutters 20 px mobile, 40 px desktop.

**Ref:** body classes on production pages (`page-layout-banner-breadcrumbs`, `page-layout-cms-full-width`, `page-layout-category-full-width`)

**QA:**

- [ ] Each layout can be picked in admin for a CMS page and renders as on production
- [ ] No horizontal scroll at 390 px; content stops at 1920 px and centres on wider screens

### FE-57 Content Studio for Open Source + Hyva, with a Page Builder content type

**Scope:** copy `Bart_ContentStudio` from m2-bart-group (`app/code/Bart/ContentStudio`, last commit ee7f5d323) into `app/code/Bart/ContentStudio` here and make it a version for Magento Open Source 2.4.9 + Hyva only. From then on this copy lives on its own; nothing is synced back. Keep the module name, the admin route `bart_content_studio`, the ACL ids, the DB tables and the export/import format as they are, so the module's own `CLAUDE.md` stays valid.

1. Remove what Elme does not use. Elme sells no mattresses, and `Bart_MagefanBlog` (the blog types) is not copied.
   - Delete: the `compare_mattress` type in `etc/content_types.xml` (the file stays, with no types; the XSD allows that), `ViewModel/Type/CompareMattress.php`, `view/frontend/templates/type/compare-mattress.phtml`, `view/base/web/js/type/compare-mattress.js`, `view/frontend/web/js/storefront.js`, `view/frontend/web/css/source/_module.less`, `view/frontend/layout/default.xml`, `view/frontend/web/js/preview/bridge.js` and its `<script>` in `view/frontend/layout/bart_content_studio_preview_sandbox.xml`, `view/adminhtml/web/js/editor/preview-hotspot.js`, `Console/Command/ImportCompareMattress.php`, `Setup/Patch/Data/MoveCompareMattressCtaToComfort.php`, `Test/Unit/ViewModel/Type/CompareMattressTest.php`, `Test/Unit/Setup/Patch/Data/MoveCompareMattressCtaToComfortTest.php`.
   - `etc/di.xml`: drop the `compare_mattress` item of `Block\Entry` `typeViewModels`, the `bart_studio_import_compare_mattress` console command and the `content_studio` item of `Controller\Adminhtml\Entry\PreviewFrame` `scripts`.
   - Admin JS and LESS (`grep -rn -i mattress view/adminhtml`): remove only the branches and selectors that serve `compare_mattress` (`cs-compare-mattress__*`, range/comfort focus, dots). Generic editor behaviour stays. `view/adminhtml/web/js/editor/field/hotspot.js` (a repeater with X/Y fields per row) stays; dragging dots on the image worked only on the mattress preview markup and goes with `preview-hotspot.js`.
   - Blog: the `blog_post_body` branch in `view/frontend/templates/preview/sandbox.phtml`.
   - Unit tests that use `compare_mattress` only as a type-code string with inline fixtures stay as they are.
2. Storefront on Hyva.
   - No LESS, no RequireJS and no global script from this module. Type templates use Tailwind and tokens; a type that needs behaviour gets a CSP-compatible Alpine component in its own template (skill `hyva-alpine-component`).
   - Elme types get no JS `renderer`. For a type without one, the editor's Live Preview window reloads with the new draft 1200 ms after typing stops (`schedulePageReload` / `PAGE_RELOAD_DEBOUNCE_MS` in `view/adminhtml/web/js/editor/state.js`); `bridge.js` was only needed for renderer types, which is why it goes.
   - Sandbox page (`bart_content_studio/preview/sandbox`): rebuild `sandbox.phtml` and the "Draft" badge in Tailwind (badge fixed bottom-centre, above the sticky header). `view/frontend/web/js/preview/badge.js` (plain script, countdown) stays.
   - Add `app/code/Bart/ContentStudio` to `hyva.config.json` -> `tailwind.include`.
3. Admin on Open Source 2.4.9 and PHP 8.4 (the `elme_php` container).
   - The editor (RequireJS + LESS, admin only) stays as it is. The module depends only on Open Source modules (Cms, Widget, Backend, Store, Ui, ImportExport, MediaStorage).
   - `Controller/Adminhtml/Entry/PreviewFrame.php` (the grid eye-button preview) links Luma's `css/styles-m.css` and `css/styles-l.css` inside Luma page wrappers. Switch it to the theme's `css/styles.css` and to the wrappers a Hyva CMS page has (read them from a CMS page on https://elme-hyva.local, do not guess them).
4. New Page Builder content type "Content Studio".
   - In the Page Builder panel under Add Content (`menu_section="add_content"`), next to Block, with the same parents as Block (`root-container`, `row`, `column`, `tab-item`). The editor drags it onto the stage, picks an entry, and the stage shows that entry with the Elme storefront styles, the way Block shows a CMS block. It works wherever Page Builder is used (CMS pages, CMS blocks, product attributes).
   - Build it like Page Builder's own `block` type in `vendor/magento/module-page-builder`: `view/adminhtml/pagebuilder/content_type/block.xml`, `view/adminhtml/ui_component/pagebuilder_block_form.xml` and `pagebuilder_block_select_grid.xml`, `view/adminhtml/web/js/content-type/block/preview.js`, `view/adminhtml/web/js/content-type/block/mass-converter/widget-directive.js`. Add `Magento_PageBuilder` to the module sequence.
   - Picker: a grid of entries of `standalone="true"` types, searchable by title and identifier (the same set the widget chooser `Block/Adminhtml/Widget/Chooser.php` offers).
   - Saved content holds the same directive the "Content Studio Entry" widget inserts, `{{widget type="Bart\ContentStudio\Block\Entry" identifier="..."}}`, so the storefront renders it through `Block\Entry` with no Page Builder JS, and the widget keeps working for WYSIWYG fields.
   - Stage preview: an `<iframe>` of `bart_content_studio/entry/previewFrame` (admin-served, same origin, renders the entry under the store theme with its CSS; fixed in point 3). Do not put the entry HTML into the admin page the way Block does: with the theme `styles.css` in the admin document Tailwind's base styles restyle the whole admin, and without it the block is unstyled. The frame takes the height of its content and has `pointer-events: none`, so Page Builder's select, move, duplicate and remove work as for Block. The stage shows the server-rendered state; Alpine does not run there. `previewFrame` takes `entry_id` and `store_id`: store the entry id next to the identifier, or let the route also accept `identifier`; `store_id` is the default store view.
   - No entry picked: a placeholder like Block's. Entry deleted: a notice in the stage; the storefront renders nothing, same as the widget.
5. First Elme type, `promo_split`, so the editor and the Page Builder type can be tested on real content.
   - Elme types are declared in `app/code/Elme/Theme/etc/content_types.xml` (Elme_Theme is already in `tailwind.include`; add `Bart_ContentStudio` to its `etc/module.xml` sequence). FE-14...16 and FE-47...49 add their types to the same file.
   - `promo_split` is the Outlet promo on the production home page: image left, heading, text, button (label and link). Template `Elme_Theme::content-type/promo-split.phtml`, Tailwind, tokens and the FE-02 button classes; `recommended` on the image field set to the image size production uses.
6. Repo rules for the copy.
   - English ASCII only in every copied file, the module `CLAUDE.md` included: em dashes, arrows, curly quotes and the ellipsis character become ASCII; visible UI characters become HTML entities or `\u{...}` escapes; the multibyte case in `SchemaValidatorTest` keeps testing multibyte through `"\u{043C}"`.
   - Update the module `CLAUDE.md`: drop the compare_mattress, blog and Sleeping Giant parts, add the Page Builder type and the Hyva notes from this ticket.
   - Not in this ticket: JS renderers for Elme types, the other Elme types, migrating old content, Hyva CMS or CMS Tailwind JIT (D11).

**Ref:** `m2-bart-group/app/code/Bart/ContentStudio` (its `CLAUDE.md` explains the schema, editor, preview and import/export); the Page Builder `block` type files listed in point 4; Outlet promo on the production home page https://www.elmeliving.com/

**QA:**

- [ ] On a clean install `setup:upgrade` and `setup:di:compile` run without errors (PHP 8.4 container)
- [ ] Module unit tests pass; `LC_ALL=C grep -rnP '[^\x00-\x7F]' app/code/Bart/ContentStudio` finds nothing
- [ ] Admin: create, edit, save, Save & Copy and delete a `promo_split` entry; store scope applies
- [ ] Type picker shows only Elme types, no `compare_mattress` or blog types
- [ ] "Open Live Preview" opens the unsaved entry on the Elme sandbox page; an edit shows after the reload; the badge counts down
- [ ] Grid eye-button preview shows the entry with Elme styles
- [ ] Page Builder: "Content Studio" is under Add Content; dragged onto a CMS page and given an entry, the stage shows it styled like the storefront; select, move, duplicate and remove work as for Block
- [ ] The saved CMS page shows the entry on the storefront, the same as the "Content Studio Entry" widget inserted through the WYSIWYG
- [ ] `promo_split` matches the production Outlet promo at 390, 768, 1440 and 1920 px
- [ ] Export entries as ZIP locally, import on staging: same entries and images
- [ ] Storefront loads no RequireJS, LESS or JS file from Bart_ContentStudio; no CSP errors

### FE-58 Theme preview image

**Scope:** replace `app/design/frontend/Elme/default/media/preview.png` (today a copy of the Hyvä default picture) with a preview in Elme style: warm off-white, sage/olive accents, DM Serif Display wordmark "Elme Living", a small "Hyvä" tag, ideally a cropped desktop screenshot of the new storefront. Same size as the Hyvä preview: 400 × 277 px PNG.

**Ref:** `vendor/hyva-themes/magento2-default-theme/media/preview.png` (size); tokens in `web/tailwind/hyva.config.json`; logo `web/images/logo.svg`

**QA:**

- [ ] Admin → Content → Design → Themes shows the new preview for Elme/default
- [ ] Image is 400 × 277 px, crisp, under 150 KB
- [ ] It reads as Elme at a glance (palette, type) and says it is the Hyvä build

### FE-70 Animated Add to Cart button

**Scope:** one add to cart button for PLP tiles and the PDP. Click: the normal button feedback (ripple, press) at once. Sending: the label fades, the cart slides to the centre and a dot keeps dropping into it until the server answers. Success, only after the server confirmed it: the cart leaves to the right, a check with "Added" comes in from the left, holds 1.5 s, then the button is back to "Add to Cart". Error: the button goes straight back and the message shows in the toast stack (FE-03). Square button in site colours (no pill). The button never changes size; icon-only buttons show only the check. Nothing is blocked: add many items in a row, requests run one after another so they never race on the quote; a second click on a button that is still sending is ignored. Reduced motion: no movement, the cart pulses. Out of scope: Quick Order, product image flying to the header cart.

**How:** add the class `btn-atc` to the submit button of an add to cart form. `Magento_Theme::elme/atc-button.phtml` (one delegated listener, CSP-safe) posts the form with `X-Requested-With`, so `checkout/cart/add` answers JSON, reads the `messages` section for errors (so the page message bar does not repeat "You added …"), reloads section data for the mini-cart. CSS: `web/tailwind/elme/add-to-cart.css`. Wired on the PLP tile (`product/list/item.phtml`) and the PDP button (`product/view/addtocart.phtml`; "Update item" on cart configure keeps the normal post). FE-19 and FE-27 must keep `btn-atc` on their buttons. "Redirect to cart after adding" = Yes in admin still redirects after the success state.

**Look:** variant 1 "Cart and dot" picked on 31 Oct 2026 (2 olive fill and 3 rolling label + count were rejected).

**Ref:** Dribbble shot the ticket was made from (not checked: Dribbble showed a bot check); compare the sequence with the reference GIF/frames before QA sign-off.

**QA:**

- [ ] Sequence checked against the reference GIF/frames from the product owner
- [ ] Logged in, PLP: Add to Cart shows sending, then "Added" for about 1.5 s, then back; mini-cart count goes up without a page reload
- [ ] PDP (simple and configurable with options chosen): same, qty from the qty field is added
- [ ] Configurable on PLP without a swatch chosen / qty above stock: button goes back, error toast with Magento's message, no "Added"
- [ ] Expired form key (other tab logged out): button goes back, error toast, no "Added"
- [ ] Click Add to Cart on five tiles quickly: every one ends in "Added", the cart has all five, no duplicates; double-click on one tile adds it once
- [ ] Button width and height do not change in any state, desktop (label) and tablet (icon only)
- [ ] No green "You added … to your shopping cart" bar under the header after an AJAX add
- [ ] Screen reader hears "Added to cart"; errors are announced by the toast; focus stays on the button
- [ ] OS "Reduce motion" on: no sliding, cart pulses while sending; no console errors or CSP violations

## Epic 1 — Header and navigation

The header on production: dusty-blue top bar, then logo, mega menu (New, Flowers & Plants, Homewares, Furniture, Outdoor, Sale), search field, Log in/Sign up, Contact Us. Logged-in users also get the mini-cart and the Quick Order cart.

### FE-05 Top bar

**Scope:** strip above the header: USP text on the left ("Australia's leading wholesale supplier of artificial flora and home decor"), links on the right (Become a Stockist, Inspiration → /shop-the-looks). Text and links editable in admin (CMS block).

**QA:**

- [ ] Text and links match production; links open the right pages
- [ ] On mobile the text wraps to two lines at most and does not overlap the header
- [ ] Changing the CMS block in admin changes the bar after cache flush

### FE-06 Header desktop + sticky header

**Scope:** logo (link to home), main menu slot, search field "Search products by style, rooms", account link (logged out: "Log in/Sign up"; logged in: customer name + account menu), Contact Us, mini-cart icon with item count and Quick Order cart icon (logged in only). Header sticks to the top on scroll down.

**Ref:** `Bart/el/Magento_Theme/layout/default.xml` (`od.header.custom.links`, `od.header.init-sticky`), `ElmeLiving_QuickOrder/templates/minicart/qo-minicart.phtml`

**QA:**

- [ ] Logged out: no cart icons, "Log in/Sign up" goes to login
- [ ] Logged in: name shown, cart count updates after add to cart without page reload
- [ ] Sticky header appears on scroll, does not jump the page, does not cover anchors
- [ ] Cached page (Varnish on) never shows another customer's name or cart count

### FE-07 Header mobile

**Scope:** burger, logo centred, search icon that opens the search field, account and cart icons. Same login rules as FE-06.

**QA:**

- [ ] 390 px: all icons tappable (min 44 × 44 px), logo not cut off
- [ ] Search opens, focuses the input, closes by × or Esc

### FE-08 Mega menu desktop

**Scope:** 5–6 top items; dropdown with category columns down to level 3 (e.g. Flowers & Plants → Arrangements → Orchid). Opens on hover with a short delay, also by keyboard. Source of the menu tree depends on D3.

**Ref:** `Bart/el/Snowdog_Menu/templates/menu.phtml`, `menu/sub_menu.phtml`, `menu/node_type/*`

**QA:**

- [ ] Every top item and link matches production menu (count and order)
- [ ] Dropdown opens on hover, closes on mouse leave without flicker when moving diagonally
- [ ] Tab / Enter / Esc open and close the dropdown; current category highlighted
- [ ] Menu at 1025 px fits on one line

### FE-09 Mobile menu drawer

**Scope:** off-canvas drawer from the burger; drill-down per level with Back and "View all \<category>" links; bottom links (account, Quick Order, Contact, Become a Stockist).

**Ref:** `Bart/el/Snowdog_Menu/templates/menu/mobile-footer.phtml`, `Bart_NavigationModern/templates/html/mobile_menu_custom.phtml`

**QA:**

- [ ] All 3 levels reachable; Back returns one level; "View all" opens the category
- [ ] Drawer closes by ×, overlay, Esc; page behind does not scroll
- [ ] Same tree as desktop menu

## Epic 2 — Footer and global blocks

The footer and the blocks repeated above it on most pages (Instagram slider, "Our Difference", "Need assistance?").

### FE-10 Footer

**Scope:** logo; three link columns — Shop (Quick Order, Furniture, Plants, Homewares, Shop the Looks, Virtual Catalogues, Shop Instagram, Style Sourcebook, Outlet & Clearance Store), Service (About Us, Meet the Team, Showroom Locator, Become a Stockist, Fairs & Events, Careers), Help (Contact, Shipping & Returns, Terms & Conditions, Privacy Policy, FAQs, Submit a Product Claim → help.elmeliving.com); social icons (Facebook, Instagram, Pinterest, LinkedIn, TikTok); payment icons (Visa, Mastercard). Columns collapse to accordions on mobile. Links editable in admin.

**Ref:** `Bart/el/Magento_Theme/templates/html/od-footer/links.phtml`, `logo.phtml`

**QA:**

- [ ] All links match production and open the right page; external links open in a new tab
- [ ] Mobile: each column is an accordion, one tap opens/closes it
- [ ] Social icons have accessible names ("Elme Living on Instagram")

### FE-11 Newsletter subscribe + signup popup

**Scope:** footer form "Be the first to know" (email + arrow button). Production also shows a signup popup on first visit ("Get 5% off your first purchase", first/last name, email, phone, company, business type) — run by Klaviyo; whether we rebuild it or keep Klaviyo depends on D7.

**Ref:** `Bart/el/Magento_Newsletter/templates/footer-subscribe.phtml`, `Bart_NewsletterPopup`

**QA:**

- [ ] Empty or invalid email shows an inline error, no request sent
- [ ] Valid email shows a success message; the same email again shows "already subscribed"
- [ ] Popup (if in scope): shows once per visitor, closes by ×/Esc/overlay, not shown to logged-in customers

### FE-12 "As styled by you" Instagram slider

**Scope:** heading, @elmeliving, Follow Us button, horizontal image slider of Instagram posts; on home, PDP and some CMS pages. Feed source depends on D10.

**Ref:** `Overdose_InstaFeed`, CMS block on production home

**QA:**

- [ ] Slider swipes on touch, arrows/keyboard work on desktop, images lazy-load
- [ ] Clicking a post opens Instagram (or the shoppable view, if FE-50 builds it)
- [ ] If the feed fails, the block hides instead of showing an empty frame

### FE-13 Shared CMS blocks

**Scope:** "Our Difference" (heading + paragraph) and "Need assistance?" (phone (03) 7067 9725, hello@elmeliving.com, showroom 1 Apollo Court, Blackburn VIC 3130) as reusable CMS blocks with Elme styling.

**QA:**

- [ ] Phone is a `tel:` link and email a `mailto:` link
- [ ] Both blocks render the same on every page they appear on

## Epic 3 — Homepage

Production home, top to bottom: hero banners → category tiles carousel → Welcome text + Become a Stockist → Outlet promo → "Be Inspired" shop-the-look → trending products → Curated Catalogues → Become a Stockist benefits → Instagram (FE-12) → Our Difference (FE-13). Each section is a Content Studio entry (FE-57) placed on the page, so content editors can fill and reorder them; content is new, nothing is copied from the old site.

### FE-14 Hero / banner slider

**Scope:** full-width banner(s) with image (separate mobile and desktop image), heading, text, CTA; slider when more than one. Content Studio type `hero`.

**Ref:** `Bart_Swiper/templates/swiper.phtml`, `block-banners` widget on production home

**QA:**

- [ ] Mobile loads the mobile image, desktop the desktop one (Network tab)
- [ ] First banner image is not lazy-loaded (LCP); others are
- [ ] Autoplay (if on) pauses on hover and has a pause control

### FE-15 Category tiles carousel

**Scope:** carousel of category tiles (image + name: Vessels, Decor, Floral Stems, Bouquets & Bundles, Coffee Tables …) with arrows; Content Studio type `category_tiles` (repeater: category, image, label).

**Ref:** `Bart_Catalog/templates/category/widget/with_children.phtml`

**QA:**

- [ ] Tiles link to the right categories; swipe on mobile, arrows on desktop
- [ ] Long names wrap without breaking the tile height

### FE-16 Homepage content sections

**Scope:** Welcome text + Become a Stockist button; Outlet promo (image left, heading, text, "Find Us In-Store" button); Curated Catalogues (dusty-blue band, Lookbook and Virtual Catalogues cards, "See All Catalogues"); Become a Stockist benefits (The Latest Trends, Premium Customer Service, Extensive Range, "Create An Account"). Built as Content Studio types `catalogue_covers` and `usp_strip`; the Outlet promo is a `promo_split` entry (type built in FE-57).

**QA:**

- [ ] Each section matches production layout on mobile (stacked) and desktop (two columns)
- [ ] All buttons go to the right pages
- [ ] Editor can change texts and images in admin without a developer

### FE-17 Product carousel widget

**Scope:** reusable slider of product cards (FE-19) with title; sources: trending, new, a category, related. Used on home, PDP (FE-30), 404 and cart. Data source depends on D1 (Algolia Recommend or native).

**Ref:** `ElmeLiving_AlgoliaRecommend` (trending, related, frequently bought together)

**QA:**

- [ ] Logged out: cards show "Sign in to see pricing"; logged in: the customer's price
- [ ] Swipe on mobile, arrows on desktop, no layout shift while loading
- [ ] Empty result hides the whole block, including the title

## Epic 4 — PLP and search

Production category page: banner with title and breadcrumbs, a Filters button that opens a drawer, sort dropdown, 4-column grid (2 on mobile), 24 per page, pagination. Test data: /flowers-plants.html (\~1,200 products), /new.html (many filters).

### FE-18 Category page header

**Scope:** category banner: a short photo band (lifestyle image, cropped, copy overlaid at the bottom left: breadcrumbs, the H1 and the description). A compact text header when the category has no image.

**Ref:** `Bart/el/Overdose_CategoryBanner/templates/category/banner.phtml`, `banner_wrapper.phtml`

**QA:**

- [ ] Category with image vs without image both look right
- [ ] Breadcrumbs show the full path for a level-3 category and each crumb links
- [ ] Title is the only H1 on the page

### FE-19 Product card (shared tile)

**Scope:** one card used on PLP, search, carousels, wishlist: image (white tile), Sale/New badge, name (2 lines max), SKU, price ex. GST or "Sign in to see pricing" when logged out, stock label ("In Stock" ≥ 10, "Low Stock" 1–9, none at 0), wishlist heart. Logged in, when "Quick order on PLP" is enabled: qty stepper (MOQ multiples) + Add to cart. Customer price loads after render (FE-53): show a placeholder, not a flash of the wrong price.

**Ref:** `Bart/el/Magento_Catalog/templates/product/list.phtml`, `category-qo-plp-enable.phtml`, `Cloudinary_Cloudinary/templates/product/image_with_borders.phtml`, `ElmeLiving_Catalog/view/frontend/web/template/product/*`

**QA:**

- [ ] Logged out: no price, no add to cart, "Sign in to see pricing" links to login and returns to the same page after login
- [ ] Logged in: price matches the cart price for that customer (different customers see different prices)
- [ ] Stock label correct for qty 0, 5, 50
- [ ] Add to cart with MOQ product adds the MOQ, mini-cart count updates
- [ ] Card heights align in a row even with long names

### FE-20 PLP toolbar

**Scope:** sort (Position, Product Name, SKU, Best Sellers, Date, Price low–high, Price high–low), per page 12/24/36/48, pagination with first/last and ellipsis. Sort by price only for logged-in users.

**QA:**

- [ ] Each sort option reorders the grid and stays in the URL on reload and on back/forward
- [ ] Changing page scrolls to the top of the grid
- [ ] Page beyond the last returns the last page or 404, not an empty grid

### FE-21 Layered navigation

**Scope:** Filters button → drawer (mobile and desktop as on production). Filters seen on production: Colour (swatches), Material, Stock Availability (In Stock / Low Stock), Plant Type, Width (cm) and Depth (cm) ranges, Comfort. Multi-select, counts, "selected filters" chips with remove and Clear all. Engine depends on D2.

**Ref:** `Bart/el/Aheadworks_Layerednav/templates/layer/view.phtml`, `filter/renderer/swatches.phtml`

**QA:**

- [ ] Selecting two values in one filter shows products with either value; counts update
- [ ] Chips show the selection; removing one chip updates the grid; Clear all resets
- [ ] Filtered URL can be shared and opens with the same filters
- [ ] Drawer: Apply/Close work, focus trapped, Esc closes

### FE-22 Search autocomplete

**Scope:** dropdown under the header search after 2–3 characters: products (image, name, SKU), categories, "see all results". Search by SKU must work (customers order by SKU). Engine depends on D1.

**Ref:** `Bart/el/Bart_AlgoliaSearchUi/templates/search.phtml`, `instant/hit.phtml`

**QA:**

- [ ] "vase" shows vases; a full SKU (e.g. PL.186.GYGN) shows that product first
- [ ] Arrow keys move through results, Enter opens, Esc closes
- [ ] No price shown logged out

### FE-23 Search results page

**Scope:** results grid with the same cards, toolbar and filters as PLP; no-results state with "Shop popular categories" links.

**Ref:** `Bart/el/Algolia_AlgoliaSearch/templates/instant/wrapper.phtml`, `facet.phtml`

**QA:**

- [ ] Query shown in the heading, results count correct
- [ ] "asdfgh" shows the no-results state with popular categories

### FE-24 Recently viewed categories / products

**Scope:** "Recently viewed" strip on PLP (and PDP via FE-30) from browser storage.

**Ref:** `ElmeLiving_RecentlyViewedCategory`, `Overdose_RecentlyViewed`

**QA:**

- [ ] After visiting 3 products/categories they show newest first; current page not included
- [ ] Hidden when the list is empty

## Epic 5 — PDP

Production PDP: gallery left, info right (badge, name, SKU, stock, short description, buy box), then a "Product Details" accordion, recommendations, Instagram, Our Difference. Test product: /acacia-hanging-bush-28-x-14-x-110cm-grey-green.html (simple, Sale); for MOQ and configurable products pick SKUs from admin.

### FE-25 PDP layout + gallery

**Scope:** breadcrumbs; gallery with main image, arrows, thumbnails, swipe on mobile, fullscreen with zoom; badge, name (H1), SKU, stock line, short description.

**Ref:** `Bart/el/Magento_Catalog/templates/product/view/gallery.phtml`, `view/form.phtml`, `layout/catalog_product_view.xml`

**QA:**

- [ ] Product with 1 image: no arrows or thumbnails; with 5+: thumbnails scroll
- [ ] Fullscreen opens on click, closes by ×/Esc, zoom works on desktop, pinch on mobile
- [ ] Main image is not lazy-loaded; no layout shift when it loads

### FE-26 PDP buy box, logged out

**Scope:** "Sign in to see pricing", "Don't have an account? Apply here" (→ registration), Login button (→ login, then back to this product).

**QA:**

- [ ] No price, qty or add to cart anywhere in the HTML (view source), not only hidden by CSS
- [ ] After login the customer lands back on the same PDP and sees the buy box from FE-27

### FE-27 PDP buy box, logged in

**Scope:** customer price ex. GST (loaded per customer, placeholder while loading), Sale price shown as was/now, MOQ note ("Minimum order 6"), qty stepper in MOQ multiples, Add to cart, Add to wishlist, Add to list (Quick Order lists, FE-41), sticky add-to-cart bar after scrolling past the buy box. No tier prices on Elme.

**Ref:** `Bart/el/Magento_Catalog/templates/product/moq-stock.phtml`, `od-product/sticky_add_to_cart.phtml`, `ElmeLiving_QuickOrder/templates/product/view/addtocart.phtml`, `ElmeLiving_CustomPricing/templates/get_prices_script.phtml`

**QA:**

- [ ] Two test customers with different Pronto prices see different prices; cart shows the same price as PDP
- [ ] Price label says ex. GST
- [ ] MOQ product: qty starts at MOQ, steps in multiples, invalid qty cannot be added
- [ ] Add to cart shows a success message and updates the mini-cart; out-of-stock product cannot be added
- [ ] Sticky bar appears after scrolling, its button adds the same qty

### FE-28 Configurable products (colour / size)

**Scope:** colour and size swatches; clicking a swatch goes to the sibling product page (Elme has 147 configurables; swatch = navigation, not an in-page switch). Swatches on PLP cards too.

**Ref:** `Bart/el/Bart_ConfigurableByAttribute/templates/product/options.phtml`, `product/listing/options.phtml`, `Overdose_ConfigurableByAttribute`

**QA:**

- [ ] Current colour is marked; clicking another opens that product's URL
- [ ] Unavailable sizes are crossed out, not hidden
- [ ] Swatch has a tooltip / accessible name with the colour name

### FE-29 Product details accordion

**Scope:** "Product Details" accordion with Details, Product Specifications, Dimensions, Delivery, Care Instructions (empty sections hidden); CMS blocks between sections (middle and material blocks); FAQ modal.

**Ref:** `Bart/el/Magento_Catalog/templates/product/tabs.phtml`, `product/view/attribute.phtml`, `product/cms-block.phtml`, `Magento_Theme/templates/html/faq.phtml`

**QA:**

- [ ] Each section opens/closes; several can be open at once
- [ ] Product with no care text has no Care Instructions row
- [ ] Attribute values match admin (dimensions in cm)

### FE-30 PDP recommendations + recently viewed

**Scope:** Frequently bought together, Related products, recommendation tabs, Recently viewed — using the carousel (FE-17) and the card (FE-19).

**Ref:** `Bart/el/ElmeLiving_AlgoliaRecommend/templates/recommendation/wrap/*`

**QA:**

- [ ] Blocks show for a product that has related data and hide for one that has none
- [ ] Current product never appears in its own recommendations

### FE-31 PDP stock and delivery info

**Scope:** "N in stock" number and label, incoming (dock) date when out of stock, delivery estimate; showroom availability. Data from Pronto / shipment service — scope depends on D6.

**Ref:** `Bart/el/Bart_OverdoseStorelocator/templates/product/view/product-stock-info.phtml`, `product-availability.phtml`, `Machship_Fusedship/layout/catalog_product_view.xml`

**QA:**

- [ ] Stock number matches admin salable qty for VTA stock
- [ ] Out-of-stock product with a dock date shows the date in AU format (e.g. 12 Nov 2026)

## Epic 6 — Cart and checkout

Cart and checkout are login-only. Checkout is our own module `Elme_Checkout` on this theme (D12); the Luma fallback checkout was removed on 29 Oct 2026. It is one page (address, delivery, payment, order details, place order, summary on the side), the server keeps the checkout state and Alpine only renders it, and every section or place-order check is a plug-in, so Elme features are added later without touching the core.

| Ticket | Part | Depends on |
| --- | --- | --- |
| BE-01 | Foundation: module, page shell, checkout state, order summary, AU GST rule | FE-02 |
| BE-02 | Delivery and billing address | BE-01 |
| BE-03 | Delivery and payment methods | BE-02 |
| BE-04 | Place order, validation, terms and conditions, e2e test | BE-02, BE-03 |
| BE-05 | Order reference (PO) and delivery instructions, through to admin, email and My Orders | BE-04 |
| FE-34 | Look pass and order success page | BE-04, BE-05 |

Checkout features (delivery date calendar, account credit payment, Elme freight, minimum order value) come later as their own backend tickets on top of this module.

### FE-32 Header cart panel (Quick Order cart)

**D14:** the cart is the Quick Order cart (BE-16), there is no default Magento mini-cart or cart page.

**Scope:** drawer from the header cart icon: items (image, name, SKU, price ex. GST, qty stepper in MOQ multiples, remove), subtotal, "View cart" and "Checkout" buttons, empty state.

**Ref:** `Bart/el/Magento_Checkout/layout/checkout_cart_sidebar_item_renderers.xml`, `Magento_Weee/templates/checkout/cart/item/price/sidebar.phtml`

**QA:**

- [ ] Opens after add to cart and from the icon; closes by ×/overlay/Esc
- [ ] Changing qty updates subtotal and header count; qty below MOQ is refused with a message
- [ ] Remove asks no browser confirm dialog and updates totals

### FE-33 Cart page (/quick-order/cart)

**D14:** the cart is the Quick Order cart (BE-16), there is no default Magento mini-cart or cart page.

**Scope:** item table (image, name, SKU, options, unit price, qty, row total, remove), update qty, MOQ messages, minimum order value notice ("Add $X more to place your order"), coupon code, totals (subtotal ex. GST, freight, GST, total), Proceed to checkout, empty cart page with a link back to shopping. No gift cards (Adobe Commerce only).

**Ref:** `Bart/el/Magento_Checkout/templates/cart/form.phtml`, `cart/item/default.phtml`, `cart/methods.phtml`, `Magento_Tax/layout/checkout_cart_index.xml`

**QA:**

- [ ] Customer below minimum order value sees the remaining amount and cannot proceed
- [ ] Valid coupon applies and shows in totals; invalid coupon shows an error
- [ ] Mobile: items stack as cards, totals below, checkout button easy to reach
- [ ] Guest opening /checkout/cart/ is sent to login with a message

### FE-34 Checkout look pass + order success page

**Scope:** success page in Elme/default (override of Hyva `Magento_Checkout::success.phtml`): heading, order number linking to the order in My Account, what happens next, delivery address, order reference and delivery instructions when given (BE-05), "Continue shopping" and "View order"; no "Create an account" block. Look pass over the new checkout built in BE-01...BE-05: tokens, focus states, section transitions (reduced motion respected), empty / loading / error states.

**Ref:** `Bart/el/Magento_Checkout/templates/success/success-main.phtml`, `templates/onepage/footer/checkout-help.phtml`; Hyva `Magento_Checkout/templates/success.phtml`

**QA:**

- [ ] After an order: success page with the order number linking to the order, reference and instructions when given; both buttons work
- [ ] No "Create an account" block on the success page
- [ ] Checkout and success page pass the web-interface-guidelines review: labels, visible focus, contrast, reduced motion

## Epic 7 — Login, registration, customer account

Customers are businesses with an ABN; the account area is split into B2B and B2C sections with their own breadcrumbs.

### FE-35 Login, forgot password, reset password

**Scope:** /customer/account/login: Email, Password, "I forgot?", Sign In, "Don't have an account? Apply for an account". Forgot password page (email + Reset Password + "Return back to Login"), reset password form.

**Ref:** `Bart/el/Magento_Customer/templates/od-form/newcustomer.phtml`, `od-form/resetforgottenpassword.phtml`

**QA:**

- [ ] Wrong password shows an error; account pending approval shows the right message
- [ ] Login from a PDP / gated page returns there afterwards
- [ ] Reset email arrives, link opens the reset form, new password works; expired link shows an error
- [ ] Password field has show/hide; browser autofill works

### FE-36 Registration "Apply for an account"

**Scope:** B2B application form: First/Last Name, Company Name, location, Business Type (Retailer, Commercial, Trade, Media, Other, Online Retailer), Job Title (optional), Phone, Email, Accounts Email, Accounts Contact Name, Accounts Phone, ABN (validated), Company Address (street, suburb, state, postcode), Terms & Conditions checkbox, password. Success page explains the account is pending approval.

**Ref:** `Bart/el/Magento_Customer/templates/od-form/register.phtml`, `widget/company_address.phtml`, `widget/termsconditions.phtml`, `widget/additional-attributes.phtml`, `ElmeLiving_Customer`

**QA:**

- [ ] Required fields marked; submitting empty shows errors next to each field
- [ ] Invalid ABN is rejected with a clear message; valid ABN passes
- [ ] Existing email shows "already registered" with a login link
- [ ] Admin receives the B2B registration notification email

### FE-37 Account layout, navigation, dashboard

**Scope:** account sidebar navigation grouped B2B / B2C (becomes a dropdown or drawer on mobile), account breadcrumbs, dashboard: contact info, default addresses, account credit (outstanding balance), recent orders.

**Ref:** `Bart/el/Magento_Customer/templates/od-account/navigation/account-nav.phtml`, `od-account/dashboard/*`, `ElmeLiving_CustomerAccountNavigation`

**QA:**

- [ ] Every nav link opens its page and the active item is highlighted
- [ ] Account credit shows the customer's balance from admin
- [ ] Mobile: navigation usable with one hand, current page name visible

### FE-38 Account info, password, newsletter

**Scope:** edit account info and change password; newsletter preferences. The address book is FE-69 (same form as checkout). Saved cards (Vault) are dropped until Elme takes card payments.

**Ref:** `Bart/el/Magento_CustomerCustomAttributes/templates/od-account/*`, `Magento_Customer/templates/od-account/address/*`, `od-account/newsletter/newsletter.phtml`, `Magento_Vault/templates/cards_list.phtml`

**QA:**

- [ ] Changing the password logs in with the new one; wrong current password is refused
- [ ] Newsletter checkbox state persists after save

### FE-69 Address book

**Scope:** address book grid, add / edit / delete, set default shipping and billing. Reuses the checkout address form from BE-02 (company fields, AU states, 4-digit postcode, autocomplete).

**QA:**

- [ ] Add, edit, delete, set default address; postcode/state validation for Australia
- [ ] The form looks and validates the same as in checkout

### FE-39 Orders

**Scope:** order history table (number, date, ship-to, total, status, actions; cancel where allowed), order view (items, totals, addresses, shipping, payment, order reference and delivery instructions), invoices / shipments / credit memos tabs, print. Reorder and CSV export buttons use BE-14.

**Ref:** `Bart/el/Magento_Sales/templates/od-order/*`, `Magento_AsyncOrder/templates/order/history.phtml`, `Magento_OrderCancellationUi/templates/order/history.phtml`

**QA:**

- [ ] History paginates; status labels readable; mobile shows orders as cards
- [ ] Order view shows reference and delivery instructions when the order has them
- [ ] Print view has no header/footer clutter

## Epic 8 — B2B: Quick Order, order templates, wishlist

Quick Order is the core page for trade customers: they reorder by SKU. On the old site it is 56 theme files (`ElmeLiving_QuickOrder`) plus its own controllers under /quick-order; the backend (controllers, tables) needs its own port ticket before these.

### FE-40 Quick Order page

**Scope:** /quick-order (login only): search by SKU or name with suggestions, add rows, qty per row in MOQ multiples, live availability per row, row price and total ex. GST, remaining amount to minimum order value, "Add all to cart" with per-row validation errors, "Recently ordered" list to add from. Separate mobile layout (cards) and desktop layout (table).

**Ref:** `Bart/el/ElmeLiving_QuickOrder/templates/page/quick-order.phtml`, `components/quick-order/*`, `script/quick-order-js.phtml` (973 lines)

**QA:**

- [ ] Guest opening /quick-order is sent to login, then back to Quick Order
- [ ] Typing a SKU suggests matching products; picking one adds a row with MOQ as qty
- [ ] Unknown SKU or out-of-stock item shows a row error and is skipped on Add all
- [ ] Add all: valid rows land in the cart, invalid rows stay with the reason
- [ ] Minimum order value remainder updates as rows change
- [ ] 50 rows stay responsive (no lag typing qty)

### FE-41 Saved toast, Add to list

**Scope:** "Saved" toast after saving a list; "Add to list" modal from PDP and wishlist (pick an existing list or create one).

**Ref:** `Bart/el/ElmeLiving_QuickOrder/templates/minicart/qo-minicart.phtml`, `script/qo-saved-toast-js.phtml`, `add-to-list/modal.phtml`, `page/quick-order-cart.phtml`

**QA:**

- [ ] Add to list from PDP: modal lists existing lists, new list can be named and saved
- [ ] Toast confirms and disappears; header Quick Order count updates

### FE-42 Order templates (saved lists)

**Scope:** list of saved order templates (name, items, date, actions), open a template into Quick Order, add all to cart, delete with confirm modal.

**Ref:** `Bart/el/ElmeLiving_QuickOrder/templates/page/order-templates.phtml`, `components/order-templates/list-table.phtml`, `modal-delete.phtml`

**QA:**

- [ ] Template opens with its items and quantities; delete removes it after confirm
- [ ] Empty state when the customer has no templates

### FE-43 Recently ordered page

**Scope:** /recently\_ordered: products from past orders with image, name, SKU, price, stock, qty (MOQ aware), add to cart, remove from list, review link.

**Ref:** `ElmeLiving_RecentlyOrdered/view/frontend/templates/recently-ordered/*`, `Bart/el/ElmeLiving_RecentlyOrdered/templates/recently-ordered/item/cart.phtml`

**QA:**

- [ ] Lists products from the test customer's past orders, newest first
- [ ] Add to cart respects MOQ; removed item stays removed after reload

### FE-44 Wishlist

**Scope:** add/remove from PLP and PDP without page reload (heart state), wishlist page with price, stock, qty, add to cart, add to Quick Order list, remove; guests sent to login.

**Ref:** `Bart/el/Overdose_WishlistAjax/templates/ajaxWishlist/wishlist.phtml`, `Magento_Wishlist/templates/od-wishlist/*`

**QA:**

- [ ] Heart toggles on PLP and PDP and stays toggled after reload
- [ ] Wishlist page add to cart moves the item with MOQ qty
- [ ] Guest clicking the heart goes to login and returns

## Epic 9 — CMS pages

All content is new; nothing is migrated from the old site. Plain text pages (T&C, Privacy, About) use Page Builder with Elme styles; structured blocks are Content Studio types (FE-57). Writing the content is a content task, not part of these tickets. All URLs below exist on production today; /blog returns 404 there and is left out.

### FE-45 Help page template with side nav

**Scope:** two-column layout with a side menu of help pages (Contact Us, Shipping & Returns, Terms & Conditions, Privacy Policy, FAQs, Become a Stockist); optional top banner. Side menu becomes a dropdown on mobile.

**Ref:** production /shipping-and-returns, /privacy-policy (body classes `help-page`, `help-page-banner`); `Bart/el/Magento_VersionsCms/templates/hierarchy/menu.phtml`

**Redesign (31 Oct 2026, help centre look):** trail and both columns centred in one frame (`--help-shell-max`), serif H1 with a sage rule. Below lg one column: the side menu is a swipeable row of chips above the title (current page scrolled into view) and "Need assistance?" moves under the content. From lg: sticky side column with a white "Help centre" menu card (icon per page, charcoal bar + filled icon on the current page, arrow on hover) and a charcoal "Need assistance?" card. Legal pages: serif H2 sections on hairlines, numbered "On this page" card in two columns, body text `ink-muted` (4.5:1 on the page colour). Become a Stockist: lead intro, numbered steps 01-03 on a hairline (no boxes), facts panel with check icons.

**QA:**

- [ ] All six pages use the template; current page highlighted in the side menu
- [ ] Long legal text readable: line length max \~80 characters, headings spaced

### FE-46 Contact Us form

**Scope:** /contact-us: Your Name, Email, Phone, Company, Business Type, Enquiry type (select), Message with 200-character counter, "Receive emails on promotions" opt-in, Submit; success and error messages.

**Ref:** `Bart/el/Magento_Contact/templates/form.phtml`, `Overdose_ContactForm/templates/widget/contact_form.phtml`

**QA:**

- [ ] Required fields validated; counter stops at 200
- [ ] Submit sends the email to the store contact address and shows success
- [ ] Form has spam protection (reCAPTCHA or honeypot)

### FE-47 FAQs accordion

**Scope:** /faqs grouped by topic (Location, Delivery, …) as an accordion; Content Studio type `faq` (repeater: question, answer, topic) so editors add questions without a developer.

**QA:**

- [ ] Each question opens/closes, keyboard accessible
- [ ] Link to a question (#anchor) opens it

### FE-48 Content pages

**Split** into one ticket per page: FE-61 About us, FE-62 Career opportunities, FE-63 Outlet & clearance store, FE-64 Style Sourcebook, FE-65 Meet the Team, FE-66 Fairs & Events, FE-67 Become a Stockist. Each scope includes the matching item of the marketing review in the FE-48 log.

### FE-61 About us page

**Scope:** /about-us: banner + breadcrumbs layout (FE-04; Content Studio `hero`, first heading is the page H1), Page Builder prose with Elme styles, FE-13 "Need assistance?" / "Our Difference" at the foot. The page ends with the same stockist CTA as Become a Stockist: "Create an Account" primary, "Talk to our team" (Meet the Team) secondary.

**Ref:** production /about-us

**QA:**

- [ ] Layout matches production at 390 and 1440 px, apart from the agreed changes
- [ ] One H1 (the banner heading); breadcrumbs Home > About us
- [ ] Page ends with the stockist CTA: one primary button, one secondary

### FE-62 Career opportunities page

**Scope:** /career-opportunities: banner + breadcrumbs layout. Open roles come from a Content Studio type `vacancies` (title, state, employment type, email) and render as rows: title, state, type, "Apply by email" (mailto). With no roles the page shows one "No current vacancies" message with one email address (live repeats the same paragraph six times and mixes hello@ and careers@).

**Ref:** production /career-opportunities

**QA:**

- [ ] Layout matches production at 390 and 1440 px, apart from the agreed changes
- [ ] No roles: exactly one "No current vacancies" message and one email address
- [ ] Two roles: two rows with title, state, type and a working "Apply by email" link

### FE-63 Outlet & clearance store page

**Scope:** /outlet-and-clearance-store: banner + breadcrumbs layout. A "Visit us" card directly under the banner: address, "Get directions" (maps link), phone (tel:), opening hours as a two-column list (Mon-Sat 9-5, Sun 10-5: two rows, not seven). Then "What You'll Find". The fine print (final sale, may include minor imperfections) becomes a short FAQ: Is it open to the public? Can I hold an item? Is stock on the website? Use the FE-47 accordion if it is built, otherwise native details/summary.

**Ref:** production /outlet-and-clearance-store

**QA:**

- [ ] Layout matches production at 390 and 1440 px, apart from the agreed changes
- [ ] Visit us card is the first block under the banner on mobile and desktop; directions and phone links open maps and the dialer
- [ ] Hours show as two rows; FAQ items open and close with mouse and keyboard

### FE-64 Style Sourcebook page

**Scope:** /style-sourcebook: banner + breadcrumbs layout, Page Builder content with Elme styles. The copy is new: do not carry over the live wording that says "James Lane".

**Ref:** production /style-sourcebook

**QA:**

- [ ] Layout matches production at 390 and 1440 px
- [ ] One H1; breadcrumbs Home > Style Sourcebook
- [ ] No "James Lane" text anywhere on the page

### FE-65 Meet the Team page

**Scope:** /meet-the-team: Content Studio type `team` (photo, name, role, email, phone, region: Head office / VIC / NSW / QLD / WA / SA-TAS). Card: photo, role small above the name, email (mailto:) and phone (tel:) as links with large tap targets. A row of region chips above the grid (All, VIC, NSW, ...) filters the cards (CSP-safe Alpine toggle). Grid: 1 column mobile, 2 tablet, 4 desktop. Live lists every manager with email and mobile; keep that.

**Ref:** production /meet-the-team

**QA:**

- [ ] Grid: 1 column at 390 px, 2 at 768 px, 4 at 1440 px
- [ ] Email and phone are links that open mail and the dialer; tap targets at least 44 px
- [ ] Chip VIC shows only VIC people, All shows everyone; chips work with the keyboard

### FE-66 Fairs & Events page

**Scope:** /fairs-events: Content Studio type `events` (name, start date, end date, city, venue, stand, text, link). Events whose end date has passed hide automatically, without a cache flush; the next event comes first. Card: date block on the left (day + month), the range written out ("31 Jul - 4 Aug 2027"), an "On now" label when today falls inside the range, CTA "Book a time with your rep" (Meet the Team or mailto), not "Learn more". With nothing upcoming: one line "No fairs scheduled right now" plus a link to the Blackburn showroom.

**Ref:** production /fairs-events

**QA:**

- [ ] An event whose end date has passed is not shown, without a cache flush
- [ ] With no upcoming events the page says so once and links to the showroom
- [ ] Events are sorted by start date; an event running today shows "On now"

### FE-67 Become a Stockist page

**Scope:** /become-a-stockist (already a help page from FE-45, keep its side nav): a three-step "How it works" row (Apply with your ABN, We review and approve, Log in to see your prices and order); a facts strip with the UX-02 facts (ABN required, minimum order applies with no amount, delivery Australia-wide); the three benefits through the FE-16 `usp_strip` type (reuse it, no second copy); "Create an Account" as the single primary button, "Talk to our team" (Meet the Team) secondary. This is the target of the UX-02 "Apply for a trade account" banner.

**Ref:** production /become-a-stockist

**QA:**

- [ ] Layout matches production at 390 and 1440 px, apart from the agreed changes
- [ ] How it works shows three steps in order; facts strip shows no minimum order amount
- [ ] Exactly one primary button (Create an Account); Talk to our team goes to /meet-the-team

### FE-49 Shop the Looks / lookbook

**Scope:** /shop-the-looks and the "Be Inspired" home block: lifestyle images with "+" hotspots; a hotspot opens a product popup (image, name, "Sign in to see pricing" or price, "View »", qty + add to cart when logged in). Content Studio type `lookbook`: image with hotspots pinned in % of the frame, each linked to a SKU.

**Ref:** production /shop-the-looks; dragging hotspots on the image as in `m2-bart-group/app/code/Bart/ContentStudio/view/adminhtml/web/js/editor/preview-hotspot.js` (it was mattress-only and is not in this repo's copy, see FE-57)

**QA:**

- [ ] Hotspot positions stay on the same object at 390, 768, 1440 px
- [ ] Popup opens on tap/click, closes on outside click and Esc
- [ ] Logged-in add to cart from the popup works with MOQ

### FE-50 Virtual Catalogues

**Scope:** /virtual-catalogues: logged out shows "Sign in to see our brand new catalogues!", logged in shows the catalogue embeds/links. Shoppable Instagram moved to FE-68.

**QA:**

- [ ] Logged out never sees catalogue links (check HTML too)
- [ ] Logged in sees and opens each catalogue

### FE-68 Shoppable Instagram page

**Scope:** /shoppable-instagram: intro text + Instagram grid where each post links to its products. Feed source depends on D10; reuses FE-12.

**QA:**

- [ ] Each post shows its linked products and they open the PDP
- [ ] If the feed fails the page shows the intro and a link to Instagram, not an empty grid

### FE-51 Showroom locator

**Scope:** /showroom-locator: search by suburb or postcode, "Use current location", filter by state (NSW, QLD, VIC), result list (name, address, "See Location"), map; location page with address, hours, map, directions link. Locations include the Melbourne Distribution & Pick-up Centre (Tarneit) and the trade-only Melbourne Showroom (Blackburn).

**Ref:** `Bart/el/Overdose_Storelocator/templates/location/list.phtml`, `location/view.phtml`, `Bart_OverdoseStorelocator`

**QA:**

- [ ] Postcode 3130 lists Blackburn first; state filter VIC shows only VIC
- [ ] Denying location permission shows a message, not a blank page
- [ ] Location page map and directions link open the right address

### FE-52 404 page

**Scope:** "Oops... Looks like something went wrong", Back to home and Contact us buttons, search field, optional product carousel (FE-17).

**QA:**

- [ ] Any unknown URL returns HTTP 404 with this page (not 200)
- [ ] Buttons go to home and contact

## Epic 10 — Cross-cutting

Rules that touch many pages. FE-53 is built early, next to FE-19, because every price and stock block depends on it.

### FE-53 Login-only rules and per-customer data

**Scope:** guests never get prices, qty inputs or add-to-cart in the HTML; "Sign in to see pricing" everywhere a price would be. Guests opening cart, checkout, Quick Order, wishlist, Virtual Catalogues are redirected to login with "Please login to access this page" and returned afterwards. Per-customer prices, cart count and names load per customer (customer section data / ajax), never cached in full-page cache.

**Ref:** `ElmeLiving_Checkout` (`DisableGuestPage`, `DisableGuestAction`), `ElmeLiving_ElmeLiving` (`HideForGuest`, `HideForLoggedIn`), `ElmeLiving_CustomPricing`

**QA:**

- [ ] With Varnish on: log in as customer A, then B in another browser; each sees own prices and cart
- [ ] Logged-out view source of PLP and PDP contains no price numbers
- [ ] Every gated URL redirects guests and returns them after login

### FE-54 Third-party scripts

**Scope:** Zendesk chat ("Can we help?" button), Klaviyo (forms, tracking), GTM / GA4, Triple Whale pixel; loaded after the page is interactive, allowed in CSP, consent-ready. Chat button must not cover the sticky add-to-cart or mobile cart buttons.

**QA:**

- [ ] No CSP errors in the console on home, PLP, PDP, cart
- [ ] Chat opens and sends a message; on mobile it does not cover buttons
- [ ] GA4 / GTM receives page\_view and add\_to\_cart (GTM preview)

### FE-55 SEO markup

**Scope:** Product, BreadcrumbList, Organization JSON-LD (no price for guests if price is hidden); meta title/description, canonical on filtered and paged PLP, noindex on search and account pages; one H1 per page.

**Ref:** `ElmeLiving_MirasvitSeoMarkup`

**QA:**

- [ ] Google Rich Results Test passes for a PDP and a PLP
- [ ] Canonical on /flowers-plants.html?p=2 and on a filtered URL is correct

### FE-56 Final QA pass

**Scope:** one regression round over the whole storefront before launch: real devices (iPhone Safari, Android Chrome, iPad), accessibility (keyboard-only walk-through, contrast, screen reader on key flows), Lighthouse on home, PLP, PDP (target: Performance ≥ 90 mobile, CLS < 0.1, Accessibility ≥ 95), side-by-side visual compare with production.

**QA:**

- [ ] Key flow logged in: search SKU → PDP → add to cart → Quick Order add → cart → checkout → success → order in account
- [ ] Key flow logged out: home → menu → PLP filter → PDP → "Apply here" → registration submitted
- [ ] Lighthouse targets met; results attached to the ticket

## Epic 11 — UX/UI improvements over production

Production audit on 22 Oct 2026, logged out, 1440 px and 390 px. Each ticket fixes a weak spot found there and names the FE tickets it extends: build it into those tickets during the port, then QA it here.

| What we found on production | Where |
| --- | --- |
| Curated Catalogues block is an empty dusty-blue area ~1,100 px tall; Instagram slider shows grey tiles; recommendations block renders empty for guests | Home, PDP |
| Search "vase" returns 747 results, first hits are "Vale Muse … Sofa"; facets include Bed Type and Queen/King sizes | Search |
| Filters drawer: "Material" listed twice, empty first row, width/depth ranges sorted as text (171–180 before 40–49), no result count on Apply | PLP |
| Mobile category banner + title push the first product to ~600 px; product names cut at 2 lines hide the dimensions | PLP mobile |
| Zendesk chat button covers "Apply filters", product cards and PDP description on mobile | Mobile, all pages |
| Login and registration inputs have `autocomplete="off"`; phone is `type="text"`; ABN and postcode open the text keyboard; 4 address fields have no label; 17 fields in one block | Login, registration |
| No skip link; ~130 focusable elements with no visible focus; 2 H1 on home (hidden title + "Added to cart" modal); 75 images with empty alt, product images included; 91 tap targets under 24 px | All pages |
| 217 script requests, ~6,600 DOM nodes, 19 third-party hosts (Nosto, Clarity, Videowise, Rakuten, TikTok, Facebook, DoubleClick, Klaviyo, Zendesk, Yotpo, Google Fonts …), ~155 console errors per page; 88 of 91 images without width/height | All pages |
| "Our Difference" is justified small grey text (rivers on mobile); footer links ~12 px; category tile labels on a thin bar, "Coffee Tables" clipped | Home, footer |
| Mobile header shows "Sign Up" that opens login, no account icon; every card repeats "Sign in to see pricing" and nothing tells a guest what a trade account gives | Header, PLP, PDP |

### UX-01 No empty or broken blocks

**Extends:** FE-12, FE-16, FE-17

**Scope:** any block whose data is empty or fails to load (catalogues, Instagram, recommendations) is not rendered, including its heading. Catalogues show cover images with links, not an embed that can stay blank.

**QA:**

- [ ] With the Instagram feed and recommendations switched off, home has no empty frames or grey tiles
- [ ] Curated Catalogues shows covers for guests (link goes to login) and for customers

### UX-02 Guest path to a trade account

**Extends:** FE-06, FE-07, FE-19, FE-26

**Scope:** header label says what it is ("Trade login" + account icon on mobile, not "Sign Up" that opens login). PLP and search get one slim banner for guests: "Wholesale prices for registered retailers — Log in / Apply for a trade account", with 3 facts (ABN required, minimum order, delivery Australia-wide). Cards show a short "Log in for price" instead of the long sentence.

**QA:**

- [ ] Guest understands from PLP alone that prices need a trade account and where to apply
- [ ] Banner is not shown to logged-in customers
- [ ] Header link text and target match on mobile and desktop

### UX-03 Products above the fold on mobile PLP

**Extends:** FE-18, FE-19, FE-20

**Scope:** on mobile the category header is compact (title + breadcrumbs, small or no banner image), the Filters / Sort bar sticks under the header while scrolling, product name shows in full, dimensions on their own line under the name.

**QA:**

- [ ] At 390 × 844 the first product row is at least partly visible without scrolling
- [ ] Filters / Sort bar stays reachable after scrolling 3 screens
- [ ] "Adara Coffee Table Travertine & …" style truncation no longer hides size or material

### UX-04 Filters quality

**Extends:** FE-21

**Scope:** one entry per attribute (no duplicate Material), numeric ranges sorted numerically, no empty rows, Apply button shows the count ("Show 124 products"), Filters button shows the number of active filters, PLP default sort is the merchandised position (production shows "Product Name").

**QA:**

- [ ] Width (cm) options read 30–39, 40–49, … 221–230
- [ ] Apply button count matches the grid after applying
- [ ] Default sort of /new.html is position, not name

### UX-05 Search relevance

**Extends:** FE-22, FE-23, D1

**Scope:** exact SKU match first, then name matches; typo tolerance off for short words so "vase" does not match "Vale"; facets limited to attributes of the categories in the results.

**QA:**

- [ ] "vase" first page shows only vases and vessels
- [ ] Full SKU (e.g. VG.228.ML) returns that product first
- [ ] Vase results show no Bed Type or mattress size facets

### UX-06 Chat button placement

**Extends:** FE-54

**Scope:** smaller Zendesk launcher, lifted above sticky bars (add to cart, filters, cookie bar), hidden while a drawer or modal is open and in checkout.

**QA:**

- [ ] At 390 px the launcher never covers a button or a price
- [ ] Filters drawer and mini-cart open without the launcher on top

### UX-07 Forms that autofill

**Extends:** FE-35, FE-36, FE-46

**Scope:** correct `autocomplete` on every field (`email`, `current-password`, `given-name`, `organization`, `tel`, `street-address`, `postal-code` …), `type="tel"` for phones, `inputmode="numeric"` for ABN and postcode, a visible label on every field. Registration split into sections Business, Contact, Accounts, Address; ABN lookup fills the company name; address autocomplete fills suburb, state, postcode.

**QA:**

- [ ] Browser password manager offers saved login on /customer/account/login
- [ ] Chrome autofill fills name, company, phone, address in registration
- [ ] On iPhone ABN and postcode open the number keypad
- [ ] Screen reader reads a label for every field

### UX-08 Accessibility baseline

**Extends:** FE-02, FE-03, FE-56

**Scope:** skip link to main content, visible focus on every control, one H1 per page (modals use H2), real alt text on product and content images, accessible names on icon links, tap targets at least 44 × 44 px on mobile, text contrast at least 4.5:1.

**QA:**

- [ ] Keyboard-only: skip link first, focus always visible, no trap
- [ ] axe DevTools: 0 serious or critical issues on home, PLP, PDP, login, registration
- [ ] Each page has exactly one H1

### UX-09 Performance budget

**Extends:** FE-08, FE-54, FE-56

**Scope:** list every third-party script with marketing and keep only agreed ones (target ≤ 8 hosts), loaded after interaction or idle; fonts from our domain only; every image with width/height, below-fold images lazy; mega menu HTML not duplicated for mobile; 0 console errors.

**QA:**

- [ ] Lighthouse mobile on home, PLP, PDP: Performance ≥ 90, CLS < 0.1, LCP < 2.5 s
- [ ] Network tab: third-party hosts match the agreed list
- [ ] Console has no errors on home, PLP, PDP, cart

### UX-10 Readable text

**Extends:** FE-10, FE-13, FE-15

**Scope:** body text left-aligned (no justify), at least 15 px, charcoal not light grey on long paragraphs; footer links at least 14 px with 44 px row height on mobile; category tile labels under the image, never clipped.

**QA:**

- [ ] "Our Difference" on mobile has no wide gaps between words
- [ ] Longest category name on the tiles carousel shows in full at 390 px

### UX-11 PDP essentials first

**Extends:** FE-25, FE-29

**Scope:** dimensions, material and MOQ / carton quantity shown next to the buy box, not only in the accordion; mobile gallery uses swipe with visible dots or thumbnails instead of arrows over the image.

**QA:**

- [ ] Dimensions visible without opening any accordion
- [ ] Mobile gallery arrows do not cover the product

### UX-12 Faster reordering for trade customers

**Extends:** FE-19, FE-32, FE-37, FE-40

**Scope:** proposals to validate with 2–3 real customers before building: paste or CSV upload of "SKU, qty" lines into Quick Order; "Buy again" list on the account dashboard; minimum order value progress bar in the mini-cart; quick add from search results.

**QA:**

- [ ] Paste of 20 "SKU qty" lines creates 20 Quick Order rows; bad SKUs are flagged
- [ ] Progress bar shows the remaining amount and disappears once the minimum is met

## Open decisions

These decisions change the scope of the tickets named in **Affects**. Make them before those tickets start. Open Source has no Adobe Commerce features (dynamic banners, customer segments, content staging, gift cards), so nothing may depend on them.

| # | Decision | Production today | Options | Affects |
| --- | --- | --- | --- | --- |
| D1 | Search and recommendations engine | Algolia InstantSearch + Algolia Recommend | Keep Algolia (Hyvä compat module) · native OpenSearch + Hyvä autocomplete, related/upsell from admin | FE-17, FE-22, FE-23, FE-30 |
| D2 | Layered navigation | Aheadworks Layered Navigation | Aheadworks + Hyvä compat · Hyvä native filters with our own ranges and chips | FE-21, FE-23 |
| D3 | Menu source | Snowdog Menu (built in admin) | **Decided:** Snowdog Menu (`snowdog/module-menu` ≥2.24, native Hyvä templates; no separate compat module). Local patch for Symfony `execute(): int` on Magento 2.4.9. | FE-08, FE-09 |
| D4 | Product reviews | Yotpo enabled, not visible to guests | Keep Yotpo · drop reviews | FE-25, FE-43 |
| D5 | Lookbook hotspots | MGS Lookbook | **Decided:** Content Studio type `lookbook` (FE-57) | FE-49, FE-16 |
| D6 | PDP stock / delivery data | Pronto stock, shipment service, Fusedship rates, showroom stock | Which of these exist on the new instance at launch | FE-31 |
| D7 | Newsletter and signup popup | Klaviyo popup + footer form | Klaviyo forms · Magento newsletter + own popup | FE-11, FE-54 |
| D8 | Product images | Cloudinary | Keep Cloudinary · native Magento images | FE-19, FE-25 |
| D9 | Blog | Magefan Blog templates exist, /blog is 404 | Drop (recommended) · port | — |
| D10 | Instagram feed | Overdose InstaFeed | Instagram API module · static images in CMS | FE-12, FE-50 |
| D11 | CMS content | Adobe Page Builder + LESS classes | **Decided:** new content, no migration; Page Builder for plain text pages, own copy of Content Studio (Open Source + Hyva) for structured blocks, placed with its Page Builder content type or widget; no CMS Tailwind JIT | FE-14...16, FE-47...49, FE-57 |
| D12 | Checkout | Luma checkout (Adobe Commerce) with Elme Knockout mixins | **Decided:** own module `Elme_Checkout` on Elme/default (one page, server-side state, CSP Alpine). Luma fallback checkout removed; no Hyva Checkout (paid licence per installation) | FE-34, BE-01...BE-05 |
| D13 | Home page layout | Hero, statement, dark Outlet band, catalogues, collage, Our Difference, dark Need assistance band | **Decided (31 Oct 2026):** "Calm gallery": hero with the H1 (made taller on 2 Nov 2026: 4:5 on phones, at least 680px or 32vw on desktop, at most 75svh, so the photos show), intro with three trade facts, all categories in a grid, Outlet + Lookbook + Virtual Catalogues as three photo cards, Become a Stockist collage with button, Instagram; Our Difference only on About us, Need assistance? in the footer; motion only in the hero and the Become a Stockist collage (4 Nov 2026: scroll reveals removed from the other home sections; seeded by `SeedHomepageContentSections`) | FE-14...16, FE-12, FE-13 |
| D14 | Cart | Custom Quick Order cart on a per-customer list, default mini-cart and cart page removed | **Decided (2 Nov 2026, chief engineer):** port it. The working cart is the list `cart_list` (sku + qty), edits save without touching the quote, and the quote is rebuilt from it when /checkout opens. Open: migration of saved lists, place of the coupon field | BE-16, FE-32, FE-33, FE-40...FE-44 |
| D15 | Public APIs | Core anonymous REST and GraphQL open | **Decided (2 Nov 2026):** closed for guests, because they gave prices and placed guest orders on the login-only site. `Elme_LoginOnly` refuses anonymous REST and SOAP routes (only `/V1/integration/admin/token` stays open) and all guest GraphQL. `Elme_Checkout` refuses API orders from customers and guests, so the place-order validators cannot be skipped; admin and integration tokens stay allowed. A feature that needs a public route adds it to `allowedPaths` in `Elme_LoginOnly/etc/webapi_rest/di.xml` | FE-53, BE-08, payment webhooks |

Out of scope for this list: transactional email templates, checkout features (delivery date calendar, account credit, freight) that come with their backend tickets, and writing the new CMS content.
