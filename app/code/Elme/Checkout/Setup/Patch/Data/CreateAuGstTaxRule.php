<?php

declare(strict_types=1);

namespace Elme\Checkout\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;
use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\Data\TaxRuleInterfaceFactory;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;

/**
 * BE-01: AU GST, 10% on the default product tax class for the default customer tax class, freight included.
 * Display settings are in etc/config.xml.
 */
class CreateAuGstTaxRule implements DataPatchInterface
{
    private const RATE_CODE = 'AU GST 10%';

    private const RULE_CODE = 'AU GST';

    private const XML_PATH_PRODUCT_CLASS = 'tax/classes/default_product_tax_class';

    private const XML_PATH_CUSTOMER_CLASS = 'tax/classes/default_customer_tax_class';

    private const XML_PATH_SHIPPING_CLASS = 'tax/classes/shipping_tax_class';

    public function __construct(
        private readonly TaxRateInterfaceFactory $rateFactory,
        private readonly TaxRateRepositoryInterface $rateRepository,
        private readonly TaxRuleInterfaceFactory $ruleFactory,
        private readonly TaxRuleRepositoryInterface $ruleRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): self
    {
        $productClassId = (int) $this->scopeConfig->getValue(self::XML_PATH_PRODUCT_CLASS);
        $customerClassId = (int) $this->scopeConfig->getValue(self::XML_PATH_CUSTOMER_CLASS);

        $rate = $this->rateFactory->create()
            ->setCode(self::RATE_CODE)
            ->setTaxCountryId('AU')
            ->setTaxRegionId(0)
            ->setTaxPostcode('*')
            ->setRate(10);
        $rate = $this->rateRepository->save($rate);

        $rule = $this->ruleFactory->create()
            ->setCode(self::RULE_CODE)
            ->setPriority(0)
            ->setPosition(0)
            ->setCalculateSubtotal(false)
            ->setCustomerTaxClassIds([$customerClassId])
            ->setProductTaxClassIds([$productClassId])
            ->setTaxRateIds([(int) $rate->getId()]);
        $this->ruleRepository->save($rule);

        $this->configWriter->save(
            self::XML_PATH_SHIPPING_CLASS,
            (string) $productClassId,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            Store::DEFAULT_STORE_ID
        );

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
