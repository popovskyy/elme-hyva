<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Plugin;

use Elme\Checkout\Plugin\RefuseOnepageOrderPlugin;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class RefuseOnepageOrderPluginTest extends TestCase
{
    public function testCoreSaveOrderIsRefused(): void
    {
        $this->expectException(LocalizedException::class);

        (new RefuseOnepageOrderPlugin())->beforeSaveOrder($this->createStub(Onepage::class));
    }
}
