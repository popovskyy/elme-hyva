/**
 * A single resize handle between two editor panels. Uses its own pointer
 * listeners (setPointerCapture, add/remove on drag end) rather than the root
 * delegated listener - a continuous drag gesture is a different shape of
 * interaction than the one-shot click/input events state.js delegates, and
 * field/hotspot.js already establishes this as the codebase's pattern for
 * pointer drags.
 */
define(['Bart_ContentStudio/js/editor/lib/dom'], function (dom) {
    'use strict';

    /**
     * @param {string} modifierClass - BEM modifier class identifying which gap this handle sits in.
     * @returns {Element} The splitter handle element.
     */
    function build(modifierClass) {
        return dom.el('div', {class: 'studio-splitter ' + modifierClass});
    }

    /**
     * @param {Element} splitter - A handle built by build().
     * @param {Object} handlers - {onStart(): void, onMove(deltaX): void, onEnd(): void}.
     * @returns {void} Wires pointerdown/move/up for a full drag gesture.
     */
    function wireDrag(splitter, handlers) {
        splitter.addEventListener('pointerdown', function (downEvent) {
            downEvent.preventDefault();
            splitter.setPointerCapture(downEvent.pointerId);
            splitter.classList.add('is-dragging');
            handlers.onStart();

            const startX = downEvent.clientX;

            const onMove = function (moveEvent) {
                handlers.onMove(moveEvent.clientX - startX);
            };

            const onEnd = function () {
                splitter.classList.remove('is-dragging');
                splitter.removeEventListener('pointermove', onMove);
                splitter.removeEventListener('pointerup', onEnd);
                handlers.onEnd();
            };

            splitter.addEventListener('pointermove', onMove);
            splitter.addEventListener('pointerup', onEnd);
        });
    }

    return {build: build, wireDrag: wireDrag};
});
