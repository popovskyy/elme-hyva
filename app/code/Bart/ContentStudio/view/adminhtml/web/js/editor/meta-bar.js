/**
 * Entry-level attributes (identifier, title, store scope, enabled) - these
 * live on bart_content_studio_entry itself, not inside the schema-driven
 * content payload, so they get their own small, non-schema-driven form.
 */
define(['Bart_ContentStudio/js/editor/lib/dom'], function (dom) {
    'use strict';

    function renderStoreSelect(meta, stores) {
        const options = [dom.el('option', {value: '0', selected: meta.storeIds.indexOf(0) !== -1}, 'All Store Views')]
            .concat(stores.map(function (store) {
                return dom.el(
                    'option',
                    {value: String(store.id), selected: meta.storeIds.indexOf(store.id) !== -1},
                    store.label
                );
            }));

        return dom.el('select', {
            class: 'studio-input studio-select',
            multiple: true,
            dataset: {metaField: 'storeIds'}
        }, options);
    }

    /**
     * @param {Object} meta - Current entry-level attributes (identifier/title/storeIds/isActive).
     * @param {Array<Object>} stores - Store view options bootstrapped from the server.
     * @returns {Element} The rendered meta bar.
     */
    function render(meta, stores) {
        return dom.el('div', {class: 'studio-meta-bar'}, [
            dom.el('label', {class: 'studio-meta-bar__field'}, [
                dom.el('span', {class: 'studio-field__label'}, 'Identifier'),
                dom.el('input', {
                    type: 'text',
                    class: 'studio-input',
                    value: meta.identifier,
                    required: true,
                    dataset: {metaField: 'identifier'}
                }),
                dom.el('p', {class: 'studio-field__error', dataset: {fieldError: 'meta.identifier'}})
            ]),
            dom.el('label', {class: 'studio-meta-bar__field'}, [
                dom.el('span', {class: 'studio-field__label'}, 'Title'),
                dom.el('input', {
                    type: 'text',
                    class: 'studio-input',
                    value: meta.title,
                    dataset: {metaField: 'title'}
                })
            ]),
            dom.el('label', {class: 'studio-meta-bar__field'}, [
                dom.el('span', {class: 'studio-field__label'}, 'Store View'),
                renderStoreSelect(meta, stores)
            ]),
            dom.el('label', {class: 'studio-meta-bar__field studio-meta-bar__field--inline'}, [
                dom.el('input', {
                    type: 'checkbox',
                    class: 'studio-checkbox',
                    checked: meta.isActive,
                    dataset: {metaField: 'isActive'}
                }),
                dom.el('span', {class: 'studio-field__label'}, 'Enabled')
            ])
        ]);
    }

    return {render: render};
});
