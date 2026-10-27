define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/lib/price',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, priceLib, base) {
    'use strict';

    /**
     * Text input that masks to `$3,999` as you type.
     *
     * `data-field-type` stays `text` - SchemaValidator still sees a string.
     * `data-price-format` is what the delegated input listener keys off.
     *
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A single-line text input row.
     */
    const render = (ctx) => {
        const input = dom.el('input', {
            type: 'text',
            class: 'studio-input',
            value: priceLib.format(base.asString(ctx.value)),
            inputmode: 'numeric',
            autocomplete: 'off',
            required: Boolean(ctx.field.required)
        });
        const row = base.row(ctx, input);

        input.dataset.priceFormat = '';

        return row;
    };

    return {render: render};
});
