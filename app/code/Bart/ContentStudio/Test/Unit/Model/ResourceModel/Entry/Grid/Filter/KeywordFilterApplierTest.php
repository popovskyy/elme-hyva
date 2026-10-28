<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\ResourceModel\Entry\Grid\Filter;

use Bart\ContentStudio\Model\ResourceModel\Entry\Grid\Filter\KeywordFilterApplier;
use Magento\Framework\Api\Filter;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Pins the WHERE clause the search box's condition type builds - this is the
 * part `mui/index/render` actually runs, so a typo here silently breaks
 * search rather than throwing.
 *
 * @see KeywordFilterApplier
 */
class KeywordFilterApplierTest extends TestCase
{
    private KeywordFilterApplier $applier;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->applier = new KeywordFilterApplier();
    }

    /**
     * @param string $value
     * @param string $expectedWhere
     */
    #[DataProvider('valueProvider')]
    public function testWhereClause(string $value, string $expectedWhere): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('quoteInto')->willReturnCallback(
            static fn (string $text, mixed $val): string => str_replace('?', "'" . $val . "'", $text)
        );

        $select = $this->createMock(Select::class);
        $select->expects($this->once())->method('where')->with($expectedWhere);

        /** @var AbstractCollection&MockObject $collection */
        $collection = $this->createMock(AbstractCollection::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getSelect')->willReturn($select);

        $filter = $this->createMock(Filter::class);
        $filter->method('getValue')->willReturn($value);

        $this->applier->apply($collection, $filter);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function valueProvider(): array
    {
        return [
            'text term, no id branch' => [
                'windsor',
                "(title LIKE '%windsor%' OR identifier LIKE '%windsor%')",
            ],
            'numeric term adds the id branch' => [
                '20',
                "(title LIKE '%20%' OR identifier LIKE '%20%' OR entry_id = '20')",
            ],
            'value is trimmed first' => [
                '  windsor  ',
                "(title LIKE '%windsor%' OR identifier LIKE '%windsor%')",
            ],
        ];
    }

    public function testEmptyValueAppliesNoCondition(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects($this->never())->method('where');

        /** @var AbstractCollection&MockObject $collection */
        $collection = $this->createMock(AbstractCollection::class);
        $collection->method('getSelect')->willReturn($select);
        $collection->expects($this->never())->method('getConnection');

        $filter = $this->createMock(Filter::class);
        $filter->method('getValue')->willReturn('   ');

        $this->applier->apply($collection, $filter);
    }

    public function testRejectsANonDatabaseCollection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $collection = $this->createMock(\Magento\Framework\Data\Collection::class);
        $filter = $this->createMock(Filter::class);
        $filter->method('getValue')->willReturn('windsor');

        $this->applier->apply($collection, $filter);
    }
}
