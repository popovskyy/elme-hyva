<?php

declare(strict_types=1);

// Test data for checkout e2e. Safe to re-run. Local only; global-setup.mjs runs it before the tests.
//
// Accounts (FE-79):
// - e2e-checkout@example.com  — Roman's manual storefront account. Seed keeps it; never clears its cart.
// - e2e-placeorder@example.com — place-order, carrier-calls, closed-apis. Cart cleared every run.
// - e2e-qty@example.com        — qty-rules, heart, shopping-lists, quick-order-cart, header-counters
//                                (seed-qty-rules.php).

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
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

const E2E_SKU = 'E2E-CHECKOUT';
const E2E_PASSWORD = 'E2eCheckout!2026';
/** Roman's manual test account — do not clear cart_list or deactivate the quote. */
const E2E_MANUAL_EMAIL = 'e2e-checkout@example.com';
/** Specs that place orders or need a clean cart. */
const E2E_PLACEORDER_EMAIL = 'e2e-placeorder@example.com';

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

$sourceItem = $om->get(SourceItemInterfaceFactory::class)->create();
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
$accountManagement = $om->get(AccountManagementInterface::class);
$addressFactory = $om->get(AddressInterfaceFactory::class);
$addressRepository = $om->get(AddressRepositoryInterface::class);
$customerFactory = $om->get(CustomerInterfaceFactory::class);
$regionId = (int) $om->get(\Magento\Directory\Model\RegionFactory::class)->create()->loadByCode('VIC', 'AU')->getId();
$quotes = $om->get(CartRepositoryInterface::class);
$cartList = $om->get(\Elme\QuickOrder\Model\CartList::class);

$ensureCustomer = static function (
    string $email,
    string $firstname,
    string $lastname
) use (
    $customers,
    $accountManagement,
    $customerFactory,
    $addressFactory,
    $addressRepository,
    $websiteId,
    $regionId
): CustomerInterface {
    try {
        $customer = $customers->get($email, $websiteId);
    } catch (NoSuchEntityException) {
        $customer = $customerFactory->create();
        $customer->setWebsiteId($websiteId)->setStoreId(1)->setEmail($email)
            ->setFirstname($firstname)->setLastname($lastname);
        $customer = $accountManagement->createAccount($customer, E2E_PASSWORD);
    }

    if (!$customer->getAddresses()) {
        $address = $addressFactory->create();
        $address->setCustomerId($customer->getId())->setFirstname($firstname)->setLastname($lastname)
            ->setCompany('Elme E2E')->setStreet(['1 Test Street'])->setCity('Melbourne')->setPostcode('3000')
            ->setCountryId('AU')->setRegionId($regionId)->setTelephone('0312345678')
            ->setIsDefaultShipping(true)->setIsDefaultBilling(true);
        $addressRepository->save($address);
        $customer = $customers->get($email, $websiteId);
    }

    return $customer;
};

$clearCart = static function (CustomerInterface $customer) use ($quotes, $cartList): void {
    try {
        $quote = $quotes->getActiveForCustomer((int) $customer->getId());
        $quote->setIsActive(false);
        $quotes->save($quote);
    } catch (NoSuchEntityException) {
        echo "no quote to clear for {$customer->getEmail()}\n";
    }
    $cartList->clear((int) $customer->getId());
};

// Keep Roman's manual account available; do not touch its cart.
$ensureCustomer(E2E_MANUAL_EMAIL, 'E2E', 'Checkout');

// Specs that place orders / need a clean cart.
$placeCustomer = $ensureCustomer(E2E_PLACEORDER_EMAIL, 'E2E', 'Placeorder');
$clearCart($placeCustomer);

require __DIR__ . '/seed-qty-rules.php';

echo json_encode([
    'sku' => E2E_SKU,
    'manual_email' => E2E_MANUAL_EMAIL,
    'email' => E2E_PLACEORDER_EMAIL,
    'password' => E2E_PASSWORD,
]), "\n";
