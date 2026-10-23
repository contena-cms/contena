<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Breadcrumb\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Breadcrumb\Extension\BreadcrumbRouteExtension;
use Contena\Core\Content\Breadcrumb\Channel\BreadcrumbRoute;
use Contena\Core\Content\Breadcrumb\Channel\BreadcrumbRouteResponse;
use Contena\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BreadcrumbRoute::class)]
class BreadcrumbRouteTest extends TestCase
{
    private BreadcrumbRoute $breadcrumbRoute;

    private Stub&CategoryBreadcrumbBuilder $breadcrumbBuilder;

    private CacheTagCollector&Stub $cacheTagCollector;

    private ChannelContext $context;

    protected function setUp(): void
    {
        $this->breadcrumbBuilder = static::createStub(CategoryBreadcrumbBuilder::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->context = static::createStub(ChannelContext::class);

        $this->breadcrumbRoute = $this->createRoute();
    }

    public function testLoadCategoryBreadcrumbReturnsCorrectBreadcrumb(): void
    {
        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId('019192b9cd82711482744d7b456b6c01');
        $categoryEntity->setName('Test LP');
        $categoryEntity->setType('category');

        $request = new Request(['type' => 'category'], [], ['id' => '1']);
        $this->breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection([new Breadcrumb('Home', 'categoryId1')]));
        $this->breadcrumbBuilder->method('loadCategory')->willReturn($categoryEntity);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(CategoryRoute::buildName('categoryId1'));

        $collection = $this->createRoute($cacheTagCollector)->load($request, $this->context)->getBreadcrumbCollection();
        static::assertCount(1, $collection);
        $firstBreadcrumb = $collection->first();
        static::assertNotNull($firstBreadcrumb);

        static::assertSame('Home', $firstBreadcrumb->name);
    }

    public function testLoadCategoryBreadcrumbReturnsCorrectBreadcrumbNullCategory(): void
    {
        $request = new Request(['type' => 'category'], [], ['id' => '1']);
        $this->breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection([new Breadcrumb('Home', 'categoryId1')]));

        $response = $this->breadcrumbRoute->load($request, $this->context);
        static::assertNull($response->getBreadcrumbCollection()->first());
    }

    public function testGetDecoratedThrowsException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->breadcrumbRoute->getDecorated();
    }

    public function testLoadBlogBreadcrumbReturnsCorrectBreadcrumb(): void
    {
        $request = new Request(['type' => 'blog'], [], ['id' => 'blogId1']);
        $this->breadcrumbBuilder->method('getBlogBreadcrumbUrls')->willReturn(new BreadcrumbCollection([new Breadcrumb('Blog', 'categoryId1')]));

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(
                CategoryRoute::buildName('categoryId1'),
                EntityCacheKeyGenerator::buildBlogTag('blogId1')
            );

        $collection = $this->createRoute($cacheTagCollector)->load($request, $this->context)->getBreadcrumbCollection();
        static::assertCount(1, $collection);
        $firstBreadcrumb = $collection->first();
        static::assertNotNull($firstBreadcrumb);

        static::assertSame('Blog', $firstBreadcrumb->name);
    }

    public function testLoadBlogBreadcrumbWithFallbackToCategory(): void
    {
        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId('019192b9cd82711482744d7b456b6c01');
        $categoryEntity->setName('Test LP');
        $categoryEntity->setType('page');

        $request = new Request(['type' => 'blog'], [], ['id' => '1']);
        $this->breadcrumbBuilder->method('getBlogBreadcrumbUrls')->willThrowException(new BlogNotFoundException('1'));
        $this->breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection([new Breadcrumb('Category', 'category')]));
        $this->breadcrumbBuilder->method('loadCategory')->willReturn($categoryEntity);

        $collection = $this->breadcrumbRoute->load($request, $this->context)->getBreadcrumbCollection();
        static::assertCount(1, $collection);
        $firstBreadcrumb = $collection->first();
        static::assertNotNull($firstBreadcrumb);
        static::assertSame('Category', $firstBreadcrumb->name);
    }

    public function testLoadBlogBreadcrumbWithFallbackToCategoryNullCategory(): void
    {
        $request = new Request(['type' => 'blog'], [], ['id' => '1']);
        $this->breadcrumbBuilder->method('getBlogBreadcrumbUrls')->willThrowException(new BlogNotFoundException('1'));
        $this->breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection([new Breadcrumb('Category', 'category')]));

        $response = $this->breadcrumbRoute->load($request, $this->context);
        static::assertNull($response->getBreadcrumbCollection()->first());
    }

    public function testLoadBreadcrumbWithInvalidType(): void
    {
        $request = new Request(['type' => 'invalid'], [], ['id' => '1']);
        $response = $this->breadcrumbRoute->load($request, $this->context);

        static::assertCount(0, $response->getBreadcrumbCollection());
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $response = static::createStub(BreadcrumbRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('breadcrumb-route.load.pre', function (BreadcrumbRouteExtension $extension) use ($request, $response): void {
            static::assertSame(['request' => $request, 'channelContext' => $this->context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new BreadcrumbRoute(
            static::createStub(CategoryBreadcrumbBuilder::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $this->context));
    }

    private function createRoute(?CacheTagCollector $cacheTagCollector = null): BreadcrumbRoute
    {
        return new BreadcrumbRoute(
            $this->breadcrumbBuilder,
            $cacheTagCollector ?? $this->cacheTagCollector,
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
