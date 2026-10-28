/**
 * Field-type dispatcher. Builds the FIELDS panel DOM from the type's field
 * list and the current data object. Every nested renderer (group, repeater,
 * blocks, hotspot) receives this module's `renderField` back via `ctx` so it
 * can recurse without requiring editor/render.js itself - see plan section 3 for
 * why (AMD cycle avoidance) and field/group.js's doc comment for the detail.
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/text',
    'Bart_ContentStudio/js/editor/field/textarea',
    'Bart_ContentStudio/js/editor/field/number',
    'Bart_ContentStudio/js/editor/field/boolean',
    'Bart_ContentStudio/js/editor/field/select',
    'Bart_ContentStudio/js/editor/field/url',
    'Bart_ContentStudio/js/editor/field/image',
    'Bart_ContentStudio/js/editor/field/color',
    'Bart_ContentStudio/js/editor/field/datetime',
    'Bart_ContentStudio/js/editor/field/reference',
    'Bart_ContentStudio/js/editor/field/reference-multi',
    'Bart_ContentStudio/js/editor/field/wysiwyg',
    'Bart_ContentStudio/js/editor/field/group',
    'Bart_ContentStudio/js/editor/field/repeater',
    'Bart_ContentStudio/js/editor/field/blocks',
    'Bart_ContentStudio/js/editor/field/hotspot',
    'Bart_ContentStudio/js/editor/field/price'
], function (
    dom, text, textarea, number, boolean_, select, url, image,
    color, datetime, reference, referenceMulti, wysiwyg, group, repeater, blocks, hotspot,
    price
) {
    'use strict';

    const RENDERERS_BY_TYPE = {
        text: text,
        textarea: textarea,
        wysiwyg: wysiwyg,
        number: number,
        boolean: boolean_,
        select: select,
        url: url,
        image: image,
        color: color,
        icon: text,
        datetime: datetime,
        reference: reference,
        reference_multi: referenceMulti,
        group: group,
        repeater: repeater,
        blocks: blocks
    };

    const RENDERERS_BY_NAME = {
        hotspot: hotspot,
        price: price
    };

    /**
     * @param {Object} ctx - Field render context: {field, value, path, schema, parentValue, renderField}.
     * @returns {Element} The rendered field row.
     */
    function renderField(ctx) {
        const module = RENDERERS_BY_NAME[ctx.field.renderer] || RENDERERS_BY_TYPE[ctx.field.type];

        if (!module) {
            const message = 'Unsupported field: ' + ctx.field.type;

            return dom.el('p', {class: 'studio-field studio-field--unsupported'}, message);
        }

        return module.render(Object.assign({}, ctx, {renderField: renderField}));
    }

    function renderFieldList(fields, data, schema) {
        return (fields || []).map(function (field) {
            return renderField({
                field: field,
                value: data[field.name],
                path: field.name,
                renderField: renderField,
                schema: schema,
                parentValue: data
            });
        });
    }

    /**
     * Replaces `container`'s contents with the rendered fields for `data`.
     *
     * @param {Element} container - The fields panel element to render into.
     * @param {Array<Object>} fields - The type's top-level field definitions.
     * @param {Object} data - Current data object for those fields.
     * @param {Object} schema - The full content-type registry (for group/repeater/blocks recursion).
     * @returns {void} Mutates `container` in place.
     */
    function renderFields(container, fields, data, schema) {
        dom.empty(container);
        renderFieldList(fields, data, schema).forEach(function (node) {
            container.appendChild(node);
        });
    }

    return {
        renderField: renderField,
        renderFields: renderFields
    };
});
