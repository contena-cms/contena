<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogExport\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\BlogExport\BlogExportCollection;
use Contena\Core\Content\BlogExport\BlogExportEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\Test\AppSystemTestBehaviour;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\ThemeService;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class BlogExportControllerTest extends TestCase
{
    use AppSystemTestBehaviour;
    use ChannelFunctionalTestBehaviour;

    /**
     * @var EntityRepository<BlogExportCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('blog_export.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testInvalidData(): void
    {
        $client = $this->createChannelBrowser(null, true);
        $client->request('GET', '/store-api/blog-export/foo/bar');

        static::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testUtf8CsvExport(): void
    {
        $channelId = Uuid::randomHex();
        $channelDomainId = Uuid::randomHex();

        $client = $this->createChannelBrowser(null, false, [
            'id' => $channelId,
            'domains' => [
                [
                    'id' => $channelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);

        $blogExport = $this->createCsvExport(
            BlogExportEntity::ENCODING_UTF8,
            $channelId,
            $channelDomainId
        );
        $client->request('GET', \sprintf('/store-api/blog-export/%s/%s', $blogExport->getAccessKey(), $blogExport->getFileName()));

        $csvRows = explode(\PHP_EOL, (string) $client->getResponse()->getContent());

        static::assertCount(4, $csvRows);
        static::assertSame(BlogExportEntity::ENCODING_UTF8, $client->getResponse()->getCharset());
    }

    public function testUtf8CsvExportWithTheme(): void
    {
        if (!static::getContainer()->has(ThemeService::class) || !static::getContainer()->has('theme.repository')) {
            static::markTestSkipped('This test needs frontend to be installed.');
        }

        $themeService = static::getContainer()->get(ThemeService::class);
        $themeRepo = static::getContainer()->get('theme.repository');

        $channelId = Uuid::randomHex();
        $channelDomainId = Uuid::randomHex();

        static::getContainer()->get(Translator::class)->reset();

        $client = $this->createChannelBrowser(null, false, [
            'id' => $channelId,
            'domains' => [
                [
                    'id' => $channelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);

        $blogExport = $this->createCsvExport(
            BlogExportEntity::ENCODING_UTF8,
            $channelId,
            $channelDomainId
        );

        $context = Context::createDefaultContext();
        $this->loadAppsFromDir(__DIR__ . '/fixtures/SwagTheme');
        $this->reloadAppSnippets();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', 'SwagTheme'));
        $themeId = $themeRepo->searchIds($criteria, $context)->firstId();
        static::assertNotNull($themeId);

        static::getContainer()->get(Translator::class)->reset();

        $themeService->assignTheme($themeId, $channelId, $context, true);

        $client->request('GET', \sprintf('/store-api/blog-export/%s/%s', $blogExport->getAccessKey(), $blogExport->getFileName()));

        $csvRows = explode(\PHP_EOL, (string) $client->getResponse()->getContent());

        static::assertCount(4, $csvRows);
        static::assertSame('SwagTheme EN Test', $csvRows[1]);
        static::assertSame(BlogExportEntity::ENCODING_UTF8, $client->getResponse()->getCharset());

        // Switch to DE
        $deChannelId = Uuid::randomHex();
        $deChannelDomainId = Uuid::randomHex();

        $client = $this->createChannelBrowser(null, false, [
            'id' => $deChannelId,
            'languageId' => $this->getDeDeLanguageId(),
            'languages' => [
                [
                    'id' => $this->getDeDeLanguageId(),
                ],
            ],
            'domains' => [
                [
                    'id' => $deChannelDomainId,
                    'languageId' => $this->getDeDeLanguageId(),
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                    'url' => 'http://example.com/de',
                ],
            ],
        ]);

        static::getContainer()->get(Translator::class)->reset();

        $themeService->assignTheme($themeId, $deChannelId, $context);
        $blogExportDe = $this->createCsvExport(
            BlogExportEntity::ENCODING_UTF8,
            $deChannelId,
            $deChannelDomainId
        );

        $client->request('GET', \sprintf('/store-api/blog-export/%s/%s', $blogExportDe->getAccessKey(), $blogExportDe->getFileName()));

        $csvRows = explode(\PHP_EOL, (string) $client->getResponse()->getContent());
        static::assertNotNull($client->getResponse()->headers->get('Last-Modified'));
        static::assertCount(4, $csvRows);
        static::assertSame('SwagTheme DE Test', $csvRows[1]);
    }

    public function testIsoCsvExport(): void
    {
        $channelId = Uuid::randomHex();
        $channelDomainId = Uuid::randomHex();

        $client = $this->createChannelBrowser(null, false, [
            'id' => $channelId,
            'domains' => [
                [
                    'id' => $channelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);

        $blogExport = $this->createCsvExport(
            BlogExportEntity::ENCODING_ISO88591,
            $channelId,
            $channelDomainId
        );

        $client->request('GET', \sprintf('/store-api/blog-export/%s/%s', $blogExport->getAccessKey(), $blogExport->getFileName()));

        $csvRows = explode(\PHP_EOL, (string) $client->getResponse()->getContent());

        static::assertCount(4, $csvRows);
        static::assertSame(BlogExportEntity::ENCODING_ISO88591, $client->getResponse()->getCharset());
    }

    public function testUtf8XmlExport(): void
    {
        $channelId = Uuid::randomHex();
        $channelDomainId = Uuid::randomHex();

        $client = $this->createChannelBrowser(null, false, [
            'id' => $channelId,
            'domains' => [
                [
                    'id' => $channelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);
        $blogExport = $this->createXmlExport(
            BlogExportEntity::ENCODING_UTF8,
            $channelId,
            $channelDomainId
        );

        $client->request('GET', \sprintf('/store-api/blog-export/%s/%s', $blogExport->getAccessKey(), $blogExport->getFileName()));

        static::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());

        $xml = simplexml_load_string((string) $client->getResponse()->getContent());

        static::assertSame(BlogExportEntity::ENCODING_UTF8, $client->getResponse()->getCharset());
        static::assertInstanceOf(\SimpleXMLElement::class, $xml);
        static::assertCount(2, $xml);
    }

    public function testIsoXmlExport(): void
    {
        $channelId = Uuid::randomHex();
        $channelDomainId = Uuid::randomHex();

        $client = $this->createChannelBrowser(null, false, [
            'id' => $channelId,
            'domains' => [
                [
                    'id' => $channelDomainId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://example.com',
                ],
            ],
        ]);
        $blogExport = $this->createXmlExport(
            BlogExportEntity::ENCODING_ISO88591,
            $channelId,
            $channelDomainId
        );

        $client->request('GET', \sprintf('/store-api/blog-export/%s/%s', $blogExport->getAccessKey(), $blogExport->getFileName()));

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $xml = simplexml_load_string((string) $response->getContent());

        static::assertSame(BlogExportEntity::ENCODING_ISO88591, $response->getCharset());
        static::assertInstanceOf(\SimpleXMLElement::class, $xml);
        static::assertCount(2, $xml);
    }

    protected function getChannelDomain(): ChannelDomainEntity
    {
        /** @var EntityRepository<ChannelDomainCollection> $repository */
        $repository = static::getContainer()->get('channel_domain.repository');

        /** @var ChannelDomainEntity $channelDomain */
        $channelDomain = $repository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertNotNull($channelDomain);

        return $channelDomain;
    }

    private function createCsvExport(string $encoding, string $channelId, string $channelDomainId): BlogExportEntity
    {
        $this->createBlogStream($channelId);

        $blogExportId = Uuid::randomHex();

        $this->repository->upsert([
            [
                'id' => $blogExportId,
                'fileName' => Uuid::randomHex(),
                'accessKey' => Uuid::randomHex(),
                'encoding' => $encoding,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_CSV,
                'interval' => 0,
                'headerTemplate' => 'name,url',
                'bodyTemplate' => '{{ "title"|trans }} {{ blog.translated.name }}',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => $channelId,
                'channelId' => TestDefaults::CHANNEL,
                'channelDomainId' => $channelDomainId,
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        /** @var BlogExportEntity $blogExport */
        $blogExport = $this->repository->search(new Criteria([$blogExportId]), $this->context)->getEntities()->get($blogExportId);
        static::assertNotNull($blogExport);

        return $blogExport;
    }

    private function createXmlExport(string $encoding, string $channelId, string $channelDomainId): BlogExportEntity
    {
        $this->createBlogStream($channelId);

        $blogExportId = Uuid::randomHex();

        $this->repository->upsert([
            [
                'id' => $blogExportId,
                'fileName' => Uuid::randomHex(),
                'accessKey' => Uuid::randomHex(),
                'encoding' => $encoding,
                'fileFormat' => BlogExportEntity::FILE_FORMAT_XML,
                'interval' => 0,
                'headerTemplate' => '<root>',
                'bodyTemplate' => '<blog><name>{{ blog.name }}</name></blog>',
                'footerTemplate' => '</root>',
                'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
                'frontendChannelId' => $channelId,
                'channelId' => TestDefaults::CHANNEL,
                'channelDomainId' => $channelDomainId,
                'generateByCronjob' => false,
                'currencyId' => Defaults::CURRENCY,
            ],
        ], $this->context);

        /** @var BlogExportEntity $blogExport */
        $blogExport = $this->repository->search(new Criteria([$blogExportId]), $this->context)->getEntities()->get($blogExportId);
        static::assertNotNull($blogExport);

        return $blogExport;
    }

    private function createBlogStream(string $channelId): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $randomBlogIds = implode('|', \array_slice(array_column($this->createBlogs($channelId), 'id'), 0, 2));

        $connection->executeStatement("
            INSERT INTO `content_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('137B079935714281BA80B40F83F8D7EB'), '[{\"type\": \"multi\", \"queries\": [{\"type\": \"multi\", \"queries\": [{\"type\": \"equalsAny\", \"field\": \"blog.id\", \"value\": \"{$randomBlogIds}\"}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"blog.width\", \"parameters\": {\"gte\": 221, \"lte\": 932}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"blog.width\", \"parameters\": {\"lte\": 245}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"equals\", \"field\": \"blog.manufacturer.id\", \"value\": \"02f6b9aa385d4f40aaf573661b2cf919\"}, {\"type\": \"range\", \"field\": \"blog.height\", \"parameters\": {\"gte\": 182}}], \"operator\": \"AND\"}], \"operator\": \"OR\"}]', 0, '2019-08-16 08:43:57.488', NULL)
            ON DUPLICATE KEY UPDATE
                `api_filter` = VALUES(`api_filter`),
                `invalid` = VALUES(`invalid`),
                `updated_at` = NOW();
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
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '{$randomBlogIds}', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL)
            ON DUPLICATE KEY UPDATE
                `type` = VALUES(`type`),
                `value` = VALUES(`value`),
                `parameters` = VALUES(`parameters`),
                `updated_at` = NOW();
        ");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createBlogs(string $channelId): array
    {
        $blogRepository = static::getContainer()->get('blog.repository');
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
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
