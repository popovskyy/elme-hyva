<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Model\OptionSource;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Import behaviour when an entry with the same identifier and store scope
 * already exists.
 */
class ImportMode implements OptionSourceInterface
{
    public const MODE_UPDATE = 'update';
    public const MODE_SKIP = 'skip';

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::MODE_UPDATE, 'label' => __('Update existing')],
            ['value' => self::MODE_SKIP, 'label' => __('Skip existing')],
        ];
    }
}
