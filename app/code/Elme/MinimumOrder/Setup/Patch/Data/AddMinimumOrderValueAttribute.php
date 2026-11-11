<?php

declare(strict_types=1);

namespace Elme\MinimumOrder\Setup\Patch\Data;

use Elme\MinimumOrder\Model\MinimumOrderValue;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\ResourceModel\Attribute as AttributeResource;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * BE-08: customer attribute minimum_order_value. Empty falls back to sales/minimum_order/amount.
 * Admin only: the customer neither sees nor edits it on the storefront.
 */
class AddMinimumOrderValueAttribute implements DataPatchInterface, PatchRevertableInterface
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
        $code = MinimumOrderValue::ATTRIBUTE_CODE;

        $customerSetup->addAttribute(Customer::ENTITY, $code, [
            'type' => 'decimal',
            'label' => 'Minimum Order Value',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'user_defined' => true,
            'system' => false,
            'position' => 210,
            'sort_order' => 210,
            'is_used_in_grid' => true,
            'is_visible_in_grid' => true,
            'is_filterable_in_grid' => true,
            'is_searchable_in_grid' => false,
            'validate_rules' => '{"input_validation":"numeric"}',
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

    public function revert(): void
    {
        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $customerSetup->removeAttribute(Customer::ENTITY, MinimumOrderValue::ATTRIBUTE_CODE);
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
