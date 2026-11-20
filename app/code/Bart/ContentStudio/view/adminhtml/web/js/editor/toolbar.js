/**
 * Builds the editor toolbar's DOM. Wiring (click handlers, keyboard
 * shortcuts) stays in state.js - this module only describes structure, same
 * split as every field renderer (see plan section 3).
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/preview-targets'
], function (dom, previewTargets) {
    'use strict';

    function iconButton(iconName, label, action, extraClass) {
        const classes = 'studio-btn studio-btn--icon studio-btn--ghost' + (extraClass ? ' ' + extraClass : '');
        const attrs = {
            type: 'button',
            class: classes,
            title: label,
            'aria-label': label,
            dataset: {toolbarAction: action}
        };

        return dom.el('button', attrs, dom.icon(iconName));
    }

    /**
     * @param {Object} [options] - `{allowCopy}`. False hides Save & Copy.
     * @returns {Object} References to every button/indicator the toolbar owns.
     */
    function build(options) {
        const allowCopy = !options || options.allowCopy !== false;
        const undoButton = iconButton('undo', 'Undo (\u{2318}Z)', 'undo');
        const redoButton = iconButton('redo', 'Redo (\u{21E7}\u{2318}Z)', 'redo');
        const searchButton = iconButton('search', 'Search fields (/)', 'toggle-search');
        const duplicateButton = iconButton('duplicate', 'Save & Copy', 'duplicate');
        const targetsBuilt = previewTargets.build();
        const dirtyIndicator = dom.el('span', {class: 'studio-toolbar__dirty', hidden: true}, 'Unsaved');
        const saveLabel = dom.el('span', {}, 'Save');
        const saveButton = dom.el(
            'button',
            {type: 'button', class: 'studio-btn studio-btn--primary', dataset: {studioSave: '1'}},
            [dom.icon('save'), saveLabel]
        );

        // Kept in the DOM (hidden) so state.js can keep a stable reference.
        duplicateButton.hidden = !allowCopy;

        const rootError = dom.el('p', {
            class: 'studio-field__error studio-editor__error',
            dataset: {fieldError: '_root'}
        });

        const left = dom.el('div', {class: 'studio-toolbar__group'}, [undoButton, redoButton, searchButton]);
        // "View on site" (targetsBuilt.wrapper) is deliberately not appended
        // here - the Live Preview panel's own "Open Live Preview" button
        // (preview-page.js) opens the same real page now, so this toolbar
        // no longer needs its own separate entry point for it. Still built,
        // not deleted: state.js's wiring (Escape-to-close, the outside-click
        // guard) references targetsBuilt.button/.popover regardless of
        // whether they're mounted, and removing them outright would mean
        // touching every one of those call sites for a feature that may come
        // back as a secondary link inside the popup itself.
        const right = dom.el('div', {class: 'studio-toolbar__group'}, [
            dirtyIndicator,
            duplicateButton,
            saveButton
        ]);

        const toolbar = dom.el('div', {class: 'studio-editor__toolbar'}, [left, rootError, right]);

        return {
            toolbar: toolbar,
            undoButton: undoButton,
            redoButton: redoButton,
            searchButton: searchButton,
            duplicateButton: duplicateButton,
            previewTargetsButton: targetsBuilt.button,
            previewTargetsPopover: targetsBuilt.popover,
            dirtyIndicator: dirtyIndicator,
            saveButton: saveButton,
            saveLabel: saveLabel,
            rootError: rootError
        };
    }

    return {build: build};
});
