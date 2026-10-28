define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, base) {
    'use strict';

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A checkbox row.
     */
    function render(ctx) {
        const input = dom.el('input', {
            type: 'checkbox',
            class: 'studio-checkbox',
            checked: Boolean(ctx.value)
        });

        return base.row(ctx, input);
    }

    return {render: render};
});
