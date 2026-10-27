define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/lib/reference-options',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, referenceOptions, base) {
    'use strict';

    // A stored identifier whose entry has since been deleted, renamed, or was
    // written by a hand-edited import. It is kept as an option so opening the
    // entry and saving it does not silently drop the reference - the label says
    // what happened and the author decides.
    function danglingOption(value) {
        return dom.el('option', {value: value, selected: true}, value + ' - missing');
    }

    function optionsFor(ctx) {
        const available = referenceOptions.forField(ctx.field);
        const value = base.asString(ctx.value);
        const known = available.filter(function (option) {
            return option.value === value;
        }).length > 0;

        const nodes = available.map(function (option) {
            return dom.el('option', {value: option.value, selected: option.value === value}, option.label);
        });

        nodes.unshift(dom.el('option', {value: '', selected: value === ''}, '- none -'));

        if (value !== '' && !known) {
            nodes.push(danglingOption(value));
        }

        return nodes;
    }

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A select of entries of the field's target content type.
     */
    function render(ctx) {
        const select = dom.el(
            'select',
            {class: 'studio-input studio-select studio-reference'},
            optionsFor(ctx)
        );

        return base.row(ctx, select);
    }

    return {render: render};
});
