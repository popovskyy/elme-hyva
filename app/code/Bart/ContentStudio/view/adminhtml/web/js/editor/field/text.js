define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, base) {
    'use strict';

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A single-line text input row.
     */
    function render(ctx) {
        const input = dom.el('input', {
            type: 'text',
            class: 'studio-input',
            value: base.asString(ctx.value),
            required: Boolean(ctx.field.required)
        });

        return base.row(ctx, input);
    }

    return {render: render};
});
