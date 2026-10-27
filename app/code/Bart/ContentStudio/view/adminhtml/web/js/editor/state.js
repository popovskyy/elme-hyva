/**
 * Editor entry point (invoked by x-magento-init as `fn(config, element)`).
 *
 * Owns the mutable editor state and is the single place that mutates it:
 * every field module only ever *describes* DOM via render(ctx) or dispatches
 * data-action/data-path markers - this file is where those markers turn into
 * state changes, via one delegated listener on the editor root (plan section 3).
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/lib/path',
    'Bart_ContentStudio/js/editor/lib/schema',
    'Bart_ContentStudio/js/editor/render',
    'Bart_ContentStudio/js/editor/meta-bar',
    'Bart_ContentStudio/js/editor/preview',
    'Bart_ContentStudio/js/editor/preview-page',
    'Bart_ContentStudio/js/editor/review',
    'Bart_ContentStudio/js/editor/toolbar',
    'Bart_ContentStudio/js/editor/preview-targets',
    'Bart_ContentStudio/js/editor/outline',
    'Bart_ContentStudio/js/editor/splitter',
    'Bart_ContentStudio/js/editor/lib/media-config',
    'Bart_ContentStudio/js/editor/lib/reference-options',
    'Bart_ContentStudio/js/editor/lib/datetime',
    'Bart_ContentStudio/js/editor/lib/price',
    'Bart_ContentStudio/js/editor/lib/mock-data',
    'Bart_ContentStudio/js/editor/field/repeater',
    'Bart_ContentStudio/js/editor/field/blocks',
    'Bart_ContentStudio/js/editor/field/color'
], function (
    dom,
    pathLib,
    schemaLib,
    renderModule,
    metaBar,
    previewModule,
    previewPageModule,
    reviewModule,
    toolbarModule,
    previewTargetsModule,
    outlineModule,
    splitterModule,
    mediaConfigModule,
    referenceOptionsModule,
    datetimeLib,
    priceLib,
    mockDataLib,
    repeaterField,
    blocksField,
    colorField
) {
    'use strict';

    const HISTORY_LIMIT = 20;
    const PREVIEW_DEBOUNCE_MS = 150;
    const VIEW_FADE_MS = 140;
    // Keep equal to the studio-outline-flash duration in _module.less - the
    // class is meant to come off exactly when the animation ends.
    const OUTLINE_HIGHLIGHT_MS = 480;
    const OUTLINE_FLASH_CLASS = 'is-outline-highlight';
    const SELECTED_CLASS = 'is-selected';
    const INVALID_CLASS = 'is-invalid';
    const INVALID_ROW_SELECTOR = '.studio-field, .studio-meta-bar__field';
    // The tree is the only place a deep entry is navigable - repeaters inside
    // repeaters - and at 220px the labels of the third level were ellipsed to
    // the point of being unreadable, so the expanded branch told you nothing.
    // The centre column gives up the room: it holds one field per line and had
    // width to spare.
    const OUTLINE_WIDTH_MIN = 180;
    const OUTLINE_WIDTH_MAX = 480;
    const OUTLINE_WIDTH_DEFAULT = 300;
    const PREVIEW_WIDTH_MIN = 280;
    const PREVIEW_WIDTH_MAX = 640;
    const PREVIEW_WIDTH_DEFAULT = 420;
    // Suffixed keys: the width is remembered per browser, so anyone who has
    // used the editor before carries the old 220 and would never see the new
    // default. A new key retires those values once.
    const STORAGE_OUTLINE_WIDTH = 'studio-outline-width-v2';
    const STORAGE_OUTLINE_COLLAPSED = 'studio-outline-collapsed';
    const STORAGE_PREVIEW_WIDTH = 'studio-preview-width-v2';
    const STORAGE_PREVIEW_DEVICE = 'studio-preview-device';

    // Collapsed, the outline keeps a rail wide enough for its toggle and the
    // word "Show". It does NOT collapse to zero: a panel that vanishes
    // completely takes its way back with it, and a chevron on its own is a
    // mystery, not a control.
    //
    // The tree is worth its width on a type that nests repeaters several
    // levels deep. On a flat type it is a table of contents, and a table of
    // contents does not need 300px of a laptop screen at all times.
    const OUTLINE_RAIL_WIDTH = 56;
    // Nothing here can repaint the popup window in place, so an edit means a
    // real navigation. Slower than the 150ms in-panel update on purpose: a
    // full navigation on every keystroke would both hammer the server and be
    // visibly janky.
    const PAGE_RELOAD_DEBOUNCE_MS = 1200;

    // Everything this editor posts up to a parent form when it is framed
    // (`?embedded=1`) carries this source, and the opener drops anything else
    // that reaches its listener. Two message types today: the save result, and
    // the dirty state below.
    const PARENT_MESSAGE_SOURCE = 'bart-content-studio';

    // The outline flash is a singleton: only one field is highlighted at a
    // time, and a new click has to cancel the previous one's pending removal
    // rather than let it fire mid-way through the new flash. The class is
    // tracked alongside the node because callers pass their own (see
    // flashOutlineTarget) - clearing the wrong one would strand it.
    let outlineHighlightTimer = null;
    let outlineHighlightNode = null;
    let outlineHighlightClass = null;

    function readControlValue(fieldType, control) {
        if (fieldType === 'boolean') {
            return control.checked;
        }
        if (fieldType === 'number') {
            return control.value === '' ? null : Number(control.value);
        }
        // The control speaks local wall-clock time and carries no zone; storage
        // is always UTC. Converting here rather than in the field renderer keeps
        // the read side in the one place that already knows how to read a
        // control (see lib/datetime.js).
        if (fieldType === 'datetime') {
            return datetimeLib.toStored(control.value);
        }
        return control.value;
    }

    // Bootstrap used to hand us a JSON Array when the entry had no data yet
    // (PHP `[]` -> `[]`). Named keys set on an Array work in memory - the
    // preview reads `data.title` fine - but JSON.stringify drops them, so
    // Save posted an empty payload and "Title is required" fired on a field
    // that looked filled. Always keep a plain object.
    function asDataObject(value) {
        if (!value || typeof value !== 'object') {
            return {};
        }

        return Array.isArray(value) ? Object.assign({}, value) : value;
    }

    // Moved up from beside save()/requestSave() (their more natural home) so
    // that Page-mode's postPageUpdate/requestPageTargets, below, can reach
    // them: renderPreviewNow calls postPageUpdate directly and sits very early in
    // this file, and this codebase enforces no-use-before-define strictly, so
    // whatever renderPreviewNow can reach has to be declared above it too.
    // These three are self-contained enough to move without consequence;
    // save()/requestSave() reference them exactly as before, just now forward
    // rather than immediately above.
    function buildPayload(ctx, duplicate) {
        const data = asDataObject(ctx.state.data);

        return {
            duplicate: Boolean(duplicate),
            type: ctx.state.typeCode,
            identifier: ctx.state.meta.identifier,
            title: ctx.state.meta.title,
            is_active: ctx.state.meta.isActive,
            store_ids: ctx.state.meta.storeIds,
            version: ctx.state.meta.version,
            entry_id: ctx.state.meta.entryId,
            // Belt-and-braces for tabs that still hold an Array from an older
            // bootstrap - see asDataObject().
            data: data
        };
    }

    function postJson(ctx, url, payload) {
        return fetch(url + '?form_key=' + encodeURIComponent(ctx.state.formKey), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json();
        });
    }

    function postPayload(ctx, payload) {
        return postJson(ctx, ctx.state.urls.save, payload);
    }

    // ---------------------------------------------------------------------
    // Preview panel - "Live Preview in a separate window". A *separate
    // browser window*, not an iframe embedded in the panel - the point is a
    // second monitor showing the real sandbox page while the editor stays
    // full-height on the first, not a shrunken copy fighting the fields
    // column for room. Purely additive: it sits above the panel's own drawn
    // preview (preview.js), which stays exactly what it always was - fast,
    // always visible - rather than a mode that replaces it. The window is a
    // server-rendered sandbox page with no script of this module on it, so an
    // edit reaches it as a debounced full navigation with a fresh draft
    // (schedulePageReload), the only way to get new markup into it.
    //
    // Always requests every store (`all_stores: true`), unlike the View on
    // site popover's request - this is "how does this look under this
    // brand", not "is this actually published there", so an entry not yet
    // assigned to Elme Living should still be previewable on it.
    //
    // `window.open()` only succeeds when called synchronously from a real
    // user gesture - anything after an `await`/`.then()` has lost it and
    // every popup blocker treats the call as an ad. So the window opens
    // first (blank), and the `previewDraft` fetch that decides what to show
    // it navigates the already-open reference afterwards - the same
    // "open, then point it somewhere" split requestPreviewTargets uses for
    // the View on site popover's tab, and the exact trap noted there.
    // ---------------------------------------------------------------------

    const PREVIEW_WINDOW_NAME = 'cs_page_preview';
    // Cross-origin windows only expose `.closed` to a script holding the
    // reference - there is no "the popup I opened just closed" event, so a
    // short poll is the only way to notice and fall back to the "closed"
    // status/button label instead of silently pushing postMessages into a
    // window that no longer exists.
    const WINDOW_WATCH_MS = 800;

    /**
     * @param {Array<Object>} targets - `previewDraft`'s response list.
     * @param {number} storeId - The store id to prefer.
     * @returns {Object|null} The matching target, or the first one as a fallback.
     */
    function resolvePageTarget(targets, storeId) {
        if (!targets || !targets.length) {
            return null;
        }

        const matched = targets.filter(function (target) {
            return target.storeId === storeId;
        })[0];

        return matched || targets[0];
    }

    function findDevicePreset(device) {
        return previewPageModule.DEVICES.filter(function (d) {
            return d.key === device;
        })[0] || previewPageModule.DEVICES[0];
    }

    function previewWindowIsOpen(ctx) {
        return Boolean(ctx.previewWindow) && !ctx.previewWindow.closed;
    }

    /**
     * Active store views the preview can open under - bootstrap `stores`
     * carries every view for the meta bar's assignment picker, including
     * switched-off ones, and TargetProvider skips those (a `___store` naming
     * an inactive view is ignored and the page quietly opens under another
     * brand's theme). Offering one here would mean a pick that can never
     * resolve to a page.
     *
     * @param {Object} ctx - The editor's mutable state and dom refs.
     * @returns {Array<Object>} `{id, label}`-shaped options for the picker.
     */
    function previewStoreOptions(ctx) {
        return (ctx.state.stores || []).filter(function (store) {
            return store.isActive;
        });
    }

    /**
     * The store the popup opens on before anyone touches the picker. Has to
     * be one the entry is actually assigned to: the preview asks for
     * `all_stores` (so every brand is switchable), which makes `targets[0]`
     * the first active store view in the system rather than one this entry
     * belongs to - an entry scoped to Sleeping Giant alone would otherwise
     * open under James Lane's theme, the exact confusion the picker exists to
     * prevent. `storeIds` of `[0]` means "all", matches nothing here, and
     * correctly falls through to the first active view.
     *
     * @param {Object} ctx - The editor's mutable state and dom refs.
     * @returns {number} A store id, or 0 when there are no active views.
     */
    function defaultPreviewStoreId(ctx) {
        const options = previewStoreOptions(ctx);

        if (!options.length) {
            return 0;
        }

        const assigned = ctx.state.meta.storeIds || [];
        const scoped = options.filter(function (store) {
            return assigned.indexOf(store.id) !== -1;
        })[0];

        return (scoped || options[0]).id;
    }

    function stopWindowWatcher(ctx) {
        if (ctx.pageWindowWatcher) {
            clearInterval(ctx.pageWindowWatcher);
            ctx.pageWindowWatcher = null;
        }
    }

    function startWindowWatcher(ctx) {
        stopWindowWatcher(ctx);
        ctx.pageWindowWatcher = setInterval(function () {
            if (previewWindowIsOpen(ctx)) {
                return;
            }

            stopWindowWatcher(ctx);
            ctx.previewWindow = null;
            previewPageModule.setWindowState(ctx.dom, 'closed');
        }, WINDOW_WATCH_MS);
    }

    function windowFeatures(device) {
        const found = findDevicePreset(device);
        const left = Math.max(0, Math.round((window.screen.availWidth - found.width) / 2));
        const top = Math.max(0, Math.round((window.screen.availHeight - found.height) / 2));

        return 'width=' + found.width + ',height=' + found.height
            + ',left=' + left + ',top=' + top + ',resizable=1,scrollbars=1';
    }

    /**
     * Opens the popup blank, or refocuses the one already open. Must be
     * called synchronously off a click - see this section's own comment on
     * why the window has to exist before the fetch, not after.
     *
     * @param {Object} ctx - The editor's mutable state and dom refs.
     * @returns {Window|null} The window, or null if the browser blocked it.
     */
    function openOrFocusPreviewWindow(ctx) {
        if (previewWindowIsOpen(ctx)) {
            ctx.previewWindow.focus();

            return ctx.previewWindow;
        }

        ctx.previewWindow = window.open('', PREVIEW_WINDOW_NAME, windowFeatures(ctx.state.previewDevice));

        if (ctx.previewWindow) {
            startWindowWatcher(ctx);
        }

        return ctx.previewWindow;
    }

    /**
     * Drops the popup and every bit of state tracking it, without touching the
     * status card - callers that close a window do so *because* something went
     * wrong, and the message explaining that is already on screen. Stopping the
     * watcher explicitly is what protects it: left running, its next tick would
     * see the closed window and overwrite the error with a bare "closed".
     *
     * @param {Object} ctx - The editor's mutable state and dom refs.
     * @returns {void}
     */
    function closePreviewWindow(ctx) {
        if (previewWindowIsOpen(ctx)) {
            ctx.previewWindow.close();
        }

        stopWindowWatcher(ctx);
        ctx.previewWindow = null;
    }

    /**
     * Points an already-open window at a URL. A no-op if the window was
     * closed out from under us - the watcher (startWindowWatcher) is what
     * notices that and updates the UI; this function does not need to.
     *
     * @param {Object} ctx - The editor's mutable state and dom refs.
     * @param {string} url - The sandbox URL to navigate to.
     * @param {boolean} shouldFocus - Whether to raise the window afterwards.
     *      Only true when the navigation is the direct result of a click on
     *      Open or on the store picker. A debounced reload (schedulePageReload)
     *      passes false: that fires while someone
     *      is still typing, and raising the popup over the editor mid-sentence
     *      steals the keystrokes that come next.
     * @returns {void}
     */
    function navigatePreviewWindow(ctx, url, shouldFocus) {
        if (!previewWindowIsOpen(ctx)) {
            return;
        }

        ctx.previewWindow.location.href = url;

        if (shouldFocus) {
            // A focus() call without a fresh gesture is silently ignored by
            // most browsers rather than erroring, so no guard is needed here.
            ctx.previewWindow.focus();
        }
    }

    /**
     * Mints a fresh preview token for the current payload - the same
     * `previewDraft` endpoint and payload "View on site" uses, plus
     * `all_stores: true` (see this section's file comment), so Page mode
     * always shows exactly what a save would write, on any brand.
     *
     * @param {Object} ctx - The editor's mutable state and dom refs.
     * @returns {Promise<Object|null>} The resolved target, or null on failure
     *      (status is already set on `ctx.dom` by the time it resolves null).
     */
    function requestPageTargets(ctx) {
        const requestId = ++ctx.pageLoadRequest;
        const payload = buildPayload(ctx, false);

        payload.all_stores = true;

        return postJson(ctx, ctx.state.urls.previewDraft, payload)
            .then(function (result) {
                if (requestId !== ctx.pageLoadRequest) {
                    return null;
                }

                if (!result || !result.success) {
                    previewPageModule.setWindowState(ctx.dom, 'error', result && result.message);

                    return null;
                }

                ctx.pageTargets = result.targets || [];

                const target = resolvePageTarget(ctx.pageTargets, ctx.state.previewStoreId);

                if (!target) {
                    previewPageModule.setWindowState(ctx.dom, 'error', 'No storefront page shows this entry yet.');

                    return null;
                }

                // The picker's options come from the bootstrap store list and
                // are built once, at mount - not rebuilt from the response, so
                // it is populated and usable before the first window is ever
                // opened, and its labels never change mid-session (the two
                // sources word them differently: "James Lane - James Lane Store
                // View" here against TargetProvider's bare store name). Only
                // the *selection* is resynced, and only when resolvePageTarget
                // had to fall back - a store deactivated since the page loaded.
                if (target.storeId !== ctx.state.previewStoreId) {
                    ctx.state.previewStoreId = target.storeId;
                    previewPageModule.setStores(
                        ctx.dom.previewStoreSelect,
                        previewStoreOptions(ctx),
                        target.storeId
                    );
                }

                return target;
            })
            .catch(function () {
                if (requestId === ctx.pageLoadRequest) {
                    previewPageModule.setWindowState(ctx.dom, 'error');
                }

                return null;
            });
    }

    /**
     * What the "Open Live Preview" button calls.
     *
     * @param {Object} ctx - The editor's mutable state and dom refs.
     * @returns {void}
     */
    function openPreviewWindow(ctx) {
        const reusedExisting = previewWindowIsOpen(ctx);
        const win = openOrFocusPreviewWindow(ctx);

        if (!win) {
            previewPageModule.setWindowState(ctx.dom, 'blocked');

            return;
        }

        previewPageModule.setWindowState(ctx.dom, 'opening');

        requestPageTargets(ctx).then(function (target) {
            if (!previewWindowIsOpen(ctx)) {
                return;
            }

            if (!target) {
                // A window opened for this click is still sitting on
                // about:blank with nothing to show it, and previewWindowIsOpen
                // would go on reporting it as live - which means
                // schedulePageReload firing a fresh, equally doomed
                // previewDraft every 1.2s for the rest of the session.
                // Close it; the status card requestPageTargets already set is
                // what explains the failure. A window that was already open
                // keeps whatever it is showing - it is still a valid preview
                // of the last good state.
                if (!reusedExisting) {
                    closePreviewWindow(ctx);
                }

                return;
            }

            navigatePreviewWindow(ctx, target.url, true);
            previewPageModule.setWindowState(ctx.dom, 'open');
        });
    }

    // Debounced separately from the 150ms live path (PREVIEW_DEBOUNCE_MS):
    // this is a full navigation, not a repaint, and firing one on every
    // keystroke would be both slow to watch and needless load on the server.
    // A no-op with no window open - nothing to navigate, and no reason to
    // spend a token on a page nobody has opened yet.
    function schedulePageReload(ctx) {
        if (!previewWindowIsOpen(ctx)) {
            return;
        }

        if (ctx.pageReloadTimer) {
            clearTimeout(ctx.pageReloadTimer);
        }
        ctx.pageReloadTimer = setTimeout(function () {
            requestPageTargets(ctx).then(function (target) {
                if (target) {
                    // No focus: this fires mid-typing (see the parameter's own
                    // doc on navigatePreviewWindow).
                    navigatePreviewWindow(ctx, target.url, false);
                }
            });
        }, PAGE_RELOAD_DEBOUNCE_MS);
    }

    /**
     * Keeps the popup window in step with the latest edit. A no-op unless the
     * window is actually open - run regardless of anything else on screen,
     * because the whole point is a second monitor staying in sync while the
     * first one shows the in-panel Block preview (preview.js), not a mode you
     * have to be "in" for this to run.
     *
     * Always a reload, renderer or not: the sandbox page carries no script
     * that could repaint the block in place.
     *
     * @param {Object} ctx - The editor's mutable state and dom refs.
     * @returns {void}
     */
    function postPageUpdate(ctx) {
        schedulePageReload(ctx);
    }

    /**
     * @param {Object} ctx - The editor's mutable state and dom refs.
     * @param {string} device - One of preview-page.js's DEVICES keys.
     * @returns {void}
     */
    function changePreviewDevice(ctx, device) {
        ctx.state.previewDevice = device;
        window.localStorage.setItem(STORAGE_PREVIEW_DEVICE, device);
        previewPageModule.setDevice(ctx.dom, device);

        if (!previewWindowIsOpen(ctx)) {
            return;
        }

        const found = findDevicePreset(device);

        try {
            ctx.previewWindow.resizeTo(found.width, found.height);
        } catch (e) {
            // Some browsers refuse resizeTo() on a window they no longer
            // consider "script-controlled" after it navigated cross-origin.
            // Not fatal - the window just stays whatever size it already is.
        }
    }

    /**
     * Moves the preview to another brand's store view. With no window open the
     * choice is simply remembered, and whenever one is next opened it opens
     * there.
     *
     * Re-mints rather than reusing the matching entry in `ctx.pageTargets`.
     * Those were minted at the last open or reload and a token lives fifteen
     * minutes. Reusing one late in a long editing session navigates to a
     * token the storefront has already expired, and the sandbox route answers
     * a 404.
     * Even inside the fifteen minutes the stored draft is whatever the entry
     * looked like at open time, so a plain navigation would drop every edit
     * made since - the switch has to carry the current payload.
     *
     * @param {Object} ctx - The editor's mutable state and dom refs.
     * @param {number} storeId - The store id the `<select>` was changed to.
     * @returns {void}
     */
    function changePreviewStore(ctx, storeId) {
        ctx.state.previewStoreId = storeId;

        if (!previewWindowIsOpen(ctx)) {
            return;
        }

        requestPageTargets(ctx).then(function (target) {
            if (target) {
                // Focus: this navigation is the direct result of a click.
                navigatePreviewWindow(ctx, target.url, true);
            }
        });
    }

    // Pushes a *pre-change* snapshot onto the undo stack so undo() can pop it
    // back as the current state, and clears the redo stack (a fresh edit
    // invalidates whatever was previously undone).
    function pushSnapshot(ctx, snapshot) {
        ctx.state.history.push(snapshot);

        if (ctx.state.history.length > HISTORY_LIMIT) {
            ctx.state.history.shift();
        }
        ctx.state.redo = [];
    }

    // The one place the parent form is told whether there is anything to lose.
    // `beforeunload` never fires for a framed page being removed from the DOM,
    // so a modal closing over unsaved work is silent from in here - the opener
    // has to hold the flag and refuse the close itself.
    function postDirtyState(ctx) {
        if (!ctx.state.embedded) {
            return;
        }

        window.parent.postMessage({
            source: PARENT_MESSAGE_SOURCE,
            type: 'dirty',
            dirty: Boolean(ctx.state.dirty)
        }, window.location.origin);
    }

    function updateSaveButton(ctx) {
        ctx.dom.saveButton.disabled = !ctx.state.dirty;
        ctx.dom.saveLabel.textContent = ctx.state.dirty ? 'Save' : 'Saved';
        ctx.dom.dirtyIndicator.hidden = !ctx.state.dirty;
        postDirtyState(ctx);
    }

    // Whether the entry holds nothing an author would mind losing. Every
    // declared field is either absent or an empty string/list - which is what
    // both a brand-new entry and a freshly created, still-untouched one look
    // like.
    function isBlankEntry(data) {
        return Object.keys(data || {}).every(function (key) {
            const value = data[key];

            return value === null || typeof value === 'undefined' || value === '' ||
                (Array.isArray(value) && value.length === 0);
        });
    }

    // Shown only while there is nothing to lose: an entry with no content yet
    // that nobody has touched in this session (state.dirty false). The moment
    // either stops being true - the user typed something, or added a row - it
    // stops rendering on the next pass, so it can never appear as an offer to
    // overwrite content someone has already started.
    //
    // Emptiness, not `state.meta.entryId`, is the test: an entry created and
    // saved by another admin form *before* the editor opens (`?embedded=1`)
    // already has an id, and gating on that would hide the prompt from it.
    function renderMockDataPrompt(ctx) {
        const fixture = mockDataLib[ctx.state.typeCode];

        if (!fixture || ctx.state.dirty || !isBlankEntry(ctx.state.data)) {
            return;
        }

        const prompt = dom.el('div', {class: 'studio-mock-data'}, [
            dom.el('p', {class: 'studio-mock-data__title'}, 'Starting from scratch?'),
            dom.el(
                'button',
                {type: 'button', class: 'studio-btn studio-btn--secondary', dataset: {action: 'add-mock-data'}},
                'Add Mock Data'
            ),
            // Per fixture: "one range with two layer dots" and "one of every
            // component" are different promises, and one sentence cannot make
            // both honestly.
            dom.el('p', {class: 'studio-mock-data__caption'}, fixture.caption)
        ]);

        ctx.dom.fieldsPanel.insertBefore(prompt, ctx.dom.fieldsPanel.firstChild);
    }

    function renderFieldsPanel(ctx) {
        const type = schemaLib.getType(ctx.state.schema, ctx.state.typeCode) || {fields: []};

        renderModule.renderFields(ctx.dom.fieldsPanel, type.fields, ctx.state.data, ctx.state.schema);
        renderMockDataPrompt(ctx);
    }

    function renderPreviewNow(ctx) {
        previewModule.renderPreview(
            ctx.dom.previewBody,
            ctx.state.schema,
            ctx.state.typeCode,
            ctx.state.data
        );
        // A no-op unless the preview window is open - see postPageUpdate's own
        // doc comment.
        postPageUpdate(ctx);
    }

    // Outline clicks navigate the fields panel rather than mutating state, so
    // this stays outside STRUCTURAL_ACTIONS. A brief highlight class (removed
    // after OUTLINE_HIGHLIGHT_MS) is the only visual feedback - no animation
    // on the scroll itself per plan section 14 (keyboard/navigation actions repeat
    // too often per session to animate).
    //
    // `className` lets a caller flash something other than an outline row:
    // `is-outline-highlight` animates background-color, which is free on a
    // transparent row and blanks anything whose background is the element.
    function flashOutlineTarget(target, className) {
        const flashClass = className || OUTLINE_FLASH_CLASS;

        if (outlineHighlightTimer !== null) {
            clearTimeout(outlineHighlightTimer);
            outlineHighlightNode.classList.remove(outlineHighlightClass);
        }

        target.classList.remove(flashClass);
        // Reading layout flushes the removal, so re-adding the class starts a
        // new animation instead of collapsing into a no-op. Without this,
        // clicking the same outline node twice gives no feedback the second
        // time - the class is already there and CSS has nothing to restart.
        target.getBoundingClientRect();
        target.classList.add(flashClass);
        outlineHighlightNode = target;
        outlineHighlightClass = flashClass;

        outlineHighlightTimer = setTimeout(function () {
            target.classList.remove(flashClass);
            outlineHighlightTimer = null;
            outlineHighlightNode = null;
            outlineHighlightClass = null;
        }, OUTLINE_HIGHLIGHT_MS);
    }

    // Accordion: only the rows on the path down to the selection stay open.
    // With forty rows across six parents - or a dozen components -
    // leaving every row expanded made the panel a wall of inputs with no
    // sense of where you were.
    function collapseRowsExceptAncestorsOf(ctx, path) {
        const target = String(path || '');

        ctx.dom.fieldsPanel.querySelectorAll('.studio-repeater-row, .studio-block').forEach((row) => {
            const rowPath = row.dataset.row || row.dataset.block || '';
            const onPath = rowPath !== '' && (target === rowPath || target.indexOf(rowPath + '.') === 0);

            row.classList.toggle('is-collapsed', !onPath);
        });
    }

    // Mirror of collapseRowsExceptAncestorsOf for the outline's <details> tree.
    // openAncestors alone could only ever open - a previous branch stayed
    // expanded forever, so the tree drifted from the accordion in the fields.
    function syncOutlineOpenState(ctx, path) {
        const target = String(path || '');

        ctx.dom.outlineTree.querySelectorAll('details').forEach(function (details) {
            const link = details.querySelector(':scope > summary[data-outline-path]');
            const rowPath = link ? link.dataset.outlinePath : '';
            const onPath = rowPath !== '' && (target === rowPath || target.indexOf(rowPath + '.') === 0);

            details.open = onPath;
        });
    }

    // Re-apply accordion state after a re-render (fresh DOM is all-collapsed).
    function revealSelectedPath(ctx) {
        const path = ctx.state.selectedPath;

        if (!path) {
            return;
        }

        collapseRowsExceptAncestorsOf(ctx, path);
        syncOutlineOpenState(ctx, path);
    }

    // ---------------------------------------------------------------------
    // Selection - one data path is "current" at a time, and the marker on the
    // image, its row in the fields panel and its node in the outline all show
    // it. Without that link, picking a dot off the picture left the editor
    // with no clue which of five identical-looking rows it belonged to.
    // ---------------------------------------------------------------------

    function selectionNodes(ctx, path) {
        return [
            ctx.dom.fieldsPanel.querySelector('[data-row="' + path + '"], [data-block="' + path + '"]'),
            ctx.dom.outlineTree.querySelector('[data-outline-path="' + path + '"]')
        ];
    }

    function applySelection(ctx) {
        ctx.dom.root.querySelectorAll('.' + SELECTED_CLASS).forEach(function (node) {
            node.classList.remove(SELECTED_CLASS);
        });

        if (!ctx.state.selectedPath) {
            return;
        }

        selectionNodes(ctx, ctx.state.selectedPath).forEach(function (node) {
            if (node) {
                node.classList.add(SELECTED_CLASS);
            }
        });
    }

    function revealRow(row) {
        row.classList.remove('is-collapsed');
        row.scrollIntoView({behavior: 'smooth', block: 'center'});

        const firstControl = row.querySelector('input, textarea, select');

        if (firstControl) {
            firstControl.focus({preventScroll: true});
        }
    }

    function selectPath(ctx, path) {
        ctx.state.selectedPath = path;
        applySelection(ctx);
        collapseRowsExceptAncestorsOf(ctx, path);
        syncOutlineOpenState(ctx, path);

        const [row, outlineNode] = selectionNodes(ctx, path);

        if (row) {
            revealRow(row);
        }

        if (outlineNode) {
            outlineNode.scrollIntoView({block: 'nearest'});
        }
    }

    // Outline mirrors the fields panel's structure, so it is re-rendered
    // alongside it. A re-render drops any live text filter, so the current
    // query (if any) is immediately re-applied to the fresh tree.
    function renderOutline(ctx) {
        const type = schemaLib.getType(ctx.state.schema, ctx.state.typeCode) || {fields: []};

        outlineModule.render(ctx.dom.outlineTree, type.fields, ctx.state.data, ctx.state.schema);

        if (ctx.state.outlineQuery) {
            outlineModule.filter(ctx.dom.outlineTree, ctx.state.outlineQuery);
        }
    }

    // Both panels are rebuilt from the same data, so anything that changes the
    // data redraws them together - and re-paints the current selection, which
    // the fresh DOM would otherwise have lost. Fresh rows/details start
    // collapsed; revealSelectedPath reopens the branch that was in focus.
    // Rebuilding the fields panel empties it before filling it again, and it is
    // the *document* that scrolls here - the panel has no overflow of its own.
    // Any layout read taken while it is empty (this function's own
    // scrollIntoView calls, via revealSelectedPath, are enough) clamps
    // window.scrollY to the collapsed page height, and the position is not
    // restored when the panel comes back at full height a moment later: the
    // editor visibly snaps back toward the top.
    //
    // Dragging a dot in the live preview is where this was actually noticed -
    // every drop commits, every commit re-renders, and the page jumped away
    // from the row being edited on each one, which reads as "scrolling is
    // broken" rather than as a re-render. Undo/redo went the same way.
    function renderFormPanels(ctx) {
        const scrollX = window.scrollX;
        const scrollY = window.scrollY;

        renderFieldsPanel(ctx);
        renderOutline(ctx);
        revealSelectedPath(ctx);
        applySelection(ctx);

        if (ctx.state.outlineQuery) {
            outlineModule.filter(ctx.dom.outlineTree, ctx.state.outlineQuery);
            revealSelectedPath(ctx);
        }

        // Two-argument scrollTo, not the options object: it is always instant,
        // where {behavior: 'instant'} is comparatively recent and silently
        // becomes a smooth animation where it is unsupported - an animated
        // correction is exactly the jump this is trying to remove.
        if (window.scrollX !== scrollX || window.scrollY !== scrollY) {
            window.scrollTo(scrollX, scrollY);
        }
    }

    // Debounced so a fast typist doesn't trigger a full preview rebuild (and,
    // for a type with a renderer, a renderer re-run) on every keystroke -
    // still "live" at 150ms, per plan section 4.
    function schedulePreviewUpdate(ctx) {
        if (ctx.previewTimer) {
            clearTimeout(ctx.previewTimer);
        }
        ctx.previewTimer = setTimeout(function () {
            renderPreviewNow(ctx);
        }, PREVIEW_DEBOUNCE_MS);
    }

    // Applies a mutation to the live data object, marks the entry dirty and
    // refreshes the fields panel. Callers decide whether the edit is history
    // worthy (leaf `input` events are not; everything else is).
    function commit(ctx, mutate, recordHistory) {
        if (recordHistory) {
            pushSnapshot(ctx, pathLib.cloneDeep(ctx.state.data));
        }

        mutate(ctx.state.data);
        ctx.state.dirty = true;
        updateSaveButton(ctx);
        schedulePreviewUpdate(ctx);

        if (recordHistory) {
            renderFormPanels(ctx);
        }
    }

    function renderMetaBar(ctx) {
        dom.empty(ctx.dom.metaBar);
        ctx.dom.metaBar.appendChild(metaBar.render(ctx.state.meta, ctx.state.stores));
    }

    function findPathControl(event) {
        return event.target.closest ? event.target.closest('[data-path]') : null;
    }

    // The counter lives in the same .studio-field row as its control, so it is
    // found by walking up rather than by building a selector out of the path -
    // paths contain dots and indices and would need escaping.
    function updateFieldCount(control) {
        const max = Number(control.dataset.maxlength || 0);

        if (!max) {
            return;
        }

        const row = control.closest('.studio-field'),
            counter = row ? row.querySelector('.studio-field__count') : null;

        if (!counter) {
            return;
        }

        const used = String(control.value || '').length;

        counter.textContent = used + ' / ' + max;
        counter.classList.toggle('is-near-limit', used > max * 0.9 && used <= max);
        counter.classList.toggle('is-over-limit', used > max);
    }

    // The error <p> is a sibling of the control inside its row, so the element
    // that has to turn red is that row: .studio-field in the form panel,
    // .studio-meta-bar__field for identifier/title. The toolbar's own `_root`
    // error lives in neither and is left alone.
    function markFieldInvalid(errorNode, invalid) {
        const row = errorNode.closest(INVALID_ROW_SELECTOR);

        if (row) {
            row.classList.toggle(INVALID_CLASS, invalid);
        }
    }

    // Drops the mark as soon as the user starts fixing the field it belongs to.
    // Errors otherwise only clear on the next save attempt, so a row stayed red
    // the whole time it was being corrected.
    function clearFieldErrorAt(ctx, path) {
        const node = ctx.dom.root.querySelector('[data-field-error="' + path + '"]');

        if (node && node.textContent !== '') {
            node.textContent = '';
            markFieldInvalid(node, false);
        }
    }

    function onInput(ctx, event) {
        // Meta-bar controls (identifier/title) carry no data-path and commit on
        // `change`, but their error still has to lift while the user is typing
        // - "Identifier is required" is the one people actually hit.
        const metaControl = event.target.closest('[data-meta-field]');

        if (metaControl) {
            clearFieldErrorAt(ctx, 'meta.' + metaControl.dataset.metaField);

            return;
        }

        const control = findPathControl(event);

        if (!control) {
            return;
        }

        if ('priceFormat' in control.dataset) {
            priceLib.applyToInput(control);
        }

        updateFieldCount(control);
        // The swatch and the hex box are two controls on one path; whichever
        // moved has to carry the other with it, and a leaf edit does not
        // re-render the row that would otherwise do it.
        if (control.dataset.fieldType === 'color') {
            colorField.syncPair(control);
        }
        clearFieldErrorAt(ctx, control.dataset.path);

        const value = readControlValue(control.dataset.fieldType, control);

        commit(ctx, function (data) {
            pathLib.setByPath(data, control.dataset.path, value);
        }, false);
    }

    function onFocusIn(ctx, event) {
        const control = findPathControl(event);

        if (control && !ctx.editSnapshot) {
            ctx.editSnapshot = pathLib.cloneDeep(ctx.state.data);
        }
    }

    // The two reference_multi mutations sit up here, away from the rest of the
    // structural actions, only because onChange() below has to reach the add
    // one and no-use-before-define applies to function declarations too.
    function applyReferenceRemove(ctx, button) {
        const fieldPath = button.dataset.referencePath;
        const index = Number(button.dataset.index);

        commit(ctx, function (data) {
            const current = pathLib.getByPath(data, fieldPath);

            if (Array.isArray(current)) {
                pathLib.setByPath(data, fieldPath, current.filter(function (item, position) {
                    return position !== index;
                }));
            }
        }, true);
    }

    function applyReferenceAdd(ctx, select) {
        const fieldPath = select.dataset.referenceAdd;
        const identifier = select.value;

        if (!identifier) {
            return;
        }

        commit(ctx, function (data) {
            const current = pathLib.getByPath(data, fieldPath);
            const next = Array.isArray(current) ? current.slice() : [];

            if (next.indexOf(identifier) === -1) {
                next.push(identifier);
            }
            pathLib.setByPath(data, fieldPath, next);
        }, true);
    }

    // Full re-renders are reserved for structural changes (see commit()) so
    // a leaf edit never steals focus back from the field the user just left.
    // An image URL's thumbnail is the one leaf control with dependent
    // visual state, so it gets a targeted patch instead.
    function refreshImageThumb(control) {
        if (control.dataset.fieldType !== 'image') {
            return;
        }

        const thumb = control.closest('.studio-image-field').querySelector('.studio-image-thumb');
        const img = thumb.querySelector('img');

        thumb.hidden = control.value === '';
        thumb.classList.add('studio-skeleton', 'studio-image-thumb__skeleton');
        img.src = control.value;
    }

    // Leaf edits already mutated state.data on `input`; on `change` we only
    // decide whether that edit session deserves an undo step.
    function onChange(ctx, event) {
        // The "Add..." picker on a reference_multi field carries no data-path on
        // purpose: it names what to append, it is not the field's value, and a
        // data-path here would have the generic handler below overwrite the
        // whole array with a single identifier.
        const referenceAdd = event.target.closest('[data-reference-add]');

        if (referenceAdd) {
            applyReferenceAdd(ctx, referenceAdd);

            return;
        }

        const control = findPathControl(event);

        if (!control || !ctx.editSnapshot) {
            return;
        }

        refreshImageThumb(control);

        const snapshot = ctx.editSnapshot;
        const changed = JSON.stringify(snapshot) !== JSON.stringify(ctx.state.data);

        ctx.editSnapshot = null;

        if (changed) {
            // Outline only: row labels track a field value, so they can go
            // stale on a leaf edit, but rebuilding the fields panel here would
            // yank focus out of the control the user is still working in.
            pushSnapshot(ctx, snapshot);
            renderOutline(ctx);
            applySelection(ctx);
        }
    }

    // Resolves a field definition by its data path (e.g. "ranges",
    // "ranges.0.comforts", "content_blocks.3.items").
    //
    // Numeric segments are array indexes the schema tree does not have, so
    // they are skipped for nested *repeaters* - the child fields already sit
    // on the parent definition. A *blocks* field is different: it has no
    // nested `fields`, only `<allow>`. The row's children live on the picked
    // type (`item._type`), which is why the outline walker already consults
    // the schema here. Without that hop, Add/Duplicate on a gallery or FAQ
    // inside a post body resolved to null and threw in repeater.applyAction.
    // The index step of the walk below, split out to keep it inside the
    // linter's depth and complexity budgets. For a repeater an index changes
    // nothing about which definitions apply - the child fields already sit on
    // the parent. A `blocks` row is the exception, and a picked type carrying
    // no fields of its own genuinely resolves to none rather than falling back
    // to the parent's, which is why `null` and `fallback` are two different
    // answers here.
    function fieldsForIndex(field, item, schema, fallback) {
        if (!field || field.type !== 'blocks' || !item || !item._type || !schema) {
            return fallback;
        }

        const blockType = schemaLib.getType(schema, item._type);

        return (blockType && blockType.fields) || null;
    }

    function resolveFieldByPath(rootFields, fieldPath, data, schema) {
        const segments = fieldPath.split('.').filter(Boolean);
        let currentFields = rootFields;
        let currentData = data;
        let found = null;

        for (const segment of segments) {
            if (/^\d+$/.test(segment)) {
                const item = Array.isArray(currentData) ? currentData[Number(segment)] : null;

                currentFields = fieldsForIndex(found, item, schema, currentFields);
                currentData = item;

                continue;
            }

            if (!currentFields) {
                return null;
            }

            found = currentFields.filter((field) => field.name === segment)[0] || null;

            if (!found) {
                return null;
            }

            currentFields = found.fields || null;
            currentData = currentData && typeof currentData === 'object'
                ? currentData[found.name]
                : null;
        }

        return found;
    }

    function applyRepeaterAction(ctx, button) {
        const fieldPath = button.dataset.repeaterPath;
        const index = Number(button.dataset.index);
        const action = button.dataset.action;
        const type = schemaLib.getType(ctx.state.schema, ctx.state.typeCode) || {fields: []};
        const field = resolveFieldByPath(
            type.fields,
            fieldPath,
            ctx.state.data,
            ctx.state.schema
        );
        let focusPath = null;

        if (!field || field.type !== 'repeater') {
            return;
        }

        commit(ctx, function (data) {
            const current = pathLib.getByPath(data, fieldPath) || [];
            const before = current.length;
            const next = repeaterField.applyAction(current, field, action, index);

            pathLib.setByPath(data, fieldPath, next);

            // Point at the new row so Duplicate does not look like a no-op -
            // without this, an identical clone sat collapsed under the source.
            if (next.length > before) {
                focusPath = fieldPath + '.' + (action === 'repeater-duplicate' ? index + 1 : next.length - 1);
            }
        }, true);

        if (focusPath) {
            selectPath(ctx, focusPath);

            const newRow = selectionNodes(ctx, focusPath)[0];

            if (newRow) {
                flashOutlineTarget(newRow);
            }
        }
    }

    function applyBlocksAction(ctx, button) {
        const fieldPath = button.dataset.blocksPath;
        const index = Number(button.dataset.index);
        const action = button.dataset.action;
        const typeCode = button.dataset.typeCode;
        let focusPath = null;

        commit(ctx, (data) => {
            const current = pathLib.getByPath(data, fieldPath) || [];
            const before = current.length;
            const next = blocksField.applyAction(
                current,
                action,
                index,
                typeCode,
                ctx.state.schema
            );

            pathLib.setByPath(data, fieldPath, next);

            // Same as Duplicate on a repeater: the new card starts collapsed,
            // so without this it appears to have done nothing.
            if (next.length > before) {
                focusPath = fieldPath + '.' + (action === 'blocks-duplicate' ? index + 1 : next.length - 1);
            }
        }, true);

        if (action === 'blocks-add-type') {
            const picker = ctx.dom.fieldsPanel.querySelector('[data-block-picker="' + fieldPath + '"]');

            if (picker) {
                picker.hidden = true;
            }
        }

        if (focusPath) {
            selectPath(ctx, focusPath);

            const newRow = selectionNodes(ctx, focusPath)[0];

            if (newRow) {
                flashOutlineTarget(newRow);
            }
        }
    }

    // Merges the fixture into the live data rather than replacing it
    // wholesale: renderMockDataPrompt() already guarantees this only ever
    // runs against an untouched entry, but a plain object merge is still
    // safer than clobbering state.data outright if that guarantee ever
    // loosens. A short leave beat on the prompt (is-leaving) keeps the
    // snap-to-filled-form from reading as a hard cut - skipped under
    // prefers-reduced-motion so the fill still feels immediate.
    function applyMockDataAction(ctx) {
        const fixture = mockDataLib[ctx.state.typeCode];

        if (!fixture) {
            return;
        }

        const fill = function () {
            commit(ctx, function (data) {
                Object.assign(data, pathLib.cloneDeep(fixture.data));
            }, true);
        };

        const prompt = ctx.dom.fieldsPanel.querySelector('.studio-mock-data');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (!prompt || reduceMotion) {
            fill();
            return;
        }

        prompt.classList.add('is-leaving');
        setTimeout(fill, 200);
    }

    function applyDateTimeNow(ctx, button) {
        const fieldPath = button.dataset.datetimePath;

        commit(ctx, function (data) {
            pathLib.setByPath(data, fieldPath, new Date().toISOString().replace(/\.\d{3}Z$/, 'Z'));
        }, true);
    }

    // Wraps the textarea's current selection in the button's tag pair, then
    // fires the control's own `input` event so the edit travels the ordinary
    // leaf path. Deliberately NOT a studio:field-change: that commits with
    // history, which re-renders the fields panel and would throw the caret out
    // of a long article on every click. The undo step still lands, on blur,
    // exactly as it does for typing.
    function applyWysiwygWrap(ctx, button) {
        const area = button.closest('.studio-wysiwyg').querySelector('textarea');
        const open = button.dataset.wrapOpen;
        const close = button.dataset.wrapClose;
        const start = area.selectionStart;
        const end = area.selectionEnd;

        area.value = area.value.slice(0, start) + open + area.value.slice(start, end) +
            close + area.value.slice(end);
        area.focus();
        area.setSelectionRange(start + open.length, end + open.length);
        area.dispatchEvent(new Event('input', {bubbles: true}));
    }

    const STRUCTURAL_ACTIONS = {
        'repeater-add': applyRepeaterAction,
        'repeater-remove': applyRepeaterAction,
        'repeater-duplicate': applyRepeaterAction,
        'blocks-add-type': applyBlocksAction,
        'blocks-remove': applyBlocksAction,
        'blocks-duplicate': applyBlocksAction,
        'reference-remove': applyReferenceRemove,
        'datetime-now': applyDateTimeNow,
        'wysiwyg-wrap': applyWysiwygWrap,
        'add-mock-data': applyMockDataAction
    };

    // Reorder (native HTML5 drag & drop + Alt+Up/Down fallback, plan section 3/section 14).
    // Every entry point resolves to the same {fieldPath, index} shape so a
    // repeater row, a blocks item and an outline node are reordered
    // identically - they differ only in which dataset attribute carries the
    // item path, and the outline's nodes are therefore drag sources and drop
    // targets for free once they are listed here.
    const ITEM_SELECTOR = '[data-row], [data-block], [data-outline-row]';

    function rowInfo(el) {
        const itemPath = el && (el.dataset.row || el.dataset.block || el.dataset.outlineRow);

        return itemPath ? {itemPath: itemPath} : null;
    }

    // A filtered tree hides siblings *between* the rows still on screen, so a
    // move there lands at an index the user cannot see: the data would be right
    // and the tree would look unchanged (Alt+Down past a hidden neighbour) or the
    // drop arbitrary. Both entry points refuse until the filter is cleared;
    // fields-panel rows are never filtered and always pass.
    function isFilteredOutlineRow(ctx, el) {
        return Boolean(ctx.state.outlineQuery && el && el.dataset.outlineRow);
    }

    // Two drag affordances, one meaning: the explicit grip in a fields-panel
    // row, and - where there is no room for a grip - the outline node itself.
    function dragSourceOf(event) {
        const handle = event.target.closest('[data-drag-handle]');

        return handle
            ? handle.closest('[data-row], [data-block]')
            : event.target.closest('[data-outline-row]');
    }

    function fieldPathOf(itemPath) {
        return itemPath.replace(/\.\d+$/, '');
    }

    function indexOf(itemPath) {
        return Number(itemPath.split('.').pop());
    }

    function moveArrayItem(array, fromIndex, toIndex) {
        const next = array.slice();
        const moved = next.splice(fromIndex, 1)[0];

        next.splice(toIndex, 0, moved);

        return next;
    }

    function clearDragOver(ctx) {
        if (ctx.dragOverEl) {
            ctx.dragOverEl.classList.remove('is-drag-over');
            ctx.dragOverEl = null;
        }
    }

    // A reorder invalidates the current selection - the old index now names a
    // different row - and a re-rendered outline starts with every branch shut.
    // Pointing the selection at the moved item covers both: renderFormPanels
    // reopens its ancestors, so what you just moved stays where you put it
    // instead of collapsing out of view or highlighting its former neighbour.
    function commitMove(ctx, fieldPath, fromIndex, toIndex) {
        ctx.state.selectedPath = fieldPath + '.' + toIndex;

        commit(ctx, function (data) {
            const rows = pathLib.getByPath(data, fieldPath) || [];

            pathLib.setByPath(data, fieldPath, moveArrayItem(rows, fromIndex, toIndex));
        }, true);
    }

    function onDragStart(ctx, event) {
        const source = dragSourceOf(event);
        const info = rowInfo(source);

        if (!info || isFilteredOutlineRow(ctx, source)) {
            return;
        }

        ctx.dragState = {fieldPath: fieldPathOf(info.itemPath), fromIndex: indexOf(info.itemPath)};
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', info.itemPath);
    }

    function onDragOver(ctx, event) {
        if (!ctx.dragState) {
            return;
        }

        const row = event.target.closest(ITEM_SELECTOR);
        const info = rowInfo(row);

        if (!info || fieldPathOf(info.itemPath) !== ctx.dragState.fieldPath) {
            return;
        }

        event.preventDefault();

        if (ctx.dragOverEl !== row) {
            clearDragOver(ctx);
            row.classList.add('is-drag-over');
            ctx.dragOverEl = row;
        }
    }

    function onDrop(ctx, event) {
        if (!ctx.dragState) {
            return;
        }

        const info = rowInfo(event.target.closest(ITEM_SELECTOR));
        const dragState = ctx.dragState;

        clearDragOver(ctx);
        ctx.dragState = null;

        if (!info || fieldPathOf(info.itemPath) !== dragState.fieldPath) {
            return;
        }

        event.preventDefault();
        const toIndex = indexOf(info.itemPath);

        if (toIndex === dragState.fromIndex) {
            return;
        }

        commitMove(ctx, dragState.fieldPath, dragState.fromIndex, toIndex);
    }

    function onDragEnd(ctx) {
        clearDragOver(ctx);
        ctx.dragState = null;
    }

    // Repeating Alt+Up has to keep acting on the same item, so focus follows it
    // to its new index - and lands back in the panel or the tree depending on
    // which one the move was triggered from. Both are re-rendered by then, so
    // this must run after the commit, not before.
    function focusMovedItem(ctx, itemPath, fromOutline) {
        const selector = fromOutline
            ? '[data-outline-row="' + itemPath + '"]'
            : '[data-row="' + itemPath + '"] [data-drag-handle], '
                + '[data-block="' + itemPath + '"] [data-drag-handle]';
        const target = (fromOutline ? ctx.dom.outlineTree : ctx.dom.fieldsPanel).querySelector(selector);

        if (target) {
            target.focus();
        }
    }

    function onAltArrow(ctx, event) {
        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
            return;
        }

        const row = event.target.closest(ITEM_SELECTOR);
        const info = rowInfo(row);

        if (!info || isFilteredOutlineRow(ctx, row)) {
            return;
        }

        const fieldPath = fieldPathOf(info.itemPath);
        const fromIndex = indexOf(info.itemPath);
        const current = pathLib.getByPath(ctx.state.data, fieldPath) || [];
        const toIndex = event.key === 'ArrowUp' ? fromIndex - 1 : fromIndex + 1;

        if (toIndex < 0 || toIndex >= current.length) {
            return;
        }

        event.preventDefault();
        commitMove(ctx, fieldPath, fromIndex, toIndex);
        focusMovedItem(ctx, fieldPath + '.' + toIndex, Boolean(row.dataset.outlineRow));
    }

    function toggleBlockPicker(ctx, target) {
        const picker = ctx.dom.fieldsPanel.querySelector(
            '[data-block-picker="' + target.dataset.blocksPath + '"]'
        );

        if (picker) {
            picker.hidden = !picker.hidden;
        }
    }

    // The browser widget sets the target input's value and then announces it
    // with `jQuery(targetEl).trigger('change')` (mage/adminhtml/browser.js,
    // chooseFile). That is a jQuery-only dispatch: it walks jQuery's own
    // handler registry and, because `HTMLInputElement` has no `.change()`
    // method for jQuery to fall back to, never becomes a real DOM event. So
    // neither the one-time native listener this used to bind nor the delegated
    // native 'change' in bindEvents could hear it, and a browsed image stayed
    // in the DOM while `state.data` kept the old value - the field then failed
    // save-time validation as "required" while visibly holding a URL.
    //
    // Binding through jQuery is therefore not a style slip; it is the only way
    // to hear this particular event. The handler converts it straight back into
    // the studio:field-change event field/hotspot.js already uses, so the
    // commit/undo path downstream stays single. The namespace lets a second
    // Browse replace the first handler instead of stacking one per cancelled
    // dialog.
    function openImageBrowser(button) {
        const targetId = button.dataset.imageBrowse;
        const input = document.getElementById(targetId);
        const media = mediaConfigModule.get();

        if (!input) {
            return;
        }

        require(['jquery', 'mage/adminhtml/browser'], function ($, browser) {
            // `current_tree_path` last, and never optional: browser.js's
            // setPathId indexes the match result without a null check, so a URL
            // without it throws before the dialog finishes opening, and its
            // regex runs to `/` or end-of-string, so a parameter appended after
            // it would be read as part of the folder id. See the note on
            // `mediaFolderId` in Block\Adminhtml\Editor.
            const url = media.browserUrl + 'target_element_id/' + targetId
                + '/type/image/?isAjax=true&current_tree_path=' + media.folderId;

            $(input).off('change.studioImageBrowse').one('change.studioImageBrowse', function () {
                document.dispatchEvent(new CustomEvent('studio:field-change', {
                    detail: {path: input.dataset.path, value: input.value}
                }));
            });

            browser.openDialog(url, null, null, null, {targetElementId: targetId});
        });
    }

    function undo(ctx) {
        if (ctx.state.history.length === 0) {
            return;
        }

        ctx.state.redo.push(pathLib.cloneDeep(ctx.state.data));
        ctx.state.data = ctx.state.history.pop();
        ctx.state.dirty = true;
        renderFormPanels(ctx);
        updateSaveButton(ctx);
        renderPreviewNow(ctx);
    }

    function redo(ctx) {
        if (ctx.state.redo.length === 0) {
            return;
        }

        ctx.state.history.push(pathLib.cloneDeep(ctx.state.data));
        ctx.state.data = ctx.state.redo.pop();
        ctx.state.dirty = true;
        renderFormPanels(ctx);
        updateSaveButton(ctx);
        renderPreviewNow(ctx);
    }

    // Outline clicks navigate the fields panel rather than mutating state.
    // Native <summary> toggle is suppressed - selectPath owns which branch is
    // open in the tree and the fields panel together.
    function onOutlineNavigate(ctx, outlineLink, event) {
        const path = outlineLink.dataset.outlinePath;

        if (!path) {
            return;
        }

        event.preventDefault();
        selectPath(ctx, path);

        const [row] = selectionNodes(ctx, path);

        if (row) {
            flashOutlineTarget(row);
        }
    }

    function applyPanelWidths(ctx) {
        const outlineWidth = ctx.state.outlineCollapsed ? OUTLINE_RAIL_WIDTH : ctx.state.panelWidths.outline;

        ctx.dom.body.style.gridTemplateColumns = outlineWidth + 'px 6px minmax(0, 1fr) 6px '
            + 'minmax(320px, ' + ctx.state.panelWidths.preview + 'px)';
    }

    // The stored width is left untouched while collapsed, so reopening returns
    // the panel to the width this person chose rather than to the default.
    function applyOutlineCollapsed(ctx) {
        const collapsed = ctx.state.outlineCollapsed;

        ctx.dom.outlinePanel.classList.toggle('is-collapsed', collapsed);
        ctx.dom.outlineToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        ctx.dom.outlineToggle.setAttribute('aria-label', collapsed ? 'Show labels' : 'Hide labels');
        ctx.dom.outlineToggle.setAttribute('title', collapsed ? 'Show labels' : 'Hide labels');
        const toggleLabel = ctx.dom.outlineToggle.querySelector('.studio-outline__toggle-label');

        if (toggleLabel) {
            toggleLabel.textContent = collapsed ? 'Show' : 'Hide';
        }
        // Dragging the edge of a collapsed panel would resize something that is
        // not visible, and the drag would silently un-collapse it on the next
        // open.
        ctx.dom.outlineSplitter.hidden = collapsed;
        applyPanelWidths(ctx);
    }

    function toggleOutlineCollapsed(ctx) {
        ctx.state.outlineCollapsed = !ctx.state.outlineCollapsed;
        window.localStorage.setItem(STORAGE_OUTLINE_COLLAPSED, ctx.state.outlineCollapsed ? '1' : '0');
        applyOutlineCollapsed(ctx);
    }

    function openSearch(ctx) {
        // The field lives in the outline. A collapsed rail hides it with
        // `display: none`, so focusing it would look like the toolbar button
        // did nothing. Opening the panel first is the whole point of the
        // shortcut when someone has tucked the labels away.
        if (ctx.state.outlineCollapsed) {
            toggleOutlineCollapsed(ctx);
        }

        ctx.dom.outlineSearchWrapper.hidden = false;
        ctx.dom.outlineSearchInput.focus();
    }

    function closeSearch(ctx) {
        ctx.dom.outlineSearchWrapper.hidden = true;
        ctx.dom.outlineSearchInput.value = '';
        ctx.state.outlineQuery = '';
        outlineModule.filter(ctx.dom.outlineTree, '');
        revealSelectedPath(ctx);
    }

    function toggleSearch(ctx) {
        if (ctx.dom.outlineSearchWrapper.hidden) {
            openSearch(ctx);
        } else {
            closeSearch(ctx);
        }
    }

    // Sequential swap (fade out, switch `hidden`, fade in) rather than two
    // overlaid layers: the panels share one grid slot, so a same-slot fade
    // reads as a single transition without needing absolute positioning.
    // Drives the skeleton -> mounted editor handover (plan section 3a).
    function crossfade(hideEl, showEl) {
        hideEl.classList.add('is-view-fading');
        setTimeout(function () {
            hideEl.hidden = true;
            hideEl.classList.remove('is-view-fading');
            showEl.hidden = false;
            showEl.classList.add('is-view-fading');
            requestAnimationFrame(function () {
                showEl.classList.remove('is-view-fading');
            });
        }, VIEW_FADE_MS);
    }

    function clearFieldErrors(ctx) {
        ctx.dom.root.querySelectorAll('[data-field-error]').forEach(function (node) {
            node.textContent = '';
            markFieldInvalid(node, false);
        });
    }

    function showFieldErrors(ctx, errors) {
        Object.keys(errors || {}).forEach(function (path) {
            const node = ctx.dom.root.querySelector('[data-field-error="' + path + '"]');

            if (node) {
                node.textContent = errors[path];
                markFieldInvalid(node, true);
            }
        });
    }

    // Returns whether the entry was written, so the pre-save review overlay can
    // tell "saved" from "came back with validation errors" - the errors are
    // rendered into the editor underneath it, which it has to uncover.
    function handleSaveResult(ctx, result) {
        if (!result.success) {
            showFieldErrors(ctx, result.errors);
            ctx.dom.saveButton.disabled = false;
            ctx.dom.saveLabel.textContent = 'Save';

            return false;
        }

        ctx.state.meta.entryId = result.entryId;
        ctx.state.meta.version = result.version;
        ctx.state.dirty = false;
        updateSaveButton(ctx);

        // Framed inside another admin form (`?embedded=1`). The opener owns the page and
        // the Save button that matters; navigating this frame anywhere would
        // either strand the user inside a modal showing the entry grid, or
        // reload the whole admin page inside itself.
        if (ctx.state.embedded) {
            window.parent.postMessage({
                source: PARENT_MESSAGE_SOURCE,
                type: 'saved',
                identifier: ctx.state.meta.identifier || '',
                entryId: ctx.state.meta.entryId
            }, window.location.origin);

            return true;
        }

        // Save & Copy is the exception: the original is written, the copy
        // exists, and the server hands back where the copy lives - landing on
        // the copy is the whole point of the action, so it keeps its own
        // destination. Clearing `dirty` above keeps the unload guard quiet on
        // the way there.
        if (result.redirectUrl) {
            window.location.href = result.redirectUrl;

            return true;
        }

        // Every ordinary save now ends on the listing rather than back in the
        // editor. The review step already answered "does this look right", so
        // staying put left the user on a form they were finished with; the grid
        // is where the next thing they want to do lives. `saved` is picked up by
        // js/grid/saved-notice.js, which toasts and then strips it.
        window.location.href = ctx.state.urls.back
            + (ctx.state.urls.back.indexOf('?') === -1 ? '?' : '&')
            + 'saved=' + encodeURIComponent(ctx.state.meta.identifier || '');

        return true;
    }

    function save(ctx, duplicate) {
        clearFieldErrors(ctx);
        ctx.dom.saveButton.disabled = true;
        ctx.dom.saveLabel.textContent = duplicate ? 'Copying...' : 'Saving...';

        return postPayload(ctx, buildPayload(ctx, duplicate))
            .then(function (result) {
                return handleSaveResult(ctx, result);
            })
            .catch(function () {
                // Network / JSON parse failure - re-enable so the user can retry.
                // updateSaveButton restores disabled from dirty; override the
                // label to say what happened.
                updateSaveButton(ctx);
                ctx.dom.saveLabel.textContent = 'Save failed';

                return false;
            });
    }

    // The gate behind the toolbar's Save button: show the block at full width
    // first, and only write once the user has actually looked at it. Cmd+S and
    // Save & Copy stay on save() directly - a one-word fix should not cost two
    // clicks and a second of animation, and a copy is a different intent than
    // "ship this".
    function openReview(ctx) {
        const type = schemaLib.getType(ctx.state.schema, ctx.state.typeCode);
        const identifier = ctx.state.meta.identifier || 'Untitled entry';

        reviewModule.open({
            // No successMessage: a save redirects to the listing, so a toast
            // raised here would be thrown away by the navigation a frame later.
            // saved-notice.js raises it on the other side instead.
            title: 'Review before saving',
            subtitle: identifier + ' \u{00B7} ' + ((type && type.label) || ctx.state.typeCode),
            inertTarget: ctx.dom.root,
            render: function (stage) {
                previewModule.renderPreview(
                    stage,
                    ctx.state.schema,
                    ctx.state.typeCode,
                    ctx.state.data
                );
            },
            onConfirm: function () {
                return save(ctx);
            }
        });
    }

    // Errors are useless if you cannot see the field they belong to - it may be
    // inside a collapsed repeater row three levels down.
    function focusFirstInvalid(ctx) {
        const invalid = ctx.dom.root.querySelector('.' + INVALID_CLASS);

        if (!invalid) {
            return;
        }

        const container = invalid.closest('.studio-repeater-row, .studio-block');
        const containerPath = container && (container.dataset.row || container.dataset.block);

        if (containerPath) {
            selectPath(ctx, containerPath);
        }

        const control = invalid.querySelector('.studio-input');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        (control || invalid).scrollIntoView({
            behavior: reduceMotion ? 'auto' : 'smooth',
            block: 'center'
        });

        if (control) {
            control.focus();
        }
    }

    // The gate proper. Validation runs *before* the preview, not after: showing
    // someone a polished full-screen render of an entry that cannot be saved,
    // only to fail them at the last button, is the wrong order - the form is
    // what needs their attention at that point, not the block.
    //
    // The check is a server round-trip (`validateOnly`) rather than a JS
    // reimplementation of the rules. SchemaValidator owns required/maxlength/
    // min/max/url and reads the same merged content_types.xml; a second copy in
    // the browser would be a second copy to keep in step.
    function requestSave(ctx) {
        clearFieldErrors(ctx);
        ctx.dom.saveButton.disabled = true;
        ctx.dom.saveLabel.textContent = 'Checking...';

        const payload = buildPayload(ctx, false);

        payload.validateOnly = true;

        return postPayload(ctx, payload)
            .then(function (result) {
                updateSaveButton(ctx);

                if (!result || !result.success) {
                    showFieldErrors(ctx, (result && result.errors) || {});
                    focusFirstInvalid(ctx);

                    return;
                }

                openReview(ctx);
            })
            .catch(function () {
                updateSaveButton(ctx);
                ctx.dom.saveLabel.textContent = 'Save failed';
            });
    }

    const PREVIEW_TARGETS_ERROR = 'The preview could not be prepared. Please try again.';

    // Parks the payload the editor is holding right now on the server and
    // lists the storefront pages it can be opened on.
    //
    // Fetch first, then show links the user clicks - rather than opening a tab
    // once the response lands. A window.open() that happens after a promise
    // resolves has lost its user gesture and every popup blocker treats it as
    // an ad; a real <a target="_blank"> the user clicks never is.
    //
    // The same buildPayload() the save uses, so what the storefront renders is
    // exactly what a save would have written - a preview built from a second,
    // preview-shaped payload is a preview that can lie.
    function requestPreviewTargets(ctx) {
        const popover = ctx.dom.previewTargetsPopover;

        if (previewTargetsModule.isOpen(popover)) {
            previewTargetsModule.close(popover);

            return;
        }

        previewTargetsModule.showLoading(popover);

        // A request already in flight cannot be recalled, so the *response* is
        // what gets ignored. Without this, closing the popover (or clicking
        // anywhere else, which also closes it) left the pending `.then` free to
        // reopen the panel a moment later, on its own, over whatever the user
        // had moved on to. Incrementing per request also settles rapid clicks:
        // only the newest one is allowed to paint.
        ctx.state.previewTargetsRequest += 1;

        const request = ctx.state.previewTargetsRequest;
        const stale = function () {
            return request !== ctx.state.previewTargetsRequest;
        };

        postJson(ctx, ctx.state.urls.previewDraft, buildPayload(ctx, false))
            .then(function (result) {
                if (stale()) {
                    return;
                }

                if (!result || !result.success) {
                    previewTargetsModule.showMessage(popover, (result && result.message) || PREVIEW_TARGETS_ERROR);

                    return;
                }

                previewTargetsModule.showTargets(popover, result.targets);
            })
            .catch(function () {
                if (!stale()) {
                    previewTargetsModule.showMessage(popover, PREVIEW_TARGETS_ERROR);
                }
            });
    }

    // A type with `copyable="false"` is bound to one host record. Save & Copy would mint an orphan nothing points at.
    function isCopyableType(ctx) {
        const type = schemaLib.getType(ctx.state.schema, ctx.state.typeCode);

        return !type || type.copyable !== false;
    }

    const TOOLBAR_ACTIONS = {
        undo: undo,
        redo: redo,
        'toggle-search': toggleSearch,
        'preview-storefront': requestPreviewTargets,
        duplicate: function (ctx) {
            if (!isCopyableType(ctx)) {
                return;
            }

            save(ctx, true);
        }
    };

    function onClick(ctx, event) {
        // Anything outside the trigger and its panel dismisses it - including
        // the other toolbar buttons, whose own handlers still run below.
        if (!event.target.closest('.studio-targets')) {
            previewTargetsModule.close(ctx.dom.previewTargetsPopover);
        }

        // Before the outline target check: the toggle lives inside the outline
        // panel, and a stray `data-outline-target` ancestor would otherwise
        // navigate instead of collapsing.
        if (event.target.closest('[data-outline-toggle]')) {
            toggleOutlineCollapsed(ctx);

            return;
        }

        const outlineTarget = event.target.closest('[data-outline-target]');
        const toolbarTarget = event.target.closest('[data-toolbar-action]');
        const previewDeviceTarget = event.target.closest('[data-preview-device]');
        const previewOpenTarget = event.target.closest('[data-preview-open]');
        const actionTarget = event.target.closest('[data-action]');
        const toggleTarget = event.target.closest('[data-toggle-row]');

        if (outlineTarget) {
            onOutlineNavigate(ctx, outlineTarget, event);
            return;
        }

        if (toolbarTarget) {
            const toolbarHandler = TOOLBAR_ACTIONS[toolbarTarget.dataset.toolbarAction];

            if (toolbarHandler) {
                toolbarHandler(ctx);
            }
            return;
        }

        if (previewDeviceTarget) {
            changePreviewDevice(ctx, previewDeviceTarget.dataset.previewDevice);
            return;
        }

        // Reaches window.open() synchronously off this click - see this
        // panel's file comment for why that has to happen here, not after an
        // async fetch.
        if (previewOpenTarget) {
            openPreviewWindow(ctx);
            return;
        }

        // Duplicate/Remove live inside the header that also carries
        // data-toggle-row - ignore those clicks here so the action handler
        // below still runs. A plain header click opens the branch everywhere.
        if (toggleTarget && !event.target.closest('[data-action], [data-drag-handle]')) {
            selectPath(ctx, toggleTarget.dataset.toggleRow);
            return;
        }

        if (!actionTarget) {
            return;
        }

        event.preventDefault();

        if (actionTarget.dataset.action === 'blocks-toggle-picker') {
            toggleBlockPicker(ctx, actionTarget);
            return;
        }

        if (actionTarget.dataset.action === 'image-browse') {
            openImageBrowser(actionTarget);
            return;
        }

        const handler = STRUCTURAL_ACTIONS[actionTarget.dataset.action];

        if (handler) {
            handler(ctx, actionTarget);
        }
    }

    // A `studio:field-change` carries either one {path, value} or a `changes`
    // array. The array form exists so a gesture that moves two values at once -
    // dragging a hotspot marker changes both X and Y - lands as a single undo
    // step and a single re-render instead of two of each.
    function changesFromEvent(event) {
        if (Array.isArray(event.detail.changes)) {
            return event.detail.changes;
        }

        return [{path: event.detail.path, value: event.detail.value}];
    }

    function onFieldChangeEvent(ctx, event) {
        const changes = changesFromEvent(event);

        commit(ctx, function (data) {
            changes.forEach(function (change) {
                pathLib.setByPath(data, change.path, change.value);
            });
        }, true);
    }

    function onMetaChange(ctx, event) {
        const control = event.target.closest('[data-meta-field]');

        if (!control) {
            return;
        }

        const field = control.dataset.metaField;

        if (field === 'storeIds') {
            ctx.state.meta.storeIds = Array.from(control.selectedOptions).map(function (option) {
                return Number(option.value);
            });
        } else if (field === 'isActive') {
            ctx.state.meta.isActive = control.checked;
        } else {
            ctx.state.meta[field] = control.value;
        }

        ctx.state.dirty = true;
        updateSaveButton(ctx);
    }

    function isEditableTarget(target) {
        const tag = target.tagName;

        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable;
    }

    // Cmd+/Ctrl shortcuts fire from anywhere in the editor, including inside
    // inputs - save/undo/redo are global actions, not text-editing ones.
    function onMetaKeydown(ctx, event) {
        if (event.key === 's') {
            event.preventDefault();
            save(ctx);
        } else if (event.key === 'z' && !event.shiftKey) {
            event.preventDefault();
            undo(ctx);
        } else if (event.key === 'z' && event.shiftKey) {
            event.preventDefault();
            redo(ctx);
        }
    }

    function onKeydown(ctx, event) {
        const isMeta = event.metaKey || event.ctrlKey;

        if (isMeta) {
            onMetaKeydown(ctx, event);
            return;
        }

        if (event.altKey) {
            onAltArrow(ctx, event);
            return;
        }

        if (event.key === 'Escape' && event.target === ctx.dom.outlineSearchInput) {
            closeSearch(ctx);
            return;
        }

        if (event.key === 'Escape' && previewTargetsModule.isOpen(ctx.dom.previewTargetsPopover)) {
            previewTargetsModule.close(ctx.dom.previewTargetsPopover);
            ctx.dom.previewTargetsButton.focus();
            return;
        }

        if (event.key === '/' && !isEditableTarget(event.target)) {
            event.preventDefault();
            openSearch(ctx);
        }
    }

    function buildOutlinePanel() {
        const outlineSearchInput = dom.el('input', {
            type: 'text',
            class: 'studio-input studio-outline__search-input',
            placeholder: 'Search fields...',
            dataset: {outlineSearch: '1'}
        });
        const outlineSearchWrapper = dom.el(
            'div',
            {class: 'studio-outline__search', hidden: true},
            outlineSearchInput
        );
        const outlineTree = dom.el('div', {class: 'studio-outline__tree'});
        // The toggle sits in the panel rather than the toolbar because it is
        // the panel's own control, and because it has to stay on screen when
        // the panel is collapsed - the rail is sized around it.
        const outlineToggle = dom.el('button', {
            type: 'button',
            class: 'studio-outline__toggle',
            title: 'Hide labels',
            dataset: {outlineToggle: '1'}
        }, [
            // The shared chevron points right; CSS turns it to face the way the
            // click will move the panel. "Hide" / "Show" is the name - a
            // chevron on its own is a decoration, not a control.
            dom.icon('chevron', 'studio-outline__toggle-icon'),
            dom.el('span', {class: 'studio-outline__toggle-label'}, 'Hide')
        ]);

        outlineToggle.setAttribute('aria-expanded', 'true');
        outlineToggle.setAttribute('aria-label', 'Hide labels');

        const outlineHeader = dom.el('div', {class: 'studio-outline__header'}, [
            outlineToggle
        ]);
        const outlinePanel = dom.el('div', {class: 'studio-editor__outline'}, [
            outlineHeader,
            outlineSearchWrapper,
            outlineTree
        ]);

        return {
            outlinePanel: outlinePanel,
            outlineTree: outlineTree,
            outlineToggle: outlineToggle,
            outlineSearchWrapper: outlineSearchWrapper,
            outlineSearchInput: outlineSearchInput
        };
    }

    function buildPreviewPanel() {
        const pageBuilt = previewPageModule.build();
        const previewToolbar = dom.el('div', {class: 'studio-preview__toolbar'}, [
            dom.icon('desktop', 'studio-preview__title-icon'),
            dom.el('span', {class: 'studio-preview__title'}, 'Live Preview')
        ]);
        const previewBody = dom.el('div', {class: 'studio-preview__body'});
        // pageBuilt.previewPage (the "open in a separate window" row) sits
        // above the drawn preview, not in place of it - this panel is always
        // the same in-panel preview it always was; the row above is purely an
        // additional way to see the real page, on a second monitor if there
        // is one.
        const previewPanel = dom.el('div', {class: 'studio-preview'}, [
            previewToolbar,
            pageBuilt.previewPage,
            previewBody
        ]);

        return Object.assign({
            previewPanel: previewPanel,
            previewBody: previewBody
        }, pageBuilt);
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    // Guards against a corrupted or hand-edited localStorage value naming a
    // device this build no longer knows - falls back rather than mounting
    // the editor with, say, previewDevice: "malformed".
    function readStoredChoice(key, fallback, allowed) {
        const stored = window.localStorage.getItem(key);

        return allowed.indexOf(stored) !== -1 ? stored : fallback;
    }

    function readStoredWidth(key, fallback, min, max) {
        const stored = Number(window.localStorage.getItem(key));

        return stored ? clamp(stored, min, max) : fallback;
    }

    function wireOutlineSplitter(ctx) {
        let dragStart = 0;

        splitterModule.wireDrag(ctx.dom.outlineSplitter, {
            onStart: function () {
                dragStart = ctx.state.panelWidths.outline;
            },
            onMove: function (deltaX) {
                ctx.state.panelWidths.outline = clamp(dragStart + deltaX, OUTLINE_WIDTH_MIN, OUTLINE_WIDTH_MAX);
                applyPanelWidths(ctx);
            },
            onEnd: function () {
                window.localStorage.setItem(STORAGE_OUTLINE_WIDTH, String(ctx.state.panelWidths.outline));
            }
        });
    }

    function wirePreviewSplitter(ctx) {
        let dragStart = 0;

        splitterModule.wireDrag(ctx.dom.previewSplitter, {
            onStart: function () {
                dragStart = ctx.state.panelWidths.preview;
            },
            onMove: function (deltaX) {
                ctx.state.panelWidths.preview = clamp(dragStart - deltaX, PREVIEW_WIDTH_MIN, PREVIEW_WIDTH_MAX);
                applyPanelWidths(ctx);
            },
            onEnd: function () {
                window.localStorage.setItem(STORAGE_PREVIEW_WIDTH, String(ctx.state.panelWidths.preview));
            }
        });
    }

    function wireSplitters(ctx) {
        wireOutlineSplitter(ctx);
        wirePreviewSplitter(ctx);
    }

    function buildDom(element, options) {
        const skeleton = element.querySelector('[data-studio-skeleton]');
        const root = element.querySelector('[data-studio-root]');
        const toolbarBuilt = toolbarModule.build(options);
        const metaBarEl = dom.el('div', {class: 'studio-editor__meta'});
        const outlineBuilt = buildOutlinePanel();
        const outlineSplitter = splitterModule.build('studio-splitter--outline');
        const fieldsPanel = dom.el('div', {class: 'studio-editor__fields'});
        const previewSplitter = splitterModule.build('studio-splitter--preview');
        const previewBuilt = buildPreviewPanel();
        const body = dom.el('div', {class: 'studio-editor__body'}, [
            outlineBuilt.outlinePanel,
            outlineSplitter,
            fieldsPanel,
            previewSplitter,
            previewBuilt.previewPanel
        ]);

        root.appendChild(toolbarBuilt.toolbar);
        root.appendChild(metaBarEl);
        root.appendChild(body);

        return Object.assign({
            skeleton: skeleton,
            root: root,
            body: body,
            metaBar: metaBarEl,
            outlineSplitter: outlineSplitter,
            previewSplitter: previewSplitter,
            fieldsPanel: fieldsPanel
        }, toolbarBuilt, outlineBuilt, previewBuilt);
    }

    function onOutlineSearchInput(ctx, event) {
        ctx.state.outlineQuery = event.target.value;
        outlineModule.filter(ctx.dom.outlineTree, ctx.state.outlineQuery);
    }

    function wireEvents(ctx) {
        ctx.dom.root.addEventListener('input', function (event) {
            if (event.target.dataset.outlineSearch) {
                onOutlineSearchInput(ctx, event);
                return;
            }
            onInput(ctx, event);
        });
        ctx.dom.root.addEventListener('focusin', function (event) {
            onFocusIn(ctx, event);
        });
        ctx.dom.root.addEventListener('change', function (event) {
            onChange(ctx, event);
            onMetaChange(ctx, event);

            if (event.target === ctx.dom.previewStoreSelect) {
                changePreviewStore(ctx, Number(ctx.dom.previewStoreSelect.value));
            }
        });
        ctx.dom.root.addEventListener('click', function (event) {
            onClick(ctx, event);
        });
        ctx.dom.root.addEventListener('keydown', function (event) {
            onKeydown(ctx, event);
        });
        ctx.dom.root.addEventListener('dragstart', function (event) {
            onDragStart(ctx, event);
        });
        ctx.dom.root.addEventListener('dragover', function (event) {
            onDragOver(ctx, event);
        });
        ctx.dom.root.addEventListener('drop', function (event) {
            onDrop(ctx, event);
        });
        ctx.dom.root.addEventListener('dragend', function () {
            onDragEnd(ctx);
        });
        ctx.dom.saveButton.addEventListener('click', function () {
            requestSave(ctx);
        });
        document.addEventListener('studio:field-change', function (event) {
            onFieldChangeEvent(ctx, event);
        });
        window.addEventListener('beforeunload', function (event) {
            if (!ctx.state.dirty) {
                return;
            }
            event.preventDefault();
            event.returnValue = '';
        });
    }

    /**
     * @param {Object} config - Bootstrap payload serialized by Block\Adminhtml\Editor::getBootstrapJson().
     * @param {Element} element - The `#studio-editor` root node.
     * @returns {void} Mounts the editor into `element`.
     */
    return function initEditor(config, element) {
        mediaConfigModule.set({
            browserUrl: config.urls.mediaBrowser,
            uploadUrl: config.urls.mediaUpload,
            folderId: config.mediaFolderId,
            mediaBaseUrl: config.mediaUrl,
            formKey: config.formKey
        });
        referenceOptionsModule.set(config.references);

        const ctx = {
            editSnapshot: null,
            dragState: null,
            dragOverEl: null,
            // Page-mode bookkeeping. Not in `state` because none of it is part
            // of the entry or is meant to survive a re-render the way
            // panelWidths does moment to moment - it tracks one live popup
            // window, which a re-render must never touch (see previewWindow).
            previewWindow: null,
            pageWindowWatcher: null,
            pageReloadTimer: null,
            pageLoadRequest: 0,
            pageTargets: [],
            state: {
                typeCode: config.typeCode,
                schema: config.schema,
                data: asDataObject(config.data),
                meta: config.meta,
                stores: config.stores,
                // Set when the editor is framed by another admin form; see
                // handleSaveResult().
                embedded: Boolean(config.embedded),
                urls: config.urls,
                formKey: config.formKey,
                history: [],
                redo: [],
                dirty: false,
                outlineQuery: '',
                // Bumped per "View on site" click; a response whose ticket no
                // longer matches is dropped. See requestPreviewTargets.
                previewTargetsRequest: 0,
                previewDevice: readStoredChoice(STORAGE_PREVIEW_DEVICE, 'desktop', ['desktop', 'tablet', 'mobile']),
                // Replaced with defaultPreviewStoreId(ctx) the moment `ctx`
                // exists - that needs `meta.storeIds` and `stores`, both of
                // which are being written by this very literal.
                previewStoreId: 0,
                selectedPath: null,
                outlineCollapsed: window.localStorage.getItem(STORAGE_OUTLINE_COLLAPSED) === '1',
                panelWidths: {
                    outline: readStoredWidth(
                        STORAGE_OUTLINE_WIDTH,
                        OUTLINE_WIDTH_DEFAULT,
                        OUTLINE_WIDTH_MIN,
                        OUTLINE_WIDTH_MAX
                    ),
                    preview: readStoredWidth(
                        STORAGE_PREVIEW_WIDTH,
                        PREVIEW_WIDTH_DEFAULT,
                        PREVIEW_WIDTH_MIN,
                        PREVIEW_WIDTH_MAX
                    )
                }
            }
        };

        ctx.state.previewStoreId = defaultPreviewStoreId(ctx);

        ctx.dom = buildDom(element, {allowCopy: isCopyableType(ctx)});
        wireEvents(ctx);
        wireSplitters(ctx);
        // Covers applyPanelWidths, and restores last session's collapsed state
        // the same way the widths and the preview device are restored.
        applyOutlineCollapsed(ctx);

        // Restores last session's device choice rather than always remounting
        // on Desktop - same treatment panelWidths already gets. Deliberately
        // does NOT reopen the popup on mount even if one was open last
        // session - a page load is not a user gesture, and window.open()
        // without one is silently blocked. Explicitly set to "closed" rather
        // than relying on build()'s own initial markup, so the "is-inviting"
        // shimmer (setWindowState's job, not build()'s) actually plays.
        previewPageModule.setDevice(ctx.dom, ctx.state.previewDevice);
        previewPageModule.setWindowState(ctx.dom, 'closed');

        // The store picker is usable before the first window is ever opened -
        // pick the brand, then open, rather than open-then-switch. Built from
        // the bootstrap store list rather than by calling previewDraft here:
        // that endpoint mints a token and parks a draft in the cache, which is
        // not something merely *viewing* the editor should do, and its failure
        // path writes an error over the status card - so a brand new entry,
        // which has no identifier for a preview to be looked up by yet, would
        // greet everyone with a red error and no shimmer on a button they had
        // not touched.
        previewPageModule.setStores(
            ctx.dom.previewStoreSelect,
            previewStoreOptions(ctx),
            ctx.state.previewStoreId
        );

        renderMetaBar(ctx);
        renderFormPanels(ctx);
        renderPreviewNow(ctx);
        updateSaveButton(ctx);

        // The branded splash is a page-level overlay owned by js/splash.js now,
        // so the only handover left here is skeleton -> mounted editor.
        crossfade(ctx.dom.skeleton, ctx.dom.root);
    };
});
