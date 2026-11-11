<?php

declare(strict_types=1);

namespace Elme\Checkout\Test\Unit\Controller\Action;

use Elme\Checkout\Api\CheckoutManagementInterface;
use Elme\Checkout\Controller\Action\PlaceOrder;
use Elme\Checkout\Model\PlacedOrderRecovery;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlaceOrderTest extends TestCase
{
    private CustomerSession&MockObject $customerSession;
    private CheckoutSession&MockObject $checkoutSession;
    private UrlInterface&MockObject $urlBuilder;
    private JsonResult&MockObject $jsonResult;
    private PlacedOrderRecovery&MockObject $placedOrderRecovery;
    private PlaceOrder $action;

    protected function setUp(): void
    {
        $this->customerSession = $this->createMock(CustomerSession::class);
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->jsonResult = $this->createMock(JsonResult::class);
        $this->placedOrderRecovery = $this->createMock(PlacedOrderRecovery::class);

        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($this->jsonResult);

        $this->jsonResult->method('setHeader')->willReturnSelf();
        $this->jsonResult->method('setHttpResponseCode')->willReturnSelf();
        $this->jsonResult->method('setData')->willReturnSelf();

        $this->action = new PlaceOrder(
            $this->createStub(RequestInterface::class),
            $jsonFactory,
            $this->customerSession,
            $this->createStub(CustomerUrl::class),
            $this->checkoutSession,
            $this->urlBuilder,
            $this->createStub(Json::class),
            $this->createStub(LoggerInterface::class),
            $this->createStub(FormKeyValidator::class),
            $this->createStub(CheckoutManagementInterface::class),
            $this->placedOrderRecovery
        );
    }

    public function testEmptyCartWithSessionOrderRedirectsToSuccess(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $quote = $this->createStub(Quote::class);
        $quote->method('getId')->willReturn(null);
        $quote->method('hasItems')->willReturn(false);
        $this->checkoutSession->method('getQuote')->willReturn($quote);

        $this->placedOrderRecovery->method('getSuccessUrl')->willReturn('https://elme.test/checkout/onepage/success/');
        $this->urlBuilder->method('getUrl')->with('checkout/onepage/success')->willReturn(
            'https://elme.test/checkout/onepage/success/'
        );

        $this->jsonResult->expects($this->once())->method('setHttpResponseCode')->with(200);
        $this->jsonResult->expects($this->once())->method('setData')->with([
            'ok' => true,
            'redirect' => 'https://elme.test/checkout/onepage/success/',
        ]);

        $this->assertSame($this->jsonResult, $this->action->execute());
    }

    public function testEmptyCartWithoutSessionOrderStillRedirectsToCart(): void
    {
        $this->customerSession->method('isLoggedIn')->willReturn(true);
        $quote = $this->createStub(Quote::class);
        $quote->method('getId')->willReturn(null);
        $quote->method('hasItems')->willReturn(false);
        $this->checkoutSession->method('getQuote')->willReturn($quote);

        $this->placedOrderRecovery->method('getSuccessUrl')->willReturn(null);
        $this->urlBuilder->method('getUrl')->with('checkout/cart')->willReturn('https://elme.test/checkout/cart/');

        $this->jsonResult->expects($this->once())->method('setHttpResponseCode')->with(409);
        $this->jsonResult->expects($this->once())->method('setData')->with([
            'ok' => false,
            'message' => (string) __('Your cart is empty.'),
            'redirect' => 'https://elme.test/checkout/cart/',
        ]);

        $this->assertSame($this->jsonResult, $this->action->execute());
    }
}
