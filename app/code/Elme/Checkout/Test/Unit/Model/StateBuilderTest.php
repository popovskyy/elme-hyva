<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Model;

use Elme\Checkout\Api\SectionProviderInterface;
use Elme\Checkout\Model\CheckoutState;
use Elme\Checkout\Model\CheckoutStateFactory;
use Elme\Checkout\Model\StateBuilder;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class StateBuilderTest extends TestCase
{
    public function testSectionsFollowSortOrderNotPoolOrder(): void
    {
        $state = $this->builder([
            'payment' => ['sortOrder' => 30, 'provider' => $this->provider(['code' => 'payment'])],
            'summary' => ['sortOrder' => 10, 'provider' => $this->provider(['code' => 'summary'])],
            'address' => ['sortOrder' => 20, 'provider' => $this->provider(['code' => 'address'])],
        ])->build($this->createStub(CartInterface::class));

        $this->assertSame(['summary', 'address', 'payment'], array_keys($state->getSections()));
        $this->assertSame([], $state->getErrors());
    }

    public function testFailingProviderOnlyCostsItsOwnSection(): void
    {
        $failing = $this->createStub(SectionProviderInterface::class);
        $failing->method('getData')->willThrowException(new RuntimeException('Carrier is down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('"shipping" failed: Carrier is down'));

        $state = $this->builder([
            'summary' => ['sortOrder' => 10, 'provider' => $this->provider(['code' => 'summary'])],
            'shipping' => ['sortOrder' => 20, 'provider' => $failing],
            'payment' => ['sortOrder' => 30, 'provider' => $this->provider(['code' => 'payment'])],
        ], $logger)->build($this->createStub(CartInterface::class));

        $this->assertSame(
            ['summary' => ['code' => 'summary'], 'shipping' => null, 'payment' => ['code' => 'payment']],
            $state->getSections()
        );
        $this->assertSame(['shipping'], array_keys($state->getErrors()));
        $this->assertStringNotContainsString('Carrier is down', $state->getErrors()['shipping']);
    }

    /**
     * @param mixed[] $providers
     * @param LoggerInterface|null $logger
     * @return StateBuilder
     */
    private function builder(array $providers, ?LoggerInterface $logger = null): StateBuilder
    {
        $factory = $this->createStub(CheckoutStateFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (array $data): CheckoutState => new CheckoutState($data['sections'], $data['errors'])
        );

        return new StateBuilder($factory, $logger ?? $this->createStub(LoggerInterface::class), $providers);
    }

    /**
     * @param mixed[] $data
     * @return SectionProviderInterface
     */
    private function provider(array $data): SectionProviderInterface
    {
        $provider = $this->createStub(SectionProviderInterface::class);
        $provider->method('getData')->willReturn($data);

        return $provider;
    }
}
