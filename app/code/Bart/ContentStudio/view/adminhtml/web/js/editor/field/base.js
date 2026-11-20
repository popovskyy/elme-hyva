/**
 * Shared markup/wiring for leaf field renderers (text, textarea, number, ...).
 *
 * Every control gets `data-path` + `data-field-type` so the single delegated
 * listener in editor/render.js can route input/change events without each
 * field module attaching its own listeners (see plan section 3 - event delegation).
 */
define(['Bart_ContentStudio/js/editor/lib/dom'], function (dom) {
    'use strict';

    /**
     * @param {*} value - Any field value.
     * @returns {boolean} Whether the value counts as "not yet set".
     */
    function isEmptyValue(value) {
        return value === null || typeof value === 'undefined' || value === '';
    }

    /**
     * Marks `control` as the editable node for `ctx.path`: the delegated
     * input/change listener in editor/render.js reads both attributes off
     * the actual form control, never off a wrapper.
     *
     * @param {Object} ctx - Field render context, at minimum {field, path}.
     * @param {Element} control - The actual form control (not a wrapper).
     * @returns {Element} The same control, now wired.
     */
    function wireControl(ctx, control) {
        control.id = 'studio-field-label-' + ctx.path.replace(/\./g, '-');
        control.dataset.path = ctx.path;
        control.dataset.fieldType = ctx.field.type;

        // `readonly`, not `disabled`: the value must still be visible and must
        // survive a round trip through the form. These are keys other code
        // matches on, so an accidental edit silently detaches content from
        // whatever points at it. A guard rail against slips, not a security control -
        // the payload is validated server-side regardless.
        //
        // Locked only once it has a value: a row freshly added via "+" has no
        // key yet, and readonly-unconditionally meant there was nowhere to
        // type one in - the only way to get a real key was to copy an
        // existing entry. Typing does not re-render this control (onInput
        // never calls renderFormPanels - only change/blur does), so a key
        // typed here does not lock itself out mid-keystroke; it locks on the
        // next real re-render, i.e. once the user moves on from it.
        if (ctx.field.readonly && !isEmptyValue(ctx.value)) {
            control.readOnly = true;
            control.classList.add('is-readonly');
        }

        // Read back by the delegated input listener to update the counter.
        // Deliberately NOT mirrored onto the element's own `maxlength`
        // attribute: a hard browser cut-off truncates pasted text silently,
        // whereas the counter plus a save-time error says what happened.
        if (ctx.field.maxlength) {
            control.dataset.maxlength = String(ctx.field.maxlength);
        }

        return control;
    }

    /**
     * @param {Object} ctx - Field render context, at minimum {field, path}.
     * @param {Element} content - The control the counter reports on.
     * @returns {Element} A "used / limit" counter, seeded with the current value.
     */
    function counter(ctx, content) {
        const used = typeof content.value === 'string' ? content.value.length : 0;

        return dom.el(
            'span',
            {class: 'studio-field__count', dataset: {countFor: ctx.path}},
            used + ' / ' + ctx.field.maxlength
        );
    }

    /**
     * @param {Object} ctx - Field render context, at minimum {field, path}.
     * @param {Element} content - The control (or wrapper) to place inside the row.
     * @param {string} [labelForId] - Explicit id for the label's `for` attribute, when `content` isn't the control.
     * @returns {Element} The label/content/hint/error row.
     */
    function shell(ctx, content, labelForId) {
        const field = ctx.field;
        const forId = labelForId || content.id || null;
        // `width="half"` in the schema lets short, related fields (an X/Y pair,
        // a label and its link) sit on one row instead of each claiming a full
        // line and stretching the form out vertically.
        const widthClass = field.width === 'half' ? ' studio-field--half' : '';

        // Hint and counter share one line under the control: the guidance and
        // the budget are the same thought, and stacking them pushed every
        // field row taller than the control it describes.
        const foot = field.description || field.maxlength
            ? dom.el('div', {class: 'studio-field__foot'}, [
                field.description ? dom.el('p', {class: 'studio-field__hint'}, field.description) : null,
                field.maxlength ? counter(ctx, content) : null
            ])
            : null;

        return dom.el('div', {class: 'studio-field studio-field--' + field.type + widthClass}, [
            field.label ? dom.el('label', {class: 'studio-field__label', for: forId}, field.label) : null,
            content,
            foot,
            dom.el('p', {class: 'studio-field__error', dataset: {fieldError: ctx.path}})
        ]);
    }

    /**
     * @param {Object} ctx - Field render context, at minimum {field, path}.
     * @param {Element} control - The actual form control.
     * @returns {Element} The wired control wrapped in a row.
     */
    function row(ctx, control) {
        wireControl(ctx, control);

        return shell(ctx, control);
    }

    /**
     * @param {*} value - Any field value.
     * @returns {string} `value` as a string, or '' for null/undefined.
     */
    function asString(value) {
        return value === null || typeof value === 'undefined' ? '' : String(value);
    }

    /**
     * How many distinct nesting tints the stylesheet defines; anything deeper
     * reuses the last one rather than fading back into the panel it sits on.
     */
    const MAX_DEPTH = 3;

    /**
     * Every container (group, repeater row, block) is one step further from the
     * panel it sits on, and until this existed they all painted the same warm
     * off-white - three levels of nesting that were only told apart by their
     * indent. The class drives a per-level surface, border and accent stripe.
     *
     * @param {number} depth - Container nesting level; 0 is a top-level field.
     * @returns {string} A ` studio-depth-N` modifier, clamped to MAX_DEPTH.
     */
    function depthClass(depth) {
        return ' studio-depth-' + Math.min(Math.max(depth || 0, 1), MAX_DEPTH);
    }

    /**
     * @param {Object} ctx - Field render context.
     * @returns {number} The nesting level children of this container render at.
     */
    function childDepth(ctx) {
        return (ctx.depth || 0) + 1;
    }

    return {
        row: row,
        shell: shell,
        wireControl: wireControl,
        asString: asString,
        depthClass: depthClass,
        childDepth: childDepth
    };
});
