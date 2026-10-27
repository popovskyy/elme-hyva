/**
 * Flexible content: an array of `{ _type, ...fields }`, each item's own
 * shape driven by its `_type` entry in the schema rather than by this
 * field's own definition (see plan section 1, "Flexible content - for phase 2").
 *
 * Reorder is native drag & drop via the `[data-drag-handle]` element, with
 * an Alt+Up/Down keyboard fallback - both wired in state.js, same as
 * field/repeater.js.
 *
 * Expand/collapse is local DOM state, not part of the undo-able data:
 * toggling it never triggers a re-render. Rows start collapsed so a post
 * with a dozen components is a list of headers, not a wall of fields.
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/lib/schema',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, schemaLib, base) {
    'use strict';

    function rowButton(path, index, action, label) {
        return dom.el(
            'button',
            {
                type: 'button',
                class: 'studio-btn studio-btn--icon',
                dataset: {action: action, blocksPath: path, index: String(index)}
            },
            label
        );
    }

    function dragHandle(label) {
        return dom.el(
            'span',
            {class: 'studio-drag-handle', draggable: true, tabindex: '0', dataset: {dragHandle: '1'}, title: label},
            dom.icon('drag')
        );
    }

    function renderBlock(ctx, item, index) {
        const type = schemaLib.getType(ctx.schema, item._type);
        const itemPath = ctx.path + '.' + index;

        if (!type) {
            return dom.el('div', {class: 'studio-block studio-block--unknown'}, 'Unknown block type: ' + item._type);
        }

        const depth = base.childDepth(ctx);
        const body = (type.fields || []).map(function (childField) {
            return ctx.renderField({
                field: childField,
                value: item[childField.name],
                path: itemPath + '.' + childField.name,
                renderField: ctx.renderField,
                schema: ctx.schema,
                parentValue: item,
                depth: depth
            });
        });

        const header = dom.el('div', {class: 'studio-block__header', dataset: {toggleRow: itemPath}}, [
            dragHandle('Drag to reorder (or focus + Alt+\u{2191}/\u{2193})'),
            type.icon ? dom.icon(type.icon, 'studio-block__type-icon') : null,
            dom.el('span', {class: 'studio-block__title'}, type.label || item._type),
            dom.el('div', {class: 'studio-block__actions'}, [
                rowButton(ctx.path, index, 'blocks-duplicate', 'Duplicate'),
                rowButton(ctx.path, index, 'blocks-remove', 'Remove')
            ])
        ]);

        return dom.el(
            'div',
            {class: 'studio-block is-collapsed' + base.depthClass(depth), dataset: {block: itemPath}},
            [header, dom.el('div', {class: 'studio-block__body'}, body)]
        );
    }

    function renderPicker(ctx) {
        const groups = schemaLib.allowedTypesByGroup(ctx.schema, ctx.field.allow);

        const groupNodes = Object.keys(groups).map(function (groupLabel) {
            const cards = groups[groupLabel].map(function (type) {
                return dom.el(
                    'button',
                    {
                        type: 'button',
                        class: 'studio-block-picker__card',
                        dataset: {action: 'blocks-add-type', blocksPath: ctx.path, typeCode: type.code}
                    },
                    [
                        dom.icon(type.icon || 'list', 'studio-block-picker__icon'),
                        type.label
                    ]
                );
            });

            return dom.el('div', {class: 'studio-block-picker__group'}, [
                dom.el('h4', {class: 'studio-block-picker__group-label'}, groupLabel),
                dom.el('div', {class: 'studio-block-picker__cards'}, cards)
            ]);
        });

        return dom.el(
            'div',
            {class: 'studio-block-picker', hidden: true, dataset: {blockPicker: ctx.path}},
            groupNodes
        );
    }

    /**
     * @param {Object} ctx - Field render context: {field, value, path, renderField, schema}.
     * @returns {Element} The block list, an "Add block" toggle, and the (initially hidden) type picker.
     */
    function render(ctx) {
        const items = ctx.value || [];
        const blockNodes = items.map(function (item, index) {
            return renderBlock(ctx, item, index);
        });

        const addButtonAttrs = {
            type: 'button',
            class: 'studio-btn studio-btn--add',
            dataset: {action: 'blocks-toggle-picker', blocksPath: ctx.path}
        };
        const addButton = dom.el('button', addButtonAttrs, 'Add block');

        return dom.el('div', {class: 'studio-field studio-field--blocks'}, [
            ctx.field.label ? dom.el('span', {class: 'studio-field__label'}, ctx.field.label) : null,
            dom.el('div', {class: 'studio-blocks', dataset: {blocks: ctx.path}}, blockNodes),
            addButton,
            renderPicker(ctx)
        ]);
    }

    /**
     * @param {Array<Object>} items - Current blocks value.
     * @param {string} action - One of blocks-add-type/remove/duplicate.
     * @param {number} index - The block index the action targets.
     * @param {string} [typeCode] - The content type to add, for blocks-add-type.
     * @param {Object} [schema] - The full content-type registry, for blocks-add-type's defaults.
     * @returns {Array<Object>} A new array reflecting the action; `items` is left untouched.
     */
    function applyAction(items, action, index, typeCode, schema) {
        const next = items.slice();

        switch (action) {
            case 'blocks-add-type': {
                const type = schemaLib.getType(schema || {}, typeCode) || {fields: []};

                next.push(Object.assign({_type: typeCode}, schemaLib.defaultData(type.fields)));
                break;
            }
            case 'blocks-remove':
                next.splice(index, 1);
                break;
            case 'blocks-duplicate':
                next.splice(index + 1, 0, JSON.parse(JSON.stringify(next[index])));
                break;
            default:
                break;
        }

        return next;
    }

    return {render: render, applyAction: applyAction};
});
