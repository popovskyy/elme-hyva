/**
 * Repeater: an array of rows, each shaped like the field's nested `fields`.
 *
 * Reorder is native HTML5 drag & drop via the `[data-drag-handle]` element,
 * with an Alt+Up/Down keyboard fallback - both wired by state.js's delegated
 * listeners (see plan section 3), which is why this module only marks the handle
 * and row wrapper rather than attaching drag listeners itself.
 *
 * Row expand/collapse is local DOM state, not part of the editor's undo-able
 * data: toggling it never triggers a re-render, so nothing is lost by
 * keeping it out of the model.
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/lib/schema',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, schemaLib, base) {
    'use strict';

    function rowLabel(field, row, index) {
        const labelField = field.label_field;
        const value = labelField ? row[labelField] : null;

        return value ? String(value) : (field.label || 'Item') + ' ' + (index + 1);
    }

    function rowButton(path, index, action, label) {
        return dom.el(
            'button',
            {
                type: 'button',
                class: 'studio-btn studio-btn--icon',
                dataset: {action: action, repeaterPath: path, index: String(index)}
            },
            label || ''
        );
    }

    function dragHandle(label) {
        return dom.el(
            'span',
            {class: 'studio-drag-handle', draggable: true, tabindex: '0', dataset: {dragHandle: '1'}, title: label},
            dom.icon('drag')
        );
    }

    function renderRow(ctx, rows, index) {
        const rowValue = rows[index];
        const rowPath = ctx.path + '.' + index;
        const depth = base.childDepth(ctx);
        const body = (ctx.field.fields || []).map(function (childField) {
            return ctx.renderField({
                field: childField,
                value: rowValue[childField.name],
                path: rowPath + '.' + childField.name,
                renderField: ctx.renderField,
                schema: ctx.schema,
                parentValue: rowValue,
                depth: depth
            });
        });

        const header = dom.el('div', {class: 'studio-repeater-row__header', dataset: {toggleRow: rowPath}}, [
            dragHandle('Drag to reorder (or focus + Alt+\u{2191}/\u{2193})'),
            dom.el('span', {class: 'studio-repeater-row__title'}, rowLabel(ctx.field, rowValue, index)),
            dom.el('div', {class: 'studio-repeater-row__actions'}, [
                rowButton(ctx.path, index, 'repeater-duplicate', 'Duplicate'),
                rowButton(ctx.path, index, 'repeater-remove', 'Remove')
            ])
        ]);

        const rowClass = 'studio-repeater-row is-collapsed' + base.depthClass(depth);

        return dom.el('div', {class: rowClass, dataset: {row: rowPath}}, [
            header,
            dom.el('div', {class: 'studio-repeater-row__body'}, body)
        ]);
    }

    /**
     * @param {Object} ctx - Field render context: {field, value, path, renderField, schema}.
     * @returns {Element} The repeater's row list plus an "Add" button.
     */
    function render(ctx) {
        const rows = ctx.value || [];
        const max = ctx.field.max;
        const atMax = typeof max === 'number' && rows.length >= max;

        const rowNodes = rows.map(function (row, index) {
            return renderRow(ctx, rows, index);
        });

        const addLabel = 'Add ' + (ctx.field.label || 'item').replace(/s$/, '');
        const addButton = dom.el(
            'button',
            {
                type: 'button',
                class: 'studio-btn studio-btn--add',
                disabled: atMax,
                dataset: {action: 'repeater-add', repeaterPath: ctx.path}
            },
            addLabel
        );

        return dom.el('div', {class: 'studio-field studio-field--repeater'}, [
            ctx.field.label ? dom.el('span', {class: 'studio-field__label'}, ctx.field.label) : null,
            ctx.field.description ? dom.el('p', {class: 'studio-field__hint'}, ctx.field.description) : null,
            dom.el('div', {class: 'studio-repeater', dataset: {repeater: ctx.path}}, rowNodes),
            addButton
        ]);
    }

    // Walks "Label" -> "Label - copy" -> "Label - copy-2" ... until the result
    // is not already used by a sibling. Without that, Duplicate x2 on the
    // same row minted two identical "Windsor - copy" headers (and two empty
    // keys waiting to be filled with the same value).
    function nextCopyLabel(label, maxlength, taken) {
        const match = String(label || '').match(/^(.*?)(?: - copy(?:-(\d+))?)?$/);
        // `stem`, not `base` - that name now belongs to the field/base module
        // this file imports for its depth helpers.
        const stem = ((match && match[1]) || label || 'Item').replace(/\s+$/, '') || 'Item';
        const used = taken || {};
        let n = 1;

        while (n < 1000) {
            const suffix = n === 1 ? ' - copy' : ' - copy-' + n;
            let candidate = stem + suffix;

            if (maxlength && candidate.length > maxlength) {
                const trimmed = stem.slice(0, Math.max(0, maxlength - suffix.length)).replace(/\s+$/, '');

                candidate = (trimmed || stem.slice(0, 1)) + suffix;
            }

            if (!used[candidate]) {
                return candidate;
            }

            n += 1;
        }

        return stem + ' - copy-' + Date.now();
    }

    function siblingLabels(rows, labelName) {
        const taken = {};

        if (!labelName) {
            return taken;
        }

        rows.forEach(function (row) {
            if (row && row[labelName]) {
                taken[String(row[labelName])] = true;
            }
        });

        return taken;
    }

    function cloneForDuplicate(row, field, siblings) {
        const copy = JSON.parse(JSON.stringify(row));
        const labelName = field && field.label_field;
        const children = (field && field.fields) || [];

        if (labelName && copy[labelName]) {
            const labelSchema = children.filter(function (child) {
                return child.name === labelName;
            })[0];

            copy[labelName] = nextCopyLabel(
                String(copy[labelName]),
                labelSchema && labelSchema.maxlength,
                siblingLabels(siblings, labelName)
            );
        }

        // Readonly keys (range/comfort) must not stay locked to the source -
        // two rows sharing `windsor` would both claim the same product tab.
        children.forEach(function (child) {
            if (child.readonly && child.name !== labelName) {
                copy[child.name] = '';
            }
        });

        return copy;
    }

    /**
     * Applies a structural repeater action to a clone of the field's array
     * value. Pure - callers own committing the result.
     *
     * @param {Array<Object>} rows - Current repeater value.
     * @param {Object} field - The repeater field's own schema definition.
     * @param {string} action - One of repeater-add/remove/duplicate.
     * @param {number} index - The row index the action targets.
     * @returns {Array<Object>} A new array reflecting the action; `rows` is left untouched.
     */
    function applyAction(rows, field, action, index) {
        const next = rows.slice();
        const max = field && field.max;
        const atMax = typeof max === 'number' && next.length >= max;
        const childFields = (field && field.fields) || [];

        if (!field) {
            return next;
        }

        switch (action) {
            case 'repeater-add':
                if (!atMax) {
                    next.push(schemaLib.defaultData(childFields));
                }
                break;
            case 'repeater-remove':
                next.splice(index, 1);
                break;
            case 'repeater-duplicate':
                if (!atMax && next[index]) {
                    next.splice(index + 1, 0, cloneForDuplicate(next[index], field, next));
                }
                break;
            default:
                break;
        }

        return next;
    }

    return {render: render, applyAction: applyAction};
});
