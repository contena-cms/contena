<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogExport\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class BlogExportControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testValidate(): void
    {
        $this->createBlogStream();
        TestUser::createNewTestUser(
            $this->getBrowser()->getContainer()->get(Connection::class),
            ['blog_export:update']
        )->authorizeBrowser($this->getBrowser());

        $url = '/api/_action/blog-export/validate';

        $content = json_encode([
            'channelId' => $this->getChannelDomain()->getChannelId(),
            'channelDomainId' => $this->getChannelDomainId(),
            'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
            'headerTemplate' => '',
            'bodyTemplate' => '{{ blog.name }}',
            'footerTemplate' => '',
            'includeVariants' => false,
            'encoding' => 'UTF-8',
            'fileFormat' => 'CSV',
            'fileName' => 'test.csv',
            'accessKey' => 'test',
            'currencyId' => Defaults::CURRENCY,
        ], \JSON_THROW_ON_ERROR);

        if (!$content) {
            $content = '';
        }

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $content
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testPreviewAndValidateRequireBlogExportUpdatePrivilege(): void
    {
        foreach (['preview', 'validate'] as $action) {
            $browser = $this->getBrowser();
            TestUser::createNewTestUser(
                $browser->getContainer()->get(Connection::class),
                []
            )->authorizeBrowser($browser);

            $browser->request('POST', \sprintf('/api/_action/blog-export/%s', $action));

            static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode());
        }
    }

    public function testValidateFailure(): void
    {
        $this->createBlogStream();

        $url = '/api/_action/blog-export/validate';

        $content = json_encode([
            'channelId' => $this->getChannelDomain()->getChannelId(),
            'channelDomainId' => $this->getChannelDomainId(),
            'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
            'headerTemplate' => '',
            'bodyTemplate' => '{{ blog.name }', // Missing closing curly brace
            'footerTemplate' => '',
            'includeVariants' => false,
            'encoding' => 'UTF-8',
            'fileFormat' => 'CSV',
            'fileName' => 'test.csv',
            'accessKey' => 'test',
            'currencyId' => Defaults::CURRENCY,
        ], \JSON_THROW_ON_ERROR);

        if (!$content) {
            $content = '';
        }

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $content
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());
    }

    public function testValidateReturnsStructuredProviderErrors(): void
    {
        // the agentic-commerce providers (open-ai/google) move to SwagAgenticCommerce with 6.8
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->createBlogStream();

        $content = json_encode([
            'channelId' => $this->getChannelDomain()->getChannelId(),
            'channelDomainId' => $this->getChannelDomainId(),
            'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
            'headerTemplate' => '',
            'bodyTemplate' => <<<'TWIG'
{"is_eligible_search": true, "is_eligible_checkout": false, "item_id": "{{ blog.id }}", "title": "Example", "description": "Example description", "url": "https://example.com/blog", "brand": "ACME", "image_url": "https://example.com/image.jpg", "price": "10.99 EUR", "availability": "in_stock", "group_id": "{{ blog.id }}", "listing_has_variations": false, "seller_name": "Merchant", "seller_url": "https://example.com", "target_countries": ["DE"], "store_country": "DE"}
TWIG,
            'footerTemplate' => '',
            'includeVariants' => false,
            'encoding' => 'UTF-8',
            'fileFormat' => 'jsonl',
            'fileName' => 'test.jsonl',
            'accessKey' => 'test',
            'provider' => 'open-ai',
            'currencyId' => Defaults::CURRENCY,
        ], \JSON_THROW_ON_ERROR);

        if (!$content) {
            $content = '';
        }

        $this->getBrowser()->request(
            'POST',
            '/api/_action/blog-export/validate',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $content
        );

        $responseContent = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($responseContent);

        /** @var array{content: string, errors: list<array{message: string, line: int|null, column: int|null}>} $response */
        $response = json_decode($responseContent, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertNotSame('', $response['content']);
        static::assertCount(2, $response['errors']);
        static::assertSame('The field "return_policy" must be a valid absolute URL.', $response['errors'][0]['message']);
    }

    public function testValidateFalseDomain(): void
    {
        $url = '/api/_action/blog-export/validate';

        $content = json_encode([
            'channelId' => Uuid::randomHex(),
            'channelDomainId' => Uuid::randomHex(),
            'contentStreamId' => '',
            'headerTemplate' => '',
            'bodyTemplate' => '',
            'footerTemplate' => '',
            'includeVariants' => false,
            'encoding' => 'UTF-8',
            'fileFormat' => 'CSV',
            'fileName' => 'test.csv',
            'accessKey' => 'test',
            'currencyId' => Defaults::CURRENCY,
        ], \JSON_THROW_ON_ERROR);

        if (!$content) {
            $content = '';
        }

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $content
        );

        $browserResponseContent = $this->getBrowser()->getResponse()->getContent();

        if (!$browserResponseContent) {
            $browserResponseContent = '';
        }

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertStringContainsString('CONTENT__BLOG_EXPORT_CHANNEL_DOMAIN_NOT_FOUND', $browserResponseContent);
    }

    public function testPreview(): void
    {
        // the agentic-commerce providers (open-ai/google) move to SwagAgenticCommerce with 6.8
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->createBlogStream();
        TestUser::createNewTestUser(
            $this->getBrowser()->getContainer()->get(Connection::class),
            ['blog_export:update']
        )->authorizeBrowser($this->getBrowser());

        $url = '/api/_action/blog-export/preview';

        $content = json_encode([
            'channelId' => $this->getChannelDomain()->getChannelId(),
            'channelDomainId' => $this->getChannelDomainId(),
            'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
            'headerTemplate' => '',
            'bodyTemplate' => '{{ blogExport.channelId }},{{ provider.name }}',
            'footerTemplate' => '',
            'includeVariants' => false,
            'encoding' => 'UTF-8',
            'fileFormat' => 'CSV',
            'fileName' => 'test.csv',
            'accessKey' => 'test',
            'currencyId' => Defaults::CURRENCY,
            'provider' => 'open-ai',
        ], \JSON_THROW_ON_ERROR);

        if (!$content) {
            $content = '';
        }

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $content
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);
        static::assertStringContainsString('open-ai', $response);
    }

    public function testPreviewProvidesConfiguredProviderInTransientExportEntity(): void
    {
        // the agentic-commerce providers (open-ai/google) move to SwagAgenticCommerce with 6.8
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->createBlogStream();

        $content = json_encode([
            'channelId' => $this->getChannelDomain()->getChannelId(),
            'channelDomainId' => $this->getChannelDomainId(),
            'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
            'headerTemplate' => '',
            'bodyTemplate' => '{{ provider.name }}',
            'footerTemplate' => '',
            'includeVariants' => false,
            'encoding' => 'UTF-8',
            'fileFormat' => 'CSV',
            'fileName' => 'test.csv',
            'accessKey' => 'test',
            'provider' => 'open-ai',
            'currencyId' => Defaults::CURRENCY,
        ], \JSON_THROW_ON_ERROR);

        if (!$content) {
            $content = '';
        }

        $this->getBrowser()->request(
            'POST',
            '/api/_action/blog-export/preview',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $content
        );

        $responseContent = $this->getBrowser()->getResponse()->getContent();

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertIsString($responseContent);
        static::assertStringContainsString('open-ai', $responseContent);
    }

    public function testPreviewRendersFeedLabelFromRequestPayload(): void
    {
        $this->createBlogStream();

        $content = json_encode([
            'channelId' => $this->getChannelDomain()->getChannelId(),
            'channelDomainId' => $this->getChannelDomainId(),
            'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
            'headerTemplate' => '',
            'bodyTemplate' => '{{ blogExport.feedLabel|default("none") }}',
            'footerTemplate' => '',
            'includeVariants' => false,
            'encoding' => 'UTF-8',
            'fileFormat' => 'CSV',
            'fileName' => 'test.csv',
            'accessKey' => 'test',
            'currencyId' => Defaults::CURRENCY,
            'feedLabel' => 'SUMMER-2026',
        ], \JSON_THROW_ON_ERROR);

        if (!$content) {
            $content = '';
        }

        $this->getBrowser()->request(
            'POST',
            '/api/_action/blog-export/preview',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $content
        );

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        $response = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($response);
        static::assertStringContainsString('SUMMER-2026', $response);
        static::assertStringNotContainsString('none', $response);
    }

    public function testPreviewFalseDomain(): void
    {
        $this->createBlogStream();

        $url = '/api/_action/blog-export/preview';

        $content = json_encode([
            'channelId' => $this->getChannelDomain()->getChannelId(),
            'channelDomainId' => Uuid::randomHex(),
            'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
            'headerTemplate' => '',
            'bodyTemplate' => '{{ blogExport.channelId }}',
            'footerTemplate' => '',
            'includeVariants' => false,
            'encoding' => 'UTF-8',
            'fileFormat' => 'CSV',
            'fileName' => 'test.csv',
            'accessKey' => 'test',
            'currencyId' => Defaults::CURRENCY,
        ], \JSON_THROW_ON_ERROR);

        if (!$content) {
            $content = '';
        }

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $content
        );

        $browserResponseContent = $this->getBrowser()->getResponse()->getContent();

        if (!$browserResponseContent) {
            $browserResponseContent = '';
        }

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertStringContainsString('CONTENT__BLOG_EXPORT_CHANNEL_DOMAIN_NOT_FOUND', $browserResponseContent);
    }

    public function testPreviewFalseChannel(): void
    {
        $this->createBlogStream();

        $url = '/api/_action/blog-export/preview';

        $content = json_encode([
            'channelId' => Uuid::randomHex(),
            'channelDomainId' => $this->getChannelDomainId(),
            'contentStreamId' => '137b079935714281ba80b40f83f8d7eb',
            'headerTemplate' => '',
            'bodyTemplate' => '{{ blogExport.channelId }}',
            'footerTemplate' => '',
            'includeVariants' => false,
            'encoding' => 'UTF-8',
            'fileFormat' => 'CSV',
            'fileName' => 'test.csv',
            'accessKey' => 'test',
            'currencyId' => Defaults::CURRENCY,
        ], \JSON_THROW_ON_ERROR);

        if (!$content) {
            $content = '';
        }

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $content
        );

        $browserResponseContent = $this->getBrowser()->getResponse()->getContent();

        if (!$browserResponseContent) {
            $browserResponseContent = '';
        }

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertStringContainsString('CONTENT__BLOG_EXPORT_CHANNEL_NOT_FOUND', $browserResponseContent);
    }

    private function getChannelDomain(): ChannelDomainEntity
    {
        /** @var EntityRepository<ChannelDomainCollection> $repository */
        $repository = static::getContainer()->get('channel_domain.repository');

        $first = $repository->search(new Criteria(), $this->context)->getEntities()->first();

        static::assertInstanceOf(ChannelDomainEntity::class, $first);

        return $first;
    }

    private function getChannelDomainId(): string
    {
        return $this->getChannelDomain()->getId();
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
     * @return array<int, array<string, mixed>>
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
