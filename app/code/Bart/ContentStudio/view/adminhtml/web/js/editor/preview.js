/**
 * Live preview driver. Prefers the content type's own `renderer` module and
 * falls back to a generic structural summary for types that don't declare one
 * - every Elme type today. The storefront look of those is the Live Preview
 * window (preview-page.js), which reloads the sandbox page on every edit.
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom'
], function (dom) {
    'use strict';

    // Bumps on every renderPreview call so a slow require() callback from an
    // earlier keystroke cannot overwrite a newer preview.
    //
    // Kept per container, not per module: there is more than one preview pane
    // now (the editor's side panel and the pre-save review stage), and a shared
    // counter meant a render into one silently cancelled an in-flight render
    // into the other - the review stage would come up blank whenever a
    // debounced editor update landed while it was still resolving its
    // renderer.
    function nextGeneration(container) {
        container._csPreviewGeneration = (container._csPreviewGeneration || 0) + 1;

        return container._csPreviewGeneration;
    }

    function summarizeLeaf(field, value) {
        if (field.type === 'boolean') {
            return value ? 'Yes' : 'No';
        }

        if (field.type === 'reference_multi') {
            return Array.isArray(value) ? value.join(', ') : String(value);
        }

        if (field.type === 'datetime') {
            const date = new Date(value);

            return isNaN(date.getTime()) ? String(value) : date.toLocaleString();
        }

        // The stored value is markup; a summary row wants the words. DOMParser,
        // not innerHTML on a detached div: a detached node still attempts an
        // <img src> and still fires its onerror, so that route would execute
        // exactly the payload this editor otherwise never parses as markup.
        if (field.type === 'wysiwyg') {
            const parsed = new DOMParser().parseFromString(String(value), 'text/html');

            return parsed.body.textContent.trim();
        }

        return String(value);
    }

    function summarizeField(field, value) {
        const isEmpty = value === null || typeof value === 'undefined' || value === '' ||
            (Array.isArray(value) && value.length === 0);

        if (field.type === 'image') {
            return isEmpty ? null : {label: field.label || field.name, kind: 'image', value: value};
        }

        if (field.type === 'repeater' || field.type === 'blocks') {
            const count = Array.isArray(value) ? value.length : 0;

            return {label: field.label || field.name, kind: 'text', value: count + ' item(s)'};
        }

        if (field.type === 'group' || isEmpty) {
            return null;
        }

        return {label: field.label || field.name, kind: 'text', value: summarizeLeaf(field, value)};
    }

    function buildSummaryRow(summary) {
        const valueNode = summary.kind === 'image'
            ? dom.el('img', {class: 'studio-preview-fallback__thumb', src: summary.value, alt: ''})
            : dom.el('span', {class: 'studio-preview-fallback__value'}, summary.value);

        return dom.el('div', {class: 'studio-preview-fallback__row'}, [
            dom.el('span', {class: 'studio-preview-fallback__label'}, summary.label),
            valueNode
        ]);
    }

    function renderFallback(container, type, data) {
        const rows = (type.fields || [])
            .map(function (field) {
                return summarizeField(field, data[field.name]);
            })
            .filter(Boolean)
            .map(buildSummaryRow);

        dom.empty(container);
        container.appendChild(dom.el('div', {class: 'studio-preview-fallback'}, [
            dom.el('div', {class: 'studio-preview-fallback__header'}, [
                dom.el('span', {class: 'studio-preview-fallback__type'}, type.label || 'Content'),
                dom.el('span', {class: 'studio-preview-fallback__note'}, 'Summary only. Open Live Preview to see the storefront.')
            ]),
            dom.el('div', {class: 'studio-preview-fallback__body'}, rows)
        ]));
    }

    /**
     * @param {Element} container - Preview pane to render into.
     * @param {Object} schema - The full content-type registry.
     * @param {string} typeCode - The entry's content type.
     * @param {Object} data - Current entry data.
     * @returns {void} Renders via the type's own renderer, or the structural fallback.
     */
    function renderPreview(container, schema, typeCode, data) {
        const type = schema[typeCode];

        if (!type) {
            dom.empty(container);
            container.appendChild(dom.el('p', {class: 'studio-preview-fallback__note'}, 'Unknown content type.'));
            return;
        }

        if (!type.renderer) {
            renderFallback(container, type, data);
            return;
        }

        const generation = nextGeneration(container);

        require([type.renderer], function (renderModule) {
            if (generation !== container._csPreviewGeneration) {
                return;
            }

            if (!renderModule.render(container, data)) {
                renderFallback(container, type, data);
            }
        }, function () {
            if (generation !== container._csPreviewGeneration) {
                return;
            }

            renderFallback(container, type, data);
        });
    }

    return {renderPreview: renderPreview};
});
