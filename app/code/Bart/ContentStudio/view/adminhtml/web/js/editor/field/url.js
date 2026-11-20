define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, base) {
    'use strict';

    // "#" alone is allowed as a "no destination yet" placeholder. Must stay
    // character-for-character equivalent to
    // Bart_ContentStudio\Model\Schema\SchemaValidator::SAFE_URL_PATTERN -
    // including the (?![/\\]) after the slash, which is what rejects the
    // protocol-relative "//evil.com" and "/\evil.com". Without it this field
    // showed no inline error and the save then failed on the server instead.
    const SAFE_URL_PATTERN = /^(#|\/(?![/\\])|https?:\/\/)/i;

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A URL input row with inline validation against SAFE_URL_PATTERN.
     */
    function render(ctx) {
        const value = base.asString(ctx.value);
        const input = dom.el('input', {
            type: 'text',
            class: 'studio-input',
            value: value,
            placeholder: '/ or https://...',
            required: Boolean(ctx.field.required)
        });

        const container = base.row(ctx, input);

        if (value !== '' && !SAFE_URL_PATTERN.test(value)) {
            container.querySelector('[data-field-error]').textContent =
                'Links must be "#", or start with "/", "http://" or "https://".';
        }

        return container;
    }

    return {render: render, SAFE_URL_PATTERN: SAFE_URL_PATTERN};
});
