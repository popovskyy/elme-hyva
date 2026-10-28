/**
 * Stores the "Content Studio" Page Builder type as the same widget directive
 * the "Content Studio Entry" widget (etc/widget.xml) inserts:
 *
 *     {{widget type="Bart\ContentStudio\Block\Entry" identifier="..."}}
 *
 * so the storefront renders it through Block\Entry with no Page Builder JS,
 * and content saved either way reads the same. Modelled on Page Builder's
 * content-type/block/mass-converter/widget-directive.js.
 */
define([
    'Magento_PageBuilder/js/mass-converter/widget-directive-abstract',
    'Magento_PageBuilder/js/utils/object'
], function (WidgetDirectiveAbstract, objectUtils) {
    'use strict';

    const WIDGET_TYPE = 'Bart\\ContentStudio\\Block\\Entry';

    function WidgetDirective() {
        WidgetDirectiveAbstract.call(this);
    }

    WidgetDirective.prototype = Object.create(WidgetDirectiveAbstract.prototype);
    WidgetDirective.prototype.constructor = WidgetDirective;

    /**
     * Saved HTML -> data: reads `identifier` back out of the directive.
     *
     * @param {Object} data - Content type data.
     * @param {Object} config - Converter config (`html_variable`).
     * @returns {Object} The data with `identifier` set.
     */
    WidgetDirective.prototype.fromDom = function (data, config) {
        const attributes = WidgetDirectiveAbstract.prototype.fromDom.call(this, data, config);

        data.identifier = attributes.identifier || '';

        return data;
    };

    /**
     * Data -> saved HTML: no identifier, no directive (the stage shows the
     * placeholder and the storefront renders nothing).
     *
     * @param {Object} data - Content type data.
     * @param {Object} config - Converter config (`html_variable`).
     * @returns {Object} The data with the directive in `html_variable`.
     */
    WidgetDirective.prototype.toDom = function (data, config) {
        const identifier = String(data.identifier || '').trim();

        if (identifier === '') {
            objectUtils.set(data, config.html_variable, '');

            return data;
        }

        objectUtils.set(data, config.html_variable, this.buildDirective({
            type: WIDGET_TYPE,
            identifier: identifier
        }));

        return data;
    };

    return WidgetDirective;
});
