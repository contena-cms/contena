<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogExport\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\BlogExport\Event\BlogExportChangeEncodingEvent;
use Contena\Core\Content\BlogExport\Event\BlogExportLoggingEvent;
use Contena\Core\Content\BlogExport\Event\BlogExportBlogCriteriaEvent;
use Contena\Core\Content\BlogExport\Event\BlogExportRenderBodyContextEvent;
use Contena\Core\Content\BlogExport\BlogExportCollection;
use Contena\Core\Content\BlogExport\BlogExportEntity;
use Contena\Core\Content\BlogExport\BlogExportException;
use Contena\Core\Content\BlogExport\Service\BlogExportGenerator;
use Contena\Core\Content\BlogExport\Service\BlogExportGeneratorInterface;
use Contena\Core\Content\BlogExport\Service\BlogExportRenderer;
use Contena\Core\Content\BlogExport\Service\BlogExportValidator;
use Contena\Core\Content\BlogExport\Struct\ExportBehavior;
use Contena\Core\Content\BlogExport\Struct\BlogExportResult;
use Contena\Core\Content\ContentStream\DataAbstractionLayer\ContentStreamIndexer;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilder;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class BlogExportGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    /**
     * @var EntityRepository<BlogExportCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    private BlogExportGeneratorInterface $service;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('blog_export.repository');
        $this->service = static::getContainer()->get(BlogExportGenerator::class);
        $this->context = Context::createDefaultContext();
    }

    public function testExport(): void
    {
        $blogExportId = $this->createTestEntity();

        $criteria = $this->createBlogExportCriteria($blogExportId);

        $blogExport = $this->repository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        $exportResult = $this->service->generate($blogExport, new ExportBehavior());

        static::assertInstanceOf(BlogExportResult::class, $exportResult);
        static::assertStringEqualsFile(__DIR__ . '/fixtures/test-export.csv', $exportResult->getContent());
    }

    public function testExportEncodesMediaUrls(): void
    {
        $blogExportId = $this->createTestEntity([
            'bodyTemplate' => '{{ blog.cover.media.url }}',
        ]);

        $criteria = $this->createBlogExportCriteria($blogExportId);
        $blogExport = $this->repository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        $exportResult = $this->service->generate($blogExport, new ExportBehavior());

        static::assertInstanceOf(BlogExportResult::class, $exportResult);
        static::assertStringContainsString('blog%20image.jpg', $exportResult->getContent());
    }

    public function testBlogExportGenerationEvents(): void
    {
        $blogExportId = $this->createTestEntity();

        $criteria = $this->createBlogExportCriteria($blogExportId);

        $blogExport = $this->repository->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        $exportBehavior = new ExportBehavior();

        $eventDispatcher = static::getContainer()->get('event_dispatcher');

        $blogExportBlogCriteriaEventDispatched = false;
        $blogExportBlogCriteriaCallback = static function () use (
            &$blogExportBlogCriteriaEventDispatched
        ): void {
            $blogExportBlogCriteriaEventDispatched = true;
        };
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            BlogExportBlogCriteriaEvent::class,
            $blogExportBlogCriteriaCallback
        );

        $blogExportRenderBodyContextEventDispatched = false;
        $blogExportRenderBodyContextCallback = static function () use (
            &$blogExportRenderBodyContextEventDispatched
        ): void {
            $blogExportRenderBodyContextEventDispatched = true;
        };
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            BlogExportRenderBodyContextEvent::class,
            $blogExportRenderBodyContextCallback
        );

        $blogExportChangeEncodingEventDispatched = false;
        $blogExportChangeEncodingCallback = static function () use (
            &$blogExportChangeEncodingEventDispatched
        ): void {
            $blogExportChangeEncodingEventDispatched = true;
        };
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            BlogExportChangeEncodingEvent::class,
            $blogExportChangeEncodingCallback
        );

        $exportGenerator = new BlogExportGenerator(
            static::getContainer()->get(ContentStreamBuilder::class),
            static::getContainer()->get('channel.blog.repository'),
            static::getContainer()->get(BlogExportRenderer::class),
            $eventDispatcher,
            static::getContainer()->get(BlogExportValidator::class),
            static::getContainer()->get(ChannelContextService::class),
            static::getContainer()->get(Translator::class),
            static::getContainer()->get(ChannelContextPersister::class),
            static::getContainer()->get(Connection::class),
            100,
            static::getContainer()->get(SeoUrlPlaceholderHandlerInterface::class),
            static::getContainer()->get('twig'),
            static::getContainer()->get(BlogDefinition::class),
            static::getContainer()->get(LanguageLocaleCodeProvider::class),
            static::getContainer()->get(TwigVariableParserFactory::class),
            static::getContainer()->get(CategoryBreadcrumbBuilder::class)
        );

        $exportGenerator->generate($blogExport, $exportBehavior);

        static::assertTrue($blogExportBlogCriteriaEventDispatched, 'BlogExportBlogCriteriaEvent was not dispatched');
        static::assertTrue($blogExportRenderBodyContextEventDispatched, 'BlogExportRenderBodyContextEvent was not dispatched');
        static::assertTrue($blogExportChangeEncodingEventDispatched, 'BlogExportChangeEncodingEvent was not dispatched');
    }

    public function testEmptyBlogExportGenerationEvents(): void
    {
        $blogExportId = $this->createTestEntity();

        $criteria = $this->createBlogExportCriteria($blogExportId);

        $blogExport = $this->repository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        $exportBehavior = new ExportBehavior();

        $eventDispatcher = static::getContainer()->get('event_dispatcher');

        $blogExportBlogCriteriaEventDispatched = false;
        $blogExportBlogCriteriaCallback = static function (BlogExportBlogCriteriaEvent $event) use (
            &$blogExportBlogCriteriaEventDispatched
        ): void {
            $blogExportBlogCriteriaEventDispatched = true;
            // Change filters to guarantee empty export for this test
            $event->getCriteria()->addFilter(new EqualsFilter('active', true));
            $event->getCriteria()->addFilter(new EqualsFilter('active', false));
        };
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            BlogExportBlogCriteriaEvent::class,
            $blogExportBlogCriteriaCallback
        );

        $blogExportLoggingEventDispatched = false;
        $blogExportLoggingCallback = static function () use (
            &$blogExportLoggingEventDispatched
        ): void {
            $blogExportLoggingEventDispatched = true;
        };
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            BlogExportLoggingEvent::class,
            $blogExportLoggingCallback
        );

        $exportGenerator = new BlogExportGenerator(
            static::getContainer()->get(ContentStreamBuilder::class),
            static::getContainer()->get('channel.blog.repository'),
            static::getContainer()->get(BlogExportRenderer::class),
            $eventDispatcher,
            static::getContainer()->get(BlogExportValidator::class),
            static::getContainer()->get(ChannelContextService::class),
            static::getContainer()->get(Translator::class),
            static::getContainer()->get(ChannelContextPersister::class),
            static::getContainer()->get(Connection::class),
            100,
            static::getContainer()->get(SeoUrlPlaceholderHandlerInterface::class),
            static::getContainer()->get('twig'),
            static::getContainer()->get(BlogDefinition::class),
            static::getContainer()->get(LanguageLocaleCodeProvider::class),
            static::getContainer()->get(TwigVariableParserFactory::class),
            static::getContainer()->get(CategoryBreadcrumbBuilder::class)
        );

        try {
            $exportGenerator->generate($blogExport, $exportBehavior);
        } catch (BlogExportException) {
        }

        static::assertTrue($blogExportBlogCriteriaEventDispatched, 'BlogExportBlogCriteriaEvent was not dispatched');
        static::assertTrue($blogExportLoggingEventDispatched, 'BlogExportLoggingEvent was not dispatched');
    }

    public function testExportWithNestedAssociations(): void
    {
        $blogExportId = $this->createTestEntity([
            'bodyTemplate' => '{{ blog.name }},{{ blog.stock }},{{ blog.options.first.group.name }}',
        ]);

        $criteria = $this->createBlogExportCriteria($blogExportId);

        $blogExport = $this->repository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        $exportResult = $this->service->generate($blogExport, new ExportBehavior());

        static::assertInstanceOf(BlogExportResult::class, $exportResult);
        static::assertStringContainsString('options-group', $exportResult->getContent());
    }

    public function testExportWithForLoop(): void
    {
        $blogExportId = $this->createTestEntity([
            'bodyTemplate' => '{% for foo in blog.options %} {{ foo.group.name }} {% endfor %}',
        ]);

        $criteria = $this->createBlogExportCriteria($blogExportId);

        $blogExport = $this->repository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        $exportResult = $this->service->generate($blogExport, new ExportBehavior());

        static::assertInstanceOf(BlogExportResult::class, $exportResult);
        static::assertStringContainsString('options-group', $exportResult->getContent());
    }

    #[DataProvider('isoCodeProvider')]
    public function testExportRendersGivenCurrencies(string $code): void
    {
        $currencyId = $this->getCurrencyIdByIso($code);
        static::getContainer()->get('channel_currency.repository')->create([
            [
                'channelId' => $this->getChannelDomain()->getChannelId(),
                'currencyId' => $currencyId,
            ],
        ], $this->context);

        $blogExportId = $this->createTestEntity([
            'currencyId' => $currencyId,
            'bodyTemplate' => '{{ context.currency.isoCode }}',
        ]);

        $criteria = $this->createBlogExportCriteria($blogExportId);

        $blogExport = $this->repository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        $result = $this->service->generate($blogExport, new ExportBehavior());

        static::assertInstanceOf(BlogExportResult::class, $result);
        static::assertStringContainsString($code, $result->getContent());
    }

    public function testExportWithEmptyBlogStreamIncludesVariants(): void
    {
        $variantId = $this->createVariantBlog();
        $contentStreamId = $this->createEmptyBlogStream();

        static::getContainer()->get(ContentStreamIndexer::class)->handle(new EntityIndexingMessage([$contentStreamId]));

        $blogExportId = $this->createBlogExportForBlogStream($contentStreamId, [
            'includeVariants' => true,
            'headerTemplate' => '',
            'bodyTemplate' => '{{ blog.id }};',
            'footerTemplate' => '',
        ]);

        $criteria = $this->createBlogExportCriteria($blogExportId);

        $blogExport = $this->repository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        $result = $this->service->generate($blogExport, new ExportBehavior(false, false, false, false, false));

        static::assertInstanceOf(BlogExportResult::class, $result);
        static::assertStringContainsString($variantId, $result->getContent());
    }

    public static function isoCodeProvider(): \Generator
    {
        yield 'Polish zloty iso code' => ['PLN'];
        yield 'Euro iso code' => ['EUR'];
        yield 'US dollar iso code' => ['USD'];
        yield 'British pound iso code' => ['GBP'];
    }

    /**
     * @param list<string> $expectedIds
     */
    #[DataProvider('exportResultBlogIdDataProvider')]
    public function testExportWithIncludedAndExcludedVariants(bool $includeVariants, array $expectedIds): void
    {
        $ids = $this->createMixedVariantAndStandAloneBlogs();
        $contentStreamId = $this->getBlogStreamId(array_values($ids->all()));

        $blogExportId = Uuid::randomHex();
        $this->repository->upsert([
            [
                'id' => $blogExportId,
                'fileName' => 'Testexport.csv',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'headerTemplate' => 'id',
                'bodyTemplate' => '{{ blog.id }}',
                'contentStreamId' => $contentStreamId,
                'frontendChannelId' => $this->getChannelDomain()->getChannelId(),
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
                'includeVariants' => $includeVariants,
            ],
        ], $this->context);

        $criteria = $this->createBlogExportCriteria($blogExportId);

        $blogExport = $this->repository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        $exportResult = $this->service->generate($blogExport, new ExportBehavior());

        static::assertInstanceOf(BlogExportResult::class, $exportResult);
        $expectedContent = 'id' . \PHP_EOL . implode(\PHP_EOL, $ids->getList($expectedIds)) . \PHP_EOL;
        static::assertSame($expectedContent, $exportResult->getContent());
    }

    public static function exportResultBlogIdDataProvider(): \Generator
    {
        yield 'included variants' => [true, ['variant-1', 'variant-2', 'stand-alone-1']];
        yield 'excluded variants' => [false, ['parent-1', 'stand-alone-1']];
    }

    public function testExportWithHeadlessChannel(): void
    {
        $headlessChannelId = Uuid::randomHex();
        $headlessChannelDomainId = Uuid::randomHex();
        $domainUrl = 'https://composable-frontends.test';

        $this->createChannel([
            'id' => $headlessChannelId,
            'typeId' => Defaults::CHANNEL_TYPE_API,
            'name' => 'Headless sales channel',
            'domains' => [
                [
                    'id' => $headlessChannelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => $domainUrl,
                    'isExternalFrontend' => true,
                ],
            ],
        ]);

        $blogIds = $this->createHeadlessBlogs($headlessChannelId);

        $expectedUrls = [
            'https://composable-frontends.test/headless-blog-1/headless-blog-1',
            'https://composable-frontends.test/headless-blog-2/headless-blog-2',
            'https://composable-frontends.test/headless-blog-3/headless-blog-3',
        ];

        $blogExportId = Uuid::randomHex();
        $this->repository->upsert([
            [
                'id' => $blogExportId,
                'fileName' => 'Testexport.csv',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'headerTemplate' => '',
                'bodyTemplate' => '{{ entitySeoUrl("blog", blog.id) }}',
                'contentStreamId' => $this->getBlogStreamId($blogIds),
                'frontendChannelId' => $headlessChannelId,
                'channelId' => $headlessChannelId,
                'channelDomainId' => $headlessChannelDomainId,
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        $criteria = $this->createBlogExportCriteria($blogExportId);

        $blogExport = $this->repository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(BlogExportEntity::class, $blogExport);

        $exportResult = $this->service->generate($blogExport, new ExportBehavior());
        static::assertInstanceOf(BlogExportResult::class, $exportResult);

        static::assertSame(implode(\PHP_EOL, $expectedUrls) . \PHP_EOL, $exportResult->getContent());
    }

    private function createBlogExportCriteria(string $id): Criteria
    {
        $criteria = new Criteria([$id]);
        $criteria->addAssociations([
            'channel',
            'channelDomain.language',
        ]);

        return $criteria;
    }

    private function getChannelId(): string
    {
        /** @var EntityRepository<ChannelCollection> $repository */
        $repository = static::getContainer()->get('channel.repository');

        $channel = $repository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(ChannelEntity::class, $channel);

        return $channel->getId();
    }

    private function getChannelDomain(): ChannelDomainEntity
    {
        /** @var EntityRepository<ChannelDomainCollection> $repository */
        $repository = static::getContainer()->get('channel_domain.repository');

        $channelDomain = $repository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(ChannelDomainEntity::class, $channelDomain);

        return $channelDomain;
    }

    private function getChannelDomainId(): string
    {
        return $this->getChannelDomain()->getId();
    }

    /**
     * @param array<string, mixed> $override
     */
    private function createTestEntity(array $override = []): string
    {
        $this->createBlogStream();

        return $this->createBlogExportForBlogStream('137b079935714281ba80b40f83f8d7eb', $override);
    }

    /**
     * @param array<string, mixed> $override
     */
    private function createBlogExportForBlogStream(string $contentStreamId, array $override = []): string
    {
        $id = Uuid::randomHex();
        $this->repository->upsert([
            array_merge([
                'id' => $id,
                'fileName' => 'Testexport.csv',
                'accessKey' => Uuid::randomHex(),
                'encoding' => BlogExportEntity::ENCODING_UTF8,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'headerTemplate' => 'name,stock,category',
                'bodyTemplate' => '{{ blog.name }},{{ blog.stock }},{%- if blog.categories.count > 0 -%}{{ blog.categories.first.name }}{%- endif -%}',
                'contentStreamId' => $contentStreamId,
                'frontendChannelId' => $this->getChannelDomain()->getChannelId(),
                'channelId' => $this->getChannelId(),
                'channelDomainId' => $this->getChannelDomainId(),
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ], $override),
        ], $this->context);

        return $id;
    }

    private function createEmptyBlogStream(): string
    {
        $contentStreamId = Uuid::randomHex();
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO `content_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
                VALUES (UNHEX(:contentStreamId), NULL, 1, '2019-08-16 08:43:57.488', NULL)
            SQL,
            ['contentStreamId' => $contentStreamId]
        );

        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO `content_stream_filter`
                    (`id`, `content_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
                VALUES
                    (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX(:contentStreamId), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                    (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX(:contentStreamId), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'equalsAny', 'id', NULL, '', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL)
            SQL,
            ['contentStreamId' => $contentStreamId]
        );

        return $contentStreamId;
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
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '{$randomBlogIds}', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL);
    ");
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function createBlogs(): array
    {
        $blogRepository = static::getContainer()->get('blog.repository');
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $channelId = $this->getChannelDomain()->getChannelId();
        $blogs = [];

        for ($i = 0; $i < 10; ++$i) {
            $groupId = Uuid::randomHex();
            $blogMediaId = Uuid::randomHex();
            $mediaId = Uuid::randomHex();

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
                'coverId' => $blogMediaId,
                'media' => [
                    [
                        'id' => $blogMediaId,
                        'position' => 1,
                        'media' => [
                            'id' => $mediaId,
                            'fileName' => 'blog image',
                            'fileExtension' => 'jpg',
                            'mimeType' => 'image/jpeg',
                            'path' => 'media/blog image.jpg',
                        ],
                    ],
                ],
                'options' => [
                    [
                        'id' => Uuid::randomHex(),
                        'position' => 99,
                        'colorHexCode' => '#189eff',
                        'group' => [
                            'id' => $groupId,
                            'position' => 1,
                            'translations' => [
                                Defaults::LANGUAGE_SYSTEM => [
                                    'name' => 'options-group',
                                    'description' => 'Default',
                                    'displayType' => 'Default',
                                    'sortingType' => 'Default',
                                ],
                            ],
                        ],
                        'translations' => [
                            Defaults::LANGUAGE_SYSTEM => [
                                'name' => 'Default',
                            ],
                        ],
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'position' => 98,
                        'colorHexCode' => '#ff0000',
                        'groupId' => $groupId,
                        'translations' => [
                            Defaults::LANGUAGE_SYSTEM => [
                                'name' => 'options-group',
                            ],
                        ],
                    ],
                ],
                'categories' => [
                    ['id' => Uuid::randomHex(), 'name' => 'Foobar', 'active' => $i % 2 === 0],
                ],
            ];
        }

        $blogRepository->create($blogs, $this->context);

        return $blogs;
    }

    /**
     * @return list<string>
     */
    private function createHeadlessBlogs(string $channelId): array
    {
        $ids = new IdsCollection();
        $keys = ['headless-blog-1', 'headless-blog-2', 'headless-blog-3'];

        $blogs = [];
        foreach ($keys as $key) {
            $blogs[] = (new BlogBuilder($ids, $key))
                ->price(10)
                ->visibility($channelId)
                ->build();
        }

        static::getContainer()->get('blog.repository')->create($blogs, $this->context);

        return array_values($ids->getList($keys));
    }

    /**
     * @param list<string> $blogIds
     */
    private function getBlogStreamId(array $blogIds): string
    {
        $id = Uuid::randomHex();

        static::getContainer()->get('content_stream.repository')->create([
            [
                'id' => $id,
                'filters' => [
                    [
                        'type' => 'equalsAny',
                        'field' => 'id',
                        'value' => implode('|', $blogIds),
                    ],
                ],
                'name' => 'testStream',
            ],
        ], $this->context);

        return $id;
    }

    private function createMixedVariantAndStandAloneBlogs(): IdsCollection
    {
        $ids = new IdsCollection();
        $blogRepository = static::getContainer()->get('blog.repository');

        $blogs = [
            (new BlogBuilder($ids, 'parent-1'))
                ->price(100)
                ->visibility($this->getChannelDomain()->getChannelId())
                ->variant(
                    (new BlogBuilder($ids, 'variant-1'))->build()
                )
                ->variant(
                    (new BlogBuilder($ids, 'variant-2'))->build()
                )
                ->build(),
            (new BlogBuilder($ids, 'stand-alone-1'))
                ->price(100)
                ->visibility($this->getChannelDomain()->getChannelId())
                ->build(),
        ];

        $blogRepository->create($blogs, Context::createDefaultContext());

        return $ids;
    }

    private function createVariantBlog(): string
    {
        $blogRepository = static::getContainer()->get('blog.repository');
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();
        $channelId = $this->getChannelDomain()->getChannelId();

        $blogRepository->create([
            [
                'id' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'active' => true,
                'stock' => 10,
                'name' => 'Empty Stream Parent',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 19, 'name' => 'test'],
                'visibilities' => [
                    ['channelId' => $channelId, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => $variantId,
                'parentId' => $parentId,
                'blogNumber' => Uuid::randomHex(),
                'active' => true,
                'stock' => 10,
                'name' => 'Empty Stream Variant',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 11, 'net' => 10, 'linked' => false]],
                'manufacturerId' => $manufacturerId,
                'taxId' => $taxId,
                'visibilities' => [
                    ['channelId' => $channelId, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        return $variantId;
    }
}
