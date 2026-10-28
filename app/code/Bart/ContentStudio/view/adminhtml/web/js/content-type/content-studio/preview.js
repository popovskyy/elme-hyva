/**
 * Stage of the "Content Studio" Page Builder type.
 *
 * Page Builder's Block type pastes the rendered block HTML into the admin
 * document. That cannot work for an entry: its markup is Tailwind, and the
 * theme's styles.css in the admin document would restyle the whole admin
 * (Tailwind's base layer), while without it the entry is unstyled. So the
 * stage holds an <iframe> of entry/previewFrame instead: admin-served, same
 * origin, the store theme's own CSS. The frame takes the height of its
 * content and lets no pointer event through (pagebuilder.css), so Page
 * Builder's select, move, duplicate and remove work as for Block.
 *
 * The stage shows the server-rendered state: no Alpine runs in the frame.
 */
define([
    'jquery',
    'knockout',
    'mage/translate',
    'Magento_PageBuilder/js/config',
    'Magento_PageBuilder/js/content-type-menu/hide-show-option',
    'Magento_PageBuilder/js/content-type/preview'
], function ($, ko, $t, pageBuilderConfig, HideShowOption, BasePreview) {
    'use strict';

    const TYPE_NAME = 'content_studio';

    // Page Builder's own Block placeholder wording, for the same state.
    const MESSAGES = {
        notSelected: $t('Empty Content Studio Entry'),
        unknownError: $t('An unknown error occurred. Please try again.'),
        missing: $t('Entry "%1" no longer exists. The storefront shows nothing here.'),
        disabled: $t('This entry is disabled. The storefront shows nothing here until it is enabled.')
    };

    function previewConfig() {
        const types = pageBuilderConfig.getConfig('content_types') || {};
        const type = types[TYPE_NAME] || {};

        return (type.additional_data && type.additional_data.previewConfig) || {};
    }

    function frameUrlFor(identifier, storeId) {
        const base = previewConfig().frameUrl || '';
        const query = $.param({identifier: identifier, store_id: storeId, bare: 1});

        return base + (base.indexOf('?') === -1 ? '?' : '&') + query;
    }

    // The block the frame is sized to: `.page-main`, not <html> or
    // `.page-wrapper`, which are at least as tall as the frame itself, so
    // measuring them could only ever grow it.
    function frameContent(frame) {
        const doc = frame.contentDocument;

        return doc ? doc.querySelector('.page-main') || doc.body : null;
    }

    function Preview(contentType, config, observableUpdater) {
        BasePreview.call(this, contentType, config, observableUpdater);

        this.frameUrl = ko.observable('');
        this.frameTitle = ko.observable('');
        this.notice = ko.observable('');
        this.loading = ko.observable(false);
        this.placeholderText = ko.observable(MESSAGES.notSelected);
        this.lastIdentifier = null;
        this.request = 0;
        this.frameObserver = null;
    }

    Preview.prototype = Object.create(BasePreview.prototype);
    Preview.prototype.constructor = Preview;

    /**
     * Block's options plus its show/hide toggle.
     *
     * @returns {Object} The content type's toolbar options.
     */
    Preview.prototype.retrieveOptions = function () {
        const options = BasePreview.prototype.retrieveOptions.call(this);

        options.hideShow = new HideShowOption({
            preview: this,
            icon: HideShowOption.showIcon,
            title: HideShowOption.showText,
            action: this.onOptionVisibilityToggle,
            classes: ['hide-show-content-type'],
            sort: 40
        });

        return options;
    };

    /**
     * Runs after every data change (form save, load from saved content).
     *
     * @returns {void}
     */
    Preview.prototype.afterObservablesUpdated = function () {
        BasePreview.prototype.afterObservablesUpdated.call(this);

        const data = this.contentType.dataStore.getState();

        this.showEntry(String(data.identifier || '').trim());
    };

    /**
     * Placeholder for no entry; otherwise asks for the entry's metadata and
     * points the frame at it, or says the entry is gone. Style-only edits
     * (margins, border) keep the identifier and so do not reload the frame.
     *
     * @param {string} identifier - The picked entry's identifier.
     * @returns {void}
     */
    Preview.prototype.showEntry = function (identifier) {
        if (identifier === this.lastIdentifier) {
            return;
        }

        this.lastIdentifier = identifier;
        this.notice('');

        if (identifier === '') {
            this.request++;
            this.loading(false);
            this.frameUrl('');
            this.placeholderText(MESSAGES.notSelected);
            this.displayLabel(this.config.label);

            return;
        }

        const ticket = ++this.request;

        this.loading(true);

        $.ajax(previewConfig().metadataUrl, {
            method: 'POST',
            data: {identifier: identifier, 'form_key': window.FORM_KEY}
        }).done(function (meta) {
            if (ticket !== this.request) {
                return;
            }

            this.applyMetadata(identifier, meta);
        }.bind(this)).fail(function () {
            if (ticket !== this.request) {
                return;
            }

            this.loading(false);
            this.frameUrl('');
            this.placeholderText(MESSAGES.unknownError);
        }.bind(this));
    };

    /**
     * @param {string} identifier - The identifier the request was made for.
     * @param {Object|Array} meta - Metadata, or an empty array when not found.
     * @returns {void}
     */
    Preview.prototype.applyMetadata = function (identifier, meta) {
        if (!meta || Array.isArray(meta) || !meta.identifier) {
            this.loading(false);
            this.frameUrl('');
            this.placeholderText(MESSAGES.missing.replace('%1', identifier));
            this.displayLabel(this.config.label);

            return;
        }

        this.displayLabel(meta.title || this.config.label);
        this.frameTitle(meta.title || identifier);
        this.notice(meta.is_active === '1' ? '' : MESSAGES.disabled);
        // `loading` stays on until the frame's own load event.
        this.frameUrl(frameUrlFor(identifier, meta.store_id));
    };

    /**
     * Sizes the frame to its content now and whenever that content resizes
     * (late images, a narrower stage after a viewport switch).
     *
     * @param {Object} context - Knockout binding context (this preview).
     * @param {Event} event - The iframe's load event.
     * @returns {void}
     */
    Preview.prototype.onFrameLoad = function (context, event) {
        const frame = event.target;
        const content = frameContent(frame);

        this.disconnectFrame();
        this.loading(false);

        if (!content) {
            return;
        }

        const fit = function () {
            frame.style.height = Math.ceil(content.getBoundingClientRect().height) + 'px';
        };

        fit();

        if (typeof ResizeObserver === 'function') {
            this.frameObserver = new ResizeObserver(fit);
            this.frameObserver.observe(content);
        }
    };

    /**
     * @returns {void}
     */
    Preview.prototype.disconnectFrame = function () {
        if (this.frameObserver) {
            this.frameObserver.disconnect();
            this.frameObserver = null;
        }
    };

    /**
     * @returns {void}
     */
    Preview.prototype.destroy = function () {
        this.disconnectFrame();
        BasePreview.prototype.destroy.call(this);
    };

    return Preview;
});
