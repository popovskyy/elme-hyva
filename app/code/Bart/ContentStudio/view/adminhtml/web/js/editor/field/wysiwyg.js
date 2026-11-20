/**
 * Rich body copy, step one: an HTML source textarea with a tag-wrapping
 * toolbar.
 *
 * The stored value is HTML, which is also what the TinyMCE-backed version of
 * this field will store - so swapping the control later is an editor change
 * with no data migration behind it. That is the whole reason this exists as its
 * own step rather than waiting: everything downstream of the field (content
 * types, the projector, the storefront templates) can be built and shipped
 * against the final storage format today.
 *
 * What the author types is never trusted. `Model\Schema\HtmlSanitizer` reduces
 * it to an allow-list on save, so a pasted `<script>` is gone before it reaches
 * the database regardless of what this control does or does not permit.
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, base) {
    'use strict';

    // Only tags HtmlSanitizer keeps. Offering a button for anything it strips
    // would teach the author a formatting move that silently vanishes on save.
    const TOOLS = [
        {label: 'B', title: 'Bold', open: '<strong>', close: '</strong>'},
        {label: 'I', title: 'Italic', open: '<em>', close: '</em>'},
        {label: 'H2', title: 'Heading 2', open: '<h2>', close: '</h2>'},
        {label: 'H3', title: 'Heading 3', open: '<h3>', close: '</h3>'},
        {label: '\u{00B6}', title: 'Paragraph', open: '<p>', close: '</p>'},
        {label: '\u{201C}\u{201D}', title: 'Quote', open: '<blockquote>', close: '</blockquote>'},
        {label: '\u{2022} List', title: 'Bulleted list', open: '<ul>\n<li>', close: '</li>\n</ul>'},
        {label: '1. List', title: 'Numbered list', open: '<ol>\n<li>', close: '</li>\n</ol>'},
        {label: 'Link', title: 'Link', open: '<a href="/">', close: '</a>'}
    ];

    function toolButton(tool) {
        return dom.el(
            'button',
            {
                type: 'button',
                class: 'studio-btn studio-btn--secondary studio-wysiwyg__tool',
                title: tool.title,
                dataset: {action: 'wysiwyg-wrap', wrapOpen: tool.open, wrapClose: tool.close}
            },
            tool.label
        );
    }

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A toolbar above an HTML source textarea.
     */
    function render(ctx) {
        const area = dom.el('textarea', {
            class: 'studio-input studio-input--textarea studio-wysiwyg__area',
            rows: 12
        });

        // Assigned rather than passed as a child, matching field/textarea.js:
        // a textarea's child text node is its *default* value, and the two
        // diverge the moment anything else touches the control.
        area.value = base.asString(ctx.value);
        base.wireControl(ctx, area);
        const toolbar = dom.el('div', {class: 'studio-wysiwyg__toolbar'}, TOOLS.map(toolButton));
        const wrapper = dom.el('div', {class: 'studio-wysiwyg'}, [toolbar, area]);

        return base.shell(ctx, wrapper, area.id);
    }

    return {render: render};
});
