<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Sitemap\Provider\BlogUrlProvider;
use Contena\Core\Content\Sitemap\Service\ConfigHandler;
use Contena\Core\Content\Sitemap\Struct\Url;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(BlogUrlProvider::class)]
class BlogUrlProviderTest extends TestCase
{
    private readonly ConfigHandler&Stub $configHandler;

    private readonly Connection&Stub $connection;

    private readonly BlogDefinition&Stub $definition;

    private readonly IteratorFactory&Stub $iteratorFactory;

    private readonly EntityRouteResolver&Stub $entityRouteResolver;

    private readonly SystemConfigService&Stub $systemConfigService;

    private readonly EventDispatcher&Stub $dispatcher;

    private BlogUrlProvider $blogUrlProvider;

    protected function setUp(): void
    {
        $this->configHandler = static::createStub(ConfigHandler::class);
        $this->connection = static::createStub(Connection::class);
        $this->definition = static::createStub(BlogDefinition::class);
        $this->iteratorFactory = static::createStub(IteratorFactory::class);
        $this->entityRouteResolver = static::createStub(EntityRouteResolver::class);
        $this->systemConfigService = static::createStub(SystemConfigService::class);
        $this->dispatcher = static::createStub(EventDispatcher::class);

        $this->blogUrlProvider = new BlogUrlProvider(
            $this->configHandler,
            $this->connection,
            $this->definition,
            $this->iteratorFactory,
            $this->entityRouteResolver,
            $this->systemConfigService,
            $this->dispatcher
        );
    }

    public function testGetDecorated(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->blogUrlProvider->getDecorated();
    }

    public function testGetName(): void
    {
        $name = $this->blogUrlProvider->getName();
        static::assertSame('blog', $name);
    }

    public function testGetBlogUrls(): void
    {
        $ids = new IdsCollection();
        $queryResult = new Result(
            new ArrayResult(
                ['auto_increment', 'id', 'created_at', 'updated_at'],
                [
                    [1, $ids->get('blog-1'), '2021-01-01 00:00:00', null],
                    [2, $ids->get('blog-2'), '2021-01-01 00:00:00', null],
                ]
            ),
            $this->connection
        );

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'foreign_key' => $ids->get('blog-1'),
                'seo_path_info' => 'blog/1/detail',
            ],
        ]);

        $this->entityRouteResolver->method('getRouteNameForEntityName')->willReturn(BlogPageSeoUrlRoute::ROUTE_NAME);
        $this->entityRouteResolver->method('generateUrl')->willReturn('blog/2/detail');

        $queryBuilderMock = static::createStub(QueryBuilder::class);
        $queryBuilderMock->method('executeQuery')->willReturn($queryResult);

        $query = static::createStub(IterableQuery::class);
        $query->method('getQuery')->willReturn($queryBuilderMock);

        $this->iteratorFactory->method('createIterator')->willReturn($query);
        $this->configHandler->method('get')
            ->willReturn([
                [
                    'resource' => BlogEntity::class,
                    'channelId' => TestDefaults::CHANNEL,
                    'identifier' => $ids->get('blog-1'),
                ],
                [
                    'resource' => BlogEntity::class,
                    'channelId' => Uuid::randomHex(),
                    'identifier' => $ids->get('blog-2'),
                ],
            ]);

        $context = Generator::generateChannelContext();

        $urlResult = $this->blogUrlProvider->getUrls($context, 100, 50);

        $urls = $urlResult->getUrls();
        static::assertCount(2, $urls);

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($ids->get('blog-1'), $url->getIdentifier());
        static::assertSame('blog/1/detail', $url->getLoc());

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($ids->get('blog-2'), $url->getIdentifier());
        static::assertSame('blog/2/detail', $url->getLoc());

        static::assertSame(2, $urlResult->getNextOffset());
    }
}
