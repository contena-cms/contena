<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountCollection;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountDefinition;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Contena\Core\Checkout\Promotion\PromotionEntity;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingEntity;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsCollection;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaCollection;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaEntity;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceCollection;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceEntity;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Contena\Core\Content\ImportExport\Event\EnrichExportCriteriaEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportBeforeExportRecordEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportExceptionExportRecordEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportExceptionImportRecordEvent;
use Contena\Core\Content\ImportExport\Exception\UpdatedByValueNotFoundException;
use Contena\Core\Content\ImportExport\ImportExport;
use Contena\Core\Content\ImportExport\ImportExportProfileEntity;
use Contena\Core\Content\ImportExport\Processing\Pipe\AbstractPipe;
use Contena\Core\Content\ImportExport\Processing\Pipe\PipeFactory;
use Contena\Core\Content\ImportExport\Processing\Reader\AbstractReader;
use Contena\Core\Content\ImportExport\Processing\Reader\CsvReader;
use Contena\Core\Content\ImportExport\Processing\Reader\CsvReaderFactory;
use Contena\Core\Content\ImportExport\Processing\Writer\AbstractWriter;
use Contena\Core\Content\ImportExport\Processing\Writer\CsvFileWriterFactory;
use Contena\Core\Content\ImportExport\Service\FileService;
use Contena\Core\Content\ImportExport\Service\ImportExportService;
use Contena\Core\Content\ImportExport\Service\UserNumberRangeConfigService;
use Contena\Core\Content\ImportExport\Strategy\Import\BatchImportStrategy;
use Contena\Core\Content\ImportExport\Strategy\Import\ImportStrategyService;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\ImportExport\Struct\Progress;
use Contena\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Contena\Core\Content\Newsletter\Channel\NewsletterSubscribeRoute;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Content\Test\ImportExport\MockRepository;
use Contena\Core\Content\Test\ImportExport\StockSubscriber;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\NumberRangeEntity;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\Tax\TaxEntity;
use Contena\Core\System\Unit\UnitCollection;
use Contena\Core\System\Unit\UnitDefinition;
use Contena\Core\System\Unit\UnitEntity;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
class ImportExportTest extends AbstractImportExportTestCase
{
    use OrderFixture;
    private const PUBLIC_MEDIA_PATH = '/public/media';

    private bool $mediaDirCreated = false;

    private string $projectDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = static::getContainer()->getParameter('kernel.project_dir');

        if (!\is_dir($this->projectDir . self::PUBLIC_MEDIA_PATH)) {
            mkdir($this->projectDir . self::PUBLIC_MEDIA_PATH);
            $this->mediaDirCreated = true;
        }

        \copy(self::TEST_IMAGE, $this->projectDir . self::PUBLIC_MEDIA_PATH . '/ßhopware-logö.png');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \unlink(static::getContainer()->getParameter('kernel.project_dir') . '/public/media/ßhopware-logö.png');

        if ($this->mediaDirCreated) {
            rmdir(static::getContainer()->getParameter('kernel.project_dir') . '/public/media');
            $this->mediaDirCreated = false;
        }
    }

    public function testExportEvents(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->subscribe(new StockSubscriber());

        $enrichExportCriteriaCalled = false;
        $beforeExportRecordCalled = false;
        $exceptionExportRecordCalled = false;

        EventHookDispatcher::fromContainer(static::getContainer())->on(EnrichExportCriteriaEvent::class, static function () use (&$enrichExportCriteriaCalled): void {
            $enrichExportCriteriaCalled = true;
        });
        EventHookDispatcher::fromContainer(static::getContainer())->on(ImportExportBeforeExportRecordEvent::class, static function () use (&$beforeExportRecordCalled): void {
            $beforeExportRecordCalled = true;
        });
        EventHookDispatcher::fromContainer(static::getContainer())->on(ImportExportExceptionExportRecordEvent::class, static function () use (&$exceptionExportRecordCalled): void {
            $exceptionExportRecordCalled = true;
        });

        $blogId = Uuid::randomHex();
        $blog = $this->getTestBlog($blogId);
        $newStock = (int) $blog['stock'] + 1;

        $criteria = new Criteria([$blogId]);
        $progress = $this->export(Context::createDefaultContext(), BlogDefinition::ENTITY_NAME, $criteria);

        static::assertTrue($enrichExportCriteriaCalled, 'EnrichExportCriteriaEvent should have been dispatched');
        static::assertTrue($beforeExportRecordCalled, 'ImportExportBeforeExportRecordEvent should have been dispatched');
        static::assertFalse($exceptionExportRecordCalled, 'ImportExportExceptionExportRecordEvent should not have been dispatched');

        $csv = $this->getCsvContent($progress->getLogId());
        static::assertStringContainsString(\sprintf(';%s;', $newStock), $csv);
    }

    public function testImportEvents(): void
    {
        $beforeImportRecordCalled = false;
        $afterImportRecordCalled = false;
        $exceptionImportRecordCalled = false;

        EventHookDispatcher::fromContainer(static::getContainer())->on(ImportExportBeforeImportRecordEvent::class, static function () use (&$beforeImportRecordCalled): void {
            $beforeImportRecordCalled = true;
        });
        EventHookDispatcher::fromContainer(static::getContainer())->on(ImportExportAfterImportRecordEvent::class, static function () use (&$afterImportRecordCalled): void {
            $afterImportRecordCalled = true;
        });
        EventHookDispatcher::fromContainer(static::getContainer())->on(ImportExportExceptionImportRecordEvent::class, static function () use (&$exceptionImportRecordCalled): void {
            $exceptionImportRecordCalled = true;
        });

        $this->importCategoryCsv();

        static::assertTrue($beforeImportRecordCalled, 'ImportExportBeforeImportRecordEvent should have been dispatched');
        static::assertTrue($afterImportRecordCalled, 'ImportExportAfterImportRecordEvent should have been dispatched');
        static::assertFalse($exceptionImportRecordCalled, 'ImportExportExceptionImportRecordEvent should not have been dispatched');
    }

    public function testImportExport(): void
    {
        $filesystem = static::getContainer()->get('contena.filesystem.private');

        $blogId = Uuid::randomHex();
        $this->getTestBlog($blogId);
        $criteria = new Criteria([$blogId]);
        $progress = $this->export(Context::createDefaultContext(), BlogDefinition::ENTITY_NAME, $criteria);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $progress = $this->export(Context::createDefaultContext(), BlogDefinition::ENTITY_NAME, $criteria);

        /** @var EntityRepository<EntityCollection<ImportExportFileEntity>> $fileRepository */
        $fileRepository = static::getContainer()->get('import_export_file.repository');
        $criteria = new Criteria(array_filter([$this->getLogEntity($progress->getLogId())->getFileId()]));
        $file = $fileRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($file);
        $importExportFileEntity = $this->getLogEntity($progress->getLogId())->getFile();
        static::assertInstanceOf(ImportExportFileEntity::class, $importExportFileEntity);
        static::assertSame($filesystem->fileSize($importExportFileEntity->getPath()), $file->getSize());

        $this->blogRepository->delete([['id' => $blogId]], Context::createDefaultContext());
        $exportFileTmp = (string) tempnam(sys_get_temp_dir(), '');
        file_put_contents($exportFileTmp, (string) $filesystem->read($file->getPath()));

        $progress = $this->import(Context::createDefaultContext(), BlogDefinition::ENTITY_NAME, $exportFileTmp, 'test.csv', null, false, true);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('visibilities');
        $criteria->addAssociation('tax');
        $criteria->addAssociation('categories');
        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($blog);
    }

    #[Group('needsWebserver')]
    public function testMediaWithEncodedUrl(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);
        $csvContent = \sprintf('url %s', $appUrl) . '/media/%C3%9Fhopware-log%C3%B6.png';

        $fixturesPath = __DIR__ . '/fixtures/media_encoded_url.csv';
        file_put_contents($fixturesPath, $csvContent);

        try {
            $progress = $this->import(Context::createDefaultContext(), MediaDefinition::ENTITY_NAME, $fixturesPath, 'media_encoded_url.csv', null, false, true);

            static::assertTrue($progress->isFinished());
            static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
        } finally {
            unlink($fixturesPath);
        }
    }

    public function testCategory(): void
    {
        $categoryRepository = static::getContainer()->get('category.repository');

        $rootId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $categories = [
            [
                'id' => $rootId,
                'name' => 'root',
            ],
            [
                'id' => $childId,
                'name' => 'child',
                'parentId' => $rootId,
            ],
        ];
        $categoryRepository->upsert($categories, Context::createDefaultContext());

        $betweenId = Uuid::randomHex();
        $categories = [
            [
                'id' => $betweenId,
                'name' => 'between',
            ],
            [
                'id' => $childId,
                'parentId' => $betweenId,
            ],
        ];
        $categoryRepository->upsert($categories, Context::createDefaultContext());

        $filesystem = static::getContainer()->get('contena.filesystem.private');

        $criteria = new Criteria([$rootId, $betweenId, $childId]);
        $progress = $this->export(Context::createDefaultContext(), CategoryDefinition::ENTITY_NAME, $criteria);
        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $progress = $this->export(Context::createDefaultContext(), CategoryDefinition::ENTITY_NAME, $criteria);

        $categoryRepository->delete([['id' => $childId], ['id' => $betweenId], ['id' => $rootId]], Context::createDefaultContext());

        $exportFileTmp = (string) tempnam(sys_get_temp_dir(), '');

        $logFile = $this->getLogEntity($progress->getLogId())->getFile();
        static::assertInstanceOf(ImportExportFileEntity::class, $logFile);
        file_put_contents($exportFileTmp, (string) $filesystem->read($logFile->getPath()));

        $this->import(Context::createDefaultContext(), CategoryDefinition::ENTITY_NAME, $exportFileTmp, 'test.csv', null, false, true);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $ids = $categoryRepository->searchIds(new Criteria([$rootId, $betweenId, $childId]), Context::createDefaultContext());
        static::assertCount(3, $ids->getIds());
        static::assertTrue($ids->has($rootId));
        static::assertTrue($ids->has($betweenId));
        static::assertTrue($ids->has($childId));
    }

    public function testSortingShouldWorkAsExpected(): void
    {
        $profileRepository = static::getContainer()->get('import_export_profile.repository');

        $profile = $this->createCategoryProfileMock();
        $profileRepository->create([$profile], Context::createDefaultContext());

        $filesystem = static::getContainer()->get('contena.filesystem.private');

        $criteria = new Criteria();
        $progress = $this->export(
            Context::createDefaultContext(),
            CategoryDefinition::ENTITY_NAME,
            $criteria,
            null,
            $profile['id']
        );
        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $exportFile = $this->getLogEntity($progress->getLogId())->getFile();
        static::assertNotNull($exportFile);
        $fileContents = $filesystem->read($exportFile->getPath());

        $regexResult = preg_split('#\r?\n#', $fileContents);
        static::assertIsArray($regexResult);
        $firstLine = $regexResult[0];
        $csvColumns = explode(';', $firstLine);

        $sortedMappings = $profile['mapping'];
        usort($sortedMappings, static fn ($firstMapping, $secondMapping) => $firstMapping['position'] - $secondMapping['position']);

        foreach ($sortedMappings as $index => $mapping) {
            static::assertSame(
                $mapping['mappedKey'],
                trim($csvColumns[$index]),
                'Keys should have the same name. It may be that the sorting is broken.'
            );
        }
    }

    public function testNewsletterRecipient(): void
    {
        $filesystem = static::getContainer()->get('contena.filesystem.private');
        $testData = [
            'id' => Uuid::randomHex(),
            'salutation' => [
                'id' => Uuid::randomHex(),
                'salutationKey' => 'test',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'displayName' => 'test display name',
                        'letterName' => 'test letter name',
                    ],
                ],
            ],
            'email' => 'foo.bar@example.test',
            'title' => 'dr.',
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'zipCode' => '48599',
            'city' => 'Musterstadt',
            'street' => 'Musterstraße 7',
            'hash' => 'asdf',
            'status' => NewsletterSubscribeRoute::STATUS_DIRECT,
            'confirmedAt' => new \DateTimeImmutable('2020-02-29 13:37'),
            'channelId' => TestDefaults::CHANNEL,
        ];
        $repo = static::getContainer()->get('newsletter_recipient.repository');

        $context = Context::createDefaultContext();
        $repo->upsert([$testData], $context);

        $criteria = new Criteria([$testData['id']]);
        $progress = $this->export($context, NewsletterRecipientDefinition::ENTITY_NAME, $criteria);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $repo->delete([['id' => $testData['id']]], Context::createDefaultContext());

        $exportFileTmp = (string) tempnam(sys_get_temp_dir(), '');
        $logfile = $this->getLogEntity($progress->getLogId())->getFile();
        static::assertInstanceOf(ImportExportFileEntity::class, $logfile);
        file_put_contents($exportFileTmp, (string) $filesystem->read($logfile->getPath()));

        $progress = $this->import($context, NewsletterRecipientDefinition::ENTITY_NAME, $exportFileTmp, 'test.csv', null, false, true);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $actualNewsletter = $repo->search(new Criteria([$testData['id']]), Context::createDefaultContext());
        static::assertNotNull($actualNewsletter);
    }

    public function testImportExportAdvancedPrices(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $blogId = 'e12a77e8ed6642698b987250d8ec705d';
        $ruleId = 'cb34dc6f20b6479aa975e1290f442e65';
        $this->createBlog($blogId);
        $this->createRule($ruleId);

        $progress = $this->import($context, BlogPriceDefinition::ENTITY_NAME, '/fixtures/advanced_prices.csv', 'advanced_prices.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $blog = $this->blogRepository->search((new Criteria([$blogId]))->addAssociation('prices'), $context)->getEntities()->first();
        static::assertNotNull($blog);

        static::assertInstanceOf(BlogPriceCollection::class, $blog->getPrices());
        static::assertCount(2, $blog->getPrices());
        $firstPrice = $blog->getPrices()->first();

        static::assertInstanceOf(BlogPriceEntity::class, $firstPrice);
        static::assertSame($ruleId, $firstPrice->getRuleId());
        static::assertInstanceOf(Price::class, $firstPrice->getPrice()->first());
        static::assertSame(7.89, $firstPrice->getPrice()->first()->getNet());
        static::assertSame(9.39, $firstPrice->getPrice()->first()->getGross());
        static::assertSame(1, $firstPrice->getQuantityStart());
        static::assertSame(10, $firstPrice->getQuantityEnd());
        $lastPrice = $blog->getPrices()->last();
        static::assertInstanceOf(BlogPriceEntity::class, $lastPrice);
        static::assertSame($ruleId, $lastPrice->getRuleId());
        static::assertInstanceOf(Price::class, $lastPrice->getPrice()->first());
        static::assertSame(5.67, $lastPrice->getPrice()->first()->getNet());
        static::assertSame(6.75, $lastPrice->getPrice()->first()->getGross());
        static::assertSame(11, $lastPrice->getQuantityStart());
        static::assertNull($lastPrice->getQuantityEnd());

        $progress = $this->export($context, BlogPriceDefinition::ENTITY_NAME);

        static::assertImportExportSucceeded($progress);

        $csv = $this->getCsvContent($progress->getLogId());

        static::assertStringEqualsFile(__DIR__ . '/fixtures/advanced_prices.csv', $csv);
    }

    public function importCategoryCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, CategoryDefinition::ENTITY_NAME, '/fixtures/categories.csv', 'categories.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
    }

    public function importPropertyCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, PropertyGroupOptionDefinition::ENTITY_NAME, '/fixtures/properties.csv', 'properties.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
    }

    public function importPropertyCsvWithoutIds(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, PropertyGroupOptionDefinition::ENTITY_NAME, '/fixtures/propertieswithoutid.csv', 'properties.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $propertyRepository = static::getContainer()->get('property_group_option.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'alicebluenew'));
        $property = $propertyRepository->search($criteria, $context);
        static::assertCount(1, $property->getEntities());
    }

    public function importPropertyWithDefaultsCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        // setup profile
        $clonedPropertyProfile = $this->cloneDefaultProfile(PropertyGroupOptionDefinition::ENTITY_NAME);
        static::assertIsArray($clonedPropertyProfile->getMapping());
        $mappings = $clonedPropertyProfile->getMapping();
        foreach (array_keys($mappings) as $key) {
            static::assertIsArray($mappings[$key]);
            if ($mappings[$key]['mappedKey'] === 'name') {
                $mappings[$key]['useDefaultValue'] = true;
                $mappings[$key]['defaultValue'] = 'MyDefaultNameForProperties';

                break;
            }
        }
        $this->updateProfileMapping($clonedPropertyProfile->getId(), $mappings);

        $progress = $this->import(
            $context,
            PropertyGroupOptionDefinition::ENTITY_NAME,
            '/fixtures/properties_with_empty_names.csv',
            'properties.csv',
            $clonedPropertyProfile->getId()
        );

        // import should succeed even if required names are empty (they will be replaced by default values)
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $propertyRepository = static::getContainer()->get('property_group_option.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'MyDefaultNameForProperties'));
        $property = $propertyRepository->search($criteria, $context);
        // import should create 7 properties with default name
        static::assertCount(7, $property->getEntities());
    }

    public function importPropertyWithUserRequiredCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        // setup profile
        $clonedPropertyProfile = $this->cloneDefaultProfile(PropertyGroupOptionDefinition::ENTITY_NAME);
        static::assertIsArray($clonedPropertyProfile->getMapping());
        $mappings = $clonedPropertyProfile->getMapping();
        foreach (array_keys($mappings) as $key) {
            static::assertIsArray($mappings[$key]);
            if ($mappings[$key]['mappedKey'] === 'media_url') {
                $mappings[$key]['requiredByUser'] = true;

                break;
            }
        }
        $this->updateProfileMapping($clonedPropertyProfile->getId(), $mappings);

        $progress = $this->import(
            $context,
            PropertyGroupOptionDefinition::ENTITY_NAME,
            '/fixtures/properties.csv',
            'properties.csv',
            $clonedPropertyProfile->getId()
        );

        // import should fail even if all system required fields are set,
        // there are rows that have no values for admin_user required fields.
        // Input CSV is the same as in the 'importPropertyCsv' test (which previously succeeded here).
        static::assertImportExportFailed($progress);
        static::assertSame(0, $progress->getProcessedRecords());

        // check the errors
        $invalid = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());

        static::assertGreaterThanOrEqual(1, \count($invalid)); // there could already be other errors
        $first = $invalid[0];
        static::assertStringContainsString('media_url is set to required by the admin_user but has no value', $first['_error']);
    }

    public function testBlogsCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $this->importCategoryCsv();
        $this->importPropertyCsv();
        $this->importPropertyCsvWithoutIds();

        $this->importPropertyWithDefaultsCsv();
        $this->importPropertyWithUserRequiredCsv();

        $progress = $this->import($context, BlogDefinition::ENTITY_NAME, '/fixtures/blogs.csv', 'blogs.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria();
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('configuratorSettings');
        $criteria->addFilter(new EqualsFilter('parentId', 'e5c8b8f701034e8dbea72ac0fc32521e'));

        $firstBlog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($firstBlog);

        static::assertCount(2, $firstBlog->getVariation());

        $criteria->resetFilters();
        $criteria->addFilter(new EqualsFilter('id', 'e5c8b8f701034e8dbea72ac0fc32521e'));

        $secondBlog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($secondBlog);

        static::assertInstanceOf(BlogConfiguratorSettingCollection::class, $secondBlog->getConfiguratorSettings());
        static::assertCount(2, $secondBlog->getConfiguratorSettings());
    }

    public function testBlogsCoverIsUpdated(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, BlogDefinition::ENTITY_NAME, '/fixtures/blogs_with_updated_cover.csv', 'blogs.csv');

        static::assertSame(Progress::STATE_SUCCEEDED, $progress->getState());

        $blog = $this->blogRepository->search(
            (new Criteria(['e5c8b8f701034e8dbea72ac0fc32521e']))->addAssociation('media'),
            Context::createDefaultContext()
        )->getEntities()->first();
        static::assertNotNull($blog);

        static::assertInstanceOf(BlogMediaCollection::class, $blog->getMedia());
        static::assertCount(1, $blog->getMedia());
    }

    public function testBlogsWithVariantsCsv(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `blog`');

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, BlogDefinition::ENTITY_NAME, '/fixtures/blogs_with_variants.csv', 'blogs.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
        static::assertSame(2, $progress->getProcessedRecords());

        $criteria = new Criteria();
        $criteria->addAssociation('options.group');
        $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('parentId', null)]));

        $blogs = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertInstanceOf(BlogCollection::class, $blogs);
        static::assertCount(32, $blogs);
        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->first());
        static::assertCount(3, $blog->getVariation());
        static::assertContains('color', array_column($blog->getVariation(), 'group'));
        static::assertContains('size', array_column($blog->getVariation(), 'group'));
        static::assertContains('material', array_column($blog->getVariation(), 'group'));

        $criteria = new Criteria();
        $criteria->addAssociation('configuratorSettings');
        $criteria->addFilter(new EqualsFilter('parentId', null));

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertInstanceOf(BlogConfiguratorSettingCollection::class, $settings = $blog->getConfiguratorSettings());
        static::assertCount(10, $settings);
    }

    public function testBlogsWithInvalidVariantsCsv(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `blog`');

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, BlogDefinition::ENTITY_NAME, '/fixtures/blogs_with_invalid_variants.csv', 'blogs.csv');

        static::assertImportExportFailed($progress);

        $invalid = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());

        static::assertCount(2, $invalid);

        $first = $invalid[0];
        static::assertStringContainsString('size: M, L, XL, XXL | oops', $first['_error']);
        $second = $invalid[1];
        static::assertStringContainsString('size: , | color: Green, White, Black, Purple', $second['_error']);
    }

    public function testBlogsWithOwnIdentifier(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $blogIds = [
            Uuid::fromStringToHex('blog1'),
            Uuid::fromStringToHex('blog2'),
            Uuid::fromStringToHex('blog3'),
            Uuid::fromStringToHex('blog4'),
        ];

        $categoryRepository = static::getContainer()->get('category.repository');
        $category1Id = Uuid::fromStringToHex('category1');
        $category2Id = '0a600a2648b3486fbfdbc60993050103';
        $category3Id = Uuid::fromStringToHex('category3');
        $categoryRepository->upsert([
            [
                'id' => $category1Id,
                'name' => 'First category',
            ],

            [
                'id' => $category2Id,
                'name' => 'Second category',
            ],

            [
                'id' => $category3Id,
                'name' => 'Third category',
            ],
        ], $context);

        $progress = $this->import($context, BlogDefinition::ENTITY_NAME, '/fixtures/blogs_with_own_identifier.csv', 'blogs.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $count = $this->blogRepository->search(new Criteria($blogIds), $context)->getEntities()->count();
        static::assertSame(4, $count);

        $name = 'Name has changed';
        $this->blogRepository->upsert([
            [
                'id' => $blogIds[0],
                'name' => $name,
            ],
        ], $context);

        $this->import($context, BlogDefinition::ENTITY_NAME, '/fixtures/blogs_with_own_identifier.csv', 'blogs.csv');

        $criteria = new Criteria([$blogIds[0]]);
        $criteria->addAssociation('categories');

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertNotSame($name, $blog->getName());
        static::assertSame(Uuid::fromStringToHex('tax19'), $blog->getTaxId());
        static::assertSame(Uuid::fromStringToHex('manufacturer1'), $blog->getManufacturerId());

        static::assertInstanceOf(CategoryCollection::class, $categories = $blog->getCategories());
        static::assertCount(3, $categories);
        static::assertTrue($categories->has($category1Id));
        static::assertTrue($categories->has($category2Id));
        static::assertTrue($categories->has($category3Id));
    }

    public function testBlogsWithCategoryPaths(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $categoryRepository = static::getContainer()->get('category.repository');
        $categoryHome = Uuid::fromStringToHex('home');
        $categoryHomeFirst = Uuid::fromStringToHex('homeFirst');
        $categoryHomeSecond = Uuid::fromStringToHex('homeSecond');
        $categoryHomeFirstSecond = Uuid::fromStringToHex('homeFirstSecond');
        $categoryHomeFirstNewSecondNew = Uuid::fromStringToHex('Main>First New>Second New');

        $categoryRepository->upsert([
            [
                'id' => $categoryHome,
                'name' => 'Main',
            ],

            [
                'id' => $categoryHomeFirst,
                'name' => 'First',
                'parentId' => $categoryHome,
            ],

            [
                'id' => $categoryHomeFirstSecond,
                'name' => 'Second',
                'parentId' => $categoryHomeFirst,
            ],

            [
                'id' => $categoryHomeSecond,
                'name' => 'Second',
                'parentId' => $categoryHome,
            ],
        ], $context);

        $progress = $this->import($context, BlogDefinition::ENTITY_NAME, '/fixtures/blogs_with_category_path.csv', 'blogs.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria([Uuid::fromStringToHex('meinhappyproduct')]);
        $criteria->addAssociation('categories');

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($blog);

        $categories = $blog->getCategories();
        static::assertInstanceOf(CategoryCollection::class, $categories);
        static::assertCount(4, $categories);
        static::assertTrue($categories->has($categoryHome));
        static::assertTrue($categories->has($categoryHomeFirstSecond));
        static::assertTrue($categories->has($categoryHomeSecond));
        static::assertTrue($categories->has($categoryHomeFirstNewSecondNew));

        $newCategoryLeaf = $categories->get($categoryHomeFirstNewSecondNew);
        static::assertSame(Uuid::fromStringToHex('Main>First New'), $newCategoryLeaf->getParentId());
    }

    #[DataProvider('coverMediaUrlDataProvider')]
    public function testImportBlogsWithCoverMediaUrl(string $imageName, string $queryParameter): void
    {
        $blogName = 'blog_with_cover_media_url';
        $csvFileName = 'blog_with_cover_media_url.csv';
        $expectedExtension = 'png';

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $filesystem = new Filesystem();
        $filesystem->copy(
            __DIR__ . '/fixtures/' . $imageName,
            $this->projectDir . self::PUBLIC_MEDIA_PATH . '/' . $imageName
        );

        $csvContent = \sprintf(
            'blog_number;stock;name;price_net;price_gross;tax_id;tax_rate;tax_name;cover_media_url
SWTEST;1;' . $blogName . ';9.35;10;0c17372fe6aa46059a97fc28b40f46c4;7;7%%;%s',
            EnvironmentHelper::getVariable('APP_URL') . '/media/' . $imageName . $queryParameter
        );

        $filesystem->dumpFile(__DIR__ . '/fixtures/' . $csvFileName, $csvContent);

        try {
            static::assertTrue($filesystem->exists($this->projectDir . self::PUBLIC_MEDIA_PATH . '/' . $imageName));
            static::assertTrue($filesystem->exists(__DIR__ . '/fixtures/' . $csvFileName));

            $progress = $this->import(
                $context,
                BlogDefinition::ENTITY_NAME,
                __DIR__ . '/fixtures/' . $csvFileName,
                $csvFileName,
                null,
                false,
                true
            );

            static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', $blogName));
            $criteria->addAssociation('cover.media');
            $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();
            static::assertInstanceOf(BlogEntity::class, $blog);

            $blogMedia = $blog->getCover();
            static::assertInstanceOf(BlogMediaEntity::class, $blogMedia);
            $media = $blogMedia->getMedia();
            static::assertInstanceOf(MediaEntity::class, $media);
            static::assertSame($imageName, $media->getFileName());
            static::assertSame($expectedExtension, $media->getFileExtension());
        } finally {
            $filesystem->remove([
                __DIR__ . '/fixtures/' . $csvFileName,
                $this->projectDir . self::PUBLIC_MEDIA_PATH . '/' . $imageName,
            ]);
        }
    }

    public static function coverMediaUrlDataProvider(): \Generator
    {
        yield 'import coverMediaUrl without extension' => [
            'test-image-without-extension',
            '',
        ];
        yield 'import coverMediaUrl without extension and query parameters' => [
            'test-image-without-extension',
            '?some=query&params=here',
        ];
    }

    public function testInvalidFile(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `blog`');

        $progress = $this->import(Context::createDefaultContext(), BlogDefinition::ENTITY_NAME, '/fixtures/blogs_with_invalid.csv', 'blogs.csv');

        static::assertImportExportFailed($progress);

        $ids = $this->blogRepository->searchIds(new Criteria(), Context::createDefaultContext());
        static::assertCount(8, $ids->getIds());

        $invalid = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());

        static::assertCount(2, $invalid);

        $first = $invalid[0];
        static::assertSame('e5c8b8f701034e8dbea72ac0fc32521e', $first['id']);
        static::assertStringContainsString('CONSTRAINT `fk.blog_', $first['_error']);

        $second = $invalid[1];
        static::assertSame('d5e8a6d00ce64f369a6aa3e29c4650cf', $second['id']);
        static::assertStringContainsString('CONSTRAINT `fk.blog_', $second['_error']);
    }

    public function testFinishedImportDoesNothing(): void
    {
        $reader = $this->createMock(AbstractReader::class);
        $reader->expects($this->never())->method('read');

        $writer = $this->createMock(AbstractWriter::class);
        $writer->expects($this->never())->method('append');

        $pipe = $this->createMock(AbstractPipe::class);
        $pipe->expects($this->never())->method('in');
        $pipe->expects($this->never())->method('out');

        $logEntity = new ImportExportLogEntity();
        $logEntity->assign([
            'id' => Uuid::randomHex(),
            'file' => (new ImportExportFileEntity())->assign([
                'path' => 'foobar', 'size' => 1337,
            ]),
            'records' => 5,
        ]);

        $importExportService = static::createStub(ImportExportService::class);
        $importExportService->method('findLog')->willReturn($logEntity);

        $importExport = new ImportExport(
            $importExportService,
            $logEntity,
            static::getContainer()->get('contena.filesystem.private'),
            $this->listener,
            static::getContainer()->get(Connection::class),
            static::createStub(EntityRepository::class),
            $pipe,
            $reader,
            $writer,
            static::getContainer()->get(FileService::class),
            static::createStub(ImportStrategyService::class)
        );

        $importExportService->method('getProgress')
            ->willReturnCallback(
                static fn () => new Progress($logEntity->getId(), $logEntity->getState())
            );

        $logEntity->setState(Progress::STATE_SUCCEEDED);
        $importExport->import(Context::createDefaultContext());
        $importExport->export(Context::createDefaultContext(), new Criteria());

        $logEntity->setState(Progress::STATE_ABORTED);
        $importExport->import(Context::createDefaultContext());
        $importExport->export(Context::createDefaultContext(), new Criteria());

        $logEntity->setState(Progress::STATE_FAILED);
        $importExport->import(Context::createDefaultContext());
        $importExport->export(Context::createDefaultContext(), new Criteria());
    }

    public function testDryRunImport(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->rollBack();
        $connection->executeStatement('DELETE FROM `blog`');

        $clonedBlogProfile = $this->cloneDefaultProfile(BlogDefinition::ENTITY_NAME);
        static::assertIsArray($clonedBlogProfile->getMapping());
        $mappings = $clonedBlogProfile->getMapping();
        foreach (array_keys($mappings) as $key) {
            static::assertIsArray($mappings[$key]);
            if ($mappings[$key]['mappedKey'] === 'description') {
                $mappings[$key]['requiredByUser'] = true;

                break;
            }
        }
        $this->updateProfileMapping($clonedBlogProfile->getId(), $mappings);

        $progress = $this->import(
            Context::createDefaultContext(),
            BlogDefinition::ENTITY_NAME,
            '/fixtures/blogs_with_invalid_dryrun.csv',
            'blogs.csv',
            $clonedBlogProfile->getId(),
            true
        );
        static::assertImportExportFailed($progress);

        $ids = $this->blogRepository->searchIds(new Criteria(), Context::createDefaultContext());
        static::assertCount(0, $ids->getIds());

        $result = $this->getLogEntity($progress->getLogId())->getResult();
        static::assertSame(2, $result['blog_category']['insertSkip']);
        static::assertSame(8, $result['blog']['insert']);
        static::assertSame(1, $result['blog']['otherError']);

        $connection->executeStatement('DELETE FROM `import_export_log`');
        $connection->executeStatement('DELETE FROM `import_export_file`');
        $connection->executeStatement(
            'DELETE FROM `import_export_profile` WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($clonedBlogProfile->getId())]
        );
        $connection->beginTransaction();
    }

    public function testBlogWithListPrice(): void
    {
        $context = Context::createDefaultContext();
        $profile = $this->cloneDefaultProfile(BlogDefinition::ENTITY_NAME);

        $mapping = $profile->getMapping();
        $mapping[] = [
            'key' => 'price.DEFAULT.listPrice.linked',
            'mappedKey' => 'list_price_linked',
        ];
        $mapping[] = [
            'key' => 'price.DEFAULT.listPrice.gross',
            'mappedKey' => 'list_price_gross',
        ];
        $mapping[] = [
            'key' => 'price.DEFAULT.listPrice.net',
            'mappedKey' => 'list_price_net',
        ];
        $this->updateProfileMapping($profile->getId(), $mapping);

        $progress = $this->import($context, BlogDefinition::ENTITY_NAME, '/fixtures/blogs_with_list_price.csv', 'blogs_with_list_price.csv', $profile->getId());

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $result = $this->blogRepository->search(new Criteria(), Context::createDefaultContext());

        static::assertCount(2, $result->getEntities());
        $blogs = $result->getEntities();

        static::assertTrue($blogs->has('bf44b430d7cd47fcac93310edf4fe4e1'));
        $firstBlog = $blogs->get('bf44b430d7cd47fcac93310edf4fe4e1');
        static::assertInstanceOf(PriceCollection::class, $firstBlog->getPrice());
        static::assertInstanceOf(Price::class, $firstBlog->getPrice()->first());
        $firstListPrice = $firstBlog->getPrice()->first()->getListPrice();
        static::assertInstanceOf(Price::class, $firstListPrice);
        static::assertSame(100.0, $firstListPrice->getNet());
        static::assertSame(5000.0, $firstListPrice->getGross());
        static::assertFalse($firstListPrice->getLinked());

        static::assertTrue($blogs->has('bf44b430d7cd47fcac93310edf4fe4e2'));
        $secondBlog = $blogs->get('bf44b430d7cd47fcac93310edf4fe4e2');
        static::assertInstanceOf(PriceCollection::class, $secondBlog->getPrice());
        static::assertInstanceOf(Price::class, $secondBlog->getPrice()->first());
        $secondListPrice = $secondBlog->getPrice()->first()->getListPrice();
        static::assertInstanceOf(Price::class, $secondListPrice);
        static::assertSame(20.0, $secondListPrice->getNet());
        static::assertSame(50.0, $secondListPrice->getGross());
        static::assertTrue($secondListPrice->getLinked());
    }

    public function testBlogImportExportWithCustomField(): void
    {
        $context = Context::createDefaultContext();
        $profile = $this->cloneDefaultProfile(BlogDefinition::ENTITY_NAME);

        $mapping = $profile->getMapping();
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields.custom_field_1',
            'mappedKey' => 'custom_field_1',
        ];
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields.custom_field_2',
            'mappedKey' => 'custom_field_2',
        ];
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields.custom_field_3',
            'mappedKey' => 'custom_field_3',
        ];
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields.custom_field_4',
            'mappedKey' => 'custom_field_4',
        ];
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields.custom_field_5',
            'mappedKey' => 'custom_field_5',
        ];
        $mapping[] = [
            'key' => 'translations.DEFAULT.customFields',
            'mappedKey' => 'custom_fields',
        ];
        $this->updateProfileMapping($profile->getId(), $mapping);

        $this->createCustomField([
            [
                'name' => 'custom_field_1',
                'type' => 'string',
            ],
            [
                'name' => 'custom_field_2',
                'type' => 'int',
            ],
            [
                'name' => 'custom_field_3',
                'type' => 'bool',
            ],
            [
                'name' => 'custom_field_4',
                'type' => 'datetime',
            ],
            [
                'name' => 'custom_field_5',
                'type' => 'select',
            ],
            [
                'name' => 'custom_field_6',
                'type' => 'string',
            ],
        ], BlogDefinition::ENTITY_NAME);

        $progress = $this->import($context, BlogDefinition::ENTITY_NAME, '/fixtures/blogs_with_custom_fields.csv', 'blogs_with_custom_fields.csv', $profile->getId());

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $blog = $this->blogRepository->search(new Criteria(['e5c8b8f701034e8dbea72ac0fc32521e']), Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($blog);

        static::assertIsArray($blog->getCustomFields());
        static::assertSame('foo', $blog->getCustomFields()['custom_field_1']);
        static::assertSame(23, $blog->getCustomFields()['custom_field_2']);
        static::assertTrue($blog->getCustomFields()['custom_field_3']);
        static::assertSame('2021-12-12T12:00:00+00:00', $blog->getCustomFields()['custom_field_4']);
        static::assertSame(['abc8b8f701034e8dbea72ac0fc32521e', 'c5c8b8f701034e8dbea72ac0fc32521e'], $blog->getCustomFields()['custom_field_5']);

        $progress = $this->export($context, BlogDefinition::ENTITY_NAME, null, null, $profile->getId());

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csv = $this->getCsvContent($progress->getLogId());
        $resource = fopen('data://text/plain;base64,' . base64_encode($csv), 'r');
        static::assertIsResource($resource);
        $reader = new CsvReader();
        $record = null;
        foreach ($reader->read(new Config([], [], []), $resource, 0) as $row) {
            $record = $row;

            break;
        }

        static::assertNotNull($record);
        static::assertSame('foo', $record['custom_field_1']);
        static::assertSame('23', $record['custom_field_2']);
        static::assertSame('1', $record['custom_field_3']);
        static::assertSame('2021-12-12T12:00:00+00:00', $record['custom_field_4']);
        static::assertSame('["abc8b8f701034e8dbea72ac0fc32521e","c5c8b8f701034e8dbea72ac0fc32521e"]', $record['custom_field_5']);
        static::assertSame(
            '{"custom_field_1":"foo","custom_field_2":23,"custom_field_3":true,"custom_field_4":"2021-12-12T12:00:00+00:00","custom_field_5":["abc8b8f701034e8dbea72ac0fc32521e","c5c8b8f701034e8dbea72ac0fc32521e"],"custom_field_6":"bar"}',
            $record['custom_fields']
        );
    }

    #[DataProvider('channelAssignmentCsvProvider')]
    public function testChannelAssignment(string $csvPath): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `blog`');
        $connection->executeStatement('DELETE FROM `blog_visibility`');

        $blogAId = 'a5c8b8f701034e8dbea72ac0fc32521e';
        $blogABId = 'abc8b8f701034e8dbea72ac0fc32521e';
        $blogCId = 'c5c8b8f701034e8dbea72ac0fc32521e';

        $channelAId = 'a8432def39fc4624b33213a56b8c944d';
        $this->createChannel([
            'id' => $channelAId,
            'name' => 'First Sales Channel',
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/a',
            ]],
        ]);

        $channelBId = 'b8432def39fc4624b33213a56b8c944d';
        $this->createChannel([
            'id' => $channelBId,
            'name' => 'Second Sales Channel',
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost.test/b',
            ]],
        ]);

        $progress = $this->import(Context::createDefaultContext(), BlogDefinition::ENTITY_NAME, $csvPath, 'blogs_with_visibilities.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria([$blogAId]);
        $criteria->addAssociation('visibilities');

        $blogA = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blogA);

        static::assertInstanceOf(BlogVisibilityCollection::class, $blogA->getVisibilities());
        static::assertCount(1, $blogA->getVisibilities());
        static::assertNotNull($blogA->getVisibilities()->filterByChannelId($channelAId)->first());

        $criteria = new Criteria([$blogABId]);
        $criteria->addAssociation('visibilities');

        $blogB = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blogB);

        static::assertInstanceOf(BlogVisibilityCollection::class, $blogB->getVisibilities());
        static::assertCount(2, $blogB->getVisibilities());
        static::assertNotNull($blogB->getVisibilities()->filterByChannelId($channelAId)->first());
        static::assertNotNull($blogB->getVisibilities()->filterByChannelId($channelBId)->first());

        $criteria = new Criteria([$blogCId]);
        $criteria->addAssociation('visibilities');

        $blogC = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blogC);

        static::assertInstanceOf(BlogVisibilityCollection::class, $blogC->getVisibilities());
        static::assertCount(0, $blogC->getVisibilities());
        static::assertNull($blogC->getVisibilities()->filterByChannelId($channelAId)->first());
        static::assertNull($blogC->getVisibilities()->filterByChannelId($channelBId)->first());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function channelAssignmentCsvProvider(): iterable
    {
        yield 'sales channel assignments are imported from visibility ids' => ['/fixtures/blogs_with_visibilities.csv'];
        yield 'sales channel assignments are imported from visibility names' => ['/fixtures/blogs_with_visibility_names.csv'];
    }

    public function testCrossSellingCsv(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $csvPath = '/fixtures/cross_selling_products_with_own_identifier.csv';
        $progress = $this->import($context, BlogDefinition::ENTITY_NAME, $csvPath, 'blogs.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csvPath = '/fixtures/cross_selling_with_own_identifier.csv';
        $progress = $this->import($context, BlogCrossSellingDefinition::ENTITY_NAME, $csvPath, 'cross_selling_with_own_identifier.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria(['cf682b73be1afad47d0f32559ac34627', 'c9a70321b66449abb54ba9306ad02835']);
        $criteria->addAssociation('crossSellings.assignedBlogs');

        $blogA = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->get('cf682b73be1afad47d0f32559ac34627');
        static::assertNotNull($blogA);
        $blogB = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->get('c9a70321b66449abb54ba9306ad02835');
        static::assertNotNull($blogB);

        static::assertInstanceOf(BlogCrossSellingCollection::class, $blogA->getCrossSellings());
        $aCrossSelling = $blogA->getCrossSellings()->first();
        static::assertInstanceOf(BlogCrossSellingEntity::class, $aCrossSelling);
        static::assertSame('Lorem', $aCrossSelling->getName());
        static::assertInstanceOf(BlogCrossSellingAssignedBlogsCollection::class, $aCrossSelling->getAssignedBlogs());
        static::assertCount(3, $aCrossSelling->getAssignedBlogs());

        static::assertInstanceOf(BlogCrossSellingCollection::class, $blogB->getCrossSellings());
        $bCrossSelling = $blogB->getCrossSellings()->first();
        static::assertInstanceOf(BlogCrossSellingEntity::class, $bCrossSelling);
        static::assertSame('Ipsum', $bCrossSelling->getName());
        static::assertInstanceOf(BlogCrossSellingAssignedBlogsCollection::class, $bCrossSelling->getAssignedBlogs());
        static::assertCount(3, $bCrossSelling->getAssignedBlogs());

        $progress = $this->export($context, BlogCrossSellingDefinition::ENTITY_NAME);

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csv = $this->getCsvContent($progress->getLogId());

        static::assertStringContainsString(
            'f26b0d8f252a76f2f99337cced08314b|c1ace7586faa4342a4d3b33e6dd33b7c|c9a70321b66449abb54ba9306ad02835',
            $csv
        );

        static::assertStringContainsString(
            'c1ace7586faa4342a4d3b33e6dd33b7c|f26b0d8f252a76f2f99337cced08314b|cf682b73be1afad47d0f32559ac34627',
            $csv
        );
    }

    public function testUsersCsv(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `user`');

        $channel = $this->createChannel();

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        $mailSent = false;

        $listenerClosure = static function () use (&$mailSent): void {
            $mailSent = true;
        };

        EventHookDispatcher::fromContainer(static::getContainer())->on(MailSentEvent::class, $listenerClosure);

        $profile = $this->cloneDefaultProfile(UserDefinition::ENTITY_NAME);
        $mapping = $profile->getMapping();
        $mapping[] = [
            'key' => 'password',
            'mappedKey' => 'password',
        ];
        $this->updateProfileMapping($profile->getId(), $mapping);

        $progress = $this->import(
            $context,
            UserDefinition::ENTITY_NAME,
            '/fixtures/users.csv',
            'users.csv',
            $profile->getId(),
        );
        static::getContainer()->get(BufferedFlowExecutor::class)->executeBufferedFlows();

        static::assertTrue($context->hasState(Context::SKIP_TRIGGER_FLOW));
        static::assertFalse($mailSent, 'The mail.sent Event did run');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $criteria = new Criteria();
        $criteria->addAssociation('addresses');
        $criteria->addAssociation('defaultBillingAddress');
        $criteria->addAssociation('defaultShippingAddress');
        /** @var EntityRepository<UserCollection> $repository */
        $repository = static::getContainer()->get('user.repository');
        $result = $repository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(3, $result);

        static::assertTrue($result->has('0a1dea4bd2de43929ac210fd17339dde'));
        $userWithMultipleAddresses = $result->get('0a1dea4bd2de43929ac210fd17339dde');

        $passwords = \array_values(array_map(static fn (UserEntity $user) => $user->getPassword(), $result->getElements()));
        static::assertCount(3, $passwords);
        static::assertNull($passwords[0]);
        static::assertNull($passwords[1]);
        static::assertNull($passwords[2]);

        static::assertInstanceOf(UserAddressCollection::class, $userWithMultipleAddresses->getAddresses());
        static::assertCount(4, $userWithMultipleAddresses->getAddresses());
        static::assertInstanceOf(UserAddressEntity::class, $userWithMultipleAddresses->getDefaultBillingAddress());
        static::assertSame('Contena', $userWithMultipleAddresses->getDefaultBillingAddress()->getCompany());

        static::assertTrue($result->has('f3bb913bc8cc48479c3834a75e82920b'));
        $userWithUpdatedAddresses = $result->get('f3bb913bc8cc48479c3834a75e82920b');

        static::assertInstanceOf(UserAddressCollection::class, $userWithUpdatedAddresses->getAddresses());
        static::assertCount(2, $userWithUpdatedAddresses->getAddresses());
        static::assertInstanceOf(UserAddressEntity::class, $userWithUpdatedAddresses->getDefaultShippingAddress());
        static::assertSame('Contena', $userWithUpdatedAddresses->getDefaultShippingAddress()->getCompany());

        $progress = $this->export(
            $context,
            UserDefinition::ENTITY_NAME,
            null,
            null,
            $profile->getId()
        );

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csv = $this->getCsvContent($progress->getLogId());
        static::assertStringContainsString($channel['name'], $csv);
        static::assertStringContainsString('Contena', $csv);
        static::assertStringContainsString('en-GB', $csv);
        static::assertStringContainsString('Standard user group', $csv);
        static::assertStringNotContainsString('password', $csv);
    }

    public function testImportWithCreateAndUpdateConfig(): void
    {
        // expect default upsert
        $mockRepo = $this->runUserImportWithConfigAndMockedRepository([
            'createEntities' => true,
            'updateEntities' => true,
        ]);
        static::assertSame(5, $mockRepo->upsertCalls);
        static::assertSame(0, $mockRepo->createCalls);
        static::assertSame(0, $mockRepo->updateCalls);

        // expect create
        $mockRepo = $this->runUserImportWithConfigAndMockedRepository([
            'createEntities' => true,
            'updateEntities' => false,
        ]);
        static::assertSame(0, $mockRepo->upsertCalls);
        static::assertSame(5, $mockRepo->createCalls);
        static::assertSame(0, $mockRepo->updateCalls);

        // expect update
        $mockRepo = $this->runUserImportWithConfigAndMockedRepository([
            'createEntities' => false,
            'updateEntities' => true,
        ]);
        static::assertSame(0, $mockRepo->upsertCalls);
        static::assertSame(0, $mockRepo->createCalls);
        static::assertSame(5, $mockRepo->updateCalls);

        // expect upsert if both flags are false
        $mockRepo = $this->runUserImportWithConfigAndMockedRepository([
            'createEntities' => false,
            'updateEntities' => false,
        ]);
        static::assertSame(5, $mockRepo->upsertCalls);
        static::assertSame(0, $mockRepo->createCalls);
        static::assertSame(0, $mockRepo->updateCalls);
    }

    public function testUserImportExportWithBoundChannel(): void
    {
        $userRepository = self::getContainer()->get('user.repository');
        $users = $userRepository->search(new Criteria(), Context::createDefaultContext());
        static::assertCount(0, $users->getEntities());

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $profile = $this->cloneDefaultProfile(UserDefinition::ENTITY_NAME);
        $mapping = $profile->getMapping();
        $mapping[] = [
            'key' => 'boundChannelId',
            'mappedKey' => 'boundsaleschannel_id',
        ];
        $this->updateProfileMapping($profile->getId(), $mapping);

        $channel = $this->createChannel([
            'id' => '01902502a01172ad948f5a50096da0bd',
            'name' => 'Sales-Channel-Name',
        ]);

        $numberRangeRepository = self::getContainer()->get('number_range.repository');

        $numberRangeCriteria = new Criteria();
        $numberRangeCriteria->addAssociation('type');
        $numberRangeCriteria->addFilter(new EqualsFilter(
            'type.technicalName',
            UserDefinition::ENTITY_NAME,
        ));
        $numberRangeCriteria->addFilter(new EqualsFilter('global', true));

        $userNumberRange = $numberRangeRepository
            ->search($numberRangeCriteria, $context)->getEntities()->first();

        static::assertNotNull($userNumberRange);
        static::assertInstanceOf(NumberRangeEntity::class, $userNumberRange);

        $numberRangeRepository->update([[
            'id' => $userNumberRange->getId(),
            'pattern' => 'SWDEMO{n}',
        ]], $context);
        static::getContainer()->get(UserNumberRangeConfigService::class)->reset();

        $progress = $this->import(
            $context,
            UserDefinition::ENTITY_NAME,
            '/fixtures/users_boundsaleschannel.csv',
            'users_boundsaleschannel.csv',
            $profile->getId()
        );

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $users = $userRepository->search(new Criteria(), Context::createDefaultContext());
        static::assertCount(1, $users->getEntities());
        $user = $users->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        $boundChannelId = $user->getBoundChannelId();
        static::assertIsString($boundChannelId);
        static::assertSame($channel['id'], $boundChannelId);

        $progress = $this->export($context, UserDefinition::ENTITY_NAME, null, null, $profile->getId());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csvContent = $this->getCsvContent($progress->getLogId());
        static::assertStringContainsString($channel['id'], $csvContent);
    }

    public function testPromotionCodeImportExport(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `promotion_individual_code`');

        // create the promotion before the import
        $promotion = $this->createPromotion([
            'id' => 'c1a28776116d4431a2208eb2960ec340',
            'name' => 'MyPromo',
        ]);

        // add one already generated code to the promotion
        // already existing codes can only be updated by import
        // -> code is unique
        $this->createPromotionCode($promotion['id'], [
            'code' => 'TestCode',
        ]);

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import($context, PromotionIndividualCodeDefinition::ENTITY_NAME, '/fixtures/promotion_individual_codes.csv', 'promotion_individual_codes.csv');

        // validate import
        static::assertImportExportFailed($progress);

        $failingRecords = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());
        static::assertCount(4, $failingRecords);

        /** @var EntityRepository<PromotionIndividualCodeCollection> $repository */
        $repository = static::getContainer()->get('promotion_individual_code.repository');
        $criteria = new Criteria();
        $criteria->addAssociation('promotion');
        $individualCodes = $repository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(10, $individualCodes);

        foreach ($individualCodes as $promoCodeResult) {
            static::assertInstanceOf(PromotionEntity::class, $promoCodeResult->getPromotion());
            static::assertTrue($promoCodeResult->getPromotion()->isUseIndividualCodes(), 'Promotion should have useIndividualCodes set to true after import');
        }

        // export
        $progress = $this->export($context, PromotionIndividualCodeDefinition::ENTITY_NAME);

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $csv = $this->getCsvContent($progress->getLogId());

        // validate export
        foreach ($individualCodes as $promoCodeResult) {
            static::assertStringContainsString($promoCodeResult->getId(), $csv);
            static::assertInstanceOf(PromotionEntity::class, $promoCodeResult->getPromotion());
            static::assertStringContainsString($promoCodeResult->getPromotion()->getId(), $csv);
            static::assertStringContainsString($promoCodeResult->getCode(), $csv);
        }
    }

    public function testPromotionDiscountImportExport(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $promotionId = '6081116ad83747b9b9ce086460e8569a';
        $this->createPromotion(['id' => $promotionId, 'name' => 'MyPromo']);

        $ruleId = 'cb34dc6f20b6479aa975e1290f442e65';
        $this->createRule($ruleId);

        $progress = $this->import($context, PromotionDiscountDefinition::ENTITY_NAME, '/fixtures/promotion_discounts.csv', 'promotion_discounts.csv');

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        /** @var EntityRepository<PromotionCollection> $promotionRepo */
        $promotionRepo = static::getContainer()->get('promotion.repository');
        $promotion = $promotionRepo->search((new Criteria([$promotionId]))->addAssociation('discounts.discountRules'), $context)->getEntities()->first();
        static::assertNotNull($promotion);

        $discounts = $promotion->getDiscounts();
        static::assertInstanceOf(PromotionDiscountCollection::class, $discounts);
        static::assertCount(2, $discounts);

        $firstDiscount = $discounts->first();
        static::assertInstanceOf(PromotionDiscountEntity::class, $firstDiscount);
        static::assertSame('cart', $firstDiscount->getScope());
        static::assertSame('absolute', $firstDiscount->getType());
        static::assertSame(5.0, $firstDiscount->getValue());
        static::assertFalse($firstDiscount->isConsiderAdvancedRules());
        static::assertNull($firstDiscount->getMaxValue());
        static::assertSame('PRICE_ASC', $firstDiscount->getSorterKey());
        static::assertSame('ALL', $firstDiscount->getApplierKey());
        static::assertSame('ALL', $firstDiscount->getUsageKey());
        static::assertSame('', $firstDiscount->getPickerKey());
        static::assertInstanceOf(RuleCollection::class, $firstDiscountRules = $firstDiscount->getDiscountRules());
        static::assertCount(0, $firstDiscountRules->getIds());

        $lastDiscount = $discounts->last();
        static::assertInstanceOf(PromotionDiscountEntity::class, $lastDiscount);
        static::assertSame('set', $lastDiscount->getScope());
        static::assertSame('percentage', $lastDiscount->getType());
        static::assertSame(2.5, $lastDiscount->getValue());
        static::assertTrue($lastDiscount->isConsiderAdvancedRules());
        static::assertSame(4.0, $lastDiscount->getMaxValue());
        static::assertSame('PRICE_DESC', $lastDiscount->getSorterKey());
        static::assertSame('1', $lastDiscount->getApplierKey());
        static::assertSame('1', $lastDiscount->getUsageKey());
        static::assertSame('VERTICAL', $lastDiscount->getPickerKey());
        static::assertInstanceOf(RuleCollection::class, $lastDiscountRules = $lastDiscount->getDiscountRules());
        static::assertContains($ruleId, $lastDiscountRules->getIds());

        $progress = $this->export($context, PromotionDiscountDefinition::ENTITY_NAME);
        static::assertImportExportSucceeded($progress);

        $csv = $this->getCsvContent($progress->getLogId());
        static::assertStringEqualsFile(__DIR__ . '/fixtures/promotion_discounts_export.csv', $csv);
    }

    public function testExportOrders(): void
    {
        $orderId = Uuid::randomHex();
        $testOrder = $this->getOrderData($orderId, Context::createDefaultContext())[0];
        $orderRepository = static::getContainer()->get('order.repository');

        $context = Context::createDefaultContext();
        $orderRepository->upsert([$testOrder], $context);

        $criteria = new Criteria([$testOrder['id']]);
        $progress = $this->export(Context::createDefaultContext(), OrderDefinition::ENTITY_NAME, $criteria);

        static::assertTrue($progress->isFinished());
        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));
    }

    public function testExportBlogsWithDeliveryTimeTranslation(): void
    {
        $deliveryTimeId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        static::getContainer()->get('delivery_time.repository')->create([
            [
                'id' => $deliveryTimeId,
                'name' => '1-3 working days',
                'min' => 1,
                'max' => 3,
                'unit' => 'day',
            ],
        ], $context);

        static::getContainer()->get('blog.repository')->create([
            [
                'id' => $blogId,
                'name' => 'delivery-time-export-test',
                'blogNumber' => 'delivery-time-export-test',
                'stock' => 10,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
                ],
                'active' => true,
                'tax' => ['name' => 'delivery-time-tax', 'taxRate' => 19],
                'deliveryTimeId' => $deliveryTimeId,
            ],
        ], $context);

        $profile = $this->cloneDefaultProfile(BlogDefinition::ENTITY_NAME);

        $mappings = $profile->getMapping();
        static::assertIsArray($mappings);

        array_unshift($mappings, [
            'key' => 'deliveryTime.translations.DEFAULT.name',
            'mappedKey' => 'delivery_time',
            'position' => -1,
        ]);

        $this->updateProfileMapping($profile->getId(), $mappings);

        $progress = $this->export(Context::createDefaultContext(), BlogDefinition::ENTITY_NAME, new Criteria([$blogId]), null, $profile->getId());

        static::assertImportExportSucceeded($progress);

        $csv = $this->getCsvContent($progress->getLogId());
        $rows = array_map(static fn (string $line) => str_getcsv($line, ';', '"', '\\'), array_filter(explode("\n", trim($csv))));

        static::assertCount(2, $rows);
        static::assertSame('delivery_time', $rows[0][0]);
        static::assertSame('1-3 working days', $rows[1][0]);
    }

    public function testImportBlogsWithUpdateByMapping(): void
    {
        $this->importCategoryCsv();
        $this->importPropertyCsv();

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        // setup profile
        $clonedPropertyProfile = $this->cloneDefaultProfile(BlogDefinition::ENTITY_NAME);
        $mappings = $clonedPropertyProfile->getMapping();
        $mappings[] = [
            'key' => 'unit.translations.DEFAULT.name',
            'mappedKey' => 'unit_name_en',
        ];
        $mappings[] = [
            'key' => 'unit.translations.DEFAULT.shortCode',
            'mappedKey' => 'unit_short_code_en',
        ];
        $mappings[] = [
            'key' => 'unit.translations.de-DE.name',
            'mappedKey' => 'unit_name_de',
        ];
        $mappings[] = [
            'key' => 'unit.translations.de-DE.shortCode',
            'mappedKey' => 'unit_short_code_de',
        ];
        $this->updateProfileMapping($clonedPropertyProfile->getId(), $mappings);
        $updateBy = [
            ['entityName' => BlogDefinition::ENTITY_NAME, 'mappedKey' => 'blogNumber'],
            ['entityName' => TaxDefinition::ENTITY_NAME, 'mappedKey' => 'taxRate'],
            ['entityName' => BlogManufacturerDefinition::ENTITY_NAME, 'mappedKey' => 'translations.DEFAULT.name'],
            ['entityName' => UnitDefinition::ENTITY_NAME, 'mappedKey' => 'translations.de-DE.name'],
            ['entityName' => CategoryDefinition::ENTITY_NAME, 'mappedKey' => 'translations.en-GB.name'],
            ['entityName' => PropertyGroupOptionDefinition::ENTITY_NAME, 'mappedKey' => 'translations.DEFAULT.name'],
        ];
        $this->updateProfileUpdateBy($clonedPropertyProfile->getId(), $updateBy);

        $progress = $this->import(
            $context,
            BlogDefinition::ENTITY_NAME,
            '/fixtures/blogs_with_update_by.csv',
            'blogs_with_update_by.csv',
            $clonedPropertyProfile->getId()
        );

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $blogs = $this->blogRepository->search((new Criteria())->addAssociations(['categories', 'properties']), $context);

        static::assertCount(1, $blogs->getEntities());
        static::assertInstanceOf(BlogEntity::class, $blog = $blogs->getEntities()->first());
        static::assertInstanceOf(CategoryCollection::class, $categories = $blog->getCategories());
        static::assertInstanceOf(PropertyGroupOptionCollection::class, $properties = $blog->getProperties());
        static::assertCount(3, $categories);
        static::assertCount(3, $properties);

        $taxes = static::getContainer()->get('tax.repository')->search(
            (new Criteria())->addFilter(new EqualsFilter('taxRate', 23)),
            $context
        )->getEntities();

        static::assertInstanceOf(TaxCollection::class, $taxes);
        static::assertCount(1, $taxes);
        static::assertInstanceOf(TaxEntity::class, $tax = $taxes->first());
        static::assertSame('changed', $tax->getName());

        $manufacturerCount = static::getContainer()->get('blog_manufacturer.repository')->search(
            (new Criteria())->addFilter(new EqualsFilter('name', 'onlyone')),
            $context
        )->getEntities()->count();

        static::assertSame(1, $manufacturerCount);

        $units = static::getContainer()->get('unit.repository')->search(
            new Criteria(),
            $context
        )->getEntities();

        static::assertInstanceOf(UnitCollection::class, $units);
        static::assertCount(1, $units);
        static::assertInstanceOf(UnitEntity::class, $unit = $units->first());
        static::assertSame('foo', $unit->getName());
    }

    public function testImportBlogsWithInvalidUpdateByMapping(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        // setup profile
        $clonedPropertyProfile = $this->cloneDefaultProfile(BlogDefinition::ENTITY_NAME);
        $mappings = $clonedPropertyProfile->getMapping();
        $mappings[] = [
            'key' => 'manufacturer.link',
            'mappedKey' => 'manufacturer_link',
        ];
        $this->updateProfileMapping($clonedPropertyProfile->getId(), $mappings);
        $updateBy = [
            ['entityName' => BlogManufacturerDefinition::ENTITY_NAME, 'mappedKey' => 'link'],
        ];
        $this->updateProfileUpdateBy($clonedPropertyProfile->getId(), $updateBy);

        $progress = $this->import(
            $context,
            BlogDefinition::ENTITY_NAME,
            '/fixtures/blogs_with_invalid_update_by.csv',
            'blogs_with_invalid_update_by.csv',
            $clonedPropertyProfile->getId()
        );

        static::assertImportExportFailed($progress);

        $invalid = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());

        static::assertGreaterThanOrEqual(1, \count($invalid));
        $first = $invalid[0];
        static::assertStringContainsString(
            (new UpdatedByValueNotFoundException(BlogManufacturerDefinition::ENTITY_NAME, 'link'))->getMessage(),
            $first['_error']
        );
    }

    public function testBatchImport(): void
    {
        $categoryRepository = static::getContainer()->get('category.repository');

        $categories = $categoryRepository->search(new Criteria(), Context::createDefaultContext());
        $categoryCount = $categories->getTotal();

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $progress = $this->import(
            $context,
            CategoryDefinition::ENTITY_NAME,
            '/fixtures/categories.csv',
            'categories.csv',
            useBatchImport: true
        );

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        $numberOfCategoriesInCsv = 67;

        $categories = $categoryRepository->search(new Criteria(), Context::createDefaultContext());
        static::assertSame($categoryCount + $numberOfCategoriesInCsv, $categories->getTotal());
    }

    public function testBatchImportNumberOfCalls(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $importExportService = static::getContainer()->get(ImportExportService::class);
        $expireDate = new \DateTimeImmutable('2099-01-01');

        $file = new UploadedFile(__DIR__ . '/fixtures/blogs.csv', 'blogs.csv', 'text/csv');

        $logEntity = $importExportService->prepareImport(
            $context,
            $this->getDefaultProfileId('blog'),
            $expireDate,
            $file
        );

        $progress = new Progress($logEntity->getId(), Progress::STATE_PROGRESS, 0, null);

        $pipeFactory = static::getContainer()->get(PipeFactory::class);
        $readerFactory = static::getContainer()->get(CsvReaderFactory::class);
        $writerFactory = static::getContainer()->get(CsvFileWriterFactory::class);

        /** @var MockRepository<UserCollection> */
        $mockRepository = new MockRepository(static::getContainer()->get(UserDefinition::class));

        $importExport = new ImportExport(
            $importExportService,
            $logEntity,
            static::getContainer()->get('contena.filesystem.private'),
            $this->listener,
            static::getContainer()->get(Connection::class),
            $mockRepository,
            $pipeFactory->create($logEntity),
            $readerFactory->create($logEntity),
            $writerFactory->create($logEntity),
            static::getContainer()->get(FileService::class),
            new BatchImportStrategy($this->listener, $mockRepository),
            10,
            10
        );

        do {
            $progress = $importExport->import($context, $progress->getOffset());
        } while (!$progress->isFinished());

        static::assertImportExportSucceeded($progress, $this->getInvalidLogContent($progress->getInvalidRecordsLogId()));

        static::assertSame(6, $mockRepository->upsertCalls);
    }

    public function testInvalidFileInBatch(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `blog`');

        $progress = $this->import(
            Context::createDefaultContext(),
            BlogDefinition::ENTITY_NAME,
            '/fixtures/blogs_with_invalid.csv',
            'blogs.csv',
            useBatchImport: true
        );

        static::assertImportExportFailed($progress);

        $ids = $this->blogRepository->searchIds(new Criteria(), Context::createDefaultContext());
        static::assertCount(8, $ids->getIds());

        $invalid = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());

        static::assertCount(2, $invalid);

        $first = $invalid[0];
        static::assertSame('e5c8b8f701034e8dbea72ac0fc32521e', $first['id']);
        static::assertStringContainsString('CONSTRAINT `fk.blog_', $first['_error']);

        $second = $invalid[1];
        static::assertSame('d5e8a6d00ce64f369a6aa3e29c4650cf', $second['id']);
        static::assertStringContainsString('CONSTRAINT `fk.blog_', $second['_error']);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function testUpdateOnlyProfileWithNonExistingRecords(bool $useBatchMode): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM blog');

        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $profile = $this->cloneDefaultProfile(BlogDefinition::ENTITY_NAME);
        $this->updateProfileConfig($profile->getId(), [
            'createEntities' => false,
            'updateEntities' => true,
        ]);

        $progress = $this->import(
            $context,
            BlogDefinition::ENTITY_NAME,
            '/fixtures/blogs_with_invalid.csv',
            'blogs_with_invalid.csv',
            $profile->getId(),
            useBatchImport: $useBatchMode,
        );

        static::assertImportExportFailed($progress);

        $ids = $this->blogRepository->searchIds(new Criteria(), Context::createDefaultContext());
        static::assertCount(0, $ids->getIds());

        $invalid = $this->getInvalidLogContent($progress->getInvalidRecordsLogId());
        static::assertGreaterThanOrEqual(1, \count($invalid));

        $errorRecord = $invalid[0];
        static::assertArrayHasKey('_error', $errorRecord);
        static::assertSame(
            'The blog record was not found. This import profile only allows updates to existing records.',
            $errorRecord['_error']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createCategoryProfileMock(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'technicalName' => 'test_profile',
            'label' => 'Test Profile',
            'sourceEntity' => 'category',
            'type' => ImportExportProfileEntity::TYPE_IMPORT_EXPORT,
            'fileType' => 'text/csv',
            'delimiter' => ';',
            'enclosure' => '"',
            'config' => [],
            'mapping' => [
                ['key' => 'id', 'mappedKey' => 'id', 'position' => 1],
                ['key' => 'active', 'mappedKey' => 'active', 'position' => 2],
                ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name', 'position' => 3],
                ['key' => 'type', 'mappedKey' => 'type', 'position' => 0],
            ],
        ];
    }

    private function getCsvContent(string $logId): string
    {
        $filesystem = static::getContainer()->get('contena.filesystem.private');
        $logfile = $this->getLogEntity($logId)->getFile();
        static::assertInstanceOf(ImportExportFileEntity::class, $logfile);

        $csv = $filesystem->read($logfile->getPath());
        static::assertIsString($csv);

        return $csv;
    }
}
