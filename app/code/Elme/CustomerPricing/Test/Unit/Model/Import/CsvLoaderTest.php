<?php

declare(strict_types=1);

namespace Elme\CustomerPricing\Test\Unit\Model\Import;

use Elme\CustomerPricing\Model\Import\CsvLoader;
use Elme\CustomerPricing\Model\QuoteRecollect;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Which carts an import flags for a recollect.
 *
 * @see CsvLoader
 */
#[AllowMockObjectsWithoutExpectations]
class CsvLoaderTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    private CustomerRepositoryInterface&MockObject $customerRepository;
    private QuoteRecollect&MockObject $quoteRecollect;
    private CsvLoader $loader;

    /**
     * @var string[]
     */
    private array $files = [];

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('distinct')->willReturnSelf();
        $select->method('from')->willReturnSelf();
        $this->connection->method('select')->willReturn($select);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        $customerFactory = $this->createMock(CustomerFactory::class);
        $customerFactory->method('create')->willReturn($this->createMock(Customer::class));
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getDefaultStoreView')->willReturn($store);

        $this->customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $this->quoteRecollect = $this->createMock(QuoteRecollect::class);
        $this->loader = new CsvLoader(
            $resource,
            $this->customerRepository,
            $customerFactory,
            $this->createMock(CustomerResource::class),
            $storeManager,
            $this->quoteRecollect
        );
    }

    protected function tearDown(): void
    {
        array_map('unlink', $this->files);
    }

    public function testPriceListFlagsCartsWithItsSkus(): void
    {
        $this->quoteRecollect->expects($this->once())->method('bySkus')->with(['FE53-LAMP', 'TEST-SOFA-1']);
        $this->quoteRecollect->expects($this->never())->method('all');

        $this->loader->load('price-list', $this->csv("sku,price\nFE53-LAMP,370\nTEST-SOFA-1,1299\n"));
    }

    public function testReplaceAlsoFlagsCartsWithTheDeletedSkus(): void
    {
        $this->connection->method('fetchCol')->willReturn(['FE53-LAMP', 'OLD-SKU']);
        $this->quoteRecollect->expects($this->once())->method('bySkus')->with(['FE53-LAMP', 'OLD-SKU']);

        $this->loader->load('price-list', $this->csv("sku,price\nFE53-LAMP,370\n"), true);
    }

    public function testCustomerPriceFlagsCartsWithItsSku(): void
    {
        $this->quoteRecollect->expects($this->once())->method('bySkus')->with(['FE53-LAMP']);

        $this->loader->load(
            'cust-mkt',
            $this->csv("discount_type,sku,pronto_customer_id,price\nA1,FE53-LAMP,C100,370\n")
        );
    }

    public function testItemGroupDiscountFlagsAllCarts(): void
    {
        $this->quoteRecollect->expects($this->once())->method('all');
        $this->quoteRecollect->expects($this->never())->method('bySkus');

        $this->loader->load(
            'cust-mkt',
            $this->csv("discount_type,sku,item_group,marketing_flag,discount\n20,FE53-LAMP,,MKB,10\n25,,SOFAS,MKB,20\n")
        );
    }

    public function testFileWithoutASkuColumnFlagsAllCarts(): void
    {
        $this->quoteRecollect->expects($this->once())->method('all');

        $this->loader->load('cust-mkt', $this->csv("discount_type,item_group,marketing_flag,discount\n25,SOFAS,MKB,20\n"));
    }

    public function testReplaceOfATableWithItemGroupRowsFlagsAllCarts(): void
    {
        $this->connection->method('fetchCol')->willReturn(['FE53-LAMP', null]);
        $this->quoteRecollect->expects($this->once())->method('all');

        $this->loader->load(
            'cust-mkt',
            $this->csv("discount_type,sku,pronto_customer_id,price\nA1,FE53-LAMP,C100,370\n"),
            true
        );
    }

    public function testCustomerMappingFlagsAllCarts(): void
    {
        $this->quoteRecollect->expects($this->once())->method('all');

        $this->loader->load('customer', $this->csv("pronto_customer_id,marketing_flag\nC100,MKA\n"));
    }

    public function testCustomerLinkFlagsTheCartsOfTheLinkedCustomers(): void
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $this->customerRepository->method('get')->willReturn($customer);
        $this->quoteRecollect->expects($this->once())->method('byCustomerIds')->with([42]);
        $this->quoteRecollect->expects($this->never())->method('all');

        $this->loader->load('customer-link', $this->csv("email,pronto_customer_id\nbe07-a@example.com,C300\n"));
    }

    public function testInvalidFileFlagsNothing(): void
    {
        $this->quoteRecollect->expects($this->never())->method('all');
        $this->quoteRecollect->expects($this->never())->method('bySkus');
        $this->expectException(LocalizedException::class);

        $this->loader->load('price-list', $this->csv("sku,price\nFE53-LAMP,abc\n"));
    }

    private function csv(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'elme-csv');
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }
}
