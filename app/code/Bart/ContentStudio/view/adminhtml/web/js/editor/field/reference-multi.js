/**
 * Chips for what is already selected, plus a select that only ever offers what
 * is not.
 *
 * Both the add and the remove go through state.js - the select is marked
 * `data-reference-add` for the delegated `change` listener, the remove buttons
 * are ordinary `data-action` structural actions - so neither attaches a
 * listener here. Rows are rebuilt on every commit, and per-element listeners on
 * a re-rendered list leak.
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/lib/reference-options',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, referenceOptions, base) {
    'use strict';

    function selected(ctx) {
        return Array.isArray(ctx.value) ? ctx.value : [];
    }

    function chip(ctx, identifier, index) {
        return dom.el('li', {class: 'studio-chip'}, [
            dom.el('span', {class: 'studio-chip__label'}, referenceOptions.labelFor(ctx.field, identifier)),
            dom.el(
                'button',
                {
                    type: 'button',
                    class: 'studio-chip__remove',
                    'aria-label': 'Remove',
                    dataset: {
                        action: 'reference-remove',
                        referencePath: ctx.path,
                        index: String(index)
                    }
                },
                '\u{00D7}'
            )
        ]);
    }

    // The picker resets itself to the placeholder after every pick, so the
    // control always reads as "add another" rather than as a value it holds.
    function picker(ctx, chosen) {
        const remaining = referenceOptions.forField(ctx.field).filter(function (option) {
            return chosen.indexOf(option.value) === -1;
        });
        const max = Number(ctx.field.max || 0);
        const full = max > 0 && chosen.length >= max;
        const options = remaining.map(function (option) {
            return dom.el('option', {value: option.value}, option.label);
        });

        options.unshift(dom.el('option', {value: '', selected: true}, full ? 'Limit reached' : 'Add...'));

        return dom.el('select', {
            class: 'studio-input studio-select studio-reference-multi__add',
            disabled: full || remaining.length === 0,
            dataset: {referenceAdd: ctx.path}
        }, options);
    }

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A chip list of selected entries plus an "Add..." picker.
     */
    function render(ctx) {
        const chosen = selected(ctx);
        const chips = dom.el('ul', {class: 'studio-chips'}, chosen.map(function (identifier, index) {
            return chip(ctx, identifier, index);
        }));
        const wrapper = dom.el('div', {class: 'studio-reference-multi'}, [
            chosen.length ? chips : null,
            picker(ctx, chosen)
        ]);

        return base.shell(ctx, wrapper);
    }

    return {render: render};
});
