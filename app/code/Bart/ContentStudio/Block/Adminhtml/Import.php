<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Block\Adminhtml;

use Bart\ContentStudio\Model\OptionSource\ImportMode;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Hosts the entry import form.
 */
class Import extends Template
{
    /**
     * @param Context $context
     * @param ImportMode $importMode
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ImportMode $importMode,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getImportUrl(): string
    {
        return $this->getUrl('bart_content_studio/entry/importPost');
    }

    /**
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('bart_content_studio/entry/index');
    }

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function getImportModeOptions(): array
    {
        return $this->importMode->toOptionArray();
    }
}
