<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Block\Adminhtml\Widget;

use Bart\ContentStudio\Model\ResourceModel\Entry\CollectionFactory;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Helper\Data as BackendHelper;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Entry picker for the `bart_content_studio_entry` widget.
 *
 * Without it the widget's `identifier` parameter is a bare text box, so
 * inserting an entry means knowing and correctly typing its identifier - and a
 * typo produces a widget that silently renders nothing, because
 * `Block\Entry::resolveEntry()` returns null for an unknown identifier rather
 * than erroring.
 *
 * Built on the legacy `Widget\Grid\Extended` rather than a ui_component: that
 * is what `Magento\Widget\Block\Adminhtml\Widget\Chooser` knows how to host, and
 * it is what every core chooser (CMS block, CMS page, product) still uses.
 *
 * The grid offers only `standalone="true"` types. A type that exists solely to
 * be nested inside another entry's `blocks` field has no identifier of its own
 * to insert and would render nothing if picked.
 */
class Chooser extends Extended
{
    /**
     * Marks the cell the row-click callback reads the identifier from.
     *
     * A class rather than counting `td.next().next()` the way the core choosers
     * do - that breaks the moment a column is inserted, and does so silently,
     * by inserting whatever the third column now happens to hold.
     */
    private const IDENTIFIER_CELL_CLASS = 'cs-chooser-identifier';
    private const TITLE_CELL_CLASS = 'cs-chooser-title';

    /**
     * @param Context $context
     * @param BackendHelper $backendHelper
     * @param CollectionFactory $collectionFactory
     * @param SchemaConfig $schemaConfig
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        BackendHelper $backendHelper,
        private readonly CollectionFactory $collectionFactory,
        private readonly SchemaConfig $schemaConfig,
        array $data = []
    ) {
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setDefaultSort('chooser_updated_at');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
        // Inactive entries render as nothing on the storefront, so offering
        // them by default is offering a widget that will look broken.
        $this->setDefaultFilter(['chooser_is_active' => '1']);
    }

    /**
     * @param AbstractElement $element
     * @return AbstractElement
     */
    public function prepareElementHtml(AbstractElement $element)
    {
        $uniqId = $this->mathRandom->getUniqueHash($element->getId());
        $sourceUrl = $this->getUrl('bart_content_studio/entry/chooser', ['uniq_id' => $uniqId]);

        $chooser = $this->getLayout()->createBlock(\Magento\Widget\Block\Adminhtml\Widget\Chooser::class)
            ->setElement($element)
            ->setConfig($this->getConfig())
            ->setFieldsetId($this->getFieldsetId())
            ->setSourceUrl($sourceUrl)
            ->setUniqId($uniqId);

        $identifier = (string)$element->getValue();

        if ($identifier !== '') {
            $chooser->setLabel($this->escapeHtml($this->resolveLabel($identifier)));
        }

        $element->setData('after_element_html', $chooser->toHtml());

        return $element;
    }

    /**
     * Prototype, not jQuery: the widget chooser grid is legacy admin markup and
     * this string is evaluated in that context.
     *
     * @return string
     */
    public function getRowClickCallback()
    {
        $chooserJsObject = $this->_escaper->escapeJs($this->getId());

        return '
            function (grid, event) {
                var trElement = Event.findElement(event, "tr"),
                    identifierCell = trElement.down("td.' . self::IDENTIFIER_CELL_CLASS . '"),
                    titleCell = trElement.down("td.' . self::TITLE_CELL_CLASS . '");

                if (!identifierCell) {
                    return;
                }

                var identifier = identifierCell.innerHTML.replace(/^\s+|\s+$/g, ""),
                    label = titleCell ? titleCell.innerHTML.replace(/^\s+|\s+$/g, "") : identifier;

                ' . $chooserJsObject . '.setElementValue(identifier);
                ' . $chooserJsObject . '.setElementLabel(label);
                ' . $chooserJsObject . '.close();
            }
        ';
    }

    /**
     * @return Extended
     */
    protected function _prepareCollection()
    {
        $collection = $this->collectionFactory->create();
        $codes = array_keys($this->schemaConfig->getStandaloneTypes());

        // An empty registry must yield an empty grid, not every entry: an `in`
        // filter with no values is a condition MySQL drops entirely.
        $collection->addFieldToFilter('type', ['in' => $codes ?: ['']]);

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * @return Extended
     */
    protected function _prepareColumns()
    {
        $this->addColumn('chooser_id', [
            'header' => __('ID'),
            'align' => 'right',
            'index' => 'entry_id',
            'width' => 60,
        ]);

        $this->addColumn('chooser_title', [
            'header' => __('Title'),
            'align' => 'left',
            'index' => 'title',
            'column_css_class' => self::TITLE_CELL_CLASS,
        ]);

        $this->addColumn('chooser_identifier', [
            'header' => __('Identifier'),
            'align' => 'left',
            'index' => 'identifier',
            'column_css_class' => self::IDENTIFIER_CELL_CLASS,
        ]);

        $this->addColumn('chooser_type', [
            'header' => __('Type'),
            'align' => 'left',
            'index' => 'type',
            'type' => 'options',
            'options' => $this->getTypeOptions(),
        ]);

        $this->addColumn('chooser_is_active', [
            'header' => __('Status'),
            'index' => 'is_active',
            'type' => 'options',
            'options' => [0 => __('Disabled'), 1 => __('Enabled')],
        ]);

        $this->addColumn('chooser_updated_at', [
            'header' => __('Updated'),
            'index' => 'updated_at',
            'type' => 'datetime',
        ]);

        return parent::_prepareColumns();
    }

    /**
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('bart_content_studio/entry/chooser', ['_current' => true]);
    }

    /**
     * @return array<string, string>
     */
    private function getTypeOptions(): array
    {
        $options = [];

        foreach ($this->schemaConfig->getStandaloneTypes() as $code => $type) {
            $options[$code] = (string)($type['label'] ?? $code);
        }

        return $options;
    }

    /**
     * The label shown on the already-chosen value.
     *
     * Not store-scoped: the chooser sits in an admin form that has no
     * storefront context, and the widget resolves per store at render time
     * anyway. Falls back to the identifier when the entry has no title, or has
     * since been deleted - showing the raw identifier is what tells an author
     * the reference is stale.
     *
     * @param string $identifier
     * @return string
     */
    private function resolveLabel(string $identifier): string
    {
        $collection = $this->collectionFactory->create();
        $collection->addIdentifierFilter($identifier)->setPageSize(1);

        $entry = $collection->getFirstItem();
        $title = trim((string)$entry->getTitle());

        return $title !== '' ? $title : $identifier;
    }
}
