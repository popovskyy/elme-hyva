<?php

declare(strict_types=1);

// BE-07 QA: two customers with different Pronto mappings and one without. Safe to re-run. Local only, in the PHP
// container: php app/code/Elme/CustomerPricing/Test/_files/seed-customers.php, then load the CSVs (see README).

use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

const BE07_PASSWORD = 'Be07Prices!2026';
const BE07_CUSTOMERS = [
    'be07-a@example.com' => ['BE07', 'Customer A'],
    'be07-b@example.com' => ['BE07', 'Customer B'],
    'be07-none@example.com' => ['BE07', 'No Mapping'],
];

require '/var/www/html/app/bootstrap.php';

$om = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$om->get(State::class)->setAreaCode('frontend');
$storeManager = $om->get(StoreManagerInterface::class);
$store = $storeManager->getStore(1);
$storeManager->setCurrentStore($store);
$websiteId = (int) $store->getWebsiteId();

$customers = $om->get(CustomerRepositoryInterface::class);
foreach (BE07_CUSTOMERS as $email => [$firstname, $lastname]) {
    try {
        $customers->get($email, $websiteId);
    } catch (NoSuchEntityException) {
        $customer = $om->get(CustomerInterfaceFactory::class)->create();
        $customer->setWebsiteId($websiteId)->setStoreId(1)->setEmail($email)
            ->setFirstname($firstname)->setLastname($lastname);
        $om->get(AccountManagementInterface::class)->createAccount($customer, BE07_PASSWORD);
    }
}

echo json_encode(['emails' => array_keys(BE07_CUSTOMERS), 'password' => BE07_PASSWORD]), "\n";
