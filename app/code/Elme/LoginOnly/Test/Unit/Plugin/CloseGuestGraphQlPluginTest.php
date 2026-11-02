<?php

declare(strict_types=1);

namespace Elme\LoginOnly\Test\Unit\Plugin;

use Elme\LoginOnly\Plugin\CloseGuestGraphQlPlugin;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\GraphQl\Controller\GraphQl;
use PHPUnit\Framework\TestCase;

class CloseGuestGraphQlPluginTest extends TestCase
{
    public function testGuestGetsARefusalAndTheQueryNeverRuns(): void
    {
        $response = $this->createMock(HttpResponse::class);
        $response->expects($this->once())->method('setHttpResponseCode')->with(401);
        $response->expects($this->once())->method('setBody')->with($this->stringContains('"errors"'));

        $result = $this->plugin(null, $response)->aroundDispatch(
            $this->createStub(GraphQl::class),
            fn () => $this->fail('A guest query must not run'),
            $this->createStub(RequestInterface::class)
        );

        $this->assertSame($response, $result);
    }

    public function testLoggedInUserGetsTheQuery(): void
    {
        $answer = $this->createStub(ResponseInterface::class);
        $response = $this->createMock(HttpResponse::class);
        $response->expects($this->never())->method('setHttpResponseCode');

        $result = $this->plugin(7, $response)->aroundDispatch(
            $this->createStub(GraphQl::class),
            static fn (): ResponseInterface => $answer,
            $this->createStub(RequestInterface::class)
        );

        $this->assertSame($answer, $result);
    }

    private function plugin(?int $userId, HttpResponse $response): CloseGuestGraphQlPlugin
    {
        $userContext = $this->createStub(UserContextInterface::class);
        $userContext->method('getUserId')->willReturn($userId);

        return new CloseGuestGraphQlPlugin($userContext, $response, new Json());
    }
}
