<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\ImportExport;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Model\ImportExport\ExportFileNamer;
use PHPUnit\Framework\TestCase;

/**
 * Identifiers repeat across stores by design, so the naming rule is the only
 * thing standing between a multi-brand export and a ZIP that silently contains
 * one file where three entries were selected.
 *
 * @see ExportFileNamer
 */
class ExportFileNamerTest extends TestCase
{
    private ExportFileNamer $namer;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->namer = new ExportFileNamer();
    }

    public function testUniqueIdentifiersKeepTheirPlainName(): void
    {
        $names = $this->namer->nameFiles([
            $this->entry(10, 'compare_mattress_config'),
            $this->entry(11, 'usp_strip_home'),
        ]);

        $this->assertSame(
            [10 => 'compare_mattress_config', 11 => 'usp_strip_home'],
            $names
        );
    }

    public function testSharedIdentifierIsSuffixedWithTheEntryId(): void
    {
        $names = $this->namer->nameFiles([
            $this->entry(10, 'compare_mattress_config'),
            $this->entry(11, 'compare_mattress_config'),
        ]);

        $this->assertSame(
            [10 => 'compare_mattress_config-10', 11 => 'compare_mattress_config-11'],
            $names
        );
    }

    public function testOnlyTheCollidingIdentifiersAreSuffixed(): void
    {
        $names = $this->namer->nameFiles([
            $this->entry(10, 'shared'),
            $this->entry(11, 'shared'),
            $this->entry(12, 'alone'),
            $this->entry(13, 'shared'),
        ]);

        $this->assertSame(
            [10 => 'shared-10', 11 => 'shared-11', 12 => 'alone', 13 => 'shared-13'],
            $names
        );
    }

    public function testEveryEntryGetsADistinctFileName(): void
    {
        $names = $this->namer->nameFiles([
            $this->entry(10, 'shared'),
            $this->entry(11, 'shared'),
            $this->entry(12, 'alone'),
        ]);

        $this->assertCount(3, array_unique($names));
    }

    public function testEmptyInputProducesNoNames(): void
    {
        $this->assertSame([], $this->namer->nameFiles([]));
    }

    /**
     * A generator is the shape `Processor\Export` actually passes; counting the
     * identifiers requires buffering, which a naive rewrite would break.
     */
    public function testAcceptsAGeneratorRatherThanAnArray(): void
    {
        $entries = (function () {
            yield $this->entry(10, 'shared');
            yield $this->entry(11, 'shared');
        })();

        $this->assertSame(
            [10 => 'shared-10', 11 => 'shared-11'],
            $this->namer->nameFiles($entries)
        );
    }

    /**
     * @param int $entryId
     * @param string $identifier
     * @return EntryInterface
     */
    private function entry(int $entryId, string $identifier): EntryInterface
    {
        $entry = $this->createMock(EntryInterface::class);
        $entry->method('getEntryId')->willReturn($entryId);
        $entry->method('getIdentifier')->willReturn($identifier);

        return $entry;
    }
}
