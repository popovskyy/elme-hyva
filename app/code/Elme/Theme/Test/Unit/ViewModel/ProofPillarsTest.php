<?php

declare(strict_types=1);

namespace Elme\Theme\Test\Unit\ViewModel;

use Elme\Theme\ViewModel\ProofPillars;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @see ProofPillars
 */
class ProofPillarsTest extends TestCase
{
    public function testItemsWithoutTitleAreDroppedAndLinksNeedBothFields(): void
    {
        $proof = new ProofPillars(
            $this->createMock(StoreManagerInterface::class),
            $this->createMock(Filesystem::class)
        );

        $items = $proof->getItems([
            'items' => [
                ['title' => '', 'text' => 'x'],
                [
                    'title' => 'New lines',
                    'text' => 'Fresh stock.',
                    'link_label' => 'Shop New',
                    'link' => '/new.html',
                ],
                [
                    'title' => 'No link',
                    'text' => 'Plain.',
                    'link_label' => 'Missing',
                    'link' => '',
                ],
            ],
        ]);

        $this->assertCount(2, $items);
        $this->assertSame('/new.html', $items[0]['link']);
        $this->assertSame('', $items[1]['link']);
    }
}
