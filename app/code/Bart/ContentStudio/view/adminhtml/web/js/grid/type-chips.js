/**
 * Wires the type chips above the entries grid to the grid's own (visually
 * hidden - see _grid.less) Filters component, instead of reimplementing
 * Magento's filter-request plumbing.
 *
 * `Filters.setData(data, partial)` + `Filters.apply()` are the same public
 * methods a real click on the stock "Content Type" dropdown + "Apply
 * Filters" button would call - this is not a workaround, it is the
 * documented shape of that component (magento/module-ui's
 * view/base/web/js/grid/filters/filters.js). Using them means undo/redo,
 * bookmarking and pagination-reset all keep working exactly as they do for
 * every other column filter in this grid.
 *
 * uiRegistry may not have the Filters component ready the instant this runs
 * (the grid's own uiComponent tree bootstraps asynchronously), so the lookup
 * uses uiRegistry's own async `get(selector, callback)` form rather than the
 * sync one - it waits rather than racing the grid's own boot.
 *
 * The chip row is server-rendered with "All types" already `is-active`. The
 * grid then restores its bookmarked `applied` filter - leaving the listing
 * after Add New Entry and coming back is the usual way that happens - so the
 * chips have to read that restored value or they stay on "All types" while
 * the Active filters bar and the table already show one type.
 */
define(['jquery', 'uiRegistry'], function ($, registry) {
    'use strict';

    const ACTIVE_CLASS = 'is-active';

    /**
     * @param {Object} [applied] - The Filters component's `applied` map.
     * @returns {string} Content type code, or '' for "All types".
     */
    const typeFromApplied = (applied) => String((applied && applied.type) || '');

    /**
     * @param {Element} root - The chip row container.
     * @param {string} value - The chip's `data-chip-value` that was clicked.
     * @returns {void} Marks exactly one chip active.
     */
    const markActive = (root, value) => {
        root.querySelectorAll('[data-chip-value]').forEach((chip) => {
            chip.classList.toggle(ACTIVE_CLASS, chip.dataset.chipValue === value);
        });
    };

    /**
     * @param {string} ns - The listing's ui_component namespace.
     * @param {string} value - Content type code, or '' for "All types".
     * @returns {void} Applies (or clears) the grid's own `type` column filter.
     */
    const applyTypeFilter = (ns, value) => {
        registry.get('ns = ' + ns + ', componentType = filters', (filters) => {
            filters.setData(value ? {type: value} : {}, false);
            filters.apply();
        });
    };

    /**
     * @param {Element} root - The chip row container.
     * @param {Object} filters - The listing's Filters uiComponent.
     * @returns {void} Paints the chip that matches the grid's current filter.
     */
    const syncChips = (root, filters) => {
        markActive(root, typeFromApplied(filters.get('applied')));
    };

    /**
     * @param {Object} config - x-magento-init payload; unused.
     * @param {Element} element - The chip row root.
     * @returns {void} Wires chip clicks to the grid's Filters component.
     */
    return function initTypeChips(config, element) {
        const ns = element.dataset.listingNs;

        registry.get('ns = ' + ns + ', componentType = filters', (filters) => {
            syncChips(element, filters);
            filters.on('applied', (applied) => {
                markActive(element, typeFromApplied(applied));
            });
        });

        element.addEventListener('click', (event) => {
            const chip = event.target.closest ? event.target.closest('[data-chip-value]') : null;

            if (!chip || chip.classList.contains(ACTIVE_CLASS)) {
                return;
            }

            // Optimistic: the chip reflects the click immediately rather than
            // waiting on the grid's AJAX reload, which is the same feel as a
            // native <select> filter's "Apply Filters" click.
            markActive(element, chip.dataset.chipValue);
            applyTypeFilter(ns, chip.dataset.chipValue);
        });
    };
});
