<?php

declare(strict_types=1);

namespace Bart\ContentStudio\Test\Unit\Model\ImportExport\Processor;

use Bart\ContentStudio\Api\Data\EntryInterface;
use Bart\ContentStudio\Api\EntryRepositoryInterface;
use Bart\ContentStudio\Model\Entry;
use Bart\ContentStudio\Model\EntryFactory;
use Bart\ContentStudio\Model\ImportExport\Processor\Import;
use Bart\ContentStudio\Model\OptionSource\ImportMode;
use Bart\ContentStudio\Model\ResourceModel\Entry\Collection;
use Bart\ContentStudio\Model\ResourceModel\Entry\CollectionFactory;
use Bart\ContentStudio\Model\Schema\Config as SchemaConfig;
use Bart\ContentStudio\Model\Schema\SchemaValidator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Import carries two rules that nothing else in the module enforces: an entry
 * is matched by identifier *plus its exact store set* (so two brands' exports
 * never collide into one row), and a batch is all-or-nothing (a ZIP with one
 * bad file writes nothing while still reporting every file).
 *
 * @see Import
 */
class ImportTest extends TestCase
{
    private const TYPE = 'compare_mattress';

    /** @var EntryFactory&MockObject */
    private EntryFactory $entryFactory;

    /** @var EntryRepositoryInterface&MockObject */
    private EntryRepositoryInterface $entryRepository;

    /** @var CollectionFactory&MockObject */
    private CollectionFactory $collectionFactory;

    /** @var SchemaValidator&MockObject */
    private SchemaValidator $validator;

    /** @var SchemaConfig&MockObject */
    private SchemaConfig $schemaConfig;

    /** @var StoreManagerInterface&MockObject */
    private StoreManagerInterface $storeManager;

    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    private Import $import;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->entryFactory = $this->createMock(EntryFactory::class);
        $this->entryRepository = $this->createMock(EntryRepositoryInterface::class);
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->validator = $this->createMock(SchemaValidator::class);
        $this->schemaConfig = $this->createMock(SchemaConfig::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->connection = $this->createMock(AdapterInterface::class);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);

        $this->schemaConfig->method('hasType')->willReturnCallback(
            static fn (string $code): bool => $code === self::TYPE
        );
        $this->validator->method('normalize')->willReturnArgument(1);
        $this->validator->method('validate')->willReturn([]);
        $this->entryFactory->method('create')->willReturnCallback(
            fn (): EntryInterface => $this->createMock(Entry::class)
        );
        $this->existingEntries();
        $this->knownStores(['sg' => 2, 'jl' => 1]);

        $this->import = new Import(
            $this->entryFactory,
            $this->entryRepository,
            $this->collectionFactory,
            $this->validator,
            $this->schemaConfig,
            $this->storeManager,
            $resourceConnection
        );
    }

    public function testANewEntryIsCreatedAndCommitted(): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');
        $this->entryRepository->expects($this->once())->method('save');

        $report = $this->import->importBatch([$this->payload('hero', ['sg'])]);

        $this->assertSame(
            [['identifier' => 'hero', 'status' => 'created', 'message' => '']],
            $report
        );
    }

    /**
     * The rule the module exists for: same identifier, different brand, two
     * separate rows.
     */
    public function testSameIdentifierWithADifferentStoreSetCreatesASecondEntry(): void
    {
        $this->existingEntries([$this->existing([1])]);

        $report = $this->import->importBatch([$this->payload('hero', ['sg'])]);

        $this->assertSame('created', $report[0]['status']);
    }

    public function testStoreSetMatchingIgnoresOrderAndDuplicates(): void
    {
        $existing = $this->existing([2, 1, 1]);
        $this->existingEntries([$existing]);
        $this->entryRepository->expects($this->once())->method('save')->with($existing);

        $report = $this->import->importBatch([$this->payload('hero', ['jl', 'sg'])]);

        $this->assertSame('updated', $report[0]['status']);
    }

    public function testUpdateModeWritesIntoTheExistingEntry(): void
    {
        $existing = $this->existing([2]);
        $this->existingEntries([$existing]);

        $existing->expects($this->once())->method('setType')->with(self::TYPE);
        $existing->expects($this->once())->method('setIdentifier')->with('hero');
        $existing->expects($this->once())->method('setStoreIds')->with([2]);
        $existing->expects($this->once())->method('setEntryData')->with(['headline' => 'Hi']);
        $this->entryFactory->expects($this->never())->method('create');

        $report = $this->import->importBatch(
            [$this->payload('hero', ['sg'], ['headline' => 'Hi'])],
            ImportMode::MODE_UPDATE
        );

        $this->assertSame('updated', $report[0]['status']);
    }

    public function testSkipModeLeavesAnExistingEntryAlone(): void
    {
        $this->existingEntries([$this->existing([2])]);
        $this->entryRepository->expects($this->never())->method('save');
        $this->connection->expects($this->once())->method('commit');

        $report = $this->import->importBatch([$this->payload('hero', ['sg'])], ImportMode::MODE_SKIP);

        $this->assertSame(
            [['identifier' => 'hero', 'status' => 'skipped', 'message' => 'Already exists.']],
            $report
        );
    }

    public function testSkipModeStillCreatesWhenNothingMatches(): void
    {
        $this->entryRepository->expects($this->once())->method('save');

        $report = $this->import->importBatch([$this->payload('hero', ['sg'])], ImportMode::MODE_SKIP);

        $this->assertSame('created', $report[0]['status']);
    }

    public function testDryRunReportsWithoutWritingOrCommitting(): void
    {
        $this->entryRepository->expects($this->never())->method('save');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $report = $this->import->importBatch([$this->payload('hero', ['sg'])], ImportMode::MODE_UPDATE, true);

        $this->assertSame('created', $report[0]['status']);
    }

    /**
     * @param array<string, mixed> $entry
     * @param string $expectedMessage
     */
    #[DataProvider('rejectedPayloadProvider')]
    public function testRejectedPayloadsAreReportedAndNothingIsWritten(
        array $entry,
        string $expectedIdentifier,
        string $expectedMessage
    ): void {
        $this->entryRepository->expects($this->never())->method('save');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $report = $this->import->importBatch([['entry' => $entry, 'stores' => ['sg'], 'data' => []]]);

        $this->assertSame(
            [['identifier' => $expectedIdentifier, 'status' => 'error', 'message' => $expectedMessage]],
            $report
        );
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string}>
     */
    public static function rejectedPayloadProvider(): array
    {
        return [
            'no identifier key' => [
                ['type' => self::TYPE],
                '(missing identifier)',
                'Entry identifier is required.',
            ],
            'blank identifier' => [
                ['identifier' => '   ', 'type' => self::TYPE],
                '(missing identifier)',
                'Entry identifier is required.',
            ],
            'type not registered here' => [
                ['identifier' => 'hero', 'type' => 'blog_teaser'],
                'hero',
                'Content type "blog_teaser" is not registered here.',
            ],
        ];
    }

    public function testValidationErrorsAreJoinedIntoTheReportRow(): void
    {
        $validator = $this->createMock(SchemaValidator::class);
        $validator->method('normalize')->willReturnArgument(1);
        $validator->method('validate')->willReturn(['Title is required.', 'Items: at most 6 allowed.']);
        $this->replaceValidator($validator);

        $this->entryRepository->expects($this->never())->method('save');

        $report = $this->import->importBatch([$this->payload('hero', ['sg'])]);

        $this->assertSame(
            'Title is required.; Items: at most 6 allowed.',
            $report[0]['message']
        );
    }

    /**
     * Every payload gets a report line - that is what the admin reads to find
     * out what to fix - but the transaction still rolls back as a whole.
     */
    public function testOneBadPayloadRollsBackTheWholeBatch(): void
    {
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $report = $this->import->importBatch([
            $this->payload('first', ['sg']),
            ['entry' => ['identifier' => 'second', 'type' => 'nope'], 'stores' => ['sg'], 'data' => []],
            $this->payload('third', ['sg']),
        ]);

        $this->assertSame(['created', 'error', 'created'], array_column($report, 'status'));
    }

    /**
     * Once the batch is doomed, later payloads must not even be handed to the
     * repository - the rollback covers the DB, but a save can still fire
     * plugins and cache invalidation on the way there.
     */
    public function testPayloadsAfterAFailureAreNotWritten(): void
    {
        $this->entryRepository->expects($this->once())->method('save');

        $this->import->importBatch([
            $this->payload('first', ['sg']),
            ['entry' => ['identifier' => 'second', 'type' => 'nope'], 'stores' => ['sg'], 'data' => []],
            $this->payload('third', ['sg']),
            $this->payload('fourth', ['sg']),
        ]);
    }

    public function testMissingStoresFallBackToAllStoreViews(): void
    {
        $entry = $this->createMock(Entry::class);
        $entry->expects($this->once())->method('setStoreIds')->with([0]);
        $this->entryFactory = $this->createMock(EntryFactory::class);
        $this->entryFactory->method('create')->willReturn($entry);
        $this->rebuildImport();

        $report = $this->import->importBatch([['entry' => $this->entryNode('hero'), 'data' => []]]);

        $this->assertSame('', $report[0]['message'], 'an absent store list is the documented default, not a warning');
    }

    public function testAnUnknownStoreCodeIsReportedAsAWarningButKeepsTheKnownOnes(): void
    {
        $entry = $this->createMock(Entry::class);
        $entry->expects($this->once())->method('setStoreIds')->with([2]);
        $this->entryFactory = $this->createMock(EntryFactory::class);
        $this->entryFactory->method('create')->willReturn($entry);
        $this->rebuildImport();

        $report = $this->import->importBatch([$this->payload('hero', ['sg', 'gone'])]);

        $this->assertSame('created', $report[0]['status']);
        $this->assertSame('Unknown store code "gone", skipped.', $report[0]['message']);
    }

    public function testWhenNoStoreCodeResolvesTheEntryLandsOnAllStoreViews(): void
    {
        $entry = $this->createMock(Entry::class);
        $entry->expects($this->once())->method('setStoreIds')->with([0]);
        $this->entryFactory = $this->createMock(EntryFactory::class);
        $this->entryFactory->method('create')->willReturn($entry);
        $this->rebuildImport();

        $report = $this->import->importBatch([$this->payload('hero', ['gone'])]);

        $this->assertSame('created', $report[0]['status']);
        $this->assertSame(
            'Unknown store code "gone", skipped. No store codes resolved - assigned to All Store Views.',
            $report[0]['message']
        );
    }

    public function testAThrowingSaveRollsBackAndIsWrapped(): void
    {
        $this->entryRepository->method('save')->willThrowException(new \RuntimeException('deadlock'));

        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not import: deadlock');

        $this->import->importBatch([$this->payload('hero', ['sg'])]);
    }

    /**
     * Export collapses a value that starts with a media base URL into
     * `{{media url="..."}}`, and an image or url field rejects that form. The
     * import has to expand it back before validation, or an exported ZIP with
     * one uploaded image cannot be imported anywhere.
     */
    public function testAMediaDirectiveIsExpandedToThisSitesMediaUrlBeforeValidation(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_MEDIA)->willReturn('https://elme.test/media/');
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeManager->method('getStore')->willReturnCallback(
            fn ($code = null): StoreInterface => $code === 'sg' ? $this->storeWithId(2) : $store
        );

        $expanded = [
            'image' => 'https://elme.test/media/bart_content_studio/a b.jpg',
            'items' => [['link' => 'https://elme.test/media/catalogue.pdf']],
            'text' => 'Inline {{media url="kept.jpg"}} stays for the render filter.',
            'count' => 3,
        ];
        $validator = $this->createMock(SchemaValidator::class);
        $validator->expects($this->once())->method('normalize')->with(self::TYPE, $expanded)->willReturnArgument(1);
        $validator->expects($this->once())->method('validate')->with(self::TYPE, $expanded)->willReturn([]);
        $this->replaceValidator($validator);

        $report = $this->import->importBatch([$this->payload('hero', ['sg'], [
            'image' => '{{media url="bart_content_studio/a b.jpg"}}',
            'items' => [['link' => '{{media url="catalogue.pdf"}}']],
            'text' => 'Inline {{media url="kept.jpg"}} stays for the render filter.',
            'count' => 3,
        ])]);

        $this->assertSame('created', $report[0]['status']);
    }

    public function testAnEmptyBatchCommitsNothingInteresting(): void
    {
        $this->connection->expects($this->once())->method('commit');

        $this->assertSame([], $this->import->importBatch([]));
    }

    /**
     * @param string $identifier
     * @param string[] $storeCodes
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function payload(string $identifier, array $storeCodes, array $data = []): array
    {
        return [
            'entry' => $this->entryNode($identifier),
            'stores' => $storeCodes,
            'data' => $data,
        ];
    }

    /**
     * @param string $identifier
     * @return array<string, mixed>
     */
    private function entryNode(string $identifier): array
    {
        return [
            'identifier' => $identifier,
            'type' => self::TYPE,
            'title' => ucfirst($identifier),
            'is_active' => true,
        ];
    }

    /**
     * @param int[] $storeIds
     * @return Entry&MockObject
     */
    private function existing(array $storeIds): Entry
    {
        $entry = $this->createMock(Entry::class);
        $entry->method('getStoreIds')->willReturn($storeIds);

        return $entry;
    }

    /**
     * @param EntryInterface[] $candidates
     * @return void
     */
    private function existingEntries(array $candidates = []): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('addIdentifierFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator($candidates));

        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collectionFactory->method('create')->willReturn($collection);

        if (isset($this->import)) {
            $this->rebuildImport();
        }
    }

    /**
     * @param array<string, int> $codeToId
     * @return void
     */
    private function knownStores(array $codeToId): void
    {
        $this->storeManager->method('getStore')->willReturnCallback(
            function ($code) use ($codeToId): StoreInterface {
                if (!isset($codeToId[(string)$code])) {
                    throw new \Magento\Framework\Exception\NoSuchEntityException(__('No such store.'));
                }

                return $this->storeWithId($codeToId[(string)$code]);
            }
        );
    }

    /**
     * @param int $storeId
     * @return StoreInterface&MockObject
     */
    private function storeWithId(int $storeId): StoreInterface
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn($storeId);

        return $store;
    }

    /**
     * @param SchemaValidator $validator
     * @return void
     */
    private function replaceValidator(SchemaValidator $validator): void
    {
        $this->validator = $validator;
        $this->rebuildImport();
    }

    /**
     * The processor takes its collaborators as constructor-promoted readonly
     * properties, so swapping one means rebuilding it.
     *
     * @return void
     */
    private function rebuildImport(): void
    {
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);

        $this->import = new Import(
            $this->entryFactory,
            $this->entryRepository,
            $this->collectionFactory,
            $this->validator,
            $this->schemaConfig,
            $this->storeManager,
            $resourceConnection
        );
    }
}
