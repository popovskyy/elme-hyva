<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Template;

use Bart\ContentStudio\Model\Template\SafeFilter;
use Magento\Email\Model\Template\Filter;
use Magento\Framework\Filter\DirectiveProcessor\LegacyDirective;
use Magento\Framework\Filter\DirectiveProcessor\SimpleDirective;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The security contract of the module: entry content resolves `{{media}}` but
 * can never instantiate a block or run layout XML. Dispatch goes through
 * `LegacyDirective`'s reflection lookup, which is what production uses, so the
 * test fails the same way a renamed method would at runtime.
 *
 * @see SafeFilter
 */
class SafeFilterTest extends TestCase
{
    private const MEDIA_URL = 'https://example.test/media/';

    private SafeFilter $filter;

    private LegacyDirective $directives;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn(self::MEDIA_URL);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        // The parent constructor resolves half of Magento through the global
        // ObjectManager, which no unit test has; none of that is reachable from
        // the three directives under test, so only the store manager is wired.
        $this->filter = (new \ReflectionClass(SafeFilter::class))->newInstanceWithoutConstructor();

        $storeManagerProperty = new \ReflectionProperty(Filter::class, '_storeManager');
        $storeManagerProperty->setAccessible(true);
        $storeManagerProperty->setValue($this->filter, $storeManager);

        $this->directives = new LegacyDirective($this->createMock(SimpleDirective::class));
    }

    /**
     * @param string $construction
     */
    #[DataProvider('disabledDirectiveProvider')]
    public function testDisabledDirectivesResolveToNothing(string $construction): void
    {
        $this->assertSame('', $this->process($construction));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function disabledDirectiveProvider(): array
    {
        return [
            'block by class' => ['{{block class="Magento\\Framework\\View\\Element\\Template"}}'],
            'block by id' => ['{{block id="header"}}'],
            'block with template' => ['{{block class="Some\\Block" template="Some_Module::x.phtml"}}'],
            'layout handle' => ['{{layout handle="catalog_product_view"}}'],
            'layout area' => ['{{layout area="frontend" handle="cms_index_index"}}'],
        ];
    }

    public function testMediaDirectiveStillResolves(): void
    {
        $this->assertSame(
            self::MEDIA_URL . 'contentstudio/hero.jpg',
            $this->process('{{media url="contentstudio/hero.jpg"}}')
        );
    }

    /**
     * Overriding either directive with a non-empty return, or removing the
     * `extends Filter`, would drop the whole media/store/config surface.
     */
    public function testOnlyBlockAndLayoutAreOverridden(): void
    {
        $reflection = new \ReflectionClass(SafeFilter::class);

        $overridden = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            array_filter(
                $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
                static fn (\ReflectionMethod $method): bool
                    => $method->getDeclaringClass()->getName() === SafeFilter::class
            )
        );

        sort($overridden);

        $this->assertSame(['blockDirective', 'layoutDirective'], $overridden);
        $this->assertInstanceOf(Filter::class, $this->filter);
    }

    /**
     * Runs a directive the way `Magento\Framework\Filter\Template::filter()`
     * would, without the depth-meter plumbing a unit test cannot wire up.
     *
     * @param string $construction
     * @return string
     */
    private function process(string $construction): string
    {
        preg_match(\Magento\Framework\Filter\Template::CONSTRUCTION_PATTERN, $construction, $matches);

        return $this->directives->process($matches, $this->filter, []);
    }
}
