<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Block\Adminhtml;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\ImageUpload;
use Bart\ContentStudio\Model\ResourceModel\Entry\CollectionFactory as EntryCollectionFactory;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Hosts the mount-point for the schema-driven editor; all rendering is done
 * client-side by editor/state.js from the JSON this block bootstraps.
 */
class Editor extends Template
{
    /**
     * Ceiling on how many entries one `reference` picker offers.
     *
     * A picker is a plain <select>: fine for taxonomies, which number in the
     * dozens, and the reason a reference to a high-cardinality type (post ->
     * post) shows only the most recently updated entries. Raising this is not
     * the fix if that ever bites - a search-backed control is.
     */
    private const REFERENCE_OPTION_LIMIT = 200;

    private ?EntryInterface $entry = null;
    private bool $entryLoaded = false;

    /**
     * @param Context $context
     * @param EntryRepositoryInterface $entryRepository
     * @param SchemaConfig $schemaConfig
     * @param StoreManagerInterface $storeManager
     * @param Json $json
     * @param EntryCollectionFactory $entryCollectionFactory
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly SchemaConfig $schemaConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly Json $json,
        private readonly EntryCollectionFactory $entryCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return int
     */
    public function getEntryId(): int
    {
        return (int)$this->getRequest()->getParam('entry_id');
    }

    /**
     * @return EntryInterface|null
     */
    public function getEntry(): ?EntryInterface
    {
        if (!$this->entryLoaded) {
            $this->entryLoaded = true;
            $entryId = $this->getEntryId();

            if ($entryId) {
                try {
                    $this->entry = $this->entryRepository->getById($entryId);
                } catch (NoSuchEntityException $e) {
                    $this->entry = null;
                }
            }
        }

        return $this->entry;
    }

    /**
     * @return string
     */
    public function getTypeCode(): string
    {
        $entry = $this->getEntry();

        return $entry ? (string)$entry->getType() : (string)$this->getRequest()->getParam('type');
    }

    /**
     * @return string
     */
    public function getTypeLabel(): string
    {
        $type = $this->schemaConfig->getType($this->getTypeCode());

        return (string)($type['label'] ?? $this->getTypeCode());
    }

    /**
     * Serialized bootstrap payload consumed by editor/state.js.
     *
     * @return string
     */
    public function getBootstrapJson(): string
    {
        $entry = $this->getEntry();

        return $this->json->serialize([
            'typeCode' => $this->getTypeCode(),
            'schema' => $this->schemaConfig->getTypes(),
            // Empty PHP [] becomes JSON [] - a JS Array. Named keys written onto
            // that array (title, ranges, ...) survive in memory for the preview,
            // but JSON.stringify drops them, so Save posts {} and every required
            // field fails. An empty object serialises as {} instead.
            'data' => ($entry?->getEntryData() ?? []) ?: new \stdClass(),
            'meta' => [
                'entryId' => $entry?->getEntryId(),
                'identifier' => $entry?->getIdentifier() ?? '',
                'title' => $entry?->getTitle() ?? '',
                'isActive' => $entry === null || $entry->isActive(),
                'storeIds' => $entry?->getStoreIds() ?? [0],
                'version' => $entry?->getVersion() ?? 0,
            ],
            'stores' => $this->getStoreOptions(),
            'references' => $this->getReferenceOptions(),
            // Framed inside another admin form (`?embedded=1`, e.g. a modal
            // opened from another entity's edit page). The editor then reports a save
            // back to its opener instead of navigating to the entry grid -
            // navigating would strand the user inside a modal.
            'embedded' => (bool)$this->getRequest()->getParam('embedded'),
            'urls' => $this->getUrls($entry),
            'mediaUrl' => $this->getMediaBaseUrl(),
            // The folder the media browser opens in, in Magento's own id
            // encoding. Two jobs, both load-bearing:
            //
            //   1. `mage/adminhtml/browser.js::setPathId` does
            //      `url.match(/...current_tree_path.../)[3]` with no null guard, so
            //      a browser URL without this parameter throws a TypeError and
            //      the dialog never finishes opening - on the second and every
            //      later Browse, which is why it looked intermittent.
            //   2. `Images::getCurrentPath` creates the directory when it is
            //      missing, so Browse lands in the same folder drag&drop uploads
            //      into instead of the media root.
            //
            // It must stay the *last* query parameter: that regex terminates on
            // `/` or end-of-string only, so anything appended after it would be
            // swallowed into the captured path.
            // `Magento\Cms\Helper\Wysiwyg\Images::idEncode` inlined rather than
            // injected. Taking the helper would add a constructor argument to a
            // *block*, and a block's generated interceptor is written from the
            // signature at codegen time and never revalidated - so everyone who
            // pulled this would meet `Argument #7 must be of type ...Images,
            // array given` until they cleared `generated/`. Not worth it for one
            // string transform whose output is a URL wire format: changing the
            // alphabet would invalidate every media URL Magento has ever emitted.
            'mediaFolderId' => strtr(base64_encode(ImageUpload::UPLOAD_DIR), '+/=', ':_-'),
            'formKey' => $this->getFormKey(),
        ]);
    }

    /**
     * @return array<int, array{id: int, code: string, label: string, isActive: bool}>
     */
    private function getStoreOptions(): array
    {
        $options = [];

        // Not getStores(true): that adds the admin store, whose id is 0 - the
        // exact value meta-bar.js already renders as its "All Store Views"
        // option. Two options sharing value 0 both light up as selected for an
        // all-stores entry, and the admin store is not a storefront anyway.
        foreach ($this->storeManager->getStores() as $store) {
            $options[] = [
                'id' => (int)$store->getId(),
                'code' => $store->getCode(),
                'label' => $store->getWebsite()->getName() . ' - ' . $store->getName(),
                // `getStores()` does not filter on activeness, and the two
                // consumers of this list want different things: the meta bar's
                // assignment picker offers every view (assigning to one that is
                // currently switched off is legitimate), while the live
                // preview's store picker must offer only active ones -
                // TargetProvider skips inactive views, so an inactive option
                // there could never resolve to a page. Carrying the flag lets
                // each filter for itself instead of a second server round trip.
                'isActive' => (bool)$store->getIsActive(),
            ];
        }

        return $options;
    }

    /**
     * Pickable entries for every `reference` / `reference_multi` field, keyed
     * by the content type they point at.
     *
     * Collected from the whole registered schema rather than from this entry's
     * own type: a `blocks` field can nest any allowed type, each of which may
     * carry its own reference field, and walking that tree here would duplicate
     * logic the client already has. Registered types number in the dozens, so
     * the extra keys cost a few hundred bytes of bootstrap.
     *
     * Deliberately NOT filtered by store. The picker would otherwise have to be
     * refetched every time the meta bar's scope changes, and it is not the
     * boundary anyway: a reference is resolved per store at render time through
     * `getByIdentifier($identifier, $storeId)`, so one that does not exist in
     * the visitor's brand resolves to nothing and is skipped - the same way an
     * unregistered block `_type` already is.
     *
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function getReferenceOptions(): array
    {
        $targets = $this->collectReferenceTargets();

        if ($targets === []) {
            return [];
        }

        $options = array_fill_keys($targets, []);
        $collection = $this->entryCollectionFactory->create();

        $collection->addFieldToFilter('type', ['in' => $targets])
            ->addFieldToSelect(['entry_id', 'identifier', 'type', 'title'])
            ->setOrder('updated_at', 'DESC')
            ->setPageSize(self::REFERENCE_OPTION_LIMIT * count($targets));

        foreach ($collection as $item) {
            $identifier = (string)$item->getIdentifier();
            $type = (string)$item->getType();

            if (!isset($options[$type]) || count($options[$type]) >= self::REFERENCE_OPTION_LIMIT) {
                continue;
            }

            $title = trim((string)$item->getTitle());
            $options[$type][] = [
                'value' => $identifier,
                'label' => $title !== '' ? $title : $identifier,
            ];
        }

        foreach ($options as $type => $items) {
            usort($items, static fn (array $a, array $b): int => strcasecmp($a['label'], $b['label']));
            $options[$type] = $items;
        }

        return $options;
    }

    /**
     * Every type code named by an `<allow type="..."/>` under a reference field,
     * anywhere in the registry.
     *
     * @return string[]
     */
    private function collectReferenceTargets(): array
    {
        $targets = [];

        foreach ($this->schemaConfig->getTypes() as $type) {
            $this->collectReferenceTargetsFromFields($type['fields'] ?? [], $targets);
        }

        return array_values(array_unique($targets));
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @param string[] $targets
     * @return void
     */
    private function collectReferenceTargetsFromFields(array $fields, array &$targets): void
    {
        foreach ($fields as $field) {
            if (in_array($field['type'] ?? '', ['reference', 'reference_multi'], true)) {
                foreach ($field['allow'] ?? [] as $allow) {
                    if (!empty($allow['type'])) {
                        $targets[] = (string)$allow['type'];
                    }
                }
            }

            $this->collectReferenceTargetsFromFields($field['fields'] ?? [], $targets);
        }
    }

    /**
     * @param EntryInterface|null $entry
     * @return array<string, string|null>
     */
    private function getUrls(?EntryInterface $entry): array
    {
        return [
            'save' => $this->getUrl('bart_content_studio/entry/save'),
            'back' => $this->getUrl('bart_content_studio/entry/index'),
            // Parks the current, unsaved payload where the storefront can read
            // it and answers with the pages it can be opened on - the editor
            // never builds a storefront URL itself.
            'previewDraft' => $this->getUrl('bart_content_studio/entry/previewDraft'),
            'edit' => $this->getUrl('bart_content_studio/entry/edit'),
            'delete' => $entry
                ? $this->getUrl('bart_content_studio/entry/delete', ['entry_id' => $entry->getEntryId()])
                : null,
            // The classic media browser (mage/adminhtml/browser + cms/wysiwyg_images/index) is the one
            // deliberate external dependency for the image field's "pick existing" flow - see plan section 3.
            // Drag&drop upload goes through our own endpoint instead of cms/wysiwyg_images/upload:
            // that one only works after the CMS browser's own folder-navigation AJAX has set a
            // "current path" in session, which a bare drop-onto-the-field never triggers.
            'mediaBrowser' => $this->getUrl('cms/wysiwyg_images/index'),
            'mediaUpload' => $this->getUrl('bart_content_studio/entry/uploadImage'),
        ];
    }

    /**
     * @return string
     */
    private function getMediaBaseUrl(): string
    {
        $url = $this->_storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        return rtrim($url, '/') . '/';
    }
}
