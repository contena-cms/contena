<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\FindVariant;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Exception\VariantNotFoundException;
use Contena\Core\Content\Blog\Extension\FindBlogVariantRouteExtension;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\FindVariant\FindBlogVariantRoute;
use Contena\Core\Content\Blog\Channel\FindVariant\FindBlogVariantRouteResponse;
use Contena\Core\Content\Blog\Channel\FindVariant\FoundCombination;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilter;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilterFactory;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(FindBlogVariantRoute::class)]
class FindBlogVariantRouteTest extends TestCase
{
    /**
     * @var Stub&ChannelRepository<BlogCollection>
     */
    private Stub&ChannelRepository $blogRepositoryMock;

    private CacheTagCollector&Stub $cacheTagCollector;

    private Stub&SystemConfigService $systemConfigService;

    private FindBlogVariantRoute $route;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->blogRepositoryMock = static::createStub(ChannelRepository::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->systemConfigService = static::createStub(SystemConfigService::class);
        $this->route = $this->createRoute();
        $this->ids = new IdsCollection();
    }

    public function testNoDecoration(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(FindBlogVariantRoute::class));

        $this->route->getDecorated();
    }

    public function testLoad(): void
    {
        $options = [
            $this->ids->get('group1') => $this->ids->get('option1'),
            $this->ids->get('group2') => $this->ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.parentId', $this->ids->get('blogId')));
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('blog.optionIds', $this->ids->get('option1')));
        $criteria->addFilter(new EqualsFilter('blog.optionIds', $this->ids->get('option2')));
        $criteria->addFilter(new BlogCloseoutFilter());

        $context = Context::createDefaultContext();

        $found1Id = $this->ids->get('found1');
        $found2Id = $this->ids->get('found2');
        $this->blogRepositoryMock->method('searchIds')
            ->willReturn(
                new IdSearchResult(
                    2,
                    [
                        $found1Id => [
                            'primaryKey' => $found1Id,
                            'data' => [],
                        ],
                        $found2Id => [
                            'primaryKey' => $found2Id,
                            'data' => [],
                        ],
                    ],
                    $criteria,
                    $context
                )
            );

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(EntityCacheKeyGenerator::buildBlogTag($this->ids->get('blogId')));

        $this->systemConfigService->method('getBool')->willReturn(true);

        $response = $this->createRoute($cacheTagCollector)->load($this->ids->get('blogId'), $request, static::createStub(ChannelContext::class));

        static::assertSame($found1Id, $response->getFoundCombination()->getVariantId());
        static::assertSame($options, $response->getFoundCombination()->getOptions());
    }

    public function testLoadFirstVariantNotFound(): void
    {
        $options = [
            $this->ids->get('group1') => $this->ids->get('option1'),
            $this->ids->get('group2') => $this->ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.parentId', $this->ids->get('blogId')));
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('blog.optionIds', $this->ids->get('option1')));
        $criteria->addFilter(new EqualsFilter('blog.optionIds', $this->ids->get('option2')));

        $criteria2 = new Criteria();
        $criteria2->addFilter(new EqualsFilter('blog.parentId', $this->ids->get('blogId')));
        $criteria2->setLimit(1);
        $criteria2->addFilter(new EqualsFilter('blog.optionIds', $this->ids->get('option2')));

        $context = Context::createDefaultContext();

        $found1Id = $this->ids->get('found1');
        $this->blogRepositoryMock->method('searchIds')
            ->willReturnOnConsecutiveCalls(
                new IdSearchResult(
                    0,
                    [
                    ],
                    $criteria,
                    $context
                ),
                new IdSearchResult(
                    1,
                    [
                        $found1Id => [
                            'primaryKey' => $found1Id,
                            'data' => [],
                        ],
                    ],
                    $criteria2,
                    $context
                ),
            );

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(EntityCacheKeyGenerator::buildBlogTag($this->ids->get('blogId')));

        $response = $this->createRoute($cacheTagCollector)->load($this->ids->get('blogId'), $request, static::createStub(ChannelContext::class));

        static::assertSame($found1Id, $response->getFoundCombination()->getVariantId());
    }

    public function testLoadNoVariantFound(): void
    {
        $options = [
            $this->ids->get('group1') => $this->ids->get('option1'),
            $this->ids->get('group2') => $this->ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.parentId', $this->ids->get('blogId')));
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('blog.optionIds', $this->ids->get('option1')));
        $criteria->addFilter(new EqualsFilter('blog.optionIds', $this->ids->get('option2')));

        $criteria2 = new Criteria();
        $criteria2->addFilter(new EqualsFilter('blog.parentId', $this->ids->get('blogId')));
        $criteria2->setLimit(1);
        $criteria2->addFilter(new EqualsFilter('blog.optionIds', $this->ids->get('option2')));

        $context = Context::createDefaultContext();

        $this->blogRepositoryMock->method('searchIds')
            ->willReturnOnConsecutiveCalls(
                new IdSearchResult(
                    0,
                    [
                    ],
                    $criteria,
                    $context
                ),
                new IdSearchResult(
                    0,
                    [
                    ],
                    $criteria2,
                    $context
                ),
            );

        $this->expectExceptionObject(BlogException::variantNotFound(
            $this->ids->get('blogId'),
            [$this->ids->get('group2') => $this->ids->get('option2')]
        ));

        try {
            $this->route->load($this->ids->get('blogId'), $request, static::createStub(ChannelContext::class));
        } catch (VariantNotFoundException $e) {
            static::assertSame('CONTENT__BLOG_VARIANT_NOT_FOUND', $e->getErrorCode());

            throw $e;
        }
    }

    public function testLoadWithInvalidOptions(): void
    {
        $options = ['optionId1', []];

        $request = new Request(
            [
                'switched' => $this->ids->get('element'),
                'options' => $options,
            ]
        );
        $this->expectExceptionObject(BlogException::invalidOptionsParameter());

        $this->route->load($this->ids->get('blogId'), $request, static::createStub(ChannelContext::class));
    }

    public function testPublishesExtension(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request();
        $context = Generator::generateChannelContext();
        $response = new FindBlogVariantRouteResponse(new FoundCombination(Uuid::randomHex(), []));

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('find-blog-variant-route.load.pre', static function (FindBlogVariantRouteExtension $extension) use ($blogId, $request, $context, $response): void {
            static::assertSame(['blogId' => $blogId, 'request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new FindBlogVariantRoute(
            static::createStub(ChannelRepository::class),
            static::createStub(CacheTagCollector::class),
            static::createStub(SystemConfigService::class),
            static::createStub(AbstractBlogCloseoutFilterFactory::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($blogId, $request, $context));
    }

    private function createRoute(?CacheTagCollector $cacheTagCollector = null): FindBlogVariantRoute
    {
        return new FindBlogVariantRoute(
            $this->blogRepositoryMock,
            $cacheTagCollector ?? $this->cacheTagCollector,
            $this->systemConfigService,
            new BlogCloseoutFilterFactory(),
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
