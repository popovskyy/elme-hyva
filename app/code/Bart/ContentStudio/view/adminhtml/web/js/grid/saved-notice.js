/**
 * Confirms a save that happened on the *previous* page.
 *
 * The editor redirects to this listing after writing an entry, which throws
 * away any toast it raised on its way out. The `?saved=<identifier>` it leaves
 * on the URL is picked up here instead, so the confirmation lands where the
 * user actually ends up.
 *
 * The flag is stripped from the address bar straight afterwards: reloading the
 * grid, or coming back to it from a bookmark, must not replay a message about
 * something that happened once, minutes ago.
 */
define(['Bart_ContentStudio/js/lib/toast'], function (toast) {
    'use strict';

    // Hand-rolled rather than URLSearchParams: the repo's eslint env predates
    // it and flags the global as no-undef.
    function readSaved(search) {
        const match = /[?&]saved=([^&]*)/.exec(search);

        return match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : null;
    }

    // Drops the pair and then any separator it left dangling, so `?saved=x`
    // collapses to '' while `?p=2&saved=x` keeps its page.
    function stripSaved(search) {
        return search
            .replace(/([?&])saved=[^&]*&?/, '$1')
            .replace(/[?&]$/, '');
    }

    /**
     * @returns {void} Toasts the entry saved on the previous page, once.
     */
    return function initSavedNotice() {
        const identifier = readSaved(window.location.search);

        if (identifier === null) {
            return;
        }

        toast.show(identifier ? 'Saved "' + identifier + '"' : 'Entry saved');
        window.history.replaceState(
            {},
            '',
            window.location.pathname + stripSaved(window.location.search) + window.location.hash
        );
    };
});
