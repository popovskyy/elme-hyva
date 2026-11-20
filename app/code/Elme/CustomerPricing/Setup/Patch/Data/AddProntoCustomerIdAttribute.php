<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Setup\Patch\Data;

use Elme\CustomerPricing\Model\ProntoCustomerResolver;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Attribute as AttributeResource;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * BE-07: customer attribute pronto_customer_id, the link to the customer's Pronto prices. Admin only: the customer
 * neither sees nor edits it. Shown and searchable in the admin customer grid.
 */
class AddProntoCustomerIdAttribute implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly AttributeResource $attributeResource
    ) {
    }

    public function apply(): self
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $code = ProntoCustomerResolver::ATTRIBUTE_CODE;

        $customerSetup->addAttribute(Customer::ENTITY, $code, [
            'type' => 'varchar',
            'label' => 'Pronto Customer ID',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'user_defined' => true,
            'system' => false,
            'position' => 200,
            'sort_order' => 200,
            'is_used_in_grid' => true,
            'is_visible_in_grid' => true,
            'is_filterable_in_grid' => true,
            'is_searchable_in_grid' => true,
            'validate_rules' => '{"max_text_length":64}',
        ]);

        $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, $code);
        $attribute->addData([
            'attribute_set_id' => $customerSetup->getDefaultAttributeSetId(Customer::ENTITY),
            'attribute_group_id' => $customerSetup->getDefaultAttributeGroupId(Customer::ENTITY),
            'used_in_forms' => ['adminhtml_customer'],
        ]);
        $this->attributeResource->save($attribute);

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
