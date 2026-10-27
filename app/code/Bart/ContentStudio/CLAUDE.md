# Bart_ContentStudio

Schema-driven content blocks for the storefront, with a custom admin editor and
live preview. Contentful's model, scaled down: you describe a **content type**
in XML, the admin builds the form for it automatically, and a template renders
the resulting **entry** on the storefront.

It exists to replace a pattern that had grown untenable - an editor pasting a
huge JSON blob into a CMS block, an empty shell template elsewhere, and JS that
searched the DOM for that blob and physically moved the shell next to it. No
validation, no preview, no store scoping. `compare_mattress` is that block,
migrated; it is content type number one, not the point of the module.

---

## Mental model

```
etc/content_types.xml  --> Schema\Config --+--> admin: editor builds the form from the schema
   (merged, cached)                        +--> admin: live preview
                                           +--> frontend: Block\Entry picks the type's template

bart_content_studio_entry (+ _store pivot)  --> one row per entry, content is JSON in `data`
```

Three things are worth internalising before changing anything:

1. **The schema is the source of truth.** Nothing about a content type is
   hard-coded in PHP or JS. Adding a type is XML plus a template.
2. **The storefront renders on the server; the JS renderer is the admin preview.**
   `type/compare-mattress.phtml` + `ViewModel\Type\CompareMattress` emit the
   whole block, every range x comfort, all but one under `hidden`;
   `view/frontend/web/js/storefront.js` only moves that `hidden` around.
   `view/base/.../type/compare-mattress.js` builds the same markup client-side
   and now serves **only** the editor's live preview, where repainting on every
   keystroke has to be instant. They are two implementations on purpose, and
   their contract is the `cs-compare-mattress__*` class names -
   `Test/Unit/ViewModel/Type/CompareMattressTest.php` pins the shared logic.
3. **The editor mutates state in exactly one place.** `editor/state.js` owns the
   data. Field modules only *describe* DOM and mark it with `data-*`; one
   delegated listener per event type on `#studio-editor` turns those markers
   into state changes. Do not attach listeners in field renderers - repeaters
   re-render constantly and per-element listeners leak.

---

## Adding a new content type

Two files. No PHP class.

**1. Declare it.** `etc/content_types.xml` - or your own module's, the config is
merged across modules (the reader uses `Magento\Framework\App\Config\FileResolver`),
so `Bart_MagefanBlog/etc/content_types.xml` works and does not require touching
this module. That is where the twelve blog types live, and it is the working
example to copy.

```xml
<type code="usp_strip" label="USP Strip" standalone="true"
      description="Icon + text row under add-to-cart."
      template="Bart_MagefanBlog::type/usp-strip.phtml">
    <field name="title" type="text" label="Heading" required="true"/>
    <field name="items" type="repeater" label="Items" label_field="label" max="6">
        <field name="label" type="text"  label="Label" required="true" width="half"/>
        <field name="icon"  type="image" label="Icon"  width="half"/>
    </field>
</type>
```

Only `code` and `label` are required. `standalone="true"` makes it appear in the
"New entry" picker.

**2. The template**, as named in `template`. It receives `$block` =
`Bart\ContentStudio\Block\Entry`.

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
change with no migration behind it.

`datetime` stores an ISO-8601 **UTC** instant at seconds precision
(`2026-07-31T10:00:00Z`). The control is `<input type="datetime-local">`, which
has no timezone at all, so `lib/datetime.js` converts at both edges - and
`readControlValue()` in `state.js` is the read half, which is why that lib is
in `lib/` and not in the field module.

`renderer="price"` on a `text` field masks to `$3,999` as you type (dollar,
digits, commas). The stored value is still a string - `SchemaValidator` keeps
treating it as `text`.

`reference` / `reference_multi` point at other entries by `identifier`. The
target content type comes from a nested `<allow type="..."/>` - the same element
`blocks` uses, because it is the same idea. `Block\Adminhtml\Editor` ships the
pickable entries in the bootstrap under `references`, keyed by type code.

Two things about references are deliberate and easy to "fix" wrongly:

- **The picker is not store-filtered.** It would otherwise have to be refetched
  every time the meta bar's scope changes, and it is not the boundary anyway -
  a reference resolves per store at render time through
  `getByIdentifier($identifier, $storeId)`, so one that does not exist in the
  visitor's brand resolves to nothing and is skipped.
- **`SchemaValidator` does not check that the target exists.** That class is
  pure schema logic with no repository, which is what keeps it unit-testable,
  and an all-or-nothing import would otherwise become order-dependent - a post
  read before its category would fail the whole ZIP. A dangling reference is
  skipped at render, the way an unregistered block `_type` already is, and the
  editor surfaces it as "<identifier> - missing" rather than dropping it.

`REFERENCE_OPTION_LIMIT` (200, in `Block\Adminhtml\Editor`) caps one picker.
Fine for taxonomies; if a reference to a high-cardinality type ever needs more,
the fix is a search-backed control, not a bigger number.

### Field attributes
`required` `readonly` `width="half"` `default` `min` `max` `maxlength`
`description` `label_field` (which child field titles a repeater row), `max`
on a repeater (item limit), and `recommended` on an `image`.

`recommended="1200x900"` prints the spec **on** the field and measures whatever
lands there, warning when the shape is wrong and saying how much the centre
crop will discard. It exists because the same guidance sat in `description`
and was still missed: a square photo went into a 4:3 frame, lost a quarter of
its height, and took every layer dot with it - dots are pinned as a percentage
of the frame, so a re-crop silently moves all of them off what they point at.
A sentence under a control is not where anyone looks while dragging a file
onto it.

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
one host record (a blog post body is `post-body-{id}` and nothing else can
point at a copy). Absent or `true` is the default.

`icon` on a type is a sprite suffix, not an image field. `icon="video"` looks
up `#studio-icon-video` in `view/adminhtml/templates/top-bar.phtml` - that
sprite is the editor's icon library. The block picker, outline and new-entry
splash all resolve through it. A new name needs a matching `<symbol>` there;
do not vendor an npm icon pack into admin.

`readonly` is a guard rail, not a security control - use it for values other
code keys off, like `ranges[].key` here, which `Bart\Catalog`'s
`MATTRESS_RANGE_MAP` matches against.

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

The stage renders through the same `previewModule.renderPreview`, with
`{interactive: false}` so `preview-hotspot.js` is *not* wired to it - that
module keeps `latestData` at module scope and commits against the editor's
data, so a drag on a second container would write into the entry. `focus` is
passed `null`, so the stage opens on the entry's own `defaultRange` /
`defaultComfort` - what a customer meets first.

`renderPreview`'s stale-render guard is per container
(`container._csPreviewGeneration`), not per module: with two panes a shared
counter let a debounced editor update cancel the review stage's in-flight
render and leave it blank.

### Rendering a type: server first
**SSR is the default for a new type.** The `template` renders the whole thing in
PHP; anything the template must not decide goes in a view model registered per
type code under `Block\Entry`'s `typeViewModels` argument in `di.xml` (a template
cannot reach the object manager, and `Block\Entry` must not learn about
individual types). `Block\Entry::getContent()` hands over the entry data with
`{{media}}` resolved and **not** escaped - escape at the point of output.

`renderer` is optional and exists for the **admin live preview only**. Reach for
it when the editor needs pixel feedback while typing; otherwise the preview
falls back to the structural card and the storefront is unaffected.

This is why `compare_mattress` moved: the old template shipped an empty
`<div hidden>` plus a JSON blob, so the PDP carried none of the copy, no
crawlable `<a href>`, and the block popped in once RequireJS had run.

### Live preview
The preview draws the block inside a tinted **section band**
(`--studio-color-preview-band`), carried by `.cs-compare-mattress__inner` -
that element *is* the block in admin, since the renderer empties the mount
point and appends it, so there is no `.cs-compare-mattress` root here the way
there is on the storefront. It stands in for the storefront's full-bleed band
(`--cs-cm-band-bg`, defaulting to the warm neutral `#f4f0eb`; no brand
overrides it today, SG included). The admin one is green on purpose: a warm
neutral is invisible against the editor's own paper.

`renderer="Some_Module/js/type/x"` is optional. Without it the preview draws an
automatic structural card (`preview.js::renderFallback`) - type label plus a
summary of filled fields. Only write a real renderer where pixel accuracy
matters. When you do, it must export `{render(container, config): boolean}` and
should be in `view/base/` so both areas can load it.

### Storefront preview ("View on site")

The side panel is fast and right about the content, but it is drawn by the
type's JS renderer under the *editor's* stylesheet - it is not the storefront.
It has no theme, no container widths and no brand typography, so the same entry
looks identical there whether it is destined for James Lane, Sleeping Giant or
Elme Living. **View on site** in the toolbar closes that gap: it opens the
*unsaved* entry on a real storefront page, once per store view it is assigned
to.

```
editor --POST buildPayload()--> entry/previewDraft --> DraftStorage (cache, 15 min)
                                        |                       ^
                                        +-- targets --+         | token in ?cs_preview=
                                                      v         |
admin popover -- user clicks --> sandbox page --> DraftEntryPlugin
                                                  (aroundGetByIdentifier)
```

Four things carry the design:

1. **The transport is a token, not a database write.** `DraftStorage` parks the
   payload in the shared cache under 32 hex characters from `random_bytes()`,
   for fifteen minutes. That is the whole security boundary, and it is the only
   transport that survives the storefront being another area and, here, another
   domain.
2. **The interception is one plugin on `EntryRepository::getByIdentifier()`.**
   Every storefront consumer already resolves entries through it - `Block\Entry`
   itself, and `Bart\Catalog`'s `MattressCmsBlocks`, which calls it directly to
   decide whether a PDP identifier is a Studio entry at all. So the PDP wiring
   needed no preview branch. Registered in `etc/frontend/di.xml` **only**: in
   the admin the repository must keep answering with what is stored, or the
   grid, export and optimistic lock start reading drafts.
   Draft entries are always `isActive()` - previewing before switching an entry
   on is the main reason to preview at all.
3. **`PreviewCacheBypass` switches off `full_page` at runtime.** Without it the
   first preview of a product page writes unpublished content into FPC under
   the real URL. It is gated on `DraftContext::isPreview()`, which requires a
   *live token* - gating on the parameter alone would make `?cs_preview=x` a
   free way to force uncached renders of the whole catalogue.
4. **The target is a sandbox page, one per store view.**
   `bart_content_studio/preview/sandbox` is a frontend route that 404s without
   a live token, renders under the store's own theme with its real header and
   footer, and contains nothing but the entry. A `blog_post_body` sits in
   Magefan's own shells (`body.blog-post-view`, `.post-view` / `.post-content`)
   - the same column the published post uses. Do not invent a preview-only
   frame and patch component CSS to match. `TargetProvider` produces one
   per store view the entry is assigned to (skipping inactive ones -
   `___store` naming an inactive view is ignored, and the link would quietly
   open under another brand's theme) - unless the caller passes
   `allStores: true` (Live Preview's popup does; "View on site" does not), in
   which case assignment is ignored and every active store view is offered.

**Why not links to the real product pages.** An earlier version resolved those
too, per content type, out of `Bart_Catalog`. It was removed. A link is only
worth having if it is *certainly* right, and deriving it meant guessing: which
of fifty products, matched against `mattress_pdp_cms_blocks` - an attribute
declared **global** in `catalog_eav_attribute` while the values that matter are
saved at store scope, so an EAV collection filter joins store 0, finds nothing,
and reports the entry as unattached even though the PDP renders it. A link that
silently points at a product the entry was removed from is worse than no link,
because it looks like the block is broken. The sandbox answers the question
that was actually being asked - how does this content look under this brand -
with no guessing at all, and the per-product context only decides which tab
opens first, which is one click away anyway.

### Live Preview in a separate window

A second entry point onto the same sandbox mechanism above, this one built
for a two-monitor workflow: one **real** browser window, opened via
`window.open()`, showing the actual sandbox page, kept in sync live as the
entry is edited - not an embedded copy squeezed into the side panel.

An earlier version of this drew the sandbox page in an `<iframe>` inside the
preview panel instead. It worked, but it meant giving up real panel width for
a shrunken copy of a full storefront page, and a Block/Page **mode toggle**
that hid the panel's own fast, click-to-select drawn preview (`preview.js`)
whenever the iframe was showing. Both problems evaporate with a real window:
it can be as big as an actual monitor, dragged to a second one, and it does
not have to replace anything - the in-panel preview stays exactly what it
always was, visible all the time, and the "Open Live Preview" row
(`preview-page.js`) just sits above it as an additional way to look.

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
                sandbox page loads --> bridge.js posts {type:'cs:ready'}
                                        |
                                        v
        state.js marks ctx.pageWindowReady, pushes {type:'cs:data', ...}
        on every edit thereafter (postPageUpdate), same as the in-panel
        preview does, just into `ctx.previewWindow` instead of a container
```

Four things about the implementation are not obvious from reading `state.js`
top to bottom:

- **`window.open()` only succeeds inside a real, synchronous click handler.**
  Anything after an `await`/`.then()` has lost the user gesture and every
  popup blocker treats the call as an ad. So `openOrFocusPreviewWindow()`
  opens the window **blank** first, synchronously, off the click; the
  `previewDraft` fetch that decides what to show it runs after, and
  *navigates* the already-open reference (`.location.href = ...`) once it
  resolves. This is the same "open first, point it somewhere after" split
  `requestPreviewTargets` already used for the View on site popover's
  `<a target="_blank">` - see that function's own comment.
- **The popup and the admin tab are cross-origin**, so `bridge.js` on the
  sandbox page cannot use `window.parent` (that was the iframe-era check).
  A page opened via `window.open()` carries `window.opener` instead -
  `bridge.js` no-ops entirely without one (`if (!window.opener) return;`),
  which doubles as "did someone just open the plain preview link in an
  ordinary tab" the same way `window.self === window.top` used to. One
  browser caveat worth knowing: some cross-site tracking protections can
  sever the opener link on a popup that navigates cross-origin (exactly what
  happens going from the blank window to the real sandbox URL). Nothing here
  can detect that from inside the popup; the fallback is simply that the
  block renders once from the server and never goes live - indistinguishable
  from "no opener at all", and considered acceptable rather than worked
  around.
- **There is no "popup closed" event.** `startWindowWatcher()` polls
  `ctx.previewWindow.closed` every 800ms - the one property a script can
  still read on a cross-origin window reference - and falls back the button
  to its "closed" state the moment it notices. `postPageUpdate` and
  `schedulePageReload` both check `previewWindowIsOpen()` first and are
  silent no-ops otherwise; typing with the popup closed costs nothing.
- **Device sizing means literal window dimensions here, not a max-width.**
  The old iframe version's device buttons constrained a frame; there is no
  frame to constrain now, so `DEVICES` in `preview-page.js` carries real
  `width`/`height` pairs, fed to `window.open()`'s features string on open
  and to `window.resizeTo()` if the device is changed while already open.

**"View on site" moved out of the toolbar.** `preview-targets.js`'s popover
still exists - `toolbar.js` still builds it, `state.js` still wires it - but
its button is no longer appended to the DOM (`toolbar.js`'s `right` group).
Live Preview's own button covers the same "see this on a real page" need with
a live-syncing window instead of a list of static links, so the toolbar no
longer needs a second entry point for it. Nothing about the popover's own
mechanics changed; it is dead code by omission, not by deletion, in case a
secondary "open on a specific store" link inside the popup earns its way back.

### Preview links: what the token does and does not protect

Worth being precise about, because the link goes to a live storefront:

- The token is the **only** credential, and it has to be: the admin lives on
  one domain and Sleeping Giant on another, so no admin session cookie reaches
  the previewed page. This is the same model Magento's own CMS and Page Builder
  previews use.
- It grants **exactly one thing** - one identifier's draft rendered on pages
  that already show that identifier. No writes, no other entries, no admin
  actions. It expires in fifteen minutes.
- Preview responses carry `Referrer-Policy: no-referrer`. The token is in the
  query string and a PDP is full of third parties (tag manager, Nosto, image
  CDNs); without this they receive the whole URL in a `Referer` header and the
  analytics ones record it against a page view.
- **No preview cookie, on purpose.** A cookie would make the preview sticky -
  the admin keeps browsing and keeps seeing draft content without realising -
  and, worse, it would make a *normal, cacheable* URL render draft content. The
  entire reason the URL-parameter approach is safe is that preview state is
  visible in the URL and can never be confused with an ordinary request.
  Cross-domain it would not work on the first hop anyway.
- Renaming an entry's identifier in the editor and then previewing offers only
  the sandbox: the products still reference the old identifier, so no PDP
  matches the draft. That is correct, and it surprises people.

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
  area it is the admin's default store - a Sleeping Giant link arrives with
  `___store=default` and opens under the James Lane theme.

The popover fetches first and then renders `<a target="_blank">` for the user
to click, rather than opening a tab when the response lands - a `window.open()`
after a promise resolves has lost its user gesture and is blocked as a popup.
The response also carries a request ticket (`state.previewTargetsRequest`): a
fetch cannot be recalled, so a reply whose ticket is stale is dropped instead,
or closing the popover mid-flight lets it reopen itself a moment later.

**The badge on the sandbox page is `position: fixed`, bottom-centre, at a
z-index above the themes' sticky headers and chat widgets.** It was a full
width bar in the flow and that was wrong twice over: it pushed the block down
so the page no longer matched production, and at that size it read as part of
the design it was labelling. It also counts the token down live
(`view/frontend/web/js/preview/badge.js`, from an `expires_at` stamped into the
draft) - "for 15 minutes" is true when the link is made and a lie every minute
after, and the reload that suddenly 404s otherwise gets blamed on the block.

### Nesting levels have colours

A `compare_mattress` entry is three containers deep - range -> comfort -> dot -
and every level used to paint the same warm off-white, so the only thing
separating them was an indent. Each level now carries its own hue
(eucalyptus -> ochre -> clay) on its row header and on a stripe down its left
edge, while the body surfaces stay near-neutral because a form is for reading.

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

Outline rows also show their **1-based position**. For `compare_mattress` dots
that is the layer number the storefront prints inside the marker
(`ViewModel\Type\CompareMattress::buildDots` sets `layer` to `index + 1`), so
"dot 3" means the same thing in the tree and on the page. Because the ordinal
carries the position, an unlabelled row falls back to the field's singular name
("Layer dot") instead of repeating the number.

---

## Types that exist

| code | standalone | renderer | notes |
|---|---|---|---|
| `compare_mattress` | yes | yes | Range -> comfort -> dots. Used on SG mattress PDPs. |

Twelve more live in `Bart_MagefanBlog` - a `blog_post_body` wrapper and eleven
`group="blog"` components. That module's CLAUDE.md is the reference for them.

---

## Getting an entry onto a page

Three ways, in order of how often they are the right one:

1. **The widget.** `etc/widget.xml` -> `bart_content_studio_entry`, one
   parameter, `identifier`, backed by a chooser grid
   (`Block/Adminhtml/Widget/Chooser.php` + `Controller/Adminhtml/Entry/Chooser.php`).
   Works in a CMS page, a CMS block, a blog post's WYSIWYG and Page Builder's
   `html` content type. The chooser exists because a mistyped identifier
   produces a widget that renders *nothing at all* - `Block\Entry::resolveEntry()`
   returns null for an unknown one - so there is no feedback to correct from.
   It offers only `standalone="true"` types; a nested-only block type has no
   identifier to insert.
2. **Layout or a PHP block**, with `setData('identifier', ...)`.
3. **A field on another entity**, resolved by that module. `Bart_MagefanBlog`
   does this: a blog post stores an identifier and renders the entry in place of
   its Page Builder content.

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
| `etc/content_types.xml` / `.xsd` | type definitions and their schema |
| `Model/Schema/` | reader -> converter -> cached `Data` -> `Config` facade |
| `Model/Entry*`, `Model/ResourceModel/` | entity, store scoping, optimistic locking |
| `Model/ImportExport/` | JSON/ZIP export + import, all-or-nothing |
| `Block/Entry.php` | storefront renderer; also the `bart_content_studio_entry` widget |
| `Model/Preview/` | draft token storage, request context, storefront target resolution |
| `Setup/Patch/Data/` | one-off content migrations for schema moves (see below) |
| `Plugin/Preview/DraftEntryPlugin.php` | serves the draft in place of the stored entry (frontend only) |
| `Observer/PreviewCacheBypass.php` | keeps a preview out of FPC and out of the index |
| `view/base/web/js/type/` | renderers shared by storefront and preview |
| `view/frontend/web/css/source/_module.less` | storefront styles for `compare_mattress` |
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
  query, computed once per page load - good enough, since the underlying data
  only changes on save/delete/import, each a full page reload anyway).
  `view/adminhtml/web/js/grid/type-chips.js` wires clicks to the grid's own
  (now invisible) Filters component via `uiRegistry.get('ns = ..., componentType
  = filters', ...)` and its public `setData()` + `apply()` - the same call a
  real "Content Type" dropdown + "Apply Filters" click would make, so
  pagination reset / bookmarking keep working exactly as they do for every
  other column filter.

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
  `entry/previewFrame` into an `<iframe>`: the block rendered by its
  **storefront** template under that store's theme, with the theme's own
  `styles-m` / `styles-l`. Store tabs and Desktop (1440px, scaled to fit) /
  Mobile (393px) switch it. Inactive entries preview too
  (`Block\Entry::setPreviewEntry()` skips the identifier lookup).

How `PreviewFrame` works, and why it is admin-served:

- Store + area **emulation** (`Emulation` + `AppState::emulateAreaCode`), the
  mechanism transactional emails use, so templates, view models, `{{media}}`
  and asset URLs resolve as on the storefront.
- The store's static and media base URLs are then made **root-relative**.
  `pub/static` / `pub/media` are the same files on every domain, and loading
  them from the admin's host keeps web fonts (no CORS headers) and CSS-mask
  icons working.
- It is **not** the storefront sandbox in an iframe: that page lives on the
  brand's domain and the site sends `X-Frame-Options: SAMEORIGIN`, so Sleeping
  Giant cannot be framed from the admin without relaxing framing headers on
  the storefront. A same-origin admin document needs no header changes.
- Storefront scripts the blocks need (tabs, sliders) come from the
  controller's `scripts` DI argument; `Bart_ContentBlocks` adds its own the
  same way `typeViewModels` is extended.

### Grid sticky header

The stock sticky clone (`listingToolbar` -> `sticky`) is **off**: it is
positioned for the admin menu (`left: 88px`) that the full-bleed layout
removes, so it floated off to the right with misaligned columns. The real
`thead th` row is `position: sticky` in `_grid.less`, and Magento's floating
`.page-actions._fixed` bar is pinned back in place for the same reason.

## Verifying changes

`vendor/` is not in the repo - everything runs in the container:

```bash
docker compose exec php bin/magento cache:flush
docker compose exec php bin/magento setup:upgrade
```

Gates that must pass (`grumphp.yml`):

```bash
./node_modules/.bin/eslint --no-eslintrc -c .eslintrc --plugin sonarjs \
  $(find app/code/Bart/ContentStudio/view -name "*.js")
./node_modules/.bin/stylelint --customSyntax postcss-less \
  $(find app/code/Bart/ContentStudio/view -name "*.less")
./node_modules/.bin/stylelint app/code/Bart/ContentStudio/view/adminhtml/web/css/menu-icon.css
```

Unit tests live in `Test/Unit/` and are picked up automatically by
`dev/tests/unit/phpunit.xml.dist` (its suite globs `app/code/*/*/Test/Unit`).
**phpunit is not installed in this container** - the composer install is
`--no-dev`. Fetch the phar to run them:

```bash
docker compose exec php sh -c \
  "curl -sSL -o /tmp/phpunit.phar https://phar.phpunit.de/phpunit-10.phar"
docker compose exec php php -d xdebug.mode=off /tmp/phpunit.phar \
  -c dev/tests/unit/phpunit.xml.dist --no-coverage app/code/Bart/ContentStudio/Test/Unit
```

`-d xdebug.mode=off` only silences the "could not connect to debugging client"
lines this container prints on every PHP invocation - without it they interleave
with the progress dots. The Allure-extension warning is pre-existing repo
config, not ours.

`phpcs` **cannot run locally**: `magento/magento-coding-standard` is a dev
dependency, so the ruleset grumphp points at is absent from `vendor/`. That gate
first runs in CI - keep lines at 120 characters and the file docblock/`@param`
conventions of the surrounding tests.

What the suite deliberately covers (unit tests here are for logic that fails
*silently*, not for coverage percentage):

| Test | Pins |
|---|---|
| `Model/Schema/SchemaValidator*` | field validation, `normalize`, identifier uniqueness, image fields |
| `Model/Schema/ConverterTest` | XML -> array: bool/numeric casts, **absent attribute stays `null`, not `false`**, nested repeater/group, `<option>`, `<allow>` |
| `Model/Template/SafeFilterTest` | the security contract: `{{block}}`/`{{layout}}` resolve to nothing, `{{media}}` still resolves |
| `Model/ImportExport/ExportFileNamerTest` | the `-<entry_id>` suffix that stops same-identifier entries overwriting each other in a ZIP |
| `Model/ImportExport/Processor/ImportTest` | identifier + **exact store set** matching, skip/update modes, dry run, all-or-nothing rollback |
| `Model/ResourceModel/.../KeywordFilterApplierTest` | the grid search `WHERE` clause |
| `ViewModel/Type/CompareMattressTest` | logic shared with the JS preview renderer |

`SafeFilterTest` does **not** call `filter()`. `Filter::filter()` resolves
`SignatureProvider` and `FilteringDepthMeter` through the global ObjectManager,
which `Email\Template\Filter` never forwards to its parent constructor, so no
unit test can supply them. The test builds the filter with
`newInstanceWithoutConstructor()`, injects only `_storeManager`, and dispatches
through a real `LegacyDirective` - the same `{$name}Directive` reflection lookup
production uses. The passing `{{media}}` case is the control: if dispatch ever
breaks, an empty string for `{{block}}` would stop meaning anything, and that
test fails first.

ESLint here is strict: `max-depth: 2`, `sonarjs/cognitive-complexity: 15`,
`no-var`, `eqeqeq`, `no-undefined`, `no-use-before-define`, and `valid-jsdoc`
(a `/** */` block on a function must document every param and the return - use
plain `//` comments for internal helpers instead).

**Always pass file lists via `find`, never a `**` glob** - shell glob expansion
silently missed files here once and produced a false "lint clean" report.

Demo data currently in this environment: entry `compare_mattress_config`
(store 2 / Sleeping Giant) attached to two Windsor products so the same entry
opens on a different comfort tab on each - the per-product context merge.

Bypass for verifying module logic without the admin UI: a throwaway script that
does `require 'app/bootstrap.php'; $om = \Magento\Framework\App\Bootstrap::
create(BP, $_SERVER)->getObjectManager();`, sets the area code, and calls the
class directly (`$om->get(...)`/`$om->create(...)`) - that is how PHP-level
checks (schema validation, entry repository, grid search) get verified when
HTTP admin access is unavailable.

---

## Traps that have already cost time

- **Admin CSS is merged.** The grid page links one
  `pub/static/_cache/merged/<hash>.css`, not `studio.css`. After a LESS change,
  delete that file **and its `.css.dat`** - with only the CSS gone the checksum
  file says "up to date", nothing is rewritten, and the admin loses all styling
  (404). A LESS compile error (e.g. `width: min(1100px, 100%)` - LESS evaluates
  `min()` and fails on mixed units) also takes the whole `studio.css` down.
- **`[hidden]` loses to any author `display` rule.** `[hidden] { display: none }`
  lives in the *UA* stylesheet, and every author rule beats the UA stylesheet
  regardless of specificity - so `.studio-preview__page-status { display: flex }`
  and `.studio-select { display: block }` both left their elements on screen
  while `hidden` was set, with no error anywhere. Any component that sets
  `display` and is toggled via the attribute needs its own `&[hidden] { display:
  none }`. This is why the preview's status card and store picker each carry one.
- **Rebuilding the fields panel clamps the page scroll.** `.studio-editor__fields`
  has no overflow of its own - the *document* scrolls - so emptying it collapses
  the page height, and any layout read taken before it is refilled (this file's
  own `scrollIntoView` calls are enough) clamps `window.scrollY` to the collapsed
  height and never restores it. Measured at ~1070px of drift on a normal entry.
  It reads as "scrolling is broken", not as a re-render, because the trigger is
  usually a dot drag: drop -> commit -> `renderFormPanels` -> snap back to the top.
  `renderFormPanels` saves and restores the scroll position for exactly this;
  keep that guard on any new full-panel rebuild.
- **Verify against the compiled output, not the source.** A LESS change can lint
  clean and still break the build: `fade()` and `mix()` are build-time functions
  and reject CSS custom properties, which took the whole storefront stylesheet
  to a 404. Fetch the real `styles-l.css` / `studio.css` and grep it.
- **Clearing only `css/studio.css` out of `pub/static` is not enough for JS.** A
  changed `js/editor/*.js` keeps being served from `pub/static` until that path
  is deleted too; a `cache:flush` alone will not republish it, and the browser
  happily serves a file whose `version...` segment did not change. Delete the whole
  `pub/static/adminhtml/*/*/*/Bart_ContentStudio` directory when JS is involved.
- **A named preview window survives page loads.** `window.open('', 'cs_page_preview')`
  *adopts* an existing window of that name instead of making a new one - which is
  what keeps a single preview window rather than one per click, but it also means
  a popup left open by an earlier session (or an earlier entry) is picked up on
  the next Open, showing that old page until the navigation lands. Fine in
  product; badly confusing while testing, where it silently made a fresh editor
  look like it had defaulted to the wrong store. Close stray preview windows
  before drawing conclusions about which store a fresh load picked.
- **`var/view_preprocessed` caches every `@import`ed partial separately.**
  Deleting `.../Bart_ContentStudio/css/studio.*` is *not* enough - the stale
  `css/source/_preview.less` etc. next to it are what actually get recompiled
  from, so an edit to a partial silently never lands. This cost a whole round of
  "the fix doesn't work" when the fix was already correct. Clear the directory:
  ```bash
  rm -rf var/view_preprocessed/pub/static/adminhtml/*/*/*/Bart_ContentStudio \
         pub/static/adminhtml/*/*/*/Bart_ContentStudio/css/studio.css
  ```
  Do **not** `rm -rf` the module's whole `pub/static` dir - it ships the Karla /
  DM Serif woff2 files, and an open tab then 404s on fonts.
- **`.is-outline-highlight` animates `background-color`.** Harmless on an
  outline row (transparent anyway), fatal on anything whose background *is* the
  element: it blanked the preview's dot markers and their numbers for the full
  480ms. `flashOutlineTarget(target, className)` takes the class for that
  reason - dots pass `is-preview-dot-flash`, which pulses a ring instead.
- **Clearing `pub/static` mid-session makes the browser 404 on fonts.** Files
  regenerate on request in developer mode; an already-open tab keeps asking for
  the old `version...` path. Hard reload, not a bug.
- **A reset must be the weakest rule in the file.** `.studio-editor-page button`
  scores 0-1-1 and silently beat every `.studio-btn*` rule. The reset is wrapped
  in `:where()` now - keep it that way.
- **`Bart_App/css/data-grid-scroll.css` sets `overflow: auto !important`** on
  every admin grid, which clipped the row Action menu. Beaten on specificity in
  `_grid.less`, not by editing that shared file.
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
  directory, so Browse and drag&drop finally share one folder.

- **Magento's media browser announces its pick with a jQuery-only event.**
  `mage/adminhtml/browser.js::chooseFile` sets `targetEl.val(...)` and then
  `jQuery(targetEl).trigger('change')`. jQuery's `.trigger()` walks its *own*
  handler registry and only falls back to a real DOM dispatch when the element
  has a matching method (`.click()`, `.focus()`); `HTMLInputElement` has no
  `.change()`, so nothing native ever fires. Neither a listener bound with
  `addEventListener` on the input nor the delegated `change` listener on
  `#studio-editor` can hear it. The symptom is nasty because it looks like the
  opposite of a wiring bug: the URL is visibly sitting in the field while
  `state.data` still holds the old value, so Save reports the field as
  *required*. `openImageBrowser` therefore binds through jQuery - the one place
  in this module that must - and converts straight back to
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
- **`ONLY_FULL_GROUP_BY` is off locally, on elsewhere.** The store filter is a
  semi-join (`EXISTS`) rather than a JOIN + GROUP BY for that reason - MariaDB
  rejects `SELECT main_table.*` with a GROUP BY. Do not "simplify" it back.
- **CLI + `frontend` area is unreliable in this environment** (unbounded memory,
  recursion in an unrelated plugin). Verify storefront behaviour over real HTTP.

---

## Known gaps

- **Moving a field in the schema needs a data patch.** Entry content is a JSON
  blob, so a field that changes level is not "renamed" - it is simply no longer
  read, and the editor stops rendering the input that could put it back, so the
  value is unreachable rather than wrong. `Setup/Patch/Data/
  MoveCompareMattressCtaToComfort.php` is the worked example (dot CTA -> comfort
  CTA): it writes the table directly rather than going through the repository,
  because a repository save would bump `version` and `updated_at` and make the
  migration look like a person had edited every entry - and would break the
  optimistic lock for anyone with the editor open. It fills gaps only, never
  overwrites, so it is safe to rerun.

- **Identifier uniqueness is check-then-write.** No DB constraint can express it
  (one identifier may legitimately exist once per brand), so two simultaneous
  *creates* could both pass. Updating an existing entry is fully guarded.
- **Image upload fails locally** with `Invalid api_key` - `Cloudinary_Cloudinary`
  plugs into `Uploader::save()`. Content validation runs before that and works;
  the file is written locally. Environment issue, not module code. The same
  cause also surfaces through Magento's own media browser as the generic
  *"We can't upload the file to the current folder right now. Please try
  another folder."* - that dialog is `Wysiwyg\Images\Upload` swallowing the
  Cloudinary exception, not a permissions problem with the folder, and chasing
  it as one wastes an afternoon. Picking an already-uploaded file works;
  only the upload leg is blocked.
- **`icon` fields and sliders** are declared in the XSD and not implemented.
- **`wysiwyg` is a source textarea**, not a rich-text surface. The storage
  format is final (sanitized HTML); only the control is provisional. See
  `plans/007-blog-platform.md` section "Phase 0" for the TinyMCE step.
- **A reference cycle is not detected.** `related_posts` pointing back at its
  own post is accepted; nothing follows references recursively today, so it
  costs nothing yet, and the guard belongs wherever the first recursive
  traversal lands.
