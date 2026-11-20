/**
 * Left-hand navigation tree.
 *
 * It deliberately shows *structure*, not every field: containers (group /
 * repeater / blocks) and the individual rows inside them. Listing leaf fields
 * too turned the tree into a second, worse copy of the form - a nested entry
 * listed every title, subtitle and flag before the first thing anyone actually
 * wanted to navigate to. Those fields are already visible in the panel next to
 * it; what is hard to reach is the fortieth row of the third parent.
 *
 * Repeater and blocks rows can also be *reordered* from here, not just from the
 * fields panel - the tree is where the whole list is visible at once, which is
 * the view you actually want when moving the fortieth dot. The drag itself is
 * state.js's (see buildNode); this module only marks the nodes.
 *
 * Expand/collapse rides on native <details>/<summary>, but *which* branch is
 * open is owned by state.js::selectPath (same accordion as the fields panel),
 * so a click in the tree opens the matching rows and moves the live preview.
 * Clicks are handled by state.js's single delegated listener via
 * data-outline-target / data-outline-path (see plan section 3), not wired here.
 *
 * buildFieldNode() takes its own recursive entry point as a parameter
 * (`buildNodes`) rather than referencing a module-level function by name -
 * same device render.js uses for renderField/ctx.renderField, needed here
 * because buildFieldNode and the fields-array mapper recurse into each other.
 */
define(['Bart_ContentStudio/js/editor/lib/dom'], function (dom) {
    'use strict';

    const CONTAINER_TYPES = ['group', 'repeater', 'blocks'];

    function isContainer(field) {
        return CONTAINER_TYPES.indexOf(field.type) !== -1;
    }

    const HINT_TYPES = ['text', 'textarea', 'wysiwyg'];

    // Generous, because the label is no longer only what you can see: buildNode
    // now puts it in the node's `title` so the CSS-ellipsed text stays
    // reachable. Truncating hard here would have the tooltip repeat the same
    // cut and answer nothing. Still bounded - a wysiwyg body is thousands of
    // characters and none of it belongs in a tooltip.
    const HINT_MAX = 120;

    // Tried in this order, before falling back to whatever text field comes
    // first. Declaration order alone picked `alt` for an image ("Placeholder
    // image, replace with...") and `video_id` for a video ("aqz-KE-bpKQ") - both
    // technically the first text field, neither any use for telling two blocks
    // apart in a list.
    const HINT_FIELDS = ['title', 'question', 'quote', 'label', 'caption', 'text', 'body', 'name'];

    const BLOCK_TAGS = /<\/?(?:p|div|br|hr|li|ul|ol|h[1-6]|blockquote|figure|figcaption)\b[^>]*>/gi;

    // Markup in, words out. DOMParser rather than innerHTML, for the reason
    // lib/dom.js gives: a detached node still fires an <img onerror>.
    //
    // Block tags become a space first: textContent alone concatenates across
    // them, so "<h2>Text</h2><p>Body copy" reads back as "TextBody copy".
    function plainText(value) {
        const text = String(value);

        if (text.indexOf('<') === -1) {
            return text;
        }

        const spaced = text.replace(BLOCK_TAGS, ' ');

        return new DOMParser().parseFromString(spaced, 'text/html').body.textContent;
    }

    function hintValue(item, field) {
        const value = field && item[field.name];

        return typeof value === 'string' ? plainText(value).trim().replace(/\s+/g, ' ') : '';
    }

    function findHintField(fields) {
        const textFields = fields.filter(function (field) {
            return HINT_TYPES.indexOf(field.type) !== -1;
        });
        const preferred = HINT_FIELDS.map(function (name) {
            return textFields.filter(function (field) {
                return field.name === name;
            })[0];
        }).filter(Boolean);

        return preferred.concat(textFields);
    }

    // The one line that tells this block apart from the one above it.
    function blockHint(item, blockType) {
        const fields = blockType ? blockType.fields || [] : [];
        let hint = '';

        findHintField(fields).some(function (field) {
            hint = hintValue(item, field);

            return hint !== '';
        });

        return hint.length > HINT_MAX ? hint.slice(0, HINT_MAX - 1).trimEnd() + '\u{2026}' : hint;
    }

    // A `blocks` row is named after the content type it *is* - "Text",
    // "Gallery", "Pull quote" - because every row in that field would otherwise
    // read "Component", and a tree of eleven identical labels tells an author
    // nothing about what they built. The bracketed hint is the first copy in
    // the block, so two Text blocks are still telling apart.
    function blockItemLabel(item, schema) {
        const blockType = schema[item._type];
        const typeLabel = blockType && blockType.label ? blockType.label : item._type || 'Component';
        const hint = blockHint(item, blockType);

        // A block whose heading is its own type name - "Key takeaways (Key
        // takeaways)" - gains nothing from the bracket, so it does not get one.
        if (hint === '' || hint.toLowerCase() === typeLabel.toLowerCase()) {
            return typeLabel;
        }

        return typeLabel + ' (' + hint + ')';
    }

    // The ordinal badge (see buildNode) is what carries a row's position now,
    // so an unlabelled row falls back to the field's own name in the singular -
    // "Layer dot", not "Layer dots 3", which would print the number twice. Same
    // singularisation the repeater's Add button uses.
    function itemLabel(field, item, schema) {
        if (field.type === 'blocks') {
            return blockItemLabel(item, schema);
        }

        const labelField = field.label_field;
        const value = labelField ? item[labelField] : null;

        return value ? String(value) : (field.label || 'Item').replace(/s$/, '');
    }

    function leafSelector(path) {
        return '#studio-field-label-' + path.replace(/\./g, '-');
    }

    function containerSelector(field, path) {
        return field.type === 'blocks' ? '[data-blocks="' + path + '"]' : '[data-repeater="' + path + '"]';
    }

    function rowSelector(field, itemPath) {
        return field.type === 'blocks' ? '[data-block="' + itemPath + '"]' : '[data-row="' + itemPath + '"]';
    }

    // `outlinePath` is the plain data path, kept alongside the CSS selector in
    // `outlineTarget` so state.js can find a node from a path directly - used
    // when selecting a hotspot marker has to light up its row in the tree.
    function nodeAttrs(target, path) {
        return {class: 'studio-outline__link', dataset: {outlineTarget: target, outlinePath: path}};
    }

    // Icons carry the node's kind so the levels are separable at a glance:
    // a folder is a field that holds things, a list is one row that holds more
    // rows, a target is a leaf you can actually go to.
    function nodeBody(label, iconName, ordinal) {
        return [
            dom.icon(iconName, 'studio-outline__icon'),
            ordinal ? dom.el('span', {class: 'studio-outline__ordinal'}, String(ordinal)) : null,
            dom.el('span', {class: 'studio-outline__label'}, label)
        ];
    }

    // `row` is set for repeater/blocks items and null for containers, and marks
    // the two things that only apply to an item:
    //
    //  - it is draggable, carrying its item path in `data-outline-row`. That is
    //    the drag identity state.js already reads off a fields-panel row, so
    //    reordering here goes through the same commit path; the node itself is
    //    the handle because the tree has no room for a separate grip.
    //  - it shows its 1-based position, so "item 3" means the same thing in
    //    the tree and in a template that numbers its rows.
    //
    // Containers get neither: their order is the schema's, not the editor's.
    //
    // A row with nothing nested inside it (a dot, say) is a destination rather
    // than a branch, so it gets a plain button instead of an empty disclosure.
    function buildNode(label, target, path, children, iconName, row) {
        const attrs = nodeAttrs(target, path);

        // Labels are ellipsed - the tree is narrow and can be several levels
        // deep - so the full text has to be reachable
        // somehow, and the tooltip is the only room left. The drag hint rides
        // along on it rather than replacing it: which row this is matters more
        // than how to move it.
        attrs.title = label;

        if (row) {
            attrs.draggable = true;
            attrs.title = label + ' - drag to reorder (or focus + Alt+\u{2191}/\u{2193})';
            attrs.class += ' studio-outline__link--draggable';
            attrs.dataset.outlineRow = row.itemPath;
        }

        const leaf = children.length === 0;
        const body = nodeBody(label, iconName || (leaf ? 'target' : 'list'), row && row.ordinal);

        if (leaf) {
            return dom.el('li', {}, dom.el('button', Object.assign({type: 'button'}, attrs), body));
        }

        // Collapsed by default - opening is driven by selectPath in state.js so
        // the tree, the fields panel and the preview stay on the same branch.
        return dom.el('li', {}, dom.el('details', {}, [
            dom.el('summary', attrs, body),
            dom.el('ul', {}, children)
        ]));
    }

    function buildBlockItemChildren(item, itemPath, schema, buildNodes) {
        const blockType = schema[item._type];

        return blockType ? buildNodes(blockType.fields || [], item, itemPath, schema) : [];
    }

    function buildRepeaterChildren(field, value, path, schema, buildNodes) {
        return (value || []).map(function (item, index) {
            const itemPath = path + '.' + index;
            const children = field.type === 'blocks'
                ? buildBlockItemChildren(item, itemPath, schema, buildNodes)
                : buildNodes(field.fields || [], item, itemPath, schema);

            const iconName = field.type === 'blocks' && schema[item._type] && schema[item._type].icon
                ? schema[item._type].icon
                : (children.length === 0 ? 'target' : 'list');

            return buildNode(
                itemLabel(field, item, schema),
                rowSelector(field, itemPath),
                itemPath,
                children,
                iconName,
                {itemPath: itemPath, ordinal: index + 1}
            );
        });
    }

    function buildFieldNode(field, data, path, schema, buildNodes) {
        if (!isContainer(field)) {
            return null;
        }

        const value = data[field.name];
        const fieldPath = path === '' ? field.name : path + '.' + field.name;

        if (field.type === 'group') {
            return buildNode(
                field.label || field.name,
                leafSelector(fieldPath),
                fieldPath,
                buildNodes(field.fields || [], value || {}, fieldPath, schema),
                'folder'
            );
        }

        return buildNode(
            field.label || field.name,
            containerSelector(field, fieldPath),
            fieldPath,
            buildRepeaterChildren(field, value, fieldPath, schema, buildNodes),
            'folder'
        );
    }

    function buildFieldNodes(fields, data, path, schema) {
        return fields
            .map(function (field) {
                return buildFieldNode(field, data, path, schema, buildFieldNodes);
            })
            .filter(Boolean);
    }

    /**
     * @param {Element} container - The outline pane to render into.
     * @param {Array<Object>} fields - The type's top-level field definitions.
     * @param {Object} data - Current entry data.
     * @param {Object} schema - The full content-type registry (for blocks item resolution).
     * @returns {void} Replaces `container`'s contents with the outline tree.
     */
    function render(container, fields, data, schema) {
        const nodes = buildFieldNodes(fields, data, '', schema);

        dom.empty(container);

        if (nodes.length === 0) {
            container.appendChild(
                dom.el('p', {class: 'studio-outline__empty'}, 'This content type is a flat list of fields.')
            );

            return;
        }

        container.appendChild(dom.el('ul', {class: 'studio-outline__list'}, nodes));
    }

    // Everything from the match up to the root has to stay visible, or the
    // hit is hidden inside a collapsed ancestor. Only those ancestors open -
    // forcing every <details> open made a search dump the whole tree again.
    function revealMatch(link) {
        const matched = link.closest('li');

        matched.querySelectorAll('li').forEach(function (child) {
            child.hidden = false;
        });

        let node = matched;

        while (node) {
            node.hidden = false;
            node = node.parentElement ? node.parentElement.closest('li') : null;
        }

        let details = link.closest('details');

        while (details) {
            details.open = true;
            details = details.parentElement ? details.parentElement.closest('details') : null;
        }
    }

    /**
     * @param {Element} container - The outline pane.
     * @param {string} query - Free text; empty restores the whole tree.
     * @returns {void} Hides tree nodes whose label does not match.
     */
    function filter(container, query) {
        const needle = String(query || '').trim().toLowerCase();
        const items = container.querySelectorAll('li');

        items.forEach(function (item) {
            item.hidden = needle !== '';
        });

        if (needle === '') {
            return;
        }

        container.querySelectorAll('.studio-outline__link').forEach(function (link) {
            if (link.textContent.toLowerCase().indexOf(needle) !== -1) {
                revealMatch(link);
            }
        });
    }

    return {render: render, filter: filter};
});
