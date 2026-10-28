/**
 * A `group` field is a nested object with its own field list - no value of
 * its own, just a namespace. Recurses back into the dispatcher passed on
 * `ctx.renderField` rather than requiring editor/render.js directly, which
 * would create an AMD dependency cycle (render.js requires this module too).
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, base) {
    'use strict';

    /**
     * @param {Object} ctx - Field render context: {field, value, path, renderField, schema}.
     * @returns {Element} A fieldset containing the group's nested fields.
     */
    function render(ctx) {
        const value = ctx.value || {};
        const depth = base.childDepth(ctx);
        const children = (ctx.field.fields || []).map(function (childField) {
            return ctx.renderField({
                field: childField,
                value: value[childField.name],
                path: ctx.path + '.' + childField.name,
                renderField: ctx.renderField,
                schema: ctx.schema,
                parentValue: value,
                depth: depth
            });
        });

        return dom.el('fieldset', {class: 'studio-field studio-field--group' + base.depthClass(depth)}, [
            ctx.field.label ? dom.el('legend', {class: 'studio-field__label'}, ctx.field.label) : null,
            dom.el('div', {class: 'studio-field__group-body'}, children)
        ]);
    }

    return {render: render};
});
