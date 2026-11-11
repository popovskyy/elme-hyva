<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Plugin;

use Elme\Checkout\Model\PlacedOrderRecovery;
use Elme\Checkout\Plugin\RedirectToSuccessWhenOrderPlacedPlugin;
use Magento\Checkout\Controller\Index\Index;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\TestCase;

class RedirectToSuccessWhenOrderPlacedPluginTest extends TestCase
{
    public function testEmptyCartWithSessionOrderRedirectsToSuccess(): void
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getId')->willReturn(null);
        $quote->method('hasItems')->willReturn(false);

        $checkoutSession = $this->createStub(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $recovery = $this->createStub(PlacedOrderRecovery::class);
        $recovery->method('getSuccessUrl')->willReturn('https://elme.test/checkout/onepage/success/');

        $redirect = $this->createMock(Redirect::class);
        $redirect->expects($this->once())
            ->method('setUrl')
            ->with('https://elme.test/checkout/onepage/success/')
            ->willReturnSelf();

        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $plugin = new RedirectToSuccessWhenOrderPlacedPlugin($checkoutSession, $recovery, $redirectFactory);
        $proceed = $this->createMock(ResultInterface::class);

        $this->assertSame(
            $redirect,
            $plugin->aroundExecute(
                $this->createStub(Index::class),
                static function () use ($proceed) {
                    return $proceed;
                }
            )
        );
    }

    public function testEmptyCartWithoutSessionOrderFallsThrough(): void
    {
        $quote = $this->createStub(Quote::class);
        $quote->method('getId')->willReturn(null);
        $quote->method('hasItems')->willReturn(false);

        $checkoutSession = $this->createStub(CheckoutSession::class);
        $checkoutSession->method('getQuote')->willReturn($quote);

        $recovery = $this->createStub(PlacedOrderRecovery::class);
        $recovery->method('getSuccessUrl')->willReturn(null);

        $plugin = new RedirectToSuccessWhenOrderPlacedPlugin(
            $checkoutSession,
            $recovery,
            $this->createStub(RedirectFactory::class)
        );
        $fallback = $this->createStub(ResultInterface::class);

        $this->assertSame(
            $fallback,
            $plugin->aroundExecute(
                $this->createStub(Index::class),
                static function () use ($fallback) {
                    return $fallback;
                }
            )
        );
    }
}
