/**
 * "Review before saving" - a full-viewport stage that shows the entry the way
 * the block will actually render, and only then offers the save.
 *
 * The editor's own live preview is a 420px side panel: enough to see structure
 * and order, not enough to judge the finished block. Clicking Save now opens
 * this instead of writing straight away, so the last thing a content manager
 * looks at before committing is the block itself, at full width.
 *
 * Deliberately knows nothing about entries, payloads or saving. The caller
 * hands it a `render` callback for the stage and an `onConfirm` that returns a
 * promise; everything about *what* is being saved stays in state.js.
 *
 * The node is built once and reused, and lives on `document.body` rather than
 * inside the editor - same as lib/toast.js, and for a harder reason here:
 * `.studio-editor__root` carries a permanent `filter: blur(0)` (it drives the
 * skeleton handover), and any filter other than `none` makes an element the
 * containing block for `position: fixed` descendants. Mounted inside the
 * editor this overlay would size itself to the editor, not the viewport. The
 * design tokens still resolve because `studio-page` sits on <body>.
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/lib/toast'
], function (dom, toast) {
    'use strict';

    // A floor rather than a fixed pause: the renderer usually resolves in a
    // frame or two (its AMD module is already loaded from the side panel), and
    // showing "Building preview" for exactly as long as it truly took would
    // read as a flicker. Same shape as splash.js's MIN_VISIBLE_MS.
    //
    // Long enough to let the sequence land rather than flash past - the rule
    // finishes drawing at 1520ms and this holds a beat after it. Validation now
    // runs *before* the overlay opens, so this only ever plays for an entry
    // that is actually going to save; nobody sits through it to be told no.
    const BUILD_MIN_MS = 1750;
    const SPLASH_FADE_MS = 320;

    let ui = null;
    let session = null;

    function prefersReducedMotion() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function clearTimer(timerRef) {
        if (timerRef !== null) {
            window.clearTimeout(timerRef);
        }

        return null;
    }

    function close() {
        if (!session) {
            return;
        }

        const previousFocus = session.previousFocus;
        const inertTarget = session.inertTarget;

        // Escape during the splash has to take its pending timers with it.
        // Left running, they fire against the *next* session and hide a splash
        // that has only just been put up.
        session.splashTimer = clearTimer(session.splashTimer);
        session.exitTimer = clearTimer(session.exitTimer);
        document.removeEventListener('keydown', session.onKeydown, true);
        document.body.style.overflow = session.previousOverflow;

        if (inertTarget) {
            inertTarget.inert = false;
            inertTarget.removeAttribute('aria-hidden');
        }

        session = null;
        ui.root.hidden = true;
        dom.empty(ui.block);

        // Focus goes back where it came from, so a cancelled review leaves the
        // keyboard exactly where the user left it (normally the Save button).
        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        }
    }

    // Both outcomes close: on failure the messages live in `[data-field-error]`
    // nodes inside the editor, which is underneath this overlay - leaving it up
    // would hide the very thing the user needs to read.
    function finish(saved, successMessage) {
        if (saved && successMessage) {
            toast.show(successMessage);
        }
        close();
    }

    function onConfirmClick() {
        if (!session || session.busy) {
            return;
        }

        const successMessage = session.successMessage;

        session.busy = true;
        ui.confirm.disabled = true;
        ui.confirmLabel.textContent = 'Saving...';

        // `onConfirm` is contracted to return a thenable - state.js hands back
        // the fetch chain from save(). Called directly rather than wrapped in
        // Promise.resolve(): the repo's eslint env predates ES6 globals and
        // treats a bare `Promise` reference as no-undef.
        session.onConfirm().then(function (saved) {
            ui.confirm.disabled = false;
            ui.confirmLabel.textContent = 'Save entry';
            finish(saved, successMessage);
        });
    }

    function onOverlayKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
    }

    // The note carries the entry's own identifier, so the moment is about the
    // thing being built rather than a generic spinner.
    function buildSplash() {
        const note = dom.el('p', {class: 'studio-review__splash-note'});
        const node = dom.el('div', {class: 'studio-review__splash'}, dom.el(
            'div',
            {class: 'studio-review__splash-inner'},
            [
                dom.el('span', {class: 'studio-review__splash-orb'}, [
                    dom.el('span', {class: 'studio-review__splash-halo'}),
                    dom.icon('mark', 'studio-review__splash-mark')
                ]),
                dom.el('p', {class: 'studio-review__splash-label'}, 'Building preview'),
                note,
                dom.el(
                    'span',
                    {class: 'studio-review__splash-rule'},
                    dom.el('i', {class: 'studio-review__splash-rule-fill'})
                )
            ]
        ));

        return {node: node, note: note};
    }

    function buildBar(title, subtitle, cancel, confirm) {
        return dom.el('div', {class: 'studio-review__bar'}, [
            dom.el('div', {class: 'studio-review__heading'}, [title, subtitle]),
            dom.el('div', {class: 'studio-review__actions'}, [cancel, confirm])
        ]);
    }

    function build() {
        const title = dom.el('p', {class: 'studio-review__title'});
        const subtitle = dom.el('p', {class: 'studio-review__subtitle'});
        const cancel = dom.el(
            'button',
            {type: 'button', class: 'studio-btn studio-btn--ghost'},
            [dom.icon('back'), dom.el('span', {}, 'Back to editor')]
        );
        const confirmLabel = dom.el('span', {}, 'Save entry');
        const confirm = dom.el(
            'button',
            {type: 'button', class: 'studio-btn studio-btn--primary'},
            [dom.icon('save'), confirmLabel]
        );
        // .studio-preview is what scopes every content-type renderer's styling
        // in _preview.less; the stage reuses it wholesale and _review.less only
        // scales the result up for a full-width read.
        const block = dom.el('div', {class: 'studio-review__block'});
        const stage = dom.el('div', {class: 'studio-review__stage studio-preview'}, block);
        const splash = buildSplash();
        const root = dom.el(
            'div',
            {
                class: 'studio-review',
                role: 'dialog',
                'aria-modal': 'true',
                'aria-label': 'Review before saving',
                hidden: true
            },
            [buildBar(title, subtitle, cancel, confirm), stage, splash.node]
        );

        cancel.addEventListener('click', close);
        confirm.addEventListener('click', onConfirmClick);
        document.body.appendChild(root);

        return {
            root: root,
            title: title,
            subtitle: subtitle,
            stage: stage,
            block: block,
            splash: splash.node,
            splashNote: splash.note,
            confirm: confirm,
            confirmLabel: confirmLabel
        };
    }

    function reveal() {
        if (!session) {
            return;
        }

        session.splashTimer = null;
        ui.splash.classList.add('is-leaving');
        ui.stage.classList.remove('is-pending');
        session.exitTimer = window.setTimeout(function () {
            session.exitTimer = null;
            ui.splash.hidden = true;
            ui.splash.classList.remove('is-leaving');
        }, SPLASH_FADE_MS);
    }

    function runSplash(startedAt) {
        session.splashTimer = window.setTimeout(
            reveal,
            Math.max(0, BUILD_MIN_MS - (Date.now() - startedAt))
        );
    }

    /**
     * @param {Object} options - `{title, subtitle, render, onConfirm, successMessage, inertTarget}`.
     *   `render(stageEl)` draws the block; `onConfirm()` must return a promise
     *   resolving true when the entry was written, false when it came back with errors.
     * @returns {void} Opens the review overlay over the page.
     */
    function open(options) {
        if (!ui) {
            ui = build();
        }

        if (session) {
            return;
        }

        session = {
            onConfirm: options.onConfirm,
            successMessage: options.successMessage,
            inertTarget: options.inertTarget || null,
            previousFocus: document.activeElement,
            previousOverflow: document.body.style.overflow,
            onKeydown: onOverlayKeydown,
            splashTimer: null,
            exitTimer: null,
            busy: false
        };

        ui.title.textContent = options.title || 'Review before saving';
        ui.subtitle.textContent = options.subtitle || '';
        ui.splashNote.textContent = options.subtitle || '';
        dom.empty(ui.block);

        const skipSplash = prefersReducedMotion();
        const startedAt = Date.now();

        // Reset rather than assume: closing mid-fade leaves `is-leaving` on the
        // splash, and reopening onto that class shows a splash at opacity 0.
        ui.splash.classList.remove('is-leaving');
        ui.splash.hidden = skipSplash;
        // The node is built once and reused, so the entrance animations would
        // only ever have played on the first open. Dropping the class, flushing
        // layout, then re-adding it restarts them - same trick the outline
        // flash uses in state.js.
        ui.splash.classList.remove('is-running');
        ui.splash.getBoundingClientRect();
        ui.splash.classList.add('is-running');
        ui.stage.classList.toggle('is-pending', !skipSplash);
        ui.root.hidden = false;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', session.onKeydown, true);

        if (session.inertTarget) {
            session.inertTarget.inert = true;
            session.inertTarget.setAttribute('aria-hidden', 'true');
        }

        options.render(ui.block);
        ui.confirm.focus();

        if (!skipSplash) {
            runSplash(startedAt);
        }
    }

    return {open: open};
});
