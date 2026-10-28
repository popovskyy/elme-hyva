/**
 * Page-scoped pickable entries for reference fields, keyed by target content
 * type and set once by state.js on mount.
 *
 * Same reasoning as lib/media-config.js: group/repeater/blocks each build their
 * own child ctx object, so threading a value that never varies per field
 * through all of them would touch four files to say one thing.
 */
define([], function () {
    'use strict';

    let current = {};

    /**
     * @param {Object} optionsByType - Map of content type code => [{value, label}].
     * @returns {void}
     */
    function set(optionsByType) {
        current = optionsByType && typeof optionsByType === 'object' ? optionsByType : {};
    }

    // The target type of a reference field: the first <allow type="..."/> under
    // it. <allow group="..."/> is meaningful for `blocks`, where a field takes
    // many types, but a reference points at exactly one, so a group there has
    // nothing to resolve against and is ignored.
    function targetTypeOf(field) {
        const allow = (field.allow || []).filter(function (entry) {
            return Boolean(entry.type);
        })[0];

        return allow ? allow.type : null;
    }

    /**
     * @param {Object} field - A field definition from the schema.
     * @returns {Array<Object>} Options for that field's target type, or [] when unresolvable.
     */
    function forField(field) {
        const type = targetTypeOf(field);

        return type && Array.isArray(current[type]) ? current[type] : [];
    }

    /**
     * @param {Object} field - A field definition from the schema.
     * @param {string} identifier - The stored entry identifier.
     * @returns {string} The matching option's label, or the identifier itself when it no longer resolves.
     */
    function labelFor(field, identifier) {
        const match = forField(field).filter(function (option) {
            return option.value === identifier;
        })[0];

        return match ? match.label : identifier;
    }

    return {
        set: set,
        forField: forField,
        labelFor: labelFor,
        targetTypeOf: targetTypeOf
    };
});
