/**
 * `renderer="hotspot"` on a repeater field: a list of points on an image, each
 * row carrying its own X/Y position fields (percent of the image frame).
 *
 * The rows are a plain repeater; this only adds a hint above them. Dragging
 * markers on the preview image is not available: the drag layer worked only
 * on one storefront type's preview markup and was removed with it.
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/repeater'
], function (dom, repeaterField) {
    'use strict';

    function renderHint() {
        return dom.el(
            'p',
            {class: 'studio-hotspot__hint'},
            'Set each point by its X and Y position, in percent of the image from its top-left corner.'
        );
    }

    /**
     * @param {Object} ctx - Field render context for the `dots` repeater field.
     * @returns {Element} A hint about where positioning happens, then the row list.
     */
    function render(ctx) {
        return dom.el('div', {class: 'studio-field studio-field--hotspot'}, [
            renderHint(),
            repeaterField.render(ctx)
        ]);
    }

    return {render: render};
});
