/**
 * Minimal DOM builder. Uses element APIs (not innerHTML string concatenation)
 * throughout the editor so user-entered content is never parsed as markup.
 */
define([], function () {
    'use strict';

    const BOOLEAN_ATTRS = ['disabled', 'required', 'checked', 'hidden', 'readonly'];

    function isNil(value) {
        return value === null || typeof value === 'undefined';
    }

    function applyAttr(node, name, value) {
        if (isNil(value) || value === false) {
            return;
        }

        if (name === 'class') {
            node.className = value;
        } else if (name === 'dataset') {
            Object.keys(value).forEach(function (key) {
                node.dataset[key] = value[key];
            });
        } else if (name.indexOf('on') === 0 && typeof value === 'function') {
            node.addEventListener(name.slice(2).toLowerCase(), value);
        } else if (BOOLEAN_ATTRS.indexOf(name) !== -1) {
            node.setAttribute(name, name);
        } else {
            node.setAttribute(name, value);
        }
    }

    function appendChild(node, child) {
        if (isNil(child) || child === false) {
            return;
        }

        if (Array.isArray(child)) {
            child.forEach(function (grandchild) {
                appendChild(node, grandchild);
            });
        } else if (child instanceof Node) {
            node.appendChild(child);
        } else {
            node.appendChild(document.createTextNode(String(child)));
        }
    }

    /**
     * @param {string} tag - The element tag name to create.
     * @param {Object<string, *>} [attrs] - Attributes; `class`/`dataset`/`on*`/boolean attrs are special-cased.
     * @param {*} [children] - A node, string, nested array, or falsy value to skip.
     * @returns {Element} The built element.
     */
    function el(tag, attrs, children) {
        const node = document.createElement(tag);

        Object.keys(attrs || {}).forEach(function (name) {
            applyAttr(node, name, attrs[name]);
        });
        appendChild(node, children);

        return node;
    }

    /**
     * @param {Element} node - The element to clear.
     * @returns {void} Removes all of `node`'s children.
     */
    function empty(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    const SVG_NS = 'http://www.w3.org/2000/svg';

    /**
     * References a `<symbol>` from the sprite in top-bar.phtml - SVG elements
     * need createElementNS, not createElement, so this is separate from el().
     *
     * @param {string} name - Symbol id suffix, e.g. "save" for #studio-icon-save.
     * @param {string} [extraClass] - Additional class(es) alongside the base "studio-icon".
     * @returns {Element} An <svg><use></svg> referencing the symbol.
     */
    function icon(name, extraClass) {
        const svg = document.createElementNS(SVG_NS, 'svg');
        const use = document.createElementNS(SVG_NS, 'use');

        svg.setAttribute('class', extraClass ? 'studio-icon ' + extraClass : 'studio-icon');
        svg.setAttribute('aria-hidden', 'true');
        use.setAttribute('href', '#studio-icon-' + name);
        svg.appendChild(use);

        return svg;
    }

    return {
        el: el,
        empty: empty,
        icon: icon
    };
});
