<?php

declare(strict_types=1);

namespace Elme\ProductFamily\Test\Unit\Plugin;

use Elme\ProductFamily\Model\FamilyLoader;
use Elme\ProductFamily\Plugin\AddFamilyCacheTag;
use Magento\Catalog\Model\Product;
use PHPUnit\Framework\TestCase;

class AddFamilyCacheTagTest extends TestCase
{
    public function testAddsOldAndNewFamilyTags(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getData')->willReturnMap([[FamilyLoader::ATTR_CODE, null, 'NEW']]);
        $product->method('getOrigData')->willReturnMap([[FamilyLoader::ATTR_CODE, 'OLD']]);

        $result = (new AddFamilyCacheTag())->afterGetIdentities($product, ['cat_p_1']);

        self::assertSame(['cat_p_1', FamilyLoader::cacheTag('NEW'), FamilyLoader::cacheTag('OLD')], $result);
        self::assertMatchesRegularExpression('/^[a-z0-9_]+$/', FamilyLoader::cacheTag('ARES-DT #1'));
    }

    public function testNoTagWithoutFamily(): void
    {
        $product = $this->createStub(Product::class);
        $product->method('getData')->willReturn(null);
        $product->method('getOrigData')->willReturn('');

        self::assertSame(['cat_p_1'], (new AddFamilyCacheTag())->afterGetIdentities($product, ['cat_p_1']));
    }
}
