<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogExport\ScheduledTask;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\BlogExport\BlogExportCollection;
use Contena\Core\Content\BlogExport\BlogExportEntity;
use Contena\Core\Content\BlogExport\ScheduledTask\BlogExportGenerateTaskHandler;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
class BlogExportGenerateTaskHandlerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<BlogExportCollection>
     */
    private EntityRepository $blogExportRepository;

    private Context $context;

    private FilesystemOperator $fileSystem;

    protected function setUp(): void
    {
        if (!static::getContainer()->has(BlogPageSeoUrlRoute::class)) {
            static::markTestSkipped('BlogExport tests need frontend bundle to be active');
        }

        $this->blogExportRepository = static::getContainer()->get('blog_export.repository');
        $this->context = Context::createDefaultContext();
        $this->fileSystem = static::getContainer()->get('contena.filesystem.private');
    }

    public function testSkipGenerateByCronjobFalseBlogExports(): void
    {
        $this->createBlogStream();
        // only get seconds, not microseconds, for better comparison to DB
        $previousGeneratedAt = \DateTime::createFromFormat('U', (string) time());
        static::assertInstanceOf(\DateTimeInterface::class, $previousGeneratedAt);
        $exportId = $this->createTestEntity($previousGeneratedAt, 0, 'Testexport.csv', false);
        $this->clearQueue();
        $this->getTaskHandler()->run();

        $url = '/api/_action/message-queue/consume';
        $client = $this->getBrowser();
        $client->request('POST', $url, ['receiver' => 'async']);

        static::assertSame(200, $client->getResponse()->getStatusCode());
        $response = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('handledMessages', $response);
        static::assertIsInt($response['handledMessages']);
        static::assertSame(0, $response['handledMessages']);

        $filePath = \sprintf('%s/Testexport.csv', static::getContainer()->getParameter('blog_export.directory'));
        static::assertFalse($this->fileSystem->fileExists($filePath));

        $newExport = $this->blogExportRepository->search(new Criteria([$exportId]), $this->context)->getEntities()->first();
        static::assertNotNull($newExport);
        static::assertSame($previousGeneratedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT), $newExport->getGeneratedAt()?->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }

    public function testStaleRunningExportIsUnlockedAndGenerated(): void
    {
        $this->createBlogStream();

        $exportId = $this->createTestEntity(null, 10, 'StaleTestexport.csv', true);

        // Simulate a stuck run: set is_running=1 and updated_at in the past
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement(
            'UPDATE `blog_export` SET `is_running` = 1, `updated_at` = :updatedAt WHERE `id` = :id',
            [
                'updatedAt' => (new \DateTimeImmutable('-1 hour'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => Uuid::fromHexToBytes($exportId),
            ]
        );

        $this->clearQueue();
        $this->getTaskHandler()->run();

        // Consume all queued messages
        $client = $this->getBrowser();
        $client->request('POST', '/api/_action/message-queue/consume', ['receiver' => 'async']);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        // File should be generated
        $filePath = \sprintf('%s/%s', static::getContainer()->getParameter('blog_export.directory'), 'StaleTestexport.csv');
        static::assertTrue($this->fileSystem->fileExists($filePath));

        // And the export entity should be unlocked and have a generatedAt timestamp
        $newExport = $this->blogExportRepository->search(new Criteria([$exportId]), $this->context)->getEntities()->first();
        static::assertNotNull($newExport);
        static::assertFalse($newExport->getIsRunning());
        static::assertInstanceOf(\DateTimeInterface::class, $newExport->getGeneratedAt());
    }

    protected function createSecondFrontendChannel(): void
    {
        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('channel.repository');
        $criteria = (new Criteria())
            ->setIds([$this->getChannelDomain()->getChannelId()])
            ->addAssociation('languages');

        $originalChannel = $channelRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($originalChannel);

        $originalChannelLanguages = $originalChannel->getLanguages();
        static::assertNotNull($originalChannelLanguages);
        $originalChannelArray = $originalChannelLanguages->jsonSerialize();
        $languages = array_map(static fn (LanguageEntity $language): array => ['id' => $language->getId()], $originalChannelArray);

        $id = '000000009276457086da48d5b5628f3c';
        $data = [
            'id' => $id,
            'accessKey' => $id,
            'name' => 'A totally fake Frontend Channel',
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'currencyId' => Defaults::CURRENCY,
            'languages' => $languages,
            'languageId' => $originalChannel->getLanguageId(),
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(),
        ];
        $channelRepository->create([$data], $this->context);
    }

    private function getTaskHandler(): BlogExportGenerateTaskHandler
    {
        return new BlogExportGenerateTaskHandler(
            static::getContainer()->get('scheduled_task.repository'),
            $this->createMock(LoggerInterface::class),
            static::getContainer()->get(Connection::class),
            static::getContainer()->get('messenger.default_bus'),
            new NativeClock(),
        );
    }

    private function getChannelId(): string
    {
        /** @var EntityRepository<ChannelCollection> $repository */
        $repository = static::getContainer()->get('channel.repository');

        $id = $repository->search(new Criteria(), $this->context)->getEntities()->first()?->getId();
        static::assertIsString($id);

        return $id;
    }

    private function getChannelDomain(): ChannelDomainEntity
    {
        /** @var EntityRepository<ChannelDomainCollection> $repository */
        $repository = static::getContainer()->get('channel_domain.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('channel');
        $criteria->addFilter(new EqualsFilter('channel.typeId', Defaults::CHANNEL_TYPE_FRONTEND));

        $domainEntity = $repository->search($criteria, $this->context)->getEntities()->first();
        static::assertNotNull($domainEntity);

        return $domainEntity;
    }

    private function getChannelDomainId(): string
    {
        return $this->getChannelDomain()->getId();
    }

    private function createTestEntity(?\DateTimeInterface $generatedAt = null, int $interval = 0, string $filename = 'Testexport.csv', bool $generateByCronjob = true): string
    {
        $id = Uuid::randomHex();
        $this->blogExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => $filename,
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => $interval,
                'headerTemplate' => 'name,url',
                'bodyTemplate' => '{{ blog.name }}',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => $this->getChannelDomain()->getChannelId(),
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generatedAt' => $generatedAt,
                'generateByCronjob' => $generateByCronjob,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        return $id;
    }

    private function createBlogStream(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $randomBlogIds = implode('|', \array_slice(array_column($this->createBlogs(), 'id'), 0, 2));

        $connection->executeStatement("
            INSERT INTO `content_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('137B079935714281BA80B40F83F8D7EB'), '[{\"type\": \"multi\", \"queries\": [{\"type\": \"multi\", \"queries\": [{\"type\": \"equalsAny\", \"field\": \"blog.id\", \"value\": \"$randomBlogIds\"}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"blog.width\", \"parameters\": {\"gte\": 221, \"lte\": 932}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"blog.width\", \"parameters\": {\"lte\": 245}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"equals\", \"field\": \"blog.manufacturer.id\", \"value\": \"02f6b9aa385d4f40aaf573661b2cf919\"}, {\"type\": \"range\", \"field\": \"blog.height\", \"parameters\": {\"gte\": 182}}], \"operator\": \"AND\"}], \"operator\": \"OR\"}]', 0, '2019-08-16 08:43:57.488', NULL);
        ");

        $connection->executeStatement("
            INSERT INTO `content_stream_filter` (`id`, `content_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX('137B079935714281BA80B40F83F8D7EB'), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                (UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 1, NULL, '2019-08-16 08:43:57.478', NULL),
                (UNHEX('272B4392E7B34EF2ABB4827A33630C1D'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 3, NULL, '2019-08-16 08:43:57.486', NULL),
                (UNHEX('4A7AEB36426A482A8BFFA049F795F5E7'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.470', NULL),
                (UNHEX('BB87D86524FB4E7EA01EE548DD43A5AC'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 2, NULL, '2019-08-16 08:43:57.483', NULL),
                (UNHEX('56C5DF0B41954334A7B0CDFEDFE1D7E9'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('272B4392E7B34EF2ABB4827A33630C1D'), 'range', 'width', NULL, NULL, '{\"lte\":932,\"gte\":221}', 1, NULL, '2019-08-16 08:43:57.488', NULL),
                (UNHEX('6382E03A768F444E9C2A809C63102BD4'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('BB87D86524FB4E7EA01EE548DD43A5AC'), 'range', 'height', NULL, NULL, '{\"gte\":182}', 2, NULL, '2019-08-16 08:43:57.485', NULL),
                (UNHEX('7CBC1236ABCD43CAA697E9600BF1DF6E'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('4A7AEB36426A482A8BFFA049F795F5E7'), 'range', 'width', NULL, NULL, '{\"lte\":245}', 1, NULL, '2019-08-16 08:43:57.476', NULL),
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '$randomBlogIds', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL);
    ");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createBlogs(): array
    {
        $blogRepository = static::getContainer()->get('blog.repository');
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $channelId = $this->getChannelDomain()->getChannelId();
        $blogs = [];

        for ($i = 0; $i < 10; ++$i) {
            $blogs[] = [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 17, 'name' => 'with id'],
                'visibilities' => [
                    ['channelId' => $channelId, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        $blogRepository->create($blogs, $this->context);

        return $blogs;
    }
}
