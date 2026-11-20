define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, base) {
    'use strict';

    // Must stay character-for-character equivalent to SAFE_COLOR_PATTERN in
    // Bart_ContentStudio\Model\Schema\SchemaValidator. A value
    // this accepts but the server rejects shows no inline error and then fails
    // the save - the same trap field/url.js documents.
    const HEX_COLOR = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;

    // What the native swatch falls back to when the field is empty or holds
    // something unparseable. The control cannot represent "no colour" - it
    // always reports one - so emptiness lives in the text input alone, and
    // clearing that is how an editor turns the panel off.
    const SWATCH_FALLBACK = '#ffffff';

    // `<input type="color">` only accepts the 6-digit form, so #abc has to be
    // doubled up before it will show. The text input keeps whichever form was
    // typed; only the swatch sees this.
    function toSwatchValue(value) {
        if (!HEX_COLOR.test(value)) {
            return SWATCH_FALLBACK;
        }

        if (value.length !== 4) {
            return value.toLowerCase();
        }

        return ('#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3]).toLowerCase();
    }

    /**
     * A hex text input plus a native colour swatch.
     *
     * Both controls carry `data-path`, so the editor's one delegated listener
     * treats either as the field's control and no listener is attached here -
     * per-element listeners leak, because a repeater re-renders its rows
     * constantly. The swatch fires `input` while the picker is open, so the
     * live preview follows the drag, and `change` when it closes, which is the
     * re-render that pulls the text input back into sync.
     *
     * Native rather than Magento's colour picker on purpose: that one is a
     * jQuery UI widget built for `ui_component` forms, and this editor is
     * plain ES6 DOM with no jQuery in it.
     *
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A colour row with inline validation against HEX_COLOR.
     */
    function render(ctx) {
        const value = base.asString(ctx.value);
        const input = base.wireControl(ctx, dom.el('input', {
            type: 'text',
            class: 'studio-input',
            value: value,
            placeholder: '#ab9377',
            spellcheck: 'false',
            required: Boolean(ctx.field.required)
        }));
        const swatch = dom.el('input', {
            type: 'color',
            class: 'studio-color-swatch',
            value: toSwatchValue(value),
            'aria-label': 'Pick a colour for ' + (ctx.field.label || ctx.field.name),
            dataset: {path: ctx.path, fieldType: 'color'}
        });
        const wrapper = dom.el('div', {class: 'studio-color-field'}, [swatch, input]);

        const container = base.shell(ctx, wrapper, input.id);

        if (value !== '' && !HEX_COLOR.test(value)) {
            container.querySelector('[data-field-error]').textContent =
                'Enter a colour as a hex value, for example #ab9377.';
        }

        return container;
    }

    /**
     * Mirrors whichever half of the pair just changed onto the other.
     *
     * Called from state.js's one delegated input listener rather than from a
     * listener here, for the same reason nothing else in a field renderer
     * binds events: repeater rows re-render constantly and per-element
     * listeners leak. A leaf edit does not re-render its own row, so without
     * this, picking from the swatch left the hex box showing the old value.
     *
     * @param {Element} control - Either control inside a .studio-color-field.
     * @returns {void} Mutates the sibling control's value in place.
     */
    function syncPair(control) {
        const wrapper = control.closest ? control.closest('.studio-color-field') : null;

        if (!wrapper) {
            return;
        }

        const swatch = wrapper.querySelector('.studio-color-swatch');
        const text = wrapper.querySelector('.studio-input');

        if (control === swatch) {
            text.value = swatch.value;

            return;
        }

        swatch.value = toSwatchValue(text.value);
    }

    return {render: render, syncPair: syncPair, HEX_COLOR: HEX_COLOR};
});
