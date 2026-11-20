/**
 * A small, self-contained toast - one message on screen at a time, auto-
 * dismissed, dismissible early on click. Not tied to the editor state; any
 * admin page can `require` this and call `.show()`.
 *
 * One node is created lazily on first use and reused after that, rather than
 * appending a fresh element per call, so rapid repeat clicks (someone tapping
 * the same locked card a few times) restart the same toast instead of
 * stacking several.
 */
define([], function () {
    'use strict';

    const AUTO_DISMISS_MS = 3600;
    const EXIT_MS = 220;

    let container = null;
    let dismissTimer = null;
    let exitTimer = null;

    function clearTimer(timerRef) {
        if (timerRef !== null) {
            clearTimeout(timerRef);
        }

        return null;
    }

    function dismiss() {
        if (!container || container.hidden) {
            return;
        }

        dismissTimer = clearTimer(dismissTimer);
        exitTimer = clearTimer(exitTimer);
        // Drop is-visible so exit does not depend on CSS source order winning
        // over both classes at once.
        container.classList.remove('is-visible');
        container.classList.add('is-leaving');
        exitTimer = setTimeout(function () {
            exitTimer = null;
            container.hidden = true;
            container.classList.remove('is-leaving', 'is-visible');
        }, EXIT_MS);
    }

    function build() {
        const node = document.createElement('div');

        node.className = 'studio-toast';
        node.setAttribute('role', 'status');
        node.setAttribute('aria-live', 'polite');
        node.hidden = true;
        node.addEventListener('click', function () {
            dismiss();
        });
        document.body.appendChild(node);

        return node;
    }

    /**
     * @param {string} message - Plain text to show. Never HTML - textContent only.
     * @returns {void} Shows (or restarts) the single shared toast with `message`.
     */
    function show(message) {
        if (!container) {
            container = build();
        }

        dismissTimer = clearTimer(dismissTimer);
        exitTimer = clearTimer(exitTimer);
        container.classList.remove('is-leaving');
        container.textContent = message;
        container.hidden = false;

        // Forces the browser to register the "hidden -> visible" state before
        // is-visible is added, so the entrance transition actually runs
        // instead of being coalesced away.
        container.getBoundingClientRect();
        container.classList.add('is-visible');

        dismissTimer = setTimeout(dismiss, AUTO_DISMISS_MS);
    }

    return {show: show};
});
