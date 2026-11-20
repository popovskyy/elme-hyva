<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\Config\Source;

use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Registered standalone content types, for the grid's "Type" filter and the
 * "New entry" type picker.
 */
class ContentType implements OptionSourceInterface
{
    /**
     * @param SchemaConfig $schemaConfig
     */
    public function __construct(private readonly SchemaConfig $schemaConfig)
    {
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $options = [];

        foreach ($this->schemaConfig->getStandaloneTypes() as $code => $type) {
            $options[] = ['value' => $code, 'label' => $type['label'] ?? $code];
        }

        return $options;
    }
}
