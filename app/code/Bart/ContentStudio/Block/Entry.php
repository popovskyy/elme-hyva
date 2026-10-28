<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Block;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\Entry as EntryModel;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;

/**
 * Storefront renderer for a Content Studio entry.
 *
 * Resolves by `identifier`, scoped to the current store (falling back to All
 * Store Views), and renders it with the template its content type declares.
 * Usable directly (`identifier` set via setData()) or as a widget through
 * etc/widget.xml.
 */
class Entry extends Template implements BlockInterface, IdentityInterface
{
    private const MAX_BLOCKS_DEPTH = 3;

    private ?EntryInterface $entry = null;
    private bool $entryLoaded = false;

    /**
     * @param Context $context
     * @param EntryRepositoryInterface $entryRepository
     * @param SchemaConfig $schemaConfig
     * @param FilterProvider $filterProvider
     * @param Json $json
     * @param array<string, object> $typeViewModels Type code => view model, from di.xml.
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly EntryRepositoryInterface $entryRepository,
        private readonly SchemaConfig $schemaConfig,
        private readonly FilterProvider $filterProvider,
        private readonly Json $json,
        private readonly array $typeViewModels = [],
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The view model registered for this entry's content type, if any.
     *
     * Server-rendered types need somewhere to put the resolution logic their
     * template must not contain, and a template cannot reach the object manager
     * to fetch one. Keyed by type code in di.xml rather than hard-wired here, so
     * a type declared in another module registers its own the same way - this
     * block stays as schema-agnostic as the rest of the pipeline.
     *
     * @return object|null
     */
    public function getTypeViewModel(): ?object
    {
        $entry = $this->getEntry();

        if (!$entry) {
            return null;
        }

        return $this->typeViewModels[(string)$entry->getType()] ?? null;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return (string)$this->getData('identifier');
    }

    /**
     * @return EntryInterface|null
     */
    public function getEntry(): ?EntryInterface
    {
        if (!$this->entryLoaded) {
            $this->entryLoaded = true;
            $this->entry = $this->resolveEntry();
        }

        return $this->entry;
    }

    /**
     * Render this entry as-is, skipping the identifier lookup.
     *
     * For the admin grid's preview (Controller\Adminhtml\Entry\PreviewFrame),
     * which already holds the entry and must show it even while it is switched
     * off - "what does this look like" matters most before it goes live. The
     * storefront never calls this; it always resolves by identifier and store.
     *
     * @param EntryInterface $entry
     * @return $this
     */
    public function setPreviewEntry(EntryInterface $entry): self
    {
        $this->entry = $entry;
        $this->entryLoaded = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function _toHtml()
    {
        $entry = $this->getEntry();
        $type = $entry ? $this->schemaConfig->getType((string)$entry->getType()) : null;

        if (!$entry || !$type || !($type['template'] ?? null)) {
            return '';
        }

        $this->setTemplate($type['template']);

        return parent::_toHtml();
    }

    /**
     * @return EntryInterface|null
     */
    private function resolveEntry(): ?EntryInterface
    {
        $identifier = $this->getIdentifier();

        if ($identifier === '') {
            return null;
        }

        $storeId = (int)$this->_storeManager->getStore()->getId();

        try {
            $entry = $this->entryRepository->getByIdentifier($identifier, $storeId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        return $entry->isActive() ? $entry : null;
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        $entry = $this->getEntry();

        if (!$entry) {
            return [];
        }

        return [EntryModel::CACHE_TAG, EntryModel::CACHE_TAG . '_' . $entry->getIdentifier()];
    }

    /**
     * The entry's content payload, with `{{media url="..."}}` and similar
     * directives resolved, and caller context merged on top - non-empty
     * context values win over the stored config.
     *
     * @param array<string, mixed> $context
     * @return string
     */
    public function getConfigJson(array $context = []): string
    {
        return $this->json->serialize($this->getContent($context));
    }

    /**
     * The same payload as {@see getConfigJson()}, as an array.
     *
     * For type templates that render on the server instead of handing the
     * config to a JS renderer. Without it such a template has the choice of
     * `json_decode($block->getConfigJson())`, which is a pointless round trip,
     * or `getEntry()->getEntryData()`, which is unfiltered - a stored
     * `{{media url="..."}}` would reach the markup verbatim.
     *
     * Values are directive-resolved but NOT escaped: escape at the point of
     * output, as the plan's section 7 split requires.
     *
     * @param array<string, mixed> $context Non-empty values win over stored content.
     * @return array<string, mixed>
     */
    public function getContent(array $context = []): array
    {
        $entry = $this->getEntry();

        if (!$entry) {
            return [];
        }

        $data = $this->filterValue($entry->getEntryData());

        foreach ($context as $key => $value) {
            if ($value !== null && $value !== '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function filterValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->filterProvider->getBlockFilter()->filter($value);
        }

        if (is_array($value)) {
            return array_map(fn ($item) => $this->filterValue($item), $value);
        }

        return $value;
    }

    /**
     * Renders a `blocks` field's items - each resolved by its own `_type`
     * against the schema registry. Depth-guarded to match
     * SchemaValidator::MAX_BLOCKS_DEPTH; an item whose `_type` is no longer
     * registered (e.g. a module was removed) is skipped rather than fataling
     * the page.
     *
     * @param array<int, array<string, mixed>> $blocks
     * @param int $depth
     * @return string
     */
    public function renderBlocks(array $blocks, int $depth = 0): string
    {
        if ($depth >= self::MAX_BLOCKS_DEPTH) {
            return '';
        }

        return implode('', array_map(
            fn (array $block): string => $this->renderBlock($block, $depth),
            $blocks
        ));
    }

    /**
     * @param array<string, mixed> $block
     * @param int $depth
     * @return string
     */
    private function renderBlock(array $block, int $depth): string
    {
        $typeCode = (string)($block['_type'] ?? '');
        $type = $this->schemaConfig->getType($typeCode);

        if (!$type || !($type['template'] ?? null)) {
            return '';
        }

        $data = $this->filterValue($block);
        unset($data['_type']);

        $child = $this->getLayout()->createBlock(Template::class)
            ->setTemplate($type['template'])
            ->setData('block_config', $data)
            ->setData('block_depth', $depth + 1)
            ->setData('parent_entry_block', $this)
            // Same `typeViewModels` map, one level down: a *block* type
            // registers a view model exactly as an entry type does, from its
            // own module's di.xml. A nested block template otherwise has no way
            // to reach anything outside its own data - which is fine for prose
            // and useless the moment a block has to resolve a SKU to a product.
            ->setData('view_model', $this->typeViewModels[$typeCode] ?? null);

        return $child->toHtml();
    }
}
