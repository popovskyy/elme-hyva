/**
 * The grid's eye button (Ui\Component\Listing\Column\Preview).
 *
 *  - Hover / focus: a small popover with the entry's type, title and
 *    identifier, read straight off the button - no request.
 *  - Click: the modal opens at once with a loader, asks `entry/previewData`
 *    which stores the entry can be shown in, and loads the first one's
 *    `entry/previewFrame` into an <iframe>: the block rendered by its
 *    storefront template, under that store's theme and CSS. Store tabs and a
 *    Desktop / Mobile toggle switch the frame.
 *
 * Desktop renders the frame 1440px wide and scales it down to the modal, so
 * the block is laid out exactly as on a desktop screen rather than at the
 * modal's own (tablet-ish) width. The frame is same-origin (admin-served), so
 * its height is read directly and followed with a ResizeObserver as images
 * and fonts land.
 *
 * Everything hangs off delegated listeners on `document`: the grid re-renders
 * its rows on every filter, sort and page change.
 */
define([], function () {
    'use strict';

    const TRIGGER = '[data-cs-preview]';
    const HOVER_DELAY = 120;
    const DEVICES = {desktop: 1440, mobile: 393};
    // Plain object, not Map: the repo's eslint env predates the ES2015 globals.
    const cache = {};

    let tip = null;
    let tipTimer = 0;
    let modal = null;
    let lastTrigger = null;
    let requestTicket = 0;
    let restoringFocus = false;
    let frameObserver = null;
    const view = {stores: [], storeIndex: 0, device: 'desktop'};

    const make = (tag, className, text) => {
        const node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (typeof text === 'string') {
            node.textContent = text;
        }

        return node;
    };

    // ---------------------------------------------------------------- popover

    const buildTip = () => {
        const node = make('div', 'studio-grid-tip');

        node.setAttribute('role', 'tooltip');
        node.id = 'studio-grid-tip';
        node.hidden = true;
        document.body.append(node);

        return node;
    };

    // Beside the button, flipped to the left when it would leave the viewport.
    const placeTip = (trigger) => {
        const rect = trigger.getBoundingClientRect();
        const gap = 10;
        const width = tip.offsetWidth;
        const height = tip.offsetHeight;
        const fitsRight = rect.right + gap + width <= window.innerWidth - 8;
        const left = fitsRight ? rect.right + gap : Math.max(8, rect.left - gap - width);
        const top = Math.min(
            Math.max(8, rect.top + (rect.height / 2) - (height / 2)),
            window.innerHeight - height - 8
        );

        tip.style.left = `${Math.round(left)}px`;
        tip.style.top = `${Math.round(top)}px`;
    };

    const showTip = (trigger) => {
        tip = tip || buildTip();
        tip.replaceChildren(
            make('span', 'studio-grid-tip__type', trigger.dataset.typeLabel || ''),
            make('strong', 'studio-grid-tip__title', trigger.dataset.title || ''),
            make('code', 'studio-grid-tip__id', trigger.dataset.identifier || ''),
            make('span', 'studio-grid-tip__hint', 'Click to preview')
        );
        tip.hidden = false;
        tip.classList.remove('is-visible');
        placeTip(trigger);
        trigger.setAttribute('aria-describedby', tip.id);
        requestAnimationFrame(() => tip.classList.add('is-visible'));
    };

    const hideTip = () => {
        clearTimeout(tipTimer);

        if (tip) {
            tip.hidden = true;
            tip.classList.remove('is-visible');
        }
    };

    // ------------------------------------------------------------------ frame

    const stopObserving = () => {
        if (frameObserver) {
            frameObserver.disconnect();
            frameObserver = null;
        }
    };

    // Fits the frame to the stage: desktop is scaled down from 1440px, mobile
    // is shown at its real 393px (scaled only if the stage is narrower).
    const fitFrame = () => {
        const frame = modal.frame;
        const width = DEVICES[view.device];
        const stageStyle = getComputedStyle(modal.stage);
        const room = modal.stage.clientWidth - parseFloat(stageStyle.paddingLeft) - parseFloat(stageStyle.paddingRight);
        const scale = Math.min(1, room / width);
        const doc = frame.contentDocument;
        // `.page-main`, not <html> or `.page-wrapper`: those are at least as
        // tall as the frame itself, so measuring them could only ever grow.
        const content = doc && (doc.querySelector('.page-main') || doc.body);
        const height = content ? Math.ceil(content.getBoundingClientRect().height) : 600;

        frame.style.width = `${width}px`;
        frame.style.height = `${height}px`;
        frame.style.transform = `scale(${scale})`;
        modal.viewport.style.width = `${Math.round(width * scale)}px`;
        modal.viewport.style.height = `${Math.round(height * scale)}px`;
    };

    const onFrameLoad = () => {
        const doc = modal.frame.contentDocument;

        stopObserving();
        fitFrame();
        modal.stage.classList.remove('is-loading');

        const content = doc && (doc.querySelector('.page-main') || doc.body);

        if (content) {
            frameObserver = new ResizeObserver(fitFrame);
            frameObserver.observe(content);
        }
    };

    const loadFrame = () => {
        const store = view.stores[view.storeIndex];

        stopObserving();
        modal.stage.classList.add('is-loading');
        // Width first, so the document lays out at the device width from its
        // very first paint instead of reflowing once the load event fits it.
        modal.frame.style.width = `${DEVICES[view.device]}px`;
        modal.frame.src = store.frameUrl;
    };

    const paintToolbar = () => {
        modal.storeTabs.replaceChildren(...view.stores.map((store, index) => {
            const tab = make('button', 'studio-grid-modal__tab', store.name);

            tab.type = 'button';
            tab.dataset.storeIndex = String(index);
            tab.setAttribute('aria-pressed', index === view.storeIndex ? 'true' : 'false');

            return tab;
        }));
        modal.storeTabs.hidden = view.stores.length < 2;
        modal.devices.querySelectorAll('[data-device]').forEach((button) => {
            button.setAttribute('aria-pressed', button.dataset.device === view.device ? 'true' : 'false');
        });
    };

    // ------------------------------------------------------------------ modal

    const closeModal = () => {
        if (!modal || modal.root.hidden) {
            return;
        }

        requestTicket += 1;
        stopObserving();
        modal.frame.removeAttribute('src');
        modal.root.hidden = true;
        document.documentElement.classList.remove('studio-grid-modal-open');

        // Focus goes back to the eye it came from, without re-raising its
        // popover on top of the row the user is looking at.
        if (lastTrigger && document.contains(lastTrigger)) {
            restoringFocus = true;
            lastTrigger.focus();
            restoringFocus = false;
        }
    };

    const deviceSwitch = () => {
        const group = make('div', 'studio-grid-modal__devices');

        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', 'Device');
        Object.keys(DEVICES).forEach((device) => {
            const button = make('button', 'studio-grid-modal__tab', device === 'desktop' ? 'Desktop' : 'Mobile');

            button.type = 'button';
            button.dataset.device = device;
            group.append(button);
        });

        return group;
    };

    const onToolbarClick = (event) => {
        const button = event.target.closest('button');

        if (!button) {
            return;
        }

        if (button.dataset.storeIndex) {
            view.storeIndex = Number(button.dataset.storeIndex);
        } else if (button.dataset.device) {
            view.device = button.dataset.device;
        } else {
            return;
        }

        paintToolbar();
        loadFrame();
    };

    const buildModal = () => {
        const root = make('div', 'studio-grid-modal');
        const dialog = make('div', 'studio-grid-modal__dialog');
        const header = make('div', 'studio-grid-modal__header');
        const heading = make('div', 'studio-grid-modal__heading');
        const type = make('span', 'studio-grid-modal__type');
        const title = make('h2', 'studio-grid-modal__title');
        const id = make('code', 'studio-grid-modal__id');
        const actions = make('div', 'studio-grid-modal__actions');
        const edit = make('a', 'studio-grid-modal__edit', 'Edit');
        const close = make('button', 'studio-grid-modal__close');
        const toolbar = make('div', 'studio-grid-modal__toolbar');
        const storeTabs = make('div', 'studio-grid-modal__stores');
        const devices = deviceSwitch();
        const stage = make('div', 'studio-grid-modal__stage is-loading');
        const viewport = make('div', 'studio-grid-modal__viewport');
        const frame = make('iframe', 'studio-grid-modal__frame');
        const loader = make('div', 'studio-grid-modal__loader');
        const error = make('p', 'studio-grid-modal__error');

        root.hidden = true;
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'studio-grid-modal-title');
        title.id = 'studio-grid-modal-title';
        close.type = 'button';
        close.setAttribute('aria-label', 'Close preview');
        close.innerHTML = '<svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true">'
            + '<path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="1.6" '
            + 'stroke-linecap="round"/></svg>';
        storeTabs.setAttribute('role', 'group');
        storeTabs.setAttribute('aria-label', 'Store view');
        frame.title = 'Storefront preview';
        loader.setAttribute('role', 'status');
        loader.append(make('span', 'studio-grid-modal__spinner'), make('span', '', 'Loading preview...'));
        error.hidden = true;

        heading.append(type, title, id);
        actions.append(edit, close);
        header.append(heading, actions);
        toolbar.append(storeTabs, devices);
        viewport.append(frame);
        stage.append(viewport, loader, error);
        dialog.append(header, toolbar, stage);
        root.append(dialog);
        document.body.append(root);

        // Backdrop click closes; a drag that started inside the dialog does not.
        let downOnBackdrop = false;

        root.addEventListener('mousedown', (event) => {
            downOnBackdrop = event.target === root;
        });
        root.addEventListener('click', (event) => {
            if (downOnBackdrop && event.target === root) {
                closeModal();
            }
        });
        close.addEventListener('click', closeModal);
        toolbar.addEventListener('click', onToolbarClick);
        frame.addEventListener('load', () => {
            if (frame.getAttribute('src')) {
                onFrameLoad();
            }
        });
        window.addEventListener('resize', () => {
            if (!root.hidden && !stage.classList.contains('is-loading')) {
                fitFrame();
            }
        });

        return {root, dialog, type, title, id, edit, close, toolbar, storeTabs, devices, stage, viewport, frame, error};
    };

    const showError = (message) => {
        modal.stage.classList.remove('is-loading');
        modal.viewport.hidden = true;
        modal.error.hidden = false;
        modal.error.textContent = message;
    };

    const fetchEntry = (url) => {
        if (!cache[url]) {
            const request = fetch(url, {
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'}
            }).then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json();
            });

            // A failed request must not be cached, or the next click repeats
            // the failure without trying again.
            request.catch(() => {
                delete cache[url];
            });
            cache[url] = request;
        }

        return cache[url];
    };

    const showEntry = (payload) => {
        if (!payload || !payload.success) {
            showError((payload && payload.message) || 'The preview could not be loaded.');

            return;
        }

        if (!payload.stores.length) {
            showError('This entry is not assigned to any active store view.');

            return;
        }

        modal.type.textContent = payload.entry.typeLabel;
        modal.title.textContent = payload.entry.title || payload.entry.identifier;
        modal.edit.href = payload.editUrl;
        view.stores = payload.stores;
        view.storeIndex = 0;
        modal.toolbar.hidden = false;
        paintToolbar();
        loadFrame();
    };

    const openModal = (trigger) => {
        const ticket = requestTicket + 1;

        requestTicket = ticket;
        lastTrigger = trigger;
        hideTip();
        modal = modal || buildModal();
        modal.type.textContent = trigger.dataset.typeLabel || '';
        modal.title.textContent = trigger.dataset.title || '';
        modal.id.textContent = trigger.dataset.identifier || '';
        modal.edit.removeAttribute('href');
        modal.toolbar.hidden = true;
        modal.viewport.hidden = false;
        modal.error.hidden = true;
        modal.stage.classList.add('is-loading');
        modal.root.hidden = false;
        document.documentElement.classList.add('studio-grid-modal-open');
        modal.close.focus();

        fetchEntry(trigger.dataset.csPreview)
            .then((payload) => {
                // A newer click, or a close, happened while this was in flight.
                if (ticket === requestTicket) {
                    showEntry(payload);
                }
            })
            .catch(() => {
                if (ticket === requestTicket) {
                    showError('The preview could not be loaded. Please try again.');
                }
            });
    };

    // ------------------------------------------------------------- listeners

    const triggerOf = (event) => (event.target instanceof Element ? event.target.closest(TRIGGER) : null);

    const onOver = (event) => {
        const trigger = triggerOf(event);

        if (!trigger || trigger.contains(event.relatedTarget)) {
            return;
        }

        clearTimeout(tipTimer);
        tipTimer = setTimeout(() => showTip(trigger), HOVER_DELAY);
    };

    const onOut = (event) => {
        const trigger = triggerOf(event);

        if (trigger && !trigger.contains(event.relatedTarget)) {
            hideTip();
        }
    };

    const onClick = (event) => {
        const trigger = triggerOf(event);

        if (trigger) {
            event.preventDefault();
            openModal(trigger);
        }
    };

    // Keeps Tab inside the dialog while it is open. The frame is one stop.
    const trapFocus = (event) => {
        const focusable = [...modal.dialog.querySelectorAll('a[href], button, iframe')]
            .filter((node) => node.offsetParent !== null);
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    const onKey = (event) => {
        const open = modal && !modal.root.hidden;

        if (event.key === 'Escape') {
            hideTip();
            closeModal();
        } else if (event.key === 'Tab' && open) {
            trapFocus(event);
        }
    };

    /**
     * @returns {void} Wires the delegated listeners once per page.
     */
    return function initGridPreview() {
        document.addEventListener('mouseover', onOver);
        document.addEventListener('mouseout', onOut);
        document.addEventListener('focusin', (event) => {
            const trigger = triggerOf(event);

            if (trigger && !restoringFocus) {
                showTip(trigger);
            }
        });
        document.addEventListener('focusout', (event) => {
            if (triggerOf(event)) {
                hideTip();
            }
        });
        document.addEventListener('click', onClick);
        document.addEventListener('keydown', onKey);
        window.addEventListener('scroll', hideTip, {passive: true, capture: true});
    };
});
