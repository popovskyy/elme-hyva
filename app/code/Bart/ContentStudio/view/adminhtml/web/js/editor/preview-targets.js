/**
 * "View on site" - the popover listing real storefront pages the entry can be
 * opened on, with the current unsaved draft baked into the link.
 *
 * The side panel's live preview is the type's own JS renderer drawn under the
 * editor's stylesheet: fast, and right about the content, but it is not the
 * storefront. Only the real page carries the brand's theme, the container
 * widths, the neighbouring blocks and the per-product context a PDP supplies.
 *
 * Structure only, like every other builder here - the fetch and the open/close
 * wiring live in state.js.
 */
define(['Bart_ContentStudio/js/editor/lib/dom'], function (dom) {
    'use strict';

    /**
     * Mirrors DraftStorage::LIFETIME. Only ever shown to the user, so drift
     * costs a slightly wrong sentence rather than a broken link.
     */
    const LIFETIME_LABEL = '15 minutes';

    /**
     * @returns {Object} `{wrapper, button, popover}` - the trigger and its panel.
     */
    function build() {
        const button = dom.el('button', {
            type: 'button',
            class: 'studio-btn studio-btn--ghost studio-targets__trigger',
            title: 'Open this draft on the storefront',
            'aria-haspopup': 'true',
            'aria-expanded': 'false',
            dataset: {toolbarAction: 'preview-storefront'}
        }, [
            dom.icon('external'),
            dom.el('span', {}, 'View on site')
        ]);

        const popover = dom.el('div', {
            class: 'studio-targets__popover',
            hidden: true,
            dataset: {previewTargets: '1'}
        });

        const wrapper = dom.el('div', {class: 'studio-targets'}, [button, popover]);

        return {wrapper: wrapper, button: button, popover: popover};
    }

    function setExpanded(popover, expanded) {
        const trigger = popover.parentNode && popover.parentNode.querySelector('.studio-targets__trigger');

        if (trigger) {
            trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    }

    /**
     * @param {Element} popover - The panel built by build().
     * @returns {void} Hides the panel.
     */
    function close(popover) {
        popover.hidden = true;
        dom.empty(popover);
        setExpanded(popover, false);
    }

    /**
     * @param {Element} popover - The panel built by build().
     * @returns {boolean} Whether it is currently showing.
     */
    function isOpen(popover) {
        return !popover.hidden;
    }

    function open(popover, children) {
        dom.empty(popover);
        popover.appendChild(children);
        popover.hidden = false;
        setExpanded(popover, true);
    }

    /**
     * @param {Element} popover - The panel built by build().
     * @returns {void} Shows a pending state while the draft is being parked.
     */
    function showLoading(popover) {
        open(popover, dom.el('p', {class: 'studio-targets__note'}, 'Preparing preview...'));
    }

    /**
     * @param {Element} popover - The panel built by build().
     * @param {string} message - What to tell the user instead of a list.
     * @returns {void} Shows a message in place of the target list.
     */
    function showMessage(popover, message) {
        open(popover, dom.el('p', {class: 'studio-targets__note'}, message));
    }

    function targetLink(target) {
        return dom.el('a', {
            class: 'studio-targets__link',
            href: target.url,
            target: '_blank',
            // Without noopener the opened storefront tab gets a handle on the
            // editor via window.opener - and the editor is holding unsaved work.
            rel: 'noopener noreferrer'
        }, [
            dom.icon('external', 'studio-targets__link-icon'),
            dom.el('span', {}, target.label)
        ]);
    }

    // Targets arrive already ordered by the server (real pages first, then the
    // sandbox, per store view). Grouping walks that order rather than sorting,
    // so the list reads the way the server meant it to.
    function groupTargets(targets) {
        const groups = [];
        const byLabel = {};

        targets.forEach(function (target) {
            const label = target.group || '';

            if (!byLabel[label]) {
                byLabel[label] = {label: label, items: []};
                groups.push(byLabel[label]);
            }

            byLabel[label].items.push(target);
        });

        return groups;
    }

    function buildGroup(group, showLabel) {
        const children = group.items.map(targetLink);

        if (showLabel && group.label) {
            children.unshift(dom.el('p', {class: 'studio-targets__group-label'}, group.label));
        }

        return dom.el('div', {class: 'studio-targets__group'}, children);
    }

    /**
     * @param {Element} popover - The panel built by build().
     * @param {Array<Object>} targets - `{group, label, url}` rows from the server.
     * @returns {void} Renders the list, grouped by store view.
     */
    function showTargets(popover, targets) {
        if (!targets || !targets.length) {
            showMessage(popover, 'No storefront page shows this entry yet.');

            return;
        }

        const groups = groupTargets(targets);
        // One store view needs no heading - the entry is only in that one, so
        // naming it on every open is noise.
        const showLabels = groups.length > 1;
        const body = dom.el('div', {class: 'studio-targets__body'}, [
            groups.map(function (group) {
                return buildGroup(group, showLabels);
            }),
            dom.el(
                'p',
                {class: 'studio-targets__footnote'},
                'Opens the unsaved draft. Links stop working after ' + LIFETIME_LABEL + '.'
            )
        ]);

        open(popover, body);
    }

    return {
        build: build,
        close: close,
        isOpen: isOpen,
        showLoading: showLoading,
        showMessage: showMessage,
        showTargets: showTargets
    };
});
