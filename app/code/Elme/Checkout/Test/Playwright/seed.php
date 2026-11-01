<?php

declare(strict_types=1);

// Test data of the checkout e2e test: a product, a customer with a saved address and an empty cart. Safe to re-run.
// Local only, in the PHP container; global-setup.mjs runs it before the tests.

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

const E2E_SKU = 'E2E-CHECKOUT';
const E2E_EMAIL = 'e2e-checkout@example.com';
const E2E_PASSWORD = 'E2eCheckout!2026';

require '/var/www/html/app/bootstrap.php';

$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(State::class)->setAreaCode('frontend');
$storeManager = $om->get(StoreManagerInterface::class);
$store = $storeManager->getStore(1);
$storeManager->setCurrentStore($store);
$websiteId = (int) $store->getWebsiteId();

$products = $om->get(ProductRepositoryInterface::class);
try {
    $product = $products->get(E2E_SKU, true, 0, true);
} catch (NoSuchEntityException) {
    $product = $om->get(ProductFactory::class)->create();
    $product->setSku(E2E_SKU)->setAttributeSetId(4)->setTypeId('simple');
}
$product->setStoreId(0)->setName('E2E checkout product')->setPrice(100)->setWeight(1)
    ->setStatus(Status::STATUS_ENABLED)->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setWebsiteIds([$websiteId])->setTaxClassId(2)->setUrlKey('e2e-checkout-product');
$saved = $products->save($product);

$sourceItem =$om->get(SourceItemInterfaceFactory::class)->create();
$sourceItem->setSourceCode('default');
$sourceItem->setSku(E2E_SKU);
$sourceItem->setQuantity(10000);
$sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
$om->get(SourceItemsSaveInterface::class)->execute([$sourceItem]);
$stockRegistry = $om->get(StockRegistryInterface::class);
$stockItem = $stockRegistry->getStockItem((int) $saved->getId(), $websiteId);
$stockItem->setIsInStock(true);
$stockItem->setQty(10000);
$stockRegistry->updateStockItemBySku(E2E_SKU, $stockItem);

$customers = $om->get(CustomerRepositoryInterface::class);
try {
    $customer = $customers->get(E2E_EMAIL, $websiteId);
} catch (NoSuchEntityException) {
    $customer = $om->get(CustomerInterfaceFactory::class)->create();
    $customer->setWebsiteId($websiteId)->setStoreId(1)->setEmail(E2E_EMAIL)->setFirstname('E2E')->setLastname('Checkout');
    $customer = $om->get(AccountManagementInterface::class)->createAccount($customer, E2E_PASSWORD);
}

if (!$customer->getAddresses()) {
    $address = $om->get(AddressInterfaceFactory::class)->create();
    $address->setCustomerId($customer->getId())->setFirstname('E2E')->setLastname('Checkout')
        ->setCompany('Elme E2E')->setStreet(['1 Test Street'])->setCity('Melbourne')->setPostcode('3000')
        ->setCountryId('AU')->setRegionId(
            (int) $om->get(\Magento\Directory\Model\RegionFactory::class)->create()->loadByCode('VIC', 'AU')->getId()
        )->setTelephone('0312345678')->setIsDefaultShipping(true)->setIsDefaultBilling(true);
    $om->get(AddressRepositoryInterface::class)->save($address);
}

$quotes = $om->get(CartRepositoryInterface::class);
try {
    $quote = $quotes->getActiveForCustomer((int) $customer->getId());
    $quote->setIsActive(false);
    $quotes->save($quote);
} catch (NoSuchEntityException) {
    echo "no cart to clear\n";
}

echo json_encode(['sku' => E2E_SKU, 'email' => E2E_EMAIL, 'password' => E2E_PASSWORD]), "\n";
