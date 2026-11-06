<?php

declare(strict_types=1);

// Test data of qty-rules.spec.mjs, run by seed.php: a product with MOQ 6, one without, one that gets its MOQ during a
// test, and their own customer with an empty cart, so these tests leave the checkout tests alone. Safe to re-run.

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

$qtyEmail = 'e2e-qty@example.com';
$qtyProducts = ['E2E-MOQ6' => 6, 'E2E-NO-MOQ' => 1, 'E2E-MOQ-LATER' => 1];

foreach ($qtyProducts as $sku => $moq) {
    try {
        $qtyProduct = $products->get($sku, true, 0, true);
    } catch (NoSuchEntityException) {
        $qtyProduct = $om->get(ProductFactory::class)->create();
        $qtyProduct->setSku($sku)->setAttributeSetId(4)->setTypeId('simple');
    }
    $qtyProduct->setStoreId(0)->setName('E2E ' . $sku)->setPrice(100)->setWeight(1)
        ->setStatus(Status::STATUS_ENABLED)->setVisibility(Visibility::VISIBILITY_BOTH)
        ->setWebsiteIds([$websiteId])->setTaxClassId(2)->setUrlKey(strtolower($sku));
    $qtyProduct = $products->save($qtyProduct);

    $qtySource = $om->get(SourceItemInterfaceFactory::class)->create();
    $qtySource->setSourceCode('default');
    $qtySource->setSku($sku);
    $qtySource->setQuantity(10000);
    $qtySource->setStatus(SourceItemInterface::STATUS_IN_STOCK);
    $om->get(SourceItemsSaveInterface::class)->execute([$qtySource]);

    $qtyStockItem = $stockRegistry->getStockItem((int) $qtyProduct->getId(), $websiteId);
    $qtyStockItem->setIsInStock(true)->setQty(10000);
    $qtyStockItem->setUseConfigMinSaleQty(false)->setMinSaleQty($moq);
    $stockRegistry->updateStockItemBySku($sku, $qtyStockItem);
}

try {
    $qtyCustomer = $customers->get($qtyEmail, $websiteId);
} catch (NoSuchEntityException) {
    $qtyCustomer = $om->get(CustomerInterfaceFactory::class)->create();
    $qtyCustomer->setWebsiteId($websiteId)->setStoreId(1)->setEmail($qtyEmail)->setFirstname('E2E')->setLastname('Qty');
    $qtyCustomer = $om->get(AccountManagementInterface::class)->createAccount($qtyCustomer, E2E_PASSWORD);
}

if (!$qtyCustomer->getAddresses()) {
    $qtyAddress = $om->get(AddressInterfaceFactory::class)->create();
    $qtyAddress->setCustomerId($qtyCustomer->getId())->setFirstname('E2E')->setLastname('Qty')
        ->setCompany('Elme E2E')->setStreet(['1 Test Street'])->setCity('Melbourne')->setPostcode('3000')
        ->setCountryId('AU')->setRegionId((int) $om->get(RegionFactory::class)->create()->loadByCode('VIC', 'AU')->getId())
        ->setTelephone('0312345678')->setIsDefaultShipping(true)->setIsDefaultBilling(true);
    $om->get(AddressRepositoryInterface::class)->save($qtyAddress);
}

try {
    $qtyQuote = $quotes->getActiveForCustomer((int) $qtyCustomer->getId());
    $qtyQuote->setIsActive(false);
    $quotes->save($qtyQuote);
} catch (NoSuchEntityException) {
    echo "no qty rules cart to clear\n";
}
