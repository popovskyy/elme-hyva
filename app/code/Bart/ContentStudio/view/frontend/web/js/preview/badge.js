/**
 * Counts the draft preview badge down to the moment its token stops working.
 *
 * The banner used to say "for 15 minutes", which is true when the link is made
 * and a lie every minute after - and the failure it warns about (a reload that
 * suddenly 404s) is exactly the kind that gets blamed on the block. A live
 * figure says what is actually left.
 *
 * A plain script rather than an Alpine component: the sandbox is a debugging
 * surface and should still label itself when something on the page has broken
 * Alpine.
 */
(function () {
    'use strict';

    const TICK_MS = 1000;

    function format(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;

        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    function expire(node, label) {
        node.textContent = label;

        if (node.parentNode) {
            // The class is what turns the capsule red; the countdown stops,
            // since a negative number tells nobody anything.
            node.parentNode.classList.add('is-expired');
        }
    }

    function start(node) {
        const expiresAt = Number(node.getAttribute('data-cs-expires'));
        const expiredLabel = node.getAttribute('data-label-expired') || 'link expired';

        if (!expiresAt) {
            return;
        }

        const tick = function () {
            const left = Math.round(expiresAt - Date.now() / 1000);

            if (left <= 0) {
                expire(node, expiredLabel);

                return;
            }

            node.textContent = format(left);
            window.setTimeout(tick, TICK_MS);
        };

        tick();
    }

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('[data-cs-expires]'), start);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
