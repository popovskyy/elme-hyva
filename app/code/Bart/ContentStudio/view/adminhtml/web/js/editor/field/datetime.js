define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/lib/datetime',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, datetimeLib, base) {
    'use strict';

    // "Now" is the single most common value for a publication date and the most
    // tedious to type. It dispatches the same studio:field-change every other
    // non-input mutation uses, so it lands as one undo step.
    function nowButton(path) {
        return dom.el(
            'button',
            {
                type: 'button',
                class: 'studio-btn studio-btn--secondary studio-datetime__now',
                dataset: {action: 'datetime-now', datetimePath: path}
            },
            'Now'
        );
    }

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A datetime-local input paired with a "Now" shortcut.
     */
    function render(ctx) {
        const input = base.wireControl(ctx, dom.el('input', {
            type: 'datetime-local',
            class: 'studio-input studio-datetime__input',
            value: datetimeLib.toInputValue(ctx.value),
            required: Boolean(ctx.field.required)
        }));
        const wrapper = dom.el('div', {class: 'studio-datetime'}, [input, nowButton(ctx.path)]);

        return base.shell(ctx, wrapper, input.id);
    }

    return {render: render};
});
