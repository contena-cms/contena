<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandler;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(SeoUrlPlaceholderHandler::class)]
class SeoUrlPlaceholderHandlerTest extends TestCase
{
    private Connection&Stub $connection;

    private ChannelContext $channelContext;

    private SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn(static::createStub(AbstractPlatform::class));

        $this->channelContext = Generator::generateChannelContext();
        $this->channelContext->getChannel()->setTypeId(Defaults::CHANNEL_TYPE_WEB);

        $this->seoUrlPlaceholderHandler = $this->createHandler();
    }

    /**
     * @return iterable<string, array<string, string>>
     */
    public static function replaceDataProvider(): iterable
    {
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        yield 'one url' => [
            'host' => 'http://foo.text',
            'content' => 'Test content with url ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $blogId1 . '#.',
            'expected' => 'Test content with url http://foo.text/detail/' . $blogId1 . '.',
        ];

        yield 'url with prefix path' => [
            'host' => 'http://foo.text:8000/de',
            'content' => 'Test content with url ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $blogId1 . '#.',
            'expected' => 'Test content with url http://foo.text:8000/de/detail/' . $blogId1 . '.',
        ];

        yield 'two urls' => [
            'host' => 'http://foo.text:8000/de',
            'content' => 'Test URL 1: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $blogId1 . '# and URL 2: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $blogId2 . '#',
            'expected' => 'Test URL 1: http://foo.text:8000/de/detail/' . $blogId1 . ' and URL 2: http://foo.text:8000/de/detail/' . $blogId2,
        ];

        yield 'two equal urls' => [
            'host' => 'http://foo.text:8000/de',
            'content' => 'Test URL 1: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $blogId1 . '# and URL 2: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $blogId1 . '#',
            'expected' => 'Test URL 1: http://foo.text:8000/de/detail/' . $blogId1 . ' and URL 2: http://foo.text:8000/de/detail/' . $blogId1,
        ];

        yield 'two different entities' => [
            'host' => 'http://foo.text:8000/de',
            'content' => 'Test URL 1: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $blogId1 . '# and URL 2: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/navigation/' . $categoryId . '#',
            'expected' => 'Test URL 1: http://foo.text:8000/de/detail/' . $blogId1 . ' and URL 2: http://foo.text:8000/de/navigation/' . $categoryId,
        ];
    }

    #[DataProvider('replaceDataProvider')]
    public function testReplace(string $host, string $content, string $expected): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(static::createStub(AbstractPlatform::class));
        $connection->expects($this->once())->method('executeQuery')->willReturn(static::createStub(Result::class));

        static::assertSame($expected, $this->createHandler($connection)->replace($content, $host, $this->channelContext));
    }

    public function testSeoReplacementChannelDefaultAndOverride(): void
    {
        $blogId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'seo_path_info' => 'awesome-blog',
                'path_info' => '/detail/' . $blogId,
                'channel_id' => TestDefaults::CHANNEL,
            ],
            [
                'seo_path_info' => 'cars-default',
                'path_info' => '/navigation/' . $categoryId,
                'channel_id' => TestDefaults::CHANNEL,
            ],
        ]);
        $this->connection->method('executeQuery')->willReturn($result);

        $host = 'http://foo.text:8000/de';
        $template = 'SEO 1: %s and SEO 2: %s';

        $content = 'SEO 1: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/detail/' . $blogId . '# and SEO 2: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/navigation/' . $categoryId . '#';
        $actual = $this->seoUrlPlaceholderHandler->replace($content, $host, $this->channelContext);

        $expectedUrl1 = $host . '/awesome-blog';
        $expectedUrl2 = $host . '/cars-default';
        $expected = \sprintf($template, $expectedUrl1, $expectedUrl2);
        static::assertSame($expected, $actual);
    }

    public function testReplacePrependsExternalFrontendDomainForHeadlessChannel(): void
    {
        $blogId = Uuid::randomHex();

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'seo_path_info' => 'blog/' . $blogId,
                'path_info' => '/channel-api/blog/' . $blogId,
                'channel_id' => TestDefaults::CHANNEL,
            ],
        ]);
        $this->connection->method('executeQuery')->willReturn($result);

        $context = $this->createHeadlessChannelContext(true);

        $content = 'SEO: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/channel-api/blog/' . $blogId . '#';
        $actual = $this->seoUrlPlaceholderHandler->replace($content, 'https://my-frontend.com', $context);

        static::assertSame('SEO: https://foo.bar/blog/' . $blogId, $actual);
    }

    public function testReplaceKeepsRelativePathForHeadlessChannelWithoutExternalFrontendDomain(): void
    {
        $blogId = Uuid::randomHex();

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'seo_path_info' => 'blog/' . $blogId,
                'path_info' => '/channel-api/blog/' . $blogId,
                'channel_id' => TestDefaults::CHANNEL,
            ],
        ]);
        $this->connection->method('executeQuery')->willReturn($result);

        $context = $this->createHeadlessChannelContext(false);

        $content = 'SEO: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/channel-api/blog/' . $blogId . '#';
        $actual = $this->seoUrlPlaceholderHandler->replace($content, 'https://my-frontend.com', $context);

        static::assertSame('SEO: blog/' . $blogId, $actual);
    }

    public function testReplaceKeepsRelativePathForHeadlessChannelWhenExternalFrontendDomainLanguageDiffers(): void
    {
        $blogId = Uuid::randomHex();

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllAssociative')->willReturn([
            [
                'seo_path_info' => 'blog/' . $blogId,
                'path_info' => '/channel-api/blog/' . $blogId,
                'channel_id' => TestDefaults::CHANNEL,
            ],
        ]);
        $this->connection->method('executeQuery')->willReturn($result);

        $context = $this->createHeadlessChannelContext(true, Uuid::randomHex());

        $content = 'SEO: ' . SeoUrlPlaceholderHandler::DOMAIN_PLACEHOLDER . '/channel-api/blog/' . $blogId . '#';
        $actual = $this->seoUrlPlaceholderHandler->replace($content, 'https://my-frontend.com', $context);

        static::assertSame('SEO: blog/' . $blogId, $actual);
    }

    private function createHeadlessChannelContext(bool $externalFrontend, ?string $domainLanguageId = null): ChannelContext
    {
        $context = Generator::generateChannelContext();
        $context->getChannel()->setTypeId(Defaults::CHANNEL_TYPE_API);

        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://foo.bar');
        $domain->setIsExternalFrontend($externalFrontend);
        $domain->setLanguageId($domainLanguageId ?? $context->getLanguageId());

        $context->getChannel()->setDomains(new ChannelDomainCollection([$domain]));

        return $context;
    }

    private function createHandler(?Connection $connection = null): SeoUrlPlaceholderHandler
    {
        return new SeoUrlPlaceholderHandler(
            static::createStub(RequestStack::class),
            static::createStub(Router::class),
            $connection ?? $this->connection
        );
    }
}
