<?php

declare(strict_types=1);

namespace Elme\ThirdParty\Test\Unit\Model\OutletSignup;

use Elme\ThirdParty\Model\OutletSignup\Subscriber;
use Elme\ThirdParty\ViewModel\Config as ThirdPartyConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SubscriberTest extends TestCase
{
    public function testSubscribePostsToConfiguredList(): void
    {
        $config = $this->createStub(ThirdPartyConfig::class);
        $config->method('getKlaviyoPublicApiKey')->willReturn('TMvimz');
        $config->method('getOutletListId')->willReturn('TNqMx7');

        $curl = $this->createMock(Curl::class);
        $curl->expects(self::once())->method('setTimeout')->with(15);
        $curl->expects(self::exactly(3))->method('addHeader');
        $curl->expects(self::once())
            ->method('post')
            ->with(
                'https://a.klaviyo.com/client/subscriptions/?company_id=TMvimz',
                self::callback(static function (string $body): bool {
                    $data = json_decode($body, true);
                    return ($data['data']['relationships']['list']['data']['id'] ?? '') === 'TNqMx7'
                        && ($data['data']['attributes']['profile']['data']['attributes']['email'] ?? '')
                            === 'buyer@example.com'
                        && ($data['data']['attributes']['profile']['data']['attributes']['phone_number'] ?? '')
                            === '+61412345678';
                })
            );
        $curl->method('getStatus')->willReturn(202);

        $curlFactory = $this->createStub(CurlFactory::class);
        $curlFactory->method('create')->willReturn($curl);

        $subscriber = new Subscriber(
            $config,
            $curlFactory,
            new Json(),
            $this->createStub(LoggerInterface::class)
        );

        $subscriber->subscribe([
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'buyer@example.com',
            'phone' => '0412345678',
        ]);
    }

    public function testSubscribeRejectsInvalidEmail(): void
    {
        $config = $this->createStub(ThirdPartyConfig::class);
        $config->method('getKlaviyoPublicApiKey')->willReturn('TMvimz');
        $config->method('getOutletListId')->willReturn('TNqMx7');

        $subscriber = new Subscriber(
            $config,
            $this->createStub(CurlFactory::class),
            new Json(),
            $this->createStub(LoggerInterface::class)
        );

        $this->expectException(LocalizedException::class);
        $subscriber->subscribe([
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'not-an-email',
            'phone' => '0412345678',
        ]);
    }

    public function testSubscribeRequiresConfig(): void
    {
        $config = $this->createStub(ThirdPartyConfig::class);
        $config->method('getKlaviyoPublicApiKey')->willReturn('');
        $config->method('getOutletListId')->willReturn('TNqMx7');

        $subscriber = new Subscriber(
            $config,
            $this->createStub(CurlFactory::class),
            new Json(),
            $this->createStub(LoggerInterface::class)
        );

        $this->expectException(LocalizedException::class);
        $subscriber->subscribe([
            'first_name' => 'Ada',
            'last_name' => 'Buyer',
            'email' => 'buyer@example.com',
            'phone' => '0412345678',
        ]);
    }
}
