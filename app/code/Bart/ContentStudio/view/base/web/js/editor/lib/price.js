/**
 * `$3,999` mask: dollar, integer digits, thousands commas.
 *
 * Must stay character-for-character equivalent to
 * Bart_ContentStudio\Model\Schema\PriceFormatter - cents are dropped first
 * so `3999.00` becomes `$3,999`, not `$399,900`.
 */
define([], function () {
    'use strict';

    // A trailing separator plus one or two digits. That is the shape cents
    // take - and also the shape a thousands group takes while it is being
    // typed, or half-deleted.
    const CENTS_PATTERN = /^(.*)([.,])(\d{1,2})$/;

    // What the mask itself emits: 1-3 digits, then groups of exactly three.
    const GROUPED_PATTERN = /^\d{1,3}(?:,\d{3})*$/;

    // `.` is always the decimal point here - the mask never emits one, so
    // nothing carrying it came from us. A comma is genuinely ambiguous:
    // `3999,00` is a pasted European price, while `$1,25` is `$1,250` with its
    // last digit just deleted. Reading the second as cents ate the entire
    // thousands group on a single Backspace ($1,250 -> $1), so a comma counts
    // as a decimal point only when what precedes it is not already grouped.
    const dropCents = (value) => {
        const match = CENTS_PATTERN.exec(value);

        if (match === null) {
            return value;
        }

        const head = match[1];

        return match[2] === ',' && GROUPED_PATTERN.test(head.replace(/[^\d,]/g, '')) ? value : head;
    };

    const format = (raw) => {
        const withoutCents = dropCents(String(raw ?? ''));
        const digits = withoutCents.replace(/\D/g, '').replace(/^0+(?=\d)/, '');

        if (digits === '') {
            return '';
        }

        return '$' + digits.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };

    // The caret is kept by *digit count*, never by character offset: reformatting
    // inserts and removes commas, so the same offset means a different place
    // before and after. Named rather than inlined into applyToInput - a loop
    // wrapping two conditions is one level past the linter's depth budget, and
    // this is the half of that function with anything to reason about.
    const caretAfterDigit = (formatted, digitsBefore) => {
        if (digitsBefore === 0) {
            return formatted.charAt(0) === '$' ? 1 : 0;
        }

        let seen = 0;

        for (let i = 0; i < formatted.length; i++) {
            if (!/\d/.test(formatted.charAt(i))) {
                continue;
            }

            seen += 1;

            if (seen >= digitsBefore) {
                return i + 1;
            }
        }

        return formatted.length;
    };

    const applyToInput = (input) => {
        const formatted = format(input.value);

        if (input.value === formatted) {
            return formatted;
        }

        const digitsBefore = input.value.slice(0, input.selectionStart || 0).replace(/\D/g, '').length;
        const pos = caretAfterDigit(formatted, digitsBefore);

        input.value = formatted;
        input.setSelectionRange(pos, pos);

        return formatted;
    };

    return {
        format: format,
        applyToInput: applyToInput
    };
});
