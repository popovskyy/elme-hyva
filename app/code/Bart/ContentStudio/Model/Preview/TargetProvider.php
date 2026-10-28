<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Preview;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds the list of "open this entry on the storefront" links for the editor.
 *
 * One sandbox page per store view the entry is visible in, and nothing else.
 *
 * An earlier version also offered the real product pages the entry appears on,
 * resolved per content type out of `Bart_Catalog`. It was dropped on purpose.
 * The link is only worth anything if it is *certainly* right, and deriving it
 * meant guessing: which of fifty products, matched out of an attribute that is
 * declared global while its values are saved per store, ordered by nothing in
 * particular. A link that quietly points at a product the entry was removed
 * from is worse than no link, because it looks like the block is broken.
 *
 * The sandbox answers the actual question - how does this content look under
 * this brand's theme - with no guessing at all, and the per-product context a
 * PDP adds only changes which tab opens first, which is one click away anyway.
 *
 * Store views matter here: an entry assigned to several renders under a
 * different theme in each, and that comparison exists nowhere else.
 */
class TargetProvider
{
    private const SANDBOX_ROUTE = 'bart_content_studio/preview/sandbox';

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param EntryInterface $entry
     * @param string $token Preview token from {@see DraftStorage::save()}.
     * @param bool $allStores Ignore the entry's own store assignment and offer every
     *        active store view. Set by the editor's Page-mode preview, which is a
     *        "how does this look under this brand" check, not a "is this actually
     *        published there" one - unlike the View on site popover, which stays
     *        scoped to assigned stores so it never links to a store that would not
     *        really show this entry.
     * @return array<int, array{group: string, label: string, url: string, storeId: int, storeCode: string}>
     */
    public function getTargets(EntryInterface $entry, string $token, bool $allStores = false): array
    {
        $targets = [];

        foreach ($this->resolveStores($entry, $allStores) as $store) {
            $targets[] = [
                'group' => (string)$store->getName(),
                'label' => (string)__('Preview page'),
                'url' => $this->decorate($this->sandboxUrl($store), $store, $token),
                // `storeId`/`storeCode` are for the editor's own bookkeeping -
                // matching a target back to a store when it builds the "open on
                // site" popover or the embedded page preview's store switcher -
                // not for display. `group` (the store's display name) is fine
                // as a string to show, but not to match on: two stores could
                // share a name, and matching against it would silently point
                // the preview at the wrong one.
                'storeId' => (int)$store->getId(),
                'storeCode' => $store->getCode(),
            ];
        }

        return $targets;
    }

    /**
     * Store views the entry is visible in. `[0]` means all of them, and so does
     * the caller passing `$ignoreAssignment` - Page-mode's live preview wants
     * every brand available to switch between regardless of what the entry is
     * currently scoped to up in the form, since that is exactly the thing
     * someone previewing an about-to-be-assigned entry needs to check.
     *
     * Inactive views are skipped either way: `___store` pointing at one is
     * ignored by the store resolver, so the link silently falls back to
     * whatever that domain defaults to and shows a different brand's theme -
     * the exact failure {@see self::decorate()} exists to prevent.
     *
     * @param EntryInterface $entry
     * @param bool $ignoreAssignment
     * @return array<int, StoreInterface>
     */
    private function resolveStores(EntryInterface $entry, bool $ignoreAssignment): array
    {
        $assigned = array_map('intval', $entry->getStoreIds() ?: [0]);
        $allStores = $ignoreAssignment || in_array(0, $assigned, true);
        // getStores() without `true` - the admin store (id 0) is not a
        // storefront and has no theme to preview under.
        $stores = array_filter(
            $this->storeManager->getStores(),
            static function (StoreInterface $store) use ($assigned, $allStores): bool {
                return $store->getIsActive() && ($allStores || in_array((int)$store->getId(), $assigned, true));
            }
        );

        return array_values($stores);
    }

    /**
     * Built off the store's base link URL rather than `Store::getUrl()`.
     *
     * That method delegates to the injected `UrlInterface`, which in the admin
     * area is the *backend* URL builder - it hands back
     * `/admin/bart_content_studio/preview/sandbox/key/<secret>/`, an admin
     * route that does not exist, and the link 404s. `getBaseUrl()` reads the
     * store's configured frontend base and does not care which area asked.
     *
     * @param StoreInterface $store
     * @return string
     */
    private function sandboxUrl(StoreInterface $store): string
    {
        // getBaseUrl() lives on Magento\Store\Model\Store rather than the data
        // interface; every store the manager hands out is that class.
        $base = $store->getBaseUrl(UrlInterface::URL_TYPE_LINK);

        return rtrim($base, '/') . '/' . self::SANDBOX_ROUTE . '/';
    }

    /**
     * Adds the preview token, and pins the store view.
     *
     * `___store` matters when several store views sit on one domain: without
     * it the URL resolves to whichever view that domain defaults to, and the
     * preview would silently show the wrong brand's theme.
     *
     * @param string $url
     * @param StoreInterface $store
     * @param string $token
     * @return string
     */
    private function decorate(string $url, StoreInterface $store, string $token): string
    {
        [$path, $existingQuery] = array_pad(explode('?', $url, 2), 2, '');

        parse_str($existingQuery, $query);
        $query[DraftStorage::REQUEST_PARAM] = $token;
        $query['___store'] = $store->getCode();

        return $path . '?' . http_build_query($query);
    }
}
