/**
 * Pure helpers over the bootstrapped content-type schema (see
 * Bart\ContentStudio\Model\Schema\Config on the PHP side - this is its
 * client-side mirror for building forms and validating `blocks` picks).
 */
define([], function () {
    'use strict';

    /**
     * @param {Object} schema - The full content-type registry.
     * @param {string} typeCode - The type code to look up.
     * @returns {Object|null} The type's schema entry, or null if unregistered.
     */
    function getType(schema, typeCode) {
        return schema[typeCode] || null;
    }

    /**
     * @param {Object} field - A field's schema definition.
     * @returns {*} The field's configured default, or a type-appropriate empty value.
     */
    function defaultForField(field) {
        if (field.default !== null && typeof field.default !== 'undefined') {
            return field.type === 'number' ? Number(field.default) : field.default;
        }

        switch (field.type) {
            case 'repeater':
            case 'blocks':
                return [];
            case 'boolean':
                return false;
            case 'number':
                return null;
            default:
                return '';
        }
    }

    /**
     * Builds an initial data object for a fresh set of fields (new entry, or
     * a newly-added repeater row / block instance).
     *
     * @param {Array<Object>} fields - The field list to build default data for.
     * @returns {Object} A data object keyed by field name.
     */
    function defaultData(fields) {
        return (fields || []).reduce(function (data, field) {
            data[field.name] = field.type === 'group' ? defaultData(field.fields) : defaultForField(field);
            return data;
        }, {});
    }

    /**
     * @param {string} typeCode - The type code being considered.
     * @param {Array<Object>} allow - The `blocks` field's <allow> rules; empty means "allow everything registered".
     * @param {Object} schema - The full content-type registry.
     * @returns {boolean} Whether `typeCode` satisfies the allow rules.
     */
    function isAllowed(typeCode, allow, schema) {
        if (!allow || allow.length === 0) {
            return Boolean(getType(schema, typeCode));
        }

        const type = getType(schema, typeCode);

        if (!type) {
            return false;
        }

        return allow.some(function (rule) {
            if (rule.type) {
                return rule.type === typeCode;
            }
            if (rule.group) {
                return rule.group === type.group;
            }
            return false;
        });
    }

    /**
     * Types permitted for a `blocks` field, grouped by their `group` attribute
     * (falling back to "General") - feeds the block picker's grouped cards.
     *
     * @param {Object} schema - The full content-type registry.
     * @param {Array<Object>} allow - The `blocks` field's <allow> rules.
     * @returns {Object<string, Array<Object>>} Allowed types keyed by group label.
     */
    function allowedTypesByGroup(schema, allow) {
        return Object.keys(schema).reduce(function (groups, code) {
            if (!isAllowed(code, allow, schema)) {
                return groups;
            }

            const type = schema[code];
            const groupLabel = type.group || 'General';

            groups[groupLabel] = groups[groupLabel] || [];
            groups[groupLabel].push(Object.assign({code: code}, type));

            return groups;
        }, {});
    }

    return {
        getType: getType,
        defaultForField: defaultForField,
        defaultData: defaultData,
        isAllowed: isAllowed,
        allowedTypesByGroup: allowedTypesByGroup
    };
});
