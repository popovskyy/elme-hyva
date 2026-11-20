<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Test\Unit\Plugin;

use Elme\LoginOnly\Plugin\CloseAnonymousWebapiPlugin;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Webapi\Authorization;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use PHPUnit\Framework\TestCase;

class CloseAnonymousWebapiPluginTest extends TestCase
{
    public function testGuestIsRefusedOnAnAnonymousRoute(): void
    {
        $this->assertFalse($this->isAllowed(null, '/V1/guest-carts', true, ['anonymous']));
    }

    public function testGuestKeepsAnAllowedPath(): void
    {
        $this->assertTrue($this->isAllowed(null, '/V1/integration/admin/token', true, ['anonymous']));
    }

    public function testAllowedPathMustMatchExactly(): void
    {
        $this->assertFalse($this->isAllowed(null, '/V1/integration/admin/token/x', true, ['anonymous']));
    }

    public function testLoggedInUserKeepsAnAnonymousRoute(): void
    {
        $this->assertTrue($this->isAllowed(7, '/V1/directory/countries', true, ['anonymous']));
    }

    public function testRefusalOfCoreStays(): void
    {
        $this->assertFalse($this->isAllowed(null, '/V1/integration/admin/token', false, ['anonymous']));
    }

    public function testOtherResourcesAreLeftToCore(): void
    {
        $this->assertTrue($this->isAllowed(null, '/V1/products', true, ['Magento_Catalog::products']));
    }

    /**
     * @param int|null $userId
     * @param string $path
     * @param bool $coreAnswer
     * @param string[] $resources
     * @return bool
     */
    private function isAllowed(?int $userId, string $path, bool $coreAnswer, array $resources): bool
    {
        $userContext = $this->createStub(UserContextInterface::class);
        $userContext->method('getUserId')->willReturn($userId);
        $request = $this->createStub(RestRequest::class);
        $request->method('getPathInfo')->willReturn($path);

        return (new CloseAnonymousWebapiPlugin($userContext, $request, ['/V1/integration/admin/token']))
            ->afterIsAllowed($this->createStub(Authorization::class), $coreAnswer, $resources);
    }
}
