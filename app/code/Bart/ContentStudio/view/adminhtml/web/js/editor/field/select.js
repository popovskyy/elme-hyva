define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, base) {
    'use strict';

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A select row built from field.options.
     */
    function render(ctx) {
        const options = (ctx.field.options || []).map(function (option) {
            return dom.el('option', {value: option.value, selected: option.value === ctx.value}, option.label);
        });
        const select = dom.el('select', {class: 'studio-input studio-select'}, options);

        return base.row(ctx, select);
    }

    return {render: render};
});
