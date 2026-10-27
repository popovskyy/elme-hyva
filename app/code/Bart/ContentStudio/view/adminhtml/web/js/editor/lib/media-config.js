/**
 * Page-scoped media URLs (browser/upload endpoints, form key), set once by
 * state.js on mount. field/image.js reads this instead of having the urls
 * threaded through every level of the field-render ctx (group/repeater/
 * blocks all build their own child ctx objects - plumbing three more keys
 * through each would touch four files for values that never vary per field).
 */
define([], function () {
    'use strict';

    let current = {browserUrl: '', uploadUrl: '', folderId: '', formKey: ''};

    /**
     * @param {Object} config - {browserUrl, uploadUrl, folderId, formKey}.
     * @returns {void}
     */
    function set(config) {
        current = config;
    }

    /**
     * @returns {Object} The current {browserUrl, uploadUrl, folderId, formKey}.
     */
    function get() {
        return current;
    }

    return {set: set, get: get};
});
