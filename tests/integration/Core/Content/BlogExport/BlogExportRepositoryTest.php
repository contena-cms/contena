<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogExport;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\BlogExport\Exception\DuplicateFileNameException;
use Contena\Core\Content\BlogExport\BlogExportCollection;
use Contena\Core\Content\BlogExport\BlogExportEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class BlogExportRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogExportCollection>
     */
    private EntityRepository $blogExportRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->blogExportRepository = static::getContainer()->get('blog_export.repository');
        $this->context = Context::createDefaultContext();

        $this->createBlogStream();
    }

    public function testCreateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->blogExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => TestDefaults::CHANNEL,
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        $entity = $this->blogExportRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertInstanceOf(BlogExportEntity::class, $entity);
        static::assertSame('Testexport', $entity->getFileName());
        static::assertSame($id, $entity->getId());
    }

    public function testFrontendChannelIdIsRequired(): void
    {
        $id = Uuid::randomHex();

        $e = null;

        try {
            $this->blogExportRepository->upsert([
                [
                    'id' => $id,
                    'fileName' => 'Testexport',
                    'accessKey' => Uuid::randomHex(),
                    'encoding' => BlogExportEntity::ENCODING_UTF8,
                    'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                    'interval' => 0,
                    'bodyTemplate' => 'test',
                    'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                    'frontendChannelId' => null,
                    'channelId' => $this->getChannelId(),
                    'channelDomainId' => $this->getChannelDomainId(),
                    'generateByCronjob' => false,
                    'currencyId' => Defaults::CURRENCY,
                ],
            ], $this->context);
        } catch (WriteException $e) {
        }

        static::assertInstanceOf(WriteException::class, $e);
        $exception = $e->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        $violation = $exception->getViolations()->get(0);
        static::assertSame('/frontendChannelId', $violation->getPropertyPath());
    }

    public function testFeedLabelAcceptsValidValue(): void
    {
        $id = $this->upsertWithFeedLabel('SUMMER-2026');

        $entity = $this->blogExportRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertInstanceOf(BlogExportEntity::class, $entity);
        static::assertSame('SUMMER-2026', $entity->getFeedLabel());
    }

    public function testFeedLabelAllowsNull(): void
    {
        $id = $this->upsertWithFeedLabel(null);

        $entity = $this->blogExportRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertInstanceOf(BlogExportEntity::class, $entity);
        static::assertNull($entity->getFeedLabel());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFeedLabelProvider(): iterable
    {
        yield 'lowercase letters' => ['summer-2026'];
        yield 'mixed case' => ['Summer-2026'];
        yield 'contains space' => ['SUMMER 2026'];
        yield 'contains exclamation mark' => ['SUMMER!'];
        yield 'contains slash' => ['EU/DE'];
    }

    #[DataProvider('invalidFeedLabelProvider')]
    public function testFeedLabelRejectsInvalidValue(string $invalidValue): void
    {
        $exception = null;

        try {
            $this->upsertWithFeedLabel($invalidValue);
        } catch (WriteException $exception) {
        }

        static::assertInstanceOf(WriteException::class, $exception);
        $inner = $exception->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $inner);
        $violation = $inner->getViolations()->get(0);
        static::assertSame('/feedLabel', $violation->getPropertyPath());
        static::assertSame('BLOG_EXPORT__INVALID_FEED_LABEL_FORMAT', $violation->getCode());
    }

    public function testUpdateEntity(): void
    {
        $id = Uuid::randomHex();
        $this->blogExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => TestDefaults::CHANNEL,
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);
        $this->blogExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => 'Newexport',
            ],
        ], $this->context);

        $entity = $this->blogExportRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertInstanceOf(BlogExportEntity::class, $entity);
        static::assertSame('Newexport', $entity->getFileName());
        static::assertSame($id, $entity->getId());
    }

    public function testCreateDuplicateEntity(): void
    {
        $firstId = Uuid::randomHex();
        $this->blogExportRepository->upsert([
            [
                'id' => $firstId,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => TestDefaults::CHANNEL,
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        $this->expectExceptionObject(new DuplicateFileNameException('Testexport'));

        $secondId = Uuid::randomHex();
        $this->blogExportRepository->upsert([
            [
                'id' => $secondId,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => TestDefaults::CHANNEL,
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);
    }

    public function testFetchBlogStream(): void
    {
        $id = Uuid::randomHex();
        $contentStreamId = '137b079935714281ba80b40f83f8d7eb';
        $this->blogExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'contentStreamId' => $contentStreamId,
                'frontendChannelId' => TestDefaults::CHANNEL,
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('contentStream');
        $entity = $this->blogExportRepository->search($criteria, $this->context)->getEntities()->get($id);
        static::assertInstanceOf(BlogExportEntity::class, $entity);
        $contentStream = $entity->getBlogStream();
        static::assertNotNull($contentStream);
        static::assertSame($contentStreamId, $contentStream->getId());
    }

    public function testFetchChannel(): void
    {
        $id = Uuid::randomHex();
        $this->blogExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => TestDefaults::CHANNEL,
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('channel');
        $entity = $this->blogExportRepository->search($criteria, $this->context)->getEntities()->get($id);
        static::assertInstanceOf(BlogExportEntity::class, $entity);
        $channel = $entity->getChannel();

        static::assertNotNull($channel);
        static::assertSame($this->getChannelId(), $channel->getId());
    }

    public function testFetchChannelDomain(): void
    {
        $id = Uuid::randomHex();
        $this->blogExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => 'Testexport',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => TestDefaults::CHANNEL,
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('channelDomain');
        $entity = $this->blogExportRepository->search($criteria, $this->context)->getEntities()->get($id);
        static::assertInstanceOf(BlogExportEntity::class, $entity);
        $channelDomain = $entity->getChannelDomain();
        static::assertNotNull($channelDomain);
        static::assertSame($this->getChannelDomainId(), $channelDomain->getId());
    }

    private function upsertWithFeedLabel(?string $feedLabel): string
    {
        $id = Uuid::randomHex();

        $this->blogExportRepository->upsert([
            [
                'id' => $id,
                'fileName' => 'feed-' . $id,
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_XML,
                'interval' => 0,
                'bodyTemplate' => 'test',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => TestDefaults::CHANNEL,
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
                'feedLabel' => $feedLabel,
            ],
        ], $this->context);

        return $id;
    }

    private function getChannelId(): string
    {
        /** @var EntityRepository<ChannelCollection> $repository */
        $repository = static::getContainer()->get('channel.repository');

        $first = $repository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $first);

        return $first->getId();
    }

    private function getChannelDomainId(): string
    {
        /** @var EntityRepository<ChannelDomainCollection> $repository */
        $repository = static::getContainer()->get('channel_domain.repository');

        $first = $repository->search(new Criteria(), $this->context)->getEntities()->first();

        static::assertInstanceOf(ChannelDomainEntity::class, $first);

        return $first->getId();
    }

    private function createBlogStream(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $randomBlogIds = implode('|', \array_slice(array_column($this->createBlogs(), 'id'), 0, 2));

        $connection->executeStatement("
            INSERT INTO `content_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('137B079935714281BA80B40F83F8D7EB'), '[{\"type\": \"multi\", \"queries\": [{\"type\": \"multi\", \"queries\": [{\"type\": \"equalsAny\", \"field\": \"blog.id\", \"value\": \"{$randomBlogIds}\"}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"blog.width\", \"parameters\": {\"gte\": 221, \"lte\": 932}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"blog.width\", \"parameters\": {\"lte\": 245}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"equals\", \"field\": \"blog.manufacturer.id\", \"value\": \"02f6b9aa385d4f40aaf573661b2cf919\"}, {\"type\": \"range\", \"field\": \"blog.height\", \"parameters\": {\"gte\": 182}}], \"operator\": \"AND\"}], \"operator\": \"OR\"}]', 0, '2019-08-16 08:43:57.488', NULL);
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
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '{$randomBlogIds}', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL);
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
        $channelId = TestDefaults::CHANNEL;
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
