<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\Preview;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Model\Preview\TargetProvider;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Every failure mode here is silent. A preview link that opens under the wrong
 * store view still returns 200 and still shows a compare-mattress block - just
 * the other brand's theme, which is exactly what someone opened the link to
 * check.
 *
 * @see TargetProvider
 */
class TargetProviderTest extends TestCase
{
    private const TOKEN = 'ad1302185896edfd6abd7f8ee023bd22';

    /**
     * @var StoreManagerInterface&MockObject
     */
    private StoreManagerInterface $storeManager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
    }

    public function testOneSandboxPerStoreTheEntryIsVisibleIn(): void
    {
        $this->storeManager->method('getStores')->willReturn([
            $this->store(1, 'default', 'James Lane Store View'),
            $this->store(2, 'sleeping_giant', 'Sleeping Giant Store View'),
        ]);

        $targets = $this->provider()->getTargets($this->entry([0]), self::TOKEN);

        $this->assertCount(2, $targets);
        $this->assertSame(['James Lane Store View', 'Sleeping Giant Store View'], array_column($targets, 'group'));
    }

    public function testEntryScopedToOneStoreIsNotOfferedOnTheOthers(): void
    {
        $this->storeManager->method('getStores')->willReturn([
            $this->store(1, 'default', 'James Lane Store View'),
            $this->store(2, 'sleeping_giant', 'Sleeping Giant Store View'),
        ]);

        $targets = $this->provider()->getTargets($this->entry([2]), self::TOKEN);

        $this->assertSame(['Sleeping Giant Store View'], array_column($targets, 'group'));
    }

    /**
     * Page-mode's live preview passes `$allStores = true` so its store switcher
     * always offers every brand, even one the entry has not been assigned to
     * yet - the whole point of previewing before switching an entry on.
     *
     * @return void
     */
    public function testAllStoresIgnoresTheEntrysOwnAssignment(): void
    {
        $this->storeManager->method('getStores')->willReturn([
            $this->store(1, 'default', 'James Lane Store View'),
            $this->store(2, 'sleeping_giant', 'Sleeping Giant Store View'),
            $this->store(5, 'elme_living', 'Elme Living Store View'),
        ]);

        $targets = $this->provider()->getTargets($this->entry([2]), self::TOKEN, true);

        $this->assertSame(
            ['James Lane Store View', 'Sleeping Giant Store View', 'Elme Living Store View'],
            array_column($targets, 'group')
        );
    }

    /**
     * Even with `$allStores = true`, a retired store view has no theme to
     * preview under - the flag widens *assignment*, not activeness.
     *
     * @return void
     */
    public function testAllStoresStillSkipsInactiveStoreViews(): void
    {
        $this->storeManager->method('getStores')->willReturn([
            $this->store(1, 'default', 'James Lane Store View'),
            $this->store(9, 'retired', 'Retired Store View', false),
        ]);

        $targets = $this->provider()->getTargets($this->entry([1]), self::TOKEN, true);

        $this->assertSame(['James Lane Store View'], array_column($targets, 'group'));
    }

    /**
     * `___store` naming an inactive view is ignored by the store resolver, so
     * the link falls through to whatever that domain defaults to and quietly
     * renders another brand's theme.
     *
     * @return void
     */
    public function testInactiveStoreViewIsNotOffered(): void
    {
        $this->storeManager->method('getStores')->willReturn([
            $this->store(1, 'default', 'James Lane Store View'),
            $this->store(9, 'retired', 'Retired Store View', false),
        ]);

        $targets = $this->provider()->getTargets($this->entry([0]), self::TOKEN);

        $this->assertSame(['James Lane Store View'], array_column($targets, 'group'));
    }

    /**
     * `Store::getUrl()` goes through the injected UrlInterface, which in the
     * admin area is the *backend* builder - the sandbox link comes back as
     * `/admin/.../key/<secret>/`, an admin route that does not exist, and 404s.
     *
     * @return void
     */
    public function testSandboxLinkIsAStorefrontUrlNotAnAdminOne(): void
    {
        $this->storeManager->method('getStores')->willReturn([$this->store(2, 'sleeping_giant', 'SG')]);

        $targets = $this->provider()->getTargets($this->entry([2]), self::TOKEN);

        $this->assertStringStartsWith(
            'https://sleeping_giant.test/bart_content_studio/preview/sandbox/?',
            $targets[0]['url']
        );
    }

    public function testLinkCarriesTheTokenAndPinsTheStore(): void
    {
        $this->storeManager->method('getStores')->willReturn([$this->store(2, 'sleeping_giant', 'SG')]);

        $targets = $this->provider()->getTargets($this->entry([2]), self::TOKEN);
        parse_str((string)parse_url($targets[0]['url'], PHP_URL_QUERY), $query);

        $this->assertSame(self::TOKEN, $query['cs_preview']);
        $this->assertSame('sleeping_giant', $query['___store']);
    }

    /**
     * `storeId`/`storeCode` are what the editor matches a target against - its
     * own store selector is built from `Editor::getStoreOptions()`, a separate
     * call with its own ids. Two stores sharing a display name would make
     * matching on `group` silently pick the wrong one.
     *
     * @return void
     */
    public function testTargetCarriesTheStoreIdAndCodeForMatching(): void
    {
        $this->storeManager->method('getStores')->willReturn([$this->store(2, 'sleeping_giant', 'SG')]);

        $targets = $this->provider()->getTargets($this->entry([2]), self::TOKEN);

        $this->assertSame(2, $targets[0]['storeId']);
        $this->assertSame('sleeping_giant', $targets[0]['storeCode']);
    }

    /**
     * @return TargetProvider
     */
    private function provider(): TargetProvider
    {
        return new TargetProvider($this->storeManager);
    }

    /**
     * @param int $id
     * @param string $code
     * @param string $name
     * @param bool $isActive
     * @return StoreInterface&MockObject
     */
    private function store(int $id, string $code, string $name, bool $isActive = true): StoreInterface
    {
        // The Store model, not StoreInterface: getBaseUrl() is not on the
        // interface, and PHPUnit 12 has no addMethods() to graft it on.
        $store = $this->createMock(Store::class);

        $store->method('getId')->willReturn($id);
        $store->method('getCode')->willReturn($code);
        $store->method('getName')->willReturn($name);
        $store->method('getIsActive')->willReturn($isActive);
        $store->method('getBaseUrl')->willReturn('https://' . $code . '.test/');

        return $store;
    }

    /**
     * @param int[] $storeIds
     * @return EntryInterface&MockObject
     */
    private function entry(array $storeIds): EntryInterface
    {
        $entry = $this->createMock(EntryInterface::class);

        $entry->method('getType')->willReturn('compare_mattress');
        $entry->method('getIdentifier')->willReturn('compare_mattress_config');
        $entry->method('getStoreIds')->willReturn($storeIds);

        return $entry;
    }
}
