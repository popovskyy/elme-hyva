/**
 * Dot/index path helpers over plain JSON-safe data, e.g. "ranges.0.comforts.1.label".
 */
define([], function () {
    'use strict';

    function isNil(value) {
        return value === null || typeof value === 'undefined';
    }

    function segments(path) {
        return path === '' ? [] : path.split('.');
    }

    /**
     * @param {*} data - The root object/array to read from.
     * @param {string} path - A dot/index path, e.g. "ranges.0.label".
     * @returns {*} The value at `path`, or undefined if any segment is missing.
     */
    function getByPath(data, path) {
        return segments(path).reduce(function (node, key) {
            if (isNil(node)) {
                return null;
            }
            return node[key];
        }, data);
    }

    /**
     * Mutates `data` in place, creating intermediate arrays/objects as needed.
     * Array segments are numeric strings; a missing intermediate node is
     * created as an array when the next segment is numeric, else an object.
     *
     * @param {*} data - The root object/array to mutate.
     * @param {string} path - A dot/index path, e.g. "ranges.0.label".
     * @param {*} value - The value to assign at `path`.
     * @returns {void} Mutates `data` in place.
     */
    function setByPath(data, path, value) {
        const keys = segments(path);
        const last = keys.pop();

        if (typeof last === 'undefined') {
            return;
        }

        const parent = keys.reduce(function (node, key, index) {
            if (isNil(node[key])) {
                const nextKey = typeof keys[index + 1] !== 'undefined' ? keys[index + 1] : last;

                node[key] = /^\d+$/.test(nextKey) ? [] : {};
            }
            return node[key];
        }, data);

        parent[last] = value;
    }

    /**
     * @param {*} value - Any JSON-safe value.
     * @returns {*} A deep clone of `value`; returns nothing if `value` is undefined.
     */
    function cloneDeep(value) {
        if (typeof value === 'undefined') {
            return;
        }
        return JSON.parse(JSON.stringify(value));
    }

    return {
        getByPath: getByPath,
        setByPath: setByPath,
        cloneDeep: cloneDeep
    };
});
