define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, base) {
    'use strict';

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A numeric input row.
     */
    function render(ctx) {
        const isEmpty = ctx.value === null || typeof ctx.value === 'undefined';
        const input = dom.el('input', {
            type: 'number',
            class: 'studio-input',
            value: isEmpty ? '' : ctx.value,
            min: ctx.field.min,
            max: ctx.field.max,
            step: 'any',
            required: Boolean(ctx.field.required)
        });

        return base.row(ctx, input);
    }

    return {render: render};
});
