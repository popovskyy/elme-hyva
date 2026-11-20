<?php

declare(strict_types=1);

namespace Elme\Checkout\Model\Carrier;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Rate\ResultFactory;
use Psr\Log\LoggerInterface;

/**
 * A test carrier that writes a line to var/log/elme_checkout_carrier.log for every rate request it gets, so the
 * carrier requests of a checkout action can be counted. Off by default (carriers/elme_rate_counter/active); its
 * price comes from carriers/elme_rate_counter/price, so a price change between page load and Place order can be
 * tried out. Not for a live store: switch it on only to measure.
 */
class RateCounter extends AbstractCarrier implements CarrierInterface
{
    public const CODE = 'elme_rate_counter';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param RequestInterface $request
     * @param LoggerInterface $counterLogger Writes var/log/elme_checkout_carrier.log (etc/di.xml)
     * @param mixed[] $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        private readonly ResultFactory $rateResultFactory,
        private readonly MethodFactory $rateMethodFactory,
        private readonly RequestInterface $request,
        private readonly LoggerInterface $counterLogger,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
    }

    /**
     * @param RateRequest $request
     * @return Result|false
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $price = (float) $this->getConfigData('price');
        $items = $request->getAllItems() ?: [];
        $first = reset($items);

        $this->counterLogger->info(sprintf(
            'collectRates quote=%s action=%s postcode=%s items=%d price=%s',
            $first ? (string) $first->getQuote()?->getId() : '-',
            $this->request instanceof HttpRequest ? $this->request->getPathInfo() : '-',
            (string) $request->getDestPostcode(),
            count($items),
            $price
        ));

        $method = $this->rateMethodFactory->create();
        $method->setCarrier(self::CODE);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod('counted');
        $method->setMethodTitle($this->getConfigData('name'));
        $method->setPrice($price);
        $method->setCost($price);

        $result = $this->rateResultFactory->create();
        $result->append($method);

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function getAllowedMethods()
    {
        return ['counted' => (string) $this->getConfigData('name')];
    }
}
