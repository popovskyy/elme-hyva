<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Plugin;

use Elme\Checkout\Plugin\RefuseApiOrderPlugin;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

class RefuseApiOrderPluginTest extends TestCase
{
    /**
     * @return array<string, array{int|null}>
     */
    public static function storefrontUsers(): array
    {
        return [
            'customer' => [UserContextInterface::USER_TYPE_CUSTOMER],
            'guest' => [UserContextInterface::USER_TYPE_GUEST],
            'no user' => [null],
        ];
    }

    #[DataProvider('storefrontUsers')]
    public function testStorefrontUsersAreRefused(?int $userType): void
    {
        $this->expectException(LocalizedException::class);

        $this->place($userType);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function backOfficeUsers(): array
    {
        return [
            'admin' => [UserContextInterface::USER_TYPE_ADMIN],
            'integration' => [UserContextInterface::USER_TYPE_INTEGRATION],
        ];
    }

    #[DataProvider('backOfficeUsers')]
    #[DoesNotPerformAssertions]
    public function testBackOfficeUsersPass(int $userType): void
    {
        $this->place($userType);
    }

    private function place(?int $userType): void
    {
        $userContext = $this->createStub(UserContextInterface::class);
        $userContext->method('getUserType')->willReturn($userType);

        (new RefuseApiOrderPlugin($userContext))->beforePlaceOrder($this->createStub(CartManagementInterface::class));
    }
}
