define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, base) {
    'use strict';

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A multi-line text input row.
     */
    function render(ctx) {
        const textarea = dom.el('textarea', {
            class: 'studio-input studio-input--textarea',
            rows: 3,
            required: Boolean(ctx.field.required)
        });

        textarea.value = base.asString(ctx.value);

        return base.row(ctx, textarea);
    }

    return {render: render};
});
