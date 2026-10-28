/**
 * URL input + live thumbnail, a "Browse..." button opening Magento's own media
 * gallery (mage/adminhtml/browser - the one deliberate external dependency,
 * see plan section 3), and drag&drop upload straight onto the field.
 *
 * Drag&drop needs its own dragover/drop listeners rather than the root
 * delegated ones - same exception field/hotspot.js and editor/splitter.js
 * already establish for pointer/drag gestures, which aren't a good fit for
 * one shared click/input listener. The upload itself still ends by
 * dispatching studio:field-change, so the actual commit/undo/re-render goes
 * through the normal single path in state.js.
 *
 * When the schema declares `recommended="WIDTHxHEIGHT"`, the field also prints
 * that spec and measures whatever lands in it. An image of the wrong shape
 * does not merely letterbox: the frame crops from the centre, so an over-tall
 * photo loses its top and bottom. That was documented in the field's `description` and read by
 * nobody - a sentence under a control is not where anyone looks while
 * dragging a file onto it.
 */
define([
    'Bart_ContentStudio/js/editor/lib/dom',
    'Bart_ContentStudio/js/editor/lib/media-config',
    'Bart_ContentStudio/js/editor/field/base'
], function (dom, mediaConfigModule, base) {
    'use strict';

    /**
     * How far an image's aspect ratio may drift from the recommended one
     * before the field says something. A couple of percent is the difference
     * between 1200x900 and 1201x900 - worth ignoring, since the visible crop
     * is a fraction of a pixel.
     */
    const RATIO_TOLERANCE = 0.02;

    /**
     * @param {string} spec - The schema's `recommended` value, "1200x900".
     * @returns {Object|null} `{width, height, ratio}`, or null when unparseable.
     */
    function parseRecommended(spec) {
        const match = /^(\d+)x(\d+)$/.exec(String(spec || '').trim());

        if (!match) {
            return null;
        }

        const width = Number(match[1]);
        const height = Number(match[2]);

        return height > 0 ? {width: width, height: height, ratio: width / height} : null;
    }

    // Reduces 1200x900 to "4:3" so the spec reads as a shape and not just as
    // two numbers to copy. Falls back to the raw ratio for sizes that do not
    // reduce to anything memorable.
    function ratioLabel(size) {
        const gcd = function reduce(a, b) {
            return b === 0 ? a : reduce(b, a % b);
        };
        const divisor = gcd(size.width, size.height);
        const w = size.width / divisor;
        const h = size.height / divisor;

        return w <= 32 && h <= 32 ? w + ':' + h : size.ratio.toFixed(2) + ':1';
    }

    /**
     * How much of the image the centre crop throws away.
     *
     * @param {number} natural - The image's own ratio.
     * @param {number} target - The frame's ratio.
     * @returns {number} Fraction of the image lost, 0-1.
     */
    function croppedFraction(natural, target) {
        // Too tall: full width is kept and the height is trimmed. Too wide:
        // the other way round. Either way it is the smaller dimension ratio
        // over the larger that survives.
        return 1 - Math.min(natural, target) / Math.max(natural, target);
    }

    function describeMismatch(img, size) {
        const natural = img.naturalWidth / img.naturalHeight;
        const lost = croppedFraction(natural, size.ratio);

        return 'This image is ' + img.naturalWidth + '\u{00D7}' + img.naturalHeight
            + '. The recommended frame is ' + ratioLabel(size) + '; a template that crops to it from the centre'
            + ' hides about ' + Math.round(lost * 100) + '% of it.';
    }

    /**
     * @param {Element} warning - The warning line under the field.
     * @param {Element} img - The thumbnail, already loaded.
     * @param {Object|null} size - Parsed `recommended`, or null when none is declared.
     * @returns {void} Shows or clears the aspect-ratio warning.
     */
    function checkRatio(warning, img, size) {
        if (!size || !img.naturalWidth || !img.naturalHeight) {
            warning.hidden = true;

            return;
        }

        const natural = img.naturalWidth / img.naturalHeight;
        const drift = Math.abs(natural - size.ratio) / size.ratio;

        warning.hidden = drift <= RATIO_TOLERANCE;

        if (!warning.hidden) {
            warning.textContent = describeMismatch(img, size);
        }
    }

    function wireThumbLoading(thumb, img, warning, size) {
        thumb.classList.add('studio-skeleton', 'studio-image-thumb__skeleton');

        img.addEventListener('load', function () {
            thumb.classList.remove('studio-skeleton', 'studio-image-thumb__skeleton');
            thumb.classList.remove('studio-image-thumb--broken');
            checkRatio(warning, img, size);
        });
        img.addEventListener('error', function () {
            thumb.classList.remove('studio-skeleton', 'studio-image-thumb__skeleton');
            thumb.classList.add('studio-image-thumb--broken');
            warning.hidden = true;
        });
    }

    function showError(path, message) {
        const errorEl = document.querySelector('[data-field-error="' + path + '"]');

        if (errorEl) {
            errorEl.textContent = message || '';
        }
    }

    function applyUploadedUrl(input, url) {
        input.value = url;
        document.dispatchEvent(new CustomEvent('studio:field-change', {
            detail: {path: input.dataset.path, value: url}
        }));
    }

    function uploadFile(dropzone, input, file) {
        const media = mediaConfigModule.get();
        const formData = new FormData();

        formData.append('image', file);
        formData.append('form_key', media.formKey);
        dropzone.classList.add('is-uploading');
        showError(input.dataset.path, '');

        fetch(media.uploadUrl, {method: 'POST', credentials: 'same-origin', body: formData})
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                dropzone.classList.remove('is-uploading');

                if (result.success) {
                    applyUploadedUrl(input, result.url);
                } else {
                    showError(input.dataset.path, result.error || 'Upload failed.');
                }
            })
            .catch(function () {
                dropzone.classList.remove('is-uploading');
                showError(input.dataset.path, 'Upload failed.');
            });
    }

    function wireDropzone(dropzone, input) {
        dropzone.addEventListener('dragover', function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });
        dropzone.addEventListener('dragleave', function () {
            dropzone.classList.remove('is-dragover');
        });
        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');

            const file = event.dataTransfer.files[0];

            if (file) {
                uploadFile(dropzone, input, file);
            }
        });
    }

    function browseButton(inputId) {
        return dom.el(
            'button',
            {
                type: 'button',
                class: 'studio-btn studio-btn--icon',
                dataset: {action: 'image-browse', imageBrowse: inputId}
            },
            'Browse...'
        );
    }

    function specLine(size) {
        return dom.el('p', {class: 'studio-image-spec'}, [
            'Upload ',
            dom.el('span', {class: 'studio-image-spec__size'}, size.width + ' \u{00D7} ' + size.height),
            ' or any ' + ratioLabel(size) + ' image at least that big.'
        ]);
    }

    /**
     * @param {Object} ctx - Field render context: {field, value, path}.
     * @returns {Element} A URL input, thumbnail, browse button, all inside a drag&drop dropzone.
     */
    function render(ctx) {
        const value = base.asString(ctx.value);
        const size = parseRecommended(ctx.field.recommended);
        const input = base.wireControl(
            ctx,
            dom.el('input', {
                type: 'text',
                class: 'studio-input',
                value: value,
                placeholder: 'Image URL',
                // Read by mage/adminhtml/browser.js off the *target* element and
                // forwarded to cms/wysiwyg_images/onInsert. Without it Magento
                // answers with an encoded admin directive URL
                // (/admin/cms/wysiwyg/directive/___directive/<base64>/key/<secret>),
                // which is an authenticated backend link: it renders in the
                // editor and 404s for every customer, and it would put an admin
                // path and key into public page HTML. With it the controller
                // returns the plain media path (/media/<folder>/<file>), which is
                // what the storefront template and SchemaValidator::isSafeUrl
                // both expect. The attribute name is spelled out rather than set
                // through `dataset` on purpose: jQuery's .data('force_static_path')
                // looks for exactly this underscored attribute, while dataset
                // would emit data-force-static-path and never be found.
                'data-force_static_path': '1'
            })
        );
        const img = dom.el('img', {src: value || null, alt: ''});
        const thumb = dom.el('div', {class: 'studio-image-thumb', hidden: value === ''}, img);
        const warning = dom.el('p', {class: 'studio-image-warning', hidden: true});
        const dropzone = dom.el('div', {class: 'studio-image-field'}, [
            input,
            thumb,
            browseButton(input.id),
            size ? specLine(size) : null,
            warning
        ]);

        wireThumbLoading(thumb, img, warning, size);
        wireDropzone(dropzone, input);

        return base.shell(ctx, dropzone, input.id);
    }

    return {render: render};
});
