<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Test\Unit\Model;

use Elme\ProductFamily\Model\FabricGroups;
use PHPUnit\Framework\TestCase;

class FabricGroupsTest extends TestCase
{
    private FabricGroups $groups;

    protected function setUp(): void
    {
        $this->groups = new FabricGroups();
    }

    private function option(string $label, bool $madeToOrder, bool $selected = false, ?int $weeks = null): array
    {
        return [
            'label' => $label,
            'selected' => $selected,
            'madeToOrder' => $madeToOrder,
            'leadTime' => $weeks === null ? null : ['weeks' => $weeks, 'label' => "With you in $weeks weeks"],
        ];
    }

    private function labels(array $group): array
    {
        return array_column($group['options'], 'label');
    }

    public function testNoMadeToOrderOptionMeansNoPicker(): void
    {
        self::assertNull($this->groups->build([
            $this->option('Noa Fog', false, true),
            $this->option('Noa Desert', false),
        ]));
        self::assertNull($this->groups->build([]));
    }

    public function testQuickShipFirstThenMadeToOrderEachKeepingItsOrder(): void
    {
        $result = $this->groups->build([
            $this->option('Avon Honey', true, false, 14),
            $this->option('Noa Fog', false),
            $this->option('Muse Flax', true, true, 14),
            $this->option('Noa Desert', false),
        ]);

        self::assertSame(['quick_ship', 'made_to_order'], array_column($result['groups'], 'code'));
        self::assertSame(['Noa Fog', 'Noa Desert'], $this->labels($result['groups'][0]));
        self::assertSame(['Avon Honey', 'Muse Flax'], $this->labels($result['groups'][1]));
    }

    public function testSelectedOptionIsReturnedAndStaysInItsGroup(): void
    {
        $result = $this->groups->build([
            $this->option('Avon Honey', true, false, 14),
            $this->option('Muse Flax', true, true, 14),
        ]);

        self::assertSame('Muse Flax', $result['selected']['label']);
        self::assertSame(['Avon Honey', 'Muse Flax'], $this->labels($result['groups'][0]));
    }

    public function testAllMadeToOrderHasOneGroupAndNoQuickShipGroup(): void
    {
        $result = $this->groups->build([
            $this->option('Avon Honey', true, false, 14),
            $this->option('Muse Flax', true, true, 14),
        ]);

        self::assertCount(1, $result['groups']);
        self::assertSame('made_to_order', $result['groups'][0]['code']);
    }

    public function testGroupLeadTimeIsTheLongestOne(): void
    {
        $result = $this->groups->build([
            $this->option('Quick', false),
            $this->option('A', true, false, 10),
            $this->option('B', true, false, 16),
            $this->option('C', true, false, 12),
        ]);

        self::assertSame('', $result['groups'][0]['leadTime']);
        self::assertSame('With you in 16 weeks', $result['groups'][1]['leadTime']);
    }

    public function testMadeToOrderGroupWithoutLeadTimeDataHasNoLeadTimeText(): void
    {
        $result = $this->groups->build([$this->option('A', true, true)]);

        self::assertSame('', $result['groups'][0]['leadTime']);
    }

    public function testNoSelectedOptionGivesNullSelected(): void
    {
        $result = $this->groups->build([$this->option('A', true)]);

        self::assertNull($result['selected']);
    }
}
