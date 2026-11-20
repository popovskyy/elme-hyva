/**
 * Conversion between the stored form of a `datetime` field and what
 * `<input type="datetime-local">` speaks.
 *
 * Storage is always UTC with seconds precision, so two entries saved from
 * different admin timezones sort against each other correctly. The control has
 * no timezone at all - it shows and returns wall-clock time in the browser's
 * zone - so the conversion has to happen at the edge, on the way in and out.
 *
 * It lives in lib/ rather than in field/datetime.js because state.js needs the
 * read direction too (readControlValue), and state.js depending on a field
 * renderer would invert the module hierarchy.
 */
define([], function () {
    'use strict';

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    /**
     * @param {string|null} stored - An ISO-8601 instant, or an empty/absent value.
     * @returns {string} `YYYY-MM-DDTHH:mm` in local time, or '' when unparseable.
     */
    function toInputValue(stored) {
        if (!stored) {
            return '';
        }

        const date = new Date(stored);

        if (isNaN(date.getTime())) {
            return '';
        }

        return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) +
            'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
    }

    /**
     * @param {string} inputValue - The control's local `YYYY-MM-DDTHH:mm` value.
     * @returns {string|null} The instant as `YYYY-MM-DDTHH:mm:ssZ`, or null while the value is incomplete.
     */
    function toStored(inputValue) {
        if (!inputValue) {
            return null;
        }

        const date = new Date(inputValue);

        // Half-typed dates ("2026-07-3") land here on every keystroke, since the
        // commit runs on `input`. Returning null keeps the last good value out
        // of the payload rather than writing "Invalid Date" into it.
        if (isNaN(date.getTime())) {
            return null;
        }

        return date.toISOString().replace(/\.\d{3}Z$/, 'Z');
    }

    return {
        toInputValue: toInputValue,
        toStored: toStored
    };
});
