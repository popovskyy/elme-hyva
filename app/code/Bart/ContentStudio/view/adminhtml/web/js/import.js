/**
 * Drag & drop wiring for the entry import form. The actual upload is a plain
 * multipart form POST (file uploads don't fit the JSON-fetch pattern the
 * editor uses) - this only handles picking a file and reflecting it in the
 * UI before the browser submits the form natively.
 */
define([], function () {
    'use strict';

    function updateFileName(input, label) {
        const file = input.files[0];

        label.textContent = file ? file.name : label.dataset.defaultText;
    }

    function wireDropzone(dropzone, input, submitButton, label) {
        label.dataset.defaultText = label.textContent;

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone.addEventListener('drop', function (event) {
            const files = event.dataTransfer.files;

            if (files.length > 0) {
                input.files = files;
                updateFileName(input, label);
                submitButton.disabled = false;
            }
        });

        input.addEventListener('change', function () {
            updateFileName(input, label);
            submitButton.disabled = input.files.length === 0;
        });
    }

    /**
     * @param {Object} config - Unused; present for the x-magento-init contract.
     * @param {Element} element - The `#studio-import-form` root node.
     * @returns {void} Wires drag & drop on the form's dropzone.
     */
    return function initImportForm(config, element) {
        const dropzone = element.querySelector('#studio-import-dropzone');
        const input = element.querySelector('#studio-import-file');
        const submitButton = element.querySelector('#studio-import-submit');
        const label = element.querySelector('[data-import-filename]');

        wireDropzone(dropzone, input, submitButton, label);
    };
});
