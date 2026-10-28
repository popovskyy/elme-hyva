/**
 * The "open the real page" row above the preview panel's own drawn preview
 * (preview.js) - a separate browser window showing the actual sandbox page,
 * not a mode you switch into that replaces the in-panel preview. The in-panel
 * preview stays exactly what it always was: fast, always visible. This row is purely additive - a
 * second monitor can show the real page while the first keeps the editor and
 * this same drawn preview, side by side.
 *
 * Structure and the small pieces of purely visual state (which device size is
 * selected, which store is selected in the UI) live here - same split as
 * preview-targets.js. Opening/tracking the actual window, the fetch that
 * mints a preview token, the postMessage wiring, and the decision between a
 * live repaint and a full reload all live in state.js, which is the only
 * place editor state is allowed to change (plan section 3).
 */
define(['Bart_ContentStudio/js/editor/lib/dom'], function (dom) {
    'use strict';

    /**
     * Sizes for `window.open()`'s `width`/`height` features - there is no
     * frame to constrain here, so these are the popup's actual dimensions.
     * Tablet/mobile match the breakpoints Overdose/base themes switch layout
     * on, so the block is actually seen at the width its own CSS reacts to;
     * desktop is a generous, ordinary browser window rather than "as wide as
     * whatever monitor this happens to open on".
     */
    const DEVICES = [
        {key: 'desktop', icon: 'desktop', label: 'Desktop', width: 1440, height: 900},
        {key: 'tablet', icon: 'tablet', label: 'Tablet', width: 768, height: 1024},
        {key: 'mobile', icon: 'mobile', label: 'Mobile', width: 390, height: 844}
    ];

    const STATE_LABELS = {
        closed: 'Not open yet.',
        opening: 'Opening...',
        open: 'Open in a separate window.',
        blocked: 'The browser blocked the pop-up. Allow pop-ups for this site and try again.',
        error: 'The preview could not be loaded. Please try again.'
    };

    function deviceButton(device) {
        return dom.el('button', {
            type: 'button',
            class: 'studio-btn studio-btn--icon studio-btn--ghost studio-preview__device-btn',
            title: device.label,
            'aria-label': device.label,
            'aria-pressed': 'false',
            dataset: {previewDevice: device.key}
        }, dom.icon(device.icon));
    }

    /**
     * @returns {Object} Every node state.js needs a handle on.
     */
    function build() {
        const deviceButtons = DEVICES.map(deviceButton);
        const deviceToggle = dom.el('div', {class: 'studio-preview__device'}, deviceButtons);

        // Populated by setStores() once the entry's assigned stores are known;
        // hidden by default because most entries live on one store view, where
        // a selector with a single, unchangeable option is just noise.
        const storeSelect = dom.el('select', {class: 'studio-select studio-preview__store', hidden: true});

        const openButtonLabel = dom.el('span', {}, 'Open Live Preview');
        const openButton = dom.el('button', {
            type: 'button',
            class: 'studio-btn studio-preview__open-btn',
            dataset: {previewOpen: '1'}
        }, [dom.icon('external'), openButtonLabel]);

        // Hidden by default - the button's own label already says what will
        // happen, and a persistent "Not open yet." line under it is just
        // noise for the common case. This only appears for the states that
        // genuinely need more explaining than the button alone can: a
        // blocked pop-up or a failed fetch.
        const statusDot = dom.el('span', {class: 'studio-preview__page-status-dot'});
        const statusText = dom.el('span', {class: 'studio-preview__page-status-text'}, STATE_LABELS.closed);
        const statusCard = dom.el(
            'div',
            {class: 'studio-preview__page-status is-closed', hidden: true},
            [statusDot, statusText]
        );

        const pageControls = dom.el(
            'div',
            {class: 'studio-preview__page-controls'},
            [openButton, deviceToggle, storeSelect]
        );
        const previewPage = dom.el('div', {class: 'studio-preview__page'}, [pageControls, statusCard]);

        // Every key here is spread straight into the editor's `ctx.dom`
        // (state.js::buildPreviewPanel), so each is named for what it is
        // there, not for its role inside this module.
        return {
            previewPage: previewPage,
            previewPageControls: pageControls,
            previewDeviceToggle: deviceToggle,
            previewDeviceButtons: deviceButtons,
            previewStoreSelect: storeSelect,
            previewOpenButton: openButton,
            previewOpenButtonLabel: openButtonLabel,
            previewPageStatus: statusCard,
            previewPageStatusText: statusText
        };
    }

    /**
     * @param {Object} dom_ - The relevant slice of `ctx.dom` (build()'s return).
     * @param {string} device - One of DEVICES' keys.
     * @returns {void} Highlights the active device button.
     */
    function setDevice(dom_, device) {
        const found = DEVICES.filter(function (d) {
            return d.key === device;
        })[0] || DEVICES[0];

        dom_.previewDeviceButtons.forEach(function (btn) {
            const active = btn.dataset.previewDevice === found.key;

            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', String(active));
        });
    }

    /**
     * @param {Element} select - The store `<select>` build() returned.
     * @param {Array<{id: number, label: string}>} stores - Options to offer.
     * @param {number} selectedId - The currently chosen store id.
     * @returns {void} Rebuilds the option list; hidden entirely for a single store.
     */
    function setStores(select, stores, selectedId) {
        dom.empty(select);
        select.hidden = stores.length < 2;

        stores.forEach(function (store) {
            const option = dom.el('option', {value: String(store.id)}, store.label);

            option.selected = store.id === selectedId;
            select.appendChild(option);
        });
    }

    // Only these two states need more explaining than the button's own label
    // already gives - closed/opening/open are all self-evident from the
    // label and the shimmer/disabled treatment alone.
    const STATES_WITH_VISIBLE_STATUS = ['blocked', 'error'];

    /**
     * @param {Object} dom_ - The relevant slice of `ctx.dom` (build()'s return).
     * @param {string} state - 'closed' | 'opening' | 'open' | 'blocked' | 'error'.
     * @param {string} [message] - Overrides the default label for `state`.
     * @returns {void}
     */
    function setWindowState(dom_, state, message) {
        dom_.previewPageStatus.hidden = STATES_WITH_VISIBLE_STATUS.indexOf(state) === -1;
        dom_.previewPageStatus.className = 'studio-preview__page-status is-' + state;
        dom_.previewPageStatusText.textContent = message || STATE_LABELS[state] || '';
        // The shimmer is the "hey, try this" affordance for a window that has
        // never been opened - once one exists, clicking again just refocuses
        // or re-navigates it, so the label stays the same rather than
        // switching to a second phrase ("Reopen...") for what is, from the
        // user's side, still the same one action.
        dom_.previewOpenButton.classList.toggle('is-inviting', state === 'closed');
        dom_.previewOpenButtonLabel.textContent = state === 'opening' ? 'Opening...' : 'Open Live Preview';
        dom_.previewOpenButton.disabled = state === 'opening';
    }

    return {
        DEVICES: DEVICES,
        build: build,
        setDevice: setDevice,
        setStores: setStores,
        setWindowState: setWindowState
    };
});
