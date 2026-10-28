# Bart_ContentStudio

Schema-driven content blocks for the storefront, with a custom admin editor and
live preview. Contentful's model, scaled down: you describe a **content type**
in XML, the admin builds the form for it automatically, and a template renders
the resulting **entry** on the storefront.

It exists to replace a pattern that does not hold up - structured blocks built
by pasting markup or a JSON blob into a CMS block, with no validation, no
preview and no store scoping.

**This is the Elme Living copy** (Magento Open Source 2.4.9 + Hyva, PHP 8.4),
taken from `m2-bart-group` (`app/code/Bart/ContentStudio`, commit ee7f5d323) in
FE-57. It lives on its own from here on; nothing is synced back. The module
name, the admin route `bart_content_studio`, the ACL ids, the DB tables and the
export/import format are unchanged, so entries move between the two with the
ZIP export/import.

---

## Mental model

```
<module>/etc/content_types.xml --> Schema\Config --+--> admin: editor builds the form from the schema
   (merged, cached)                                +--> admin: live preview
                                                   +--> frontend: Block\Entry picks the type's template

bart_content_studio_entry (+ _store pivot)  --> one row per entry, content is JSON in `data`
```

Three things are worth internalising before changing anything:

1. **The schema is the source of truth.** Nothing about a content type is
   hard-coded in PHP or JS. Adding a type is XML plus a template.
2. **The storefront renders on the server.** A type's template is a Hyva
   template: Tailwind classes and theme tokens, no LESS, no RequireJS, no
   script from this module. A type that needs behaviour gets a CSP-compatible
   Alpine component in its own template. The optional JS `renderer` serves
   **only** the editor's in-panel preview; Elme types have none.
3. **The editor mutates state in exactly one place.** `editor/state.js` owns the
   data. Field modules only *describe* DOM and mark it with `data-*`; one
   delegated listener per event type on `#studio-editor` turns those markers
   into state changes. Do not attach listeners in field renderers - repeaters
   re-render constantly and per-element listeners leak.

---

## Adding a new content type

Two files. No PHP class.

**1. Declare it.** This module's own `etc/content_types.xml` declares no types.
The config is merged across modules (the reader uses
`Magento\Framework\App\Config\FileResolver`), and Elme's types live in
**`Elme_Theme/etc/content_types.xml`** (Elme_Theme has `Bart_ContentStudio` in
its sequence). `promo_split` there is the working example to copy.

```xml
<type code="promo_split" label="Promo: Image + Text" standalone="true"
      group="home" icon="image-text"
      description="Image on the left, heading, text and a button on the right."
      template="Elme_Theme::content-type/promo-split.phtml">
    <field name="image"   type="image"    label="Image"   required="true" recommended="999x428"/>
    <field name="heading" type="textarea" label="Heading" required="true" maxlength="80"/>
    <field name="text"    type="wysiwyg"  label="Text"/>
</type>
```

Only `code` and `label` are required. `standalone="true"` makes it appear in the
"New entry" picker, the widget chooser and the Page Builder picker.

**2. The template**, as named in `template`. It receives `$block` =
`Bart\ContentStudio\Block\Entry`; `$block->getContent()` is the entry data.
Tailwind and tokens only (`app/design/frontend/Elme/default/web/tailwind/
hyva.config.json`); `Elme_Theme` is in that file's `tailwind.include`, so
classes in its templates are compiled. Of this module only `view/frontend` and
`Controller/Adminhtml/Entry/PreviewFrame.php` are included: the whole module
would also scan this file and the admin JS, and any word there that is a
utility name (`snap`, `invert`) would land in the storefront CSS. Rebuild
the theme CSS after a template change (`npm run build` in the theme's
`web/tailwind`).

**3.** `bin/magento cache:clean config`.

The admin form, validation, undo/redo, outline tree and import/export all come
from the schema for free.

### Field kinds
`text` `textarea` `wysiwyg` `number` `boolean` `select` `url` `image` `icon`
`datetime` `reference` `reference_multi` `group` `repeater` `blocks`

`wysiwyg` stores **HTML**, reduced on save to a fixed allow-list by
`Model/Schema/HtmlSanitizer.php` - `p strong b em i u h2 h3 h4 ul ol li
blockquote a sup sub br`, everything else unwrapped, `<script>`/`<style>`/
`<iframe>` dropped with their contents, and `href` held to the same
protocol-relative guard `url` fields use. It is deliberately **not** on the
`strip_tags` path `text`/`textarea` take: that would destroy body copy rather
than clean it. The control today is a source textarea with a tag-wrapping
toolbar; the TinyMCE version stores the same HTML, so swapping it is an editor
change with no migration behind it. A template that prints it should still pass
it through `$escaper->escapeHtml($html, $allowedTags)` - an imported entry has
not been through the editor's save.

`datetime` stores an ISO-8601 **UTC** instant at seconds precision
(`2026-07-31T10:00:00Z`). The control is `<input type="datetime-local">`, which
has no timezone at all, so `lib/datetime.js` converts at both edges - and
`readControlValue()` in `state.js` is the read half, which is why that lib is
in `lib/` and not in the field module.

`renderer="price"` on a `text` field masks to `$3,999` as you type (dollar,
digits, commas). The stored value is still a string - `SchemaValidator` keeps
treating it as `text`.

`renderer="hotspot"` on a `repeater` (`field/hotspot.js`) is a list of points on
an image, each row with its own X/Y fields in percent of the image. Positions
are typed in; there is no drag-on-image control.

`reference` / `reference_multi` point at other entries by `identifier`. The
target content type comes from a nested `<allow type="..."/>` - the same element
`blocks` uses, because it is the same idea. `Block\Adminhtml\Editor` ships the
pickable entries in the bootstrap under `references`, keyed by type code.

Two things about references are deliberate and easy to "fix" wrongly:

- **The picker is not store-filtered.** It would otherwise have to be refetched
  every time the meta bar's scope changes, and it is not the boundary anyway -
  a reference resolves per store at render time through
  `getByIdentifier($identifier, $storeId)`, so one that does not exist in the
  visitor's store resolves to nothing and is skipped.
- **`SchemaValidator` does not check that the target exists.** That class is
  pure schema logic with no repository, which is what keeps it unit-testable,
  and an all-or-nothing import would otherwise become order-dependent - an
  entry read before the one it references would fail the whole ZIP. A dangling
  reference is skipped at render, the way an unregistered block `_type` already
  is, and the editor surfaces it as "<identifier> - missing" rather than
  dropping it.

`REFERENCE_OPTION_LIMIT` (200, in `Block\Adminhtml\Editor`) caps one picker.
Fine for taxonomies; if a reference to a high-cardinality type ever needs more,
the fix is a search-backed control, not a bigger number.

### Field attributes
`required` `readonly` `width="half"` `default` `min` `max` `maxlength`
`description` `label_field` (which child field titles a repeater row), `max`
on a repeater (item limit), and `recommended` on an `image`.

`recommended="999x428"` prints the spec **on** the field and measures whatever
lands there, warning when the shape is wrong and saying how much a centre crop
would discard. Set it to the size the storefront actually shows the image at.
It exists because the same guidance sat in `description` and was still missed:
a sentence under a control is not where anyone looks while dragging a file onto
it.

`maxlength` caps a text/textarea field and drives the live character counter in
the editor. Without it the field falls back to `SchemaValidator::MAX_TEXT_LENGTH`
(2000), which is a sane ceiling for body copy and an absurd one for a tab label.
It is **not** mirrored onto the input's HTML `maxlength`: a browser cut-off
truncates pasted text silently, whereas the counter plus a save-time error says
what happened.

### Type attributes
`code` `label` `standalone` `copyable` `group` `icon` `description` `template`
`renderer` `version`.

`copyable="false"` hides **Save & Copy**. Use it when the entry is bound to
one host record and nothing else can point at a copy. Absent or `true` is the
default.

`icon` on a type is a sprite suffix, not an image field. `icon="video"` looks
up `#studio-icon-video` in `view/adminhtml/templates/top-bar.phtml` - that
sprite is the editor's icon library. The block picker, outline and new-entry
splash all resolve through it. A new name needs a matching `<symbol>` there;
do not vendor an npm icon pack into admin.

`readonly` is a guard rail, not a security control - use it for values other
code keys off (a key a template or another module matches against).

### Saving: the review gate
Clicking **Save** in the toolbar does not write. `state.js::requestSave` first
POSTs the payload with **`validateOnly: true`** - `Save.php` runs the real
`SchemaValidator` and returns without writing - and only opens the review
overlay if that comes back clean. Invalid entries never reach the preview:
the errors are marked on the fields and the first one is scrolled to and
focused. Validating server-side rather than in JS is deliberate; a browser copy
of required/maxlength/min/max/url is a copy that drifts, and then the gate
starts passing entries the save rejects.

Past that gate, `editor/review.js` plays the "Building preview" sequence
(~1.75s, `BUILD_MIN_MS`) and renders the block at full width, read-only; the
entry is written only when the user presses **Save entry** there. `Cmd+S` and
**Save & Copy** stay wired to `save()` directly, so a one-word fix still costs
one keystroke.

The splash node is built once and reused, so its animations hang off an
`is-running` class that is removed, layout-flushed and re-added on every open -
otherwise the sequence plays only the first time the overlay is shown.

A successful save then **redirects to the listing** (`urls.back`) with
`?saved=<identifier>`; `js/grid/saved-notice.js` (booted from the listing
layout) toasts that and strips the flag via `history.replaceState`, so a reload
does not replay it. The toast lives there rather than in the overlay because
the navigation would discard one raised before it. **Save & Copy** is the
exception and still lands on the copy - that is the point of the action.

The review stage renders through the same `previewModule.renderPreview` as the
side panel. `renderPreview`'s stale-render guard is per container
(`container._csPreviewGeneration`), not per module: with two panes a shared
counter let a debounced editor update cancel the review stage's in-flight
render and leave it blank.

### Rendering a type: server first
**SSR is the only storefront path.** The `template` renders the whole thing in
PHP; anything the template must not decide goes in a view model registered per
type code under `Block\Entry`'s `typeViewModels` argument in `di.xml` (a template
cannot reach the object manager, and `Block\Entry` must not learn about
individual types). `Block\Entry::getContent()` hands over the entry data with
`{{media}}` resolved and **not** escaped - escape at the point of output.

**A type that runs to the screen edge bleeds by `-mx-(--entry-bleed)`**
(`promo_split`), never by the gutter tokens directly. The theme
(`Elme/default/web/tailwind/elme/content-studio.css`) sets `--entry-bleed` to
the container gutter only on a box that has that gutter and spans the page:
`.columns` of a 1column CMS page (home included), a contained row where it
keeps its padding (full-width layouts), a full-width row's inner. The
wrappers in between (the Page Builder element, a CMS block, a Text element
holding the widget) pass it on. Everywhere else it is 0, so the entry stays
inside its box:
- the root of a full-width page (`main.page-main-full-width`: `cms-full-width`,
  Elme's `banner-breadcrumbs`) or a full-bleed row there: no gutter, and
  `#maincontent` already spans the screen up to the container width;
- a Page Builder column or tab, a 2columns page: narrower than the gutter box;
- the product page: its `.columns` holds the product grid and description
  cards, not page-wide content.

A bleed that is always on cuts the image and scrolls the page sideways
wherever the gutter is missing, so new placements default to no bleed.
A full-width row on a 1column page has two gutters (`.columns` and the row
inner, Hyva); the entry bleeds out of the inner one only.

### Live preview (the side panel)
`renderer="Some_Module/js/type/x"` is optional. Without it the panel draws an
automatic structural card (`preview.js::renderFallback`) - type label plus a
summary of filled fields. Only write a real renderer where pixel feedback while
typing matters. When you do, it must export `{render(container, config):
boolean}` and live in `view/base/` so both areas can load it. Elme types have no
renderer: the side panel shows the card, and **Open Live Preview** shows the
real storefront.

### Live Preview in a separate window
One **real** browser window, opened via `window.open()`, showing the actual
sandbox page (below) for the unsaved entry, built for a two-monitor workflow.
The in-panel preview stays visible all the time; the "Open Live Preview" row
(`preview-page.js`) sits above it.

```
click "Open Live Preview" --> window.open('', name)  (blank, synchronous)
                                        |
                                        v
                          POST entry/previewDraft (all_stores: true)
                                        |
                                        v
                      previewWindow.location.href = target.url  (navigate)
                                        |
                                        v
             every edit --> schedulePageReload: 1200 ms after typing stops
             (PAGE_RELOAD_DEBOUNCE_MS), a fresh draft and a new navigation
```

The sandbox page carries no script of this module that could repaint the block
in place (the old `bridge.js` postMessage channel is gone with the renderer
types that needed it), so every edit reaches the window as a debounced full
navigation with a new draft token.

Things about the implementation that are not obvious from reading `state.js`
top to bottom:

- **`window.open()` only succeeds inside a real, synchronous click handler.**
  Anything after an `await`/`.then()` has lost the user gesture and every
  popup blocker treats the call as an ad. So `openOrFocusPreviewWindow()`
  opens the window **blank** first, synchronously, off the click; the
  `previewDraft` fetch that decides what to show it runs after, and
  *navigates* the already-open reference (`.location.href = ...`) once it
  resolves.
- **There is no "popup closed" event.** `startWindowWatcher()` polls
  `ctx.previewWindow.closed` every 800ms - the one property a script can
  still read on a cross-origin window reference - and falls back the button
  to its "closed" state the moment it notices. `postPageUpdate` and
  `schedulePageReload` both check `previewWindowIsOpen()` first and are
  silent no-ops otherwise; typing with the popup closed costs nothing.
- **Device sizing means literal window dimensions here, not a max-width.**
  `DEVICES` in `preview-page.js` carries real `width`/`height` pairs, fed to
  `window.open()`'s features string on open and to `window.resizeTo()` if the
  device is changed while already open.

**"View on site" is not in the toolbar.** `preview-targets.js`'s popover still
exists - `toolbar.js` still builds it, `state.js` still wires it - but its
button is not appended (`toolbar.js`'s `right` group). Live Preview covers the
same need; the popover is dead code by omission, not by deletion.

### The sandbox page and the draft token

```
editor --POST buildPayload()--> entry/previewDraft --> DraftStorage (cache, 15 min)
                                        |                       ^
                                        +-- targets --+         | token in ?cs_preview=
                                                      v         |
admin window -- navigates --> sandbox page --> DraftEntryPlugin
                                               (aroundGetByIdentifier)
```

Four things carry the design:

1. **The transport is a token, not a database write.** `DraftStorage` parks the
   payload in the shared cache under 32 hex characters from `random_bytes()`,
   for fifteen minutes. That is the whole security boundary, and it is the only
   transport that survives the storefront being another area and possibly
   another domain.
2. **The interception is one plugin on `EntryRepository::getByIdentifier()`.**
   Every storefront consumer resolves entries through it, `Block\Entry` itself
   included. Registered in `etc/frontend/di.xml` **only**: in the admin the
   repository must keep answering with what is stored, or the grid, export and
   optimistic lock start reading drafts. Draft entries are always `isActive()` -
   previewing before switching an entry on is the main reason to preview at all.
3. **`PreviewCacheBypass` switches off `full_page` at runtime.** Without it a
   preview could write unpublished content into FPC. It is gated on
   `DraftContext::isPreview()`, which requires a *live token* - gating on the
   parameter alone would make `?cs_preview=x` a free way to force uncached
   renders of any page.
4. **The target is a sandbox page, one per store view.**
   `bart_content_studio/preview/sandbox` is a frontend route that 404s without
   a live token and renders under the store's own Hyva theme with its real
   header and footer. The entry sits where a CMS page renders the widget:
   `body.cms-page-view`, 1column layout, `.columns` > `.column.main`, page
   title and breadcrumbs removed - so container width and gutters match a real
   page. Do not invent a preview-only frame and patch component CSS to match.
   `TargetProvider` produces one target per store view the entry is assigned
   to (skipping inactive ones - `___store` naming an inactive view is ignored,
   and the link would quietly open under another store's theme) - unless the
   caller passes `allStores: true` (Live Preview does), in which case
   assignment is ignored and every active store view is offered.

**The "Draft" badge** (`preview/sandbox.phtml`, Tailwind) is `position: fixed`,
bottom-centre, `z-50`, above the theme's sticky header (`z-30`) and its menus.
Out of the flow on purpose: a bar in the flow pushed the block down so the page
no longer matched production, and at that size it read as part of the design it
was labelling. The outer bar is `pointer-events-none`, so it never blocks a
click on the page. It counts the token down live
(`view/frontend/web/js/preview/badge.js`, a plain deferred script, not Alpine,
so it still runs when something else on the page has broken; from an
`expires_at` stamped into the draft) - "for 15 minutes" is true when the link is
made and a lie every minute after, and the reload that suddenly 404s otherwise
gets blamed on the block.

### Preview links: what the token does and does not protect

- The token is the **only** credential, and it has to be: the admin and the
  storefront may live on different domains, so no admin session cookie reaches
  the previewed page. This is the same model Magento's own CMS and Page Builder
  previews use.
- It grants **exactly one thing** - one identifier's draft rendered on pages
  that already show that identifier. No writes, no other entries, no admin
  actions. It expires in fifteen minutes.
- Preview responses carry `Referrer-Policy: no-referrer`. The token is in the
  query string; without this third parties on the page (tag manager, image
  CDNs) receive the whole URL in a `Referer` header.
- **No preview cookie, on purpose.** A cookie would make the preview sticky -
  the admin keeps browsing and keeps seeing draft content without realising -
  and, worse, it would make a *normal, cacheable* URL render draft content. The
  entire reason the URL-parameter approach is safe is that preview state is
  visible in the URL and can never be confused with an ordinary request.
- Renaming an entry's identifier and then previewing shows the draft only on
  the sandbox: CMS pages and widgets still reference the old identifier.

Two traps this cost time on, both worth not rediscovering:

- **`Store::getUrl()` builds an *admin* URL when called from the admin area.**
  It delegates to the injected `UrlInterface`, which is the backend builder
  there, so the sandbox link came back as
  `/admin/bart_content_studio/preview/sandbox/key/<secret>/` - an admin route
  that does not exist - and 404'd. `TargetProvider::sandboxUrl()` composes the
  link from `getBaseUrl(URL_TYPE_LINK)` instead, which reads the store's
  configured frontend base and does not care which area asked.
- **`___store` has to be *overwritten*, not merged.** Anything that builds a
  URL through Magento's own machinery may already carry one, and in the admin
  area it is the admin's default store.

The popover fetches first and then renders `<a target="_blank">` for the user
to click, rather than opening a tab when the response lands - a `window.open()`
after a promise resolves has lost its user gesture and is blocked as a popup.
The response also carries a request ticket (`state.previewTargetsRequest`): a
fetch cannot be recalled, so a reply whose ticket is stale is dropped instead,
or closing the popover mid-flight lets it reopen itself a moment later.

### Nesting levels have colours

A repeater inside a repeater inside a repeater used to paint every level the
same warm off-white, so the only thing separating them was an indent. Each level
now carries its own hue (eucalyptus -> ochre -> clay) on its row header and on a
stripe down its left edge, while the body surfaces stay near-neutral because a
form is for reading.

The containers tag themselves via `field/base.js::depthClass()` from a `depth`
threaded through the render context; `_module.less` turns `studio-depth-N` into
a set of **custom properties**, and every rule reads them through a fallback
(`var(--studio-layer-bg, ...)`). That indirection is the point: a plain
`.studio-depth-2 .studio-repeater-row__header` scores 0-2-0 and would silently
beat `.studio-repeater-row__header:hover`, killing the hover state on every
nested row. Properties also inherit, so a field inside a level picks its
colours up without being tagged.

**Inputs are `#fff`, always** - not the warm off-white they used to share with
the panel. On a tinted level a near-panel-coloured field stops reading as a
field; the one thing on the screen that is typed into has to be the one thing
that is unmistakably a hole in the paper.

### Reordering
Repeater and blocks rows move from **either** panel: the grip in a fields-panel
row, or the outline node itself (the tree has no room for a separate handle, so
the node *is* the handle). Both are the same code path - `dragSourceOf` in
`state.js` resolves either affordance to one `{fieldPath, index}` pair, and
`ITEM_SELECTOR` lists the three attributes that can carry an item path
(`data-row`, `data-block`, `data-outline-row`). Alt+Up/Down works from both and
focus follows the item to its new index in whichever panel it was moved from.

Two deliberate limits: a drop **takes the target row's index** rather than
inserting before/after it, and a drag in the tree is refused while the outline
filter is on - hidden siblings sit between the visible rows, so the resulting
index would be correct and look arbitrary.

`commitMove` points `selectedPath` at the moved item before committing. A
reorder otherwise leaves the selection on an index that now names a *different*
row, and a freshly rendered outline starts fully collapsed - so without it the
thing you just dragged disappears from the tree.

Outline rows also show their **1-based position**. Because the ordinal carries
the position, an unlabelled row falls back to the field's singular name instead
of repeating the number.

---

## Types that exist

| code | module | standalone | renderer | notes |
|---|---|---|---|---|
| `promo_split` | `Elme_Theme` | yes | no | Image left; heading, text, button right. The Outlet promo on the home page. |
| `hero` | `Elme_Theme` | yes | no | Top banner of the home page (FE-14): `slides` repeater (desktop + mobile image, heading, text, button, panel side). One slide is a plain banner; 2-5 are a scroll-snap slider with a CSP Alpine component in the template. View model `Elme\Theme\ViewModel\Hero` (registered in `Elme_Theme/etc/di.xml`) reads each image's pixel size from pub/media. `is_page_title` makes slide 1 the page H1. |

FE-14...16 and FE-47...49 add their types to the same `Elme_Theme/etc/content_types.xml`.

---

## Getting an entry onto a page

Three ways, in order of how often they are the right one:

1. **Page Builder: "Content Studio"** under Add Content, next to Block (see
   below). Works wherever Page Builder runs: CMS pages, CMS blocks, product
   attributes.
2. **The widget.** `etc/widget.xml` -> `bart_content_studio_entry`, one
   parameter, `identifier`, backed by a chooser grid
   (`Block/Adminhtml/Widget/Chooser.php` + `Controller/Adminhtml/Entry/Chooser.php`).
   For WYSIWYG fields without Page Builder, and Page Builder's Text/HTML Code.
   The chooser exists because a mistyped identifier produces a widget that
   renders *nothing at all* - `Block\Entry::resolveEntry()` returns null for an
   unknown one - so there is no feedback to correct from. It offers only
   `standalone="true"` types; a nested-only block type has no identifier to
   insert.
3. **Layout or a PHP block**, with `setData('identifier', ...)`.

All three end in `Block\Entry` with an identifier, resolved per store
(store-specific entry first, then All Store Views).

### The Page Builder content type

Built like Page Builder's own `block` type (`vendor/magento/module-page-builder`:
`content_type/block.xml`, `pagebuilder_block_form.xml`,
`pagebuilder_block_select_grid.xml`, `content-type/block/preview.js`,
`content-type/block/mass-converter/widget-directive.js`).

| File | What |
|---|---|
| `view/adminhtml/pagebuilder/content_type/content_studio.xml` | the type: `menu_section="add_content"`, parents `root-container` `row` `column` `tab-item`, Block's style properties |
| `view/adminhtml/ui_component/bart_content_studio_pagebuilder_form.xml` | edit form: Page Builder's `block-chooser` + a hidden `identifier` |
| `view/adminhtml/layout/bart_content_studio_pagebuilder_form.xml` | the form's layout handle; Page Builder loads the form through `mui/index/render_handle` with `handle=<form name>` |
| `view/adminhtml/ui_component/bart_content_studio_pagebuilder_select_grid.xml` | the picker grid, searchable by title and identifier |
| `Ui/DataProvider/PageBuilderSelectDataProvider.php` | narrows the picker to `standalone="true"` types, the widget chooser's set |
| `Controller/Adminhtml/Pagebuilder/Metadata.php` | title, type and status of one identifier (default store view); `[]` = not found |
| `js/content-type/content-studio/mass-converter/widget-directive.js` | identifier <-> `{{widget type="Bart\ContentStudio\Block\Entry" identifier="..."}}` |
| `js/content-type/content-studio/preview.js` + `template/content-type/content-studio/default/preview.html` | the stage |
| `web/css/pagebuilder.css` | panel icon and stage frame; loaded on every admin page (`layout/default.xml`) because Page Builder runs in forms that do not load the editor stylesheet |

- **Saved content is the widget directive**, the same one the widget inserts.
  The storefront renders it through `Block\Entry`; no Page Builder JS, nothing
  of this module's JS on the storefront. Only the identifier is stored, not the
  entry id: ids renumber on an export/import, identifiers do not.
- **The stage is an `<iframe>` of `entry/previewFrame`** (`?identifier=...
  &store_id=<default store view>&bare=1`), not the entry HTML pasted into the
  admin document the way Block does it. With the theme's `styles.css` in the
  admin document Tailwind's base layer restyles the whole admin; without it the
  entry is unstyled. The frame is same-origin (admin-served), takes the height
  of `.page-main` in it (a `ResizeObserver`, so late images and a narrower
  stage re-fit), and has `pointer-events: none`, so select, move, duplicate and
  remove work as for Block. It also shows the type's own outer margins (e.g.
  `promo_split`'s bottom margin), as the storefront does. The frame always has
  the 1column CMS page shell, so a bleeding type fills the frame edge to edge,
  as on the storefront in a row or at the page root; in a column or tab the
  storefront keeps it inside that box (see "Rendering a type").
- **The stage shows the server-rendered state.** Alpine does not run in the
  frame; an interactive type shows its initial state there.
- **States**: no entry picked -> Block-style placeholder; identifier unknown
  (entry deleted or never existed in the default store) -> a placeholder saying
  so, and the storefront renders nothing, same as the widget; entry disabled ->
  the frame plus a warning notice.
- Style-only edits (margins, border) keep the identifier and do not reload the
  frame (`showEntry` compares against `lastIdentifier`).

### Framed inside another admin form

`?embedded=1` on the editor URL. The only thing it changes is the end of a
successful save: `state.js::handleSaveResult` `postMessage`s
`{source: 'bart-content-studio', identifier, entryId}` to `window.parent`
instead of redirecting to the entry grid - a redirect would strand the user
inside whatever modal is framing the page, or reload the whole admin inside
itself.

Deliberately *not* a general "embedded mode": the meta bar, Save button and
review gate all still work exactly as they do standalone. The opener is expected
to own the surrounding form's own save, and to read the identifier off that
message.

---

## Where things are

| Path | What |
|---|---|
| `etc/content_types.xml` / `.xsd` | the schema; this module declares no types (Elme's: `Elme_Theme/etc/content_types.xml`) |
| `Model/Schema/` | reader -> converter -> cached `Data` -> `Config` facade |
| `Model/Entry*`, `Model/ResourceModel/` | entity, store scoping, optimistic locking |
| `Model/ImportExport/` | JSON/ZIP export + import, all-or-nothing. Export collapses a value that starts with a media base URL to `{{media url="..."}}`; import expands exactly that form back to the importing site's media URL before validation (image and url fields accept only `/`, `http://`, `https://`) |
| `Block/Entry.php` | storefront renderer; also the `bart_content_studio_entry` widget |
| `Model/Preview/` | draft token storage, request context, storefront target resolution |
| `Plugin/Preview/DraftEntryPlugin.php` | serves the draft in place of the stored entry (frontend only) |
| `Observer/PreviewCacheBypass.php` | keeps a preview out of FPC and out of the index |
| `Controller/Adminhtml/Entry/PreviewFrame.php` | one entry under the store theme, for the grid preview and the Page Builder stage |
| `view/frontend/templates/preview/sandbox.phtml` | sandbox page + Draft badge (Tailwind) |
| `view/adminhtml/pagebuilder/`, `ui_component/*pagebuilder*`, `js/content-type/` | the Page Builder type |
| `view/adminhtml/web/js/editor/` | the editor (`state.js` is the entry point) |
| `view/adminhtml/web/css/source/_tokens.less` | the admin design system |

### Editor layout handles
- `bart_content_studio_full_bleed` - strips Magento chrome, adds the top bar. Used by the **grid**.
- `bart_content_studio_full_bleed_editor` - the above plus removing stock page actions, and `studio-editor-page` on `<body>`, which enables the bare-tag reset. Used by editor / new / import.

The grid deliberately keeps Magento's `ui_component` (its filters, mass actions
and export-to-ZIP already work and are covered); `_grid.less` re-skins it.

### Grid toolbar: search + type chips

The stock "Filters" dropdown (ID range / Status / Updated date) is hidden
entirely (`_grid.less`: `.data-grid-filters-actions-wrap`,
`.admin__data-grid-filters-wrap { display: none; }`). In its place:

- **Search** - `Ui/Component/Listing/Filters/KeywordSearch.php` extends
  Magento's own `filterSearch` widget (`<filterSearch name="q">` in the
  listing XML) but gives it its own condition type, `studio_keyword`, applied
  by `Model/ResourceModel/Entry/Grid/Filter/KeywordFilterApplier.php`
  (registered in `di.xml` against the *global*
  `Magento\...\DataProvider\FilterPool`, alongside Magento's own `regular` and
  `fulltext` keys - safe to add globally because the key is namespaced and
  only activates when a filter explicitly asks for it). Matches `title`,
  `identifier`, and - for a purely numeric query - `entry_id` exactly. Not the
  stock `fulltext` condition type: that one requires a real MySQL FULLTEXT
  index (this table has none) and a short numeric ID would fall below
  MySQL's default 3-character token minimum anyway.
- **Type chips** - `Block/Adminhtml/Entry/TypeChips.php` +
  `entry/type-chips.phtml`, a plain server-rendered block placed in `content`
  *above* the `<uiComponent>` in `bart_content_studio_entry_index.xml` - not
  part of the ui_component tree. One chip per registered standalone type plus
  "All types", each with a count from `Entry::getCountsByType()` (one grouped
  query, computed once per page load). `view/adminhtml/web/js/grid/type-chips.js`
  wires clicks to the grid's own (now invisible) Filters component via
  `uiRegistry.get('ns = ..., componentType = filters', ...)` and its public
  `setData()` + `apply()` - the same call a real "Content Type" dropdown +
  "Apply Filters" click would make, so pagination reset / bookmarking keep
  working exactly as they do for every other column filter.

---

### Grid preview (eye button)

Each grid row has an eye button (`Ui/Component/Listing/Column/Preview`,
rendered through `ui/grid/cells/html` so the button carries its own data).
`js/grid/preview.js`, booted from `entry/grid-preview.phtml`, hangs delegated
listeners on `document` - rows are re-rendered on every filter/page change:

- **hover / focus** -> a small popover with type, title and identifier, read off
  the button's `data-*`. No request.
- **click** -> the modal opens with a loader, asks `entry/previewData` (JSON:
  the entry's active stores, each with a frame URL) and loads
  `entry/previewFrame` into an `<iframe>`. Store tabs and Desktop (1440px,
  scaled to fit) / Mobile (393px) switch it. Inactive entries preview too
  (`Block\Entry::setPreviewEntry()` skips the identifier lookup).

How `PreviewFrame` works, and why it is admin-served:

- Store + area **emulation** (`Emulation` + `AppState::emulateAreaCode`), the
  mechanism transactional emails use, so templates, view models, `{{media}}`
  and asset URLs resolve as on the storefront.
- **The Hyva theme's one stylesheet** (`css/styles.css`) and the shells a Hyva
  CMS page renders the widget in, read off a CMS page of this theme:
  `body.cms-page-view.page-layout-1column` > `.page-wrapper` >
  `main#maincontent.page-main` > `.columns` > `.column.main`. The theme's
  `min-height` on those shells is reset and `.page-main` loses its vertical
  margin, so the framing page can size the frame to `.page-main`. `bare=1`
  (the Page Builder stage) also drops the frame's own vertical padding; the
  container gutters stay, so a section that bleeds into them on the storefront
  bleeds in the frame too.
- The store's static and media base URLs are then made **root-relative**.
  `pub/static` / `pub/media` are the same files on every domain, and loading
  them from the admin's host keeps web fonts (no CORS headers) and CSS-mask
  icons working.
- It is **not** the storefront sandbox in an iframe: the storefront sends
  `X-Frame-Options: SAMEORIGIN` and may live on another domain than the admin.
  A same-origin admin document needs no header changes. Links in it open in a
  new tab (`<base target="_blank">`) for the same reason.
- **No Alpine, no theme JS.** The frame shows the server-rendered state. The
  controller's `scripts` DI argument (asset ids, extended per module like
  `typeViewModels`) is empty; Hyva types need no RequireJS.

### Grid sticky header

The stock sticky clone (`listingToolbar` -> `sticky`) is **off**: it is
positioned for the admin menu (`left: 88px`) that the full-bleed layout
removes, so it floated off to the right with misaligned columns. The real
`thead th` row is `position: sticky` in `_grid.less`, and Magento's floating
`.page-actions._fixed` bar is pinned back in place for the same reason.

## Verifying changes

Everything runs in the `elme_php` container (PHP 8.4), from the repo root:

```bash
bin/clinotty bash -c "bin/magento cache:flush"
bin/clinotty bash -c "bin/magento setup:upgrade"
bin/clinotty bash -c "php -d xdebug.mode=off vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist \
  --no-coverage app/code/Bart/ContentStudio/Test/Unit"
```

PHPUnit 12 is installed with the dev dependencies. The Allure-extension warning
("Config file allure/allure.config.php doesn't exist") is repo config, not
ours, and the PHPUnit notices about mocks without expectations are harmless.

English ASCII only in every file of this module, this one included:

```bash
LC_ALL=C grep -rnIP '[^\x00-\x7F]' app/code/Bart/ContentStudio
```

(`-I` skips the bundled woff2 fonts.) A visible non-ASCII character in the UI
is an HTML entity in a template or a `\u{...}` escape in JS/PHP.

After a template change in a type: rebuild the theme CSS
(`app/design/frontend/Elme/default/web/tailwind`, `npm run build`) and commit
`web/css/styles.css`. After admin JS or LESS changes see the traps below.

Lint style the admin JS was written to (the old repo's ESLint config): `max-depth: 2`,
`sonarjs/cognitive-complexity: 15`, `no-var`, `eqeqeq`, `no-undefined`,
`no-use-before-define`, and `valid-jsdoc` (a `/** */` block on a function must
document every param and the return - use plain `//` comments for internal
helpers instead).

What the suite deliberately covers (unit tests here are for logic that fails
*silently*, not for coverage percentage):

| Test | Pins |
|---|---|
| `Model/Schema/SchemaValidator*` | field validation, `normalize`, identifier uniqueness, image fields |
| `Model/Schema/ConverterTest` | XML -> array: bool/numeric casts, **absent attribute stays `null`, not `false`**, nested repeater/group, `<option>`, `<allow>` |
| `Model/Schema/HtmlSanitizerTest`, `PriceFormatterTest` | the wysiwyg allow-list, the `$3,999` mask |
| `Model/Template/SafeFilterTest` | the security contract: `{{block}}`/`{{layout}}` resolve to nothing, `{{media}}` still resolves |
| `Model/ImportExport/ExportFileNamerTest` | the `-<entry_id>` suffix that stops same-identifier entries overwriting each other in a ZIP |
| `Model/ImportExport/Processor/ImportTest` | identifier + **exact store set** matching, skip/update modes, dry run, all-or-nothing rollback |
| `Model/Preview/TargetProviderTest` | sandbox links per store view, inactive stores skipped, a storefront URL (not an admin one) that pins the store |
| `Model/ResourceModel/.../KeywordFilterApplierTest` | the grid search `WHERE` clause |

Some tests use type codes such as `compare_mattress` as plain strings with
inline fixtures; they do not need the type to exist.

`SafeFilterTest` does **not** call `filter()`. `Filter::filter()` resolves
`SignatureProvider` and `FilteringDepthMeter` through the global ObjectManager,
which `Email\Template\Filter` never forwards to its parent constructor, so no
unit test can supply them. The test builds the filter with
`newInstanceWithoutConstructor()`, injects only `_storeManager`, and dispatches
through a real `LegacyDirective` - the same `{$name}Directive` reflection lookup
production uses. The passing `{{media}}` case is the control: if dispatch ever
breaks, an empty string for `{{block}}` would stop meaning anything, and that
test fails first.

Bypass for verifying module logic without the admin UI: a throwaway script that
does `require 'app/bootstrap.php'; $om = \Magento\Framework\App\Bootstrap::
create(BP, $_SERVER)->getObjectManager();`, sets the area code, and calls the
class directly (`$om->get(...)`/`$om->create(...)`).

---

## Traps that have already cost time

- **A Page Builder form needs a layout handle with its own name.** Page Builder
  asks `mui/index/render_handle/?namespace=<form>&handle=<form>`; without
  `view/adminhtml/layout/<form>.xml` (a `<uiComponent>` in `content`, as
  Page Builder's `pagebuilder_block_form.xml`) the answer is 200 with an empty
  body, and the "Edit" slide-out spins forever with no error anywhere. A stage
  element whose content already holds the directive still renders, which hides
  it.
- **A frontend controller that does not extend `Action` must still create
  `App\ViewInterface` before its result page.** `Controller\Preview\Sandbox`
  takes it in the constructor and never uses it. Without that, App\View was
  first created during layout generation (a Magento_Swatches renderer in the
  default layout asks for it), its own Result\Page replaced the builder in the
  shared `Page\Config`, Magento built the layout a second time, and Hyva's
  `head.additional` scripts (`BASE_URL`, the `hyva` object, speculation rules)
  were left without a parent: the sandbox page shipped without them and every
  Alpine component on it threw. Symptom: `hyva.alpineInitialized is not a
  function` on one route only.
- **Admin CSS can be merged.** With CSS merging on, the grid page links one
  `pub/static/_cache/merged/<hash>.css`, not `studio.css`. After a LESS change,
  delete that file **and its `.css.dat`** - with only the CSS gone the checksum
  file says "up to date", nothing is rewritten, and the admin loses all styling
  (404). A LESS compile error (e.g. `width: min(1100px, 100%)` - LESS evaluates
  `min()` and fails on mixed units) also takes the whole `studio.css` down.
- **`_fonts.less` is compiled twice.** Magento `@magento_import`s every
  module's `css/source/_module.less` into the backend theme's own `styles.css`
  as well as into `studio.css`, so a relative `url('../fonts/...')` resolves
  against the backend theme there and 404s. The font URLs use module notation
  (`Bart_ContentStudio::fonts/...`), which resolves in both.
- **`[hidden]` loses to any author `display` rule.** `[hidden] { display: none }`
  lives in the *UA* stylesheet, and every author rule beats the UA stylesheet
  regardless of specificity - so `.studio-preview__page-status { display: flex }`
  and `.studio-select { display: block }` both left their elements on screen
  while `hidden` was set, with no error anywhere. Any component that sets
  `display` and is toggled via the attribute needs its own `&[hidden] { display:
  none }`.
- **Rebuilding the fields panel clamps the page scroll.** `.studio-editor__fields`
  has no overflow of its own - the *document* scrolls - so emptying it collapses
  the page height, and any layout read taken before it is refilled (this file's
  own `scrollIntoView` calls are enough) clamps `window.scrollY` to the collapsed
  height and never restores it. It reads as "scrolling is broken", not as a
  re-render. `renderFormPanels` saves and restores the scroll position for
  exactly this; keep that guard on any new full-panel rebuild.
- **Verify against the compiled output, not the source.** A LESS change can lint
  clean and still break the build: `fade()` and `mix()` are build-time functions
  and reject CSS custom properties. Fetch the real `studio.css` and grep it.
- **Clearing only `css/studio.css` out of `pub/static` is not enough for JS.** A
  changed `js/editor/*.js` keeps being served from `pub/static` until that path
  is deleted too; a `cache:flush` alone will not republish it, and the browser
  happily serves a file whose `version...` segment did not change. Delete the
  module's `pub/static/adminhtml/*/*/*/Bart_ContentStudio` JS when JS is
  involved. The Page Builder type's JS and templates are in the same directory.
- **A named preview window survives page loads.** `window.open('', 'cs_page_preview')`
  *adopts* an existing window of that name instead of making a new one - which is
  what keeps a single preview window rather than one per click, but it also means
  a popup left open by an earlier session (or an earlier entry) is picked up on
  the next Open, showing that old page until the navigation lands. Close stray
  preview windows before drawing conclusions about which store a fresh load
  picked.
- **`var/view_preprocessed` caches every `@import`ed partial separately.**
  Deleting `.../Bart_ContentStudio/css/studio.*` is *not* enough - the stale
  `css/source/_preview.less` etc. next to it are what actually get recompiled
  from, so an edit to a partial silently never lands. Clear the directory:
  ```bash
  rm -rf var/view_preprocessed/pub/static/adminhtml/*/*/*/Bart_ContentStudio \
         pub/static/adminhtml/*/*/*/Bart_ContentStudio/css/studio.css
  ```
  Do **not** `rm -rf` the module's whole `pub/static` dir with a tab open - it
  ships the Karla / DM Serif woff2 files, and an open tab then 404s on fonts
  until a hard reload.
- **`.is-outline-highlight` animates `background-color`.** Harmless on an
  outline row (transparent anyway), fatal on anything whose background *is* the
  element. `flashOutlineTarget(target, className)` takes the class for that
  reason.
- **A reset must be the weakest rule in the file.** `.studio-editor-page button`
  scores 0-1-1 and silently beat every `.studio-btn*` rule. The reset is wrapped
  in `:where()` now - keep it that way.
- **`browser.openDialog` throws unless the URL carries `current_tree_path`.**
  `mage/adminhtml/browser.js::setPathId` is
  `url.match(/...current_tree_path.../)[3]` with no null guard, so a URL without
  that parameter dereferences `null` and throws `Cannot read properties of null
  (reading '3')`. It looks intermittent because the first open reaches
  `setPathId` inside the AJAX `.done()` *after* `modalLoaded = true` and after
  the markup is already in the DOM - so the dialog works - while every later
  open hits it early and skips the `reload.MediaGallery` trigger that follows,
  leaving a stale file list. `Block\Adminhtml\Editor` ships the encoded folder
  as `mediaFolderId` and `openImageBrowser` appends it **last**: that regex ends
  on `/` or end-of-string, so any parameter added after it is swallowed into the
  captured path. The parameter also makes `Images::getCurrentPath` create the
  directory, so Browse and drag&drop share one folder.
- **Magento's media browser announces its pick with a jQuery-only event.**
  `mage/adminhtml/browser.js::chooseFile` sets `targetEl.val(...)` and then
  `jQuery(targetEl).trigger('change')`. jQuery's `.trigger()` walks its *own*
  handler registry and only falls back to a real DOM dispatch when the element
  has a matching method (`.click()`, `.focus()`); `HTMLInputElement` has no
  `.change()`, so nothing native ever fires. Neither a listener bound with
  `addEventListener` on the input nor the delegated `change` listener on
  `#studio-editor` can hear it. The symptom: the URL is visibly sitting in the
  field while `state.data` still holds the old value, so Save reports the field
  as *required*. `openImageBrowser` therefore binds through jQuery - the one
  place in this module that must - and converts straight back to
  `studio:field-change`.
- **The media browser returns an admin directive URL unless you ask it not to.**
  With "Use Static URLs for Media Content in WYSIWYG" off (Magento's default),
  `GetInsertImageContent` answers with
  `/admin/cms/wysiwyg/directive/___directive/<base64>/key/<secret>` - an
  authenticated *backend* link. It renders fine for a logged-in admin, 404s for
  every customer, and would publish an admin path and key into page HTML. It
  also passes `SchemaValidator::isSafeUrl` on its `https://` branch, so nothing
  downstream catches it. The fix is one attribute on the target input,
  `data-force_static_path="1"`, which browser.js forwards to `onInsert` and
  which makes the controller return the plain `/media/<folder>/<file>` path.
  Spell the attribute out; jQuery's `.data('force_static_path')` looks for
  exactly that underscored name, while `dom.el`'s `dataset` key would emit
  `data-force-static-path` and never be found.
- **The admin store (id 0) is not a store view.** `getStores()` without `true`,
  or you get a duplicate of "All Store Views" in the scope picker.
- **`ONLY_FULL_GROUP_BY` may be on elsewhere.** The store filter is a
  semi-join (`EXISTS`) rather than a JOIN + GROUP BY for that reason - MariaDB
  rejects `SELECT main_table.*` with a GROUP BY. Do not "simplify" it back.

---

## Known gaps

- **Moving a field in the schema needs a data patch.** Entry content is a JSON
  blob, so a field that changes level is not "renamed" - it is simply no longer
  read, and the editor stops rendering the input that could put it back, so the
  value is unreachable rather than wrong. Such a patch should write the table
  directly rather than go through the repository: a repository save would bump
  `version` and `updated_at`, make the migration look like a person had edited
  every entry, and break the optimistic lock for anyone with the editor open.
  Fill gaps only, never overwrite, so it is safe to rerun.
- **Identifier uniqueness is check-then-write.** No DB constraint can express it
  (one identifier may legitimately exist once per store), so two simultaneous
  *creates* could both pass. Updating an existing entry is fully guarded.
- **`icon` fields and sliders** are declared in the XSD and not implemented.
- **`wysiwyg` is a source textarea**, not a rich-text surface. The storage
  format is final (sanitized HTML); only the control is provisional.
- **A reference cycle is not detected.** An entry referencing itself is
  accepted; nothing follows references recursively today, so it costs nothing
  yet, and the guard belongs wherever the first recursive traversal lands.
- **The editor's side panel is a summary card for Elme types**, not the
  storefront look (no JS renderers, by decision). Live Preview and the Page
  Builder stage show the real thing.
