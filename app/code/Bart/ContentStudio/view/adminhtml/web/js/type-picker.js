/**
 * Wires the "Coming soon" locked cards on the New entry screen: clicking one
 * cannot navigate anywhere (there is no type behind it yet), so it shows a
 * toast instead of doing nothing.
 *
 * `<button>` is used for these cards rather than `<a>`, so Enter/Space already
 * dispatch a native click - no keydown handling needed here.
 */
define(['Bart_ContentStudio/js/lib/toast'], function (toast) {
    'use strict';

    /**
     * @param {Object} config - x-magento-init payload; unused.
     * @param {Element} element - The picker root.
     * @returns {void} Wires the coming-soon cards inside `element`.
     */
    return function initTypePicker(config, element) {
        element.addEventListener('click', function (event) {
            const card = event.target.closest ? event.target.closest('[data-coming-soon]') : null;

            if (!card) {
                return;
            }

            toast.show(card.dataset.comingSoonLabel + ': we\'ll let you know when it\'s ready.');
        });
    };
});
