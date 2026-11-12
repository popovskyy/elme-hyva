<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Setup\Patch\Data;

use Elme\StoreLocator\Model\Import\CsvImporter;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Seeds the four Elme locations currently listed on elmeliving.com/showroom-locator.
 */
class ImportElmeLocations implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CsvImporter $csvImporter
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $csv = dirname(__DIR__, 3) . '/files/elme_locations.csv';
        $this->csvImporter->import($csv, false);
        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
