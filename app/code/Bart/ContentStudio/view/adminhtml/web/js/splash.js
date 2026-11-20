/**
 * Dismisses the Content Studio entry splash.
 *
 * The overlay is already in the markup (it has to be, to cover the page while
 * it assembles) and splash.phtml drops it synchronously when the referrer says
 * we merely moved between Studio pages. This module handles the arrival case:
 * hold it long enough that the entrance animation reads as intentional rather
 * than a flash, then part the two halves and reveal the page behind them.
 */
define([], function () {
    'use strict';

    // The entrance stagger finishes at 480ms delay + 744ms = 1224ms, so a
    // shorter hold than that cut the last element off mid-rise.
    const MIN_VISIBLE_MS = 1500;

    // The exit is a curtain: both halves travel for 560ms while the base fades
    // (140ms delay + 420ms). Remove the node only after the slower of the two.
    const FADE_MS = 600;

    function remove(element) {
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }

    function dismiss(element) {
        element.classList.add('is-leaving');
        setTimeout(function () {
            remove(element);
        }, FADE_MS);
    }

    /**
     * @param {Object} config - x-magento-init payload; unused.
     * @param {Element} element - The splash overlay node.
     * @returns {void} Fades the overlay out once the page has settled.
     */
    return function initSplash(config, element) {
        const shownAt = Date.now();

        // window `load` rather than DOMContentLoaded: the point is to cover the
        // page until it actually looks finished, images and all.
        const start = function () {
            const elapsed = Date.now() - shownAt;

            setTimeout(function () {
                dismiss(element);
            }, Math.max(0, MIN_VISIBLE_MS - elapsed));
        };

        if (document.readyState === 'complete') {
            start();
        } else {
            window.addEventListener('load', start, {once: true});
        }
    };
});
