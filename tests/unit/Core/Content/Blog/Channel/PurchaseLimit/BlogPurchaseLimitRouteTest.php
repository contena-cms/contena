<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\PurchaseLimit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\AbstractBlogMaxPurchaseCalculator;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\PurchaseLimit\BlogPurchaseLimitCollection;
use Contena\Core\Content\Blog\Channel\PurchaseLimit\BlogPurchaseLimitRoute;
use Contena\Core\Content\Blog\Channel\PurchaseLimit\BlogPurchaseLimitRouteResponse;
use Contena\Core\Content\Blog\Extension\BlogPurchaseLimitRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogPurchaseLimitRoute::class)]
class BlogPurchaseLimitRouteTest extends TestCase
{
    /**
     * @var Stub&ChannelRepository<ChannelBlogCollection>
     */
    private Stub&ChannelRepository $blogRepository;

    private Stub&AbstractBlogMaxPurchaseCalculator $maxPurchaseCalculator;

    private BlogPurchaseLimitRoute $route;

    protected function setUp(): void
    {
        $this->blogRepository = static::createStub(ChannelRepository::class);
        $this->maxPurchaseCalculator = static::createStub(AbstractBlogMaxPurchaseCalculator::class);

        $this->route = $this->createRoute();
    }

    public function testLoadReturnsMultipleBlogLimits(): void
    {
        $context = Generator::generateChannelContext();
        $blogIdA = Uuid::randomHex();
        $blogIdB = Uuid::randomHex();

        $blogA = (new PartialEntity())->assign([
            'id' => $blogIdA,
            'minPurchase' => 1,
            'purchaseSteps' => 1,
            'stock' => 100,
        ]);

        $blogB = (new PartialEntity())->assign([
            'id' => $blogIdB,
            'minPurchase' => 5,
            'purchaseSteps' => 5,
            'stock' => 3,
        ]);

        $blogA->internalSetEntityData('blog', new FieldVisibility([]));
        $blogB->internalSetEntityData('blog', new FieldVisibility([]));

        $this->blogRepository->method('search')->willReturn(
            new EntitySearchResult('blog', 2, new EntityCollection([$blogA, $blogB]), null, new Criteria(), $context->getContext())
        );

        $this->maxPurchaseCalculator->method('calculate')->willReturnMap([
            [$blogA, $context, 20],
            [$blogB, $context, 50],
        ]);

        $request = new Request(['ids' => [$blogIdA, $blogIdB]]);
        $results = $this->route->readBlogsPurchaseLimit($request, $context)->getResult();

        static::assertCount(2, $results);

        $items = array_values($results->getElements());

        static::assertSame($blogIdA, $items[0]->getBlogId());
        static::assertSame(1, $items[0]->getMinPurchase());
        static::assertSame(1, $items[0]->getPurchaseSteps());
        static::assertSame(20, $items[0]->getMaxPurchase());
        static::assertSame(100, $items[0]->getStock());

        static::assertSame($blogIdB, $items[1]->getBlogId());
        static::assertSame(5, $items[1]->getMinPurchase());
        static::assertSame(5, $items[1]->getPurchaseSteps());
        static::assertSame(50, $items[1]->getMaxPurchase());
        static::assertSame(3, $items[1]->getStock());
    }

    public function testLoadDefaults(): void
    {
        $context = Generator::generateChannelContext();
        $blogId = Uuid::randomHex();

        $blog = (new PartialEntity())->assign([
            'id' => $blogId,
        ]);

        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $this->blogRepository->method('search')->willReturn(
            new EntitySearchResult('blog', 1, new EntityCollection([$blog]), null, new Criteria(), $context->getContext())
        );

        $this->maxPurchaseCalculator->method('calculate')->willReturn(5);

        $request = new Request(['ids' => [$blogId]]);
        $result = $this->route->readBlogsPurchaseLimit($request, $context)->getResult()->first();

        static::assertNotNull($result);
        static::assertSame(1, $result->getMinPurchase());
        static::assertSame(1, $result->getPurchaseSteps());
        static::assertNull($result->getStock());
    }

    public function testLoadReturnsEmptyCollectionForUnknownIds(): void
    {
        $context = Generator::generateChannelContext();
        $blogId = Uuid::randomHex();

        $this->blogRepository->method('search')->willReturn(
            new EntitySearchResult('blog', 0, new EntityCollection(), null, new Criteria(), $context->getContext())
        );

        $request = new Request(['ids' => [$blogId]]);
        $results = $this->route->readBlogsPurchaseLimit($request, $context)->getResult();

        static::assertCount(0, $results);
    }

    public function testLoadThrowsForEmptyIds(): void
    {
        $context = Generator::generateChannelContext();

        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->never())->method('search');

        $this->expectException(BlogException::class);

        $this->createRoute($blogRepository)->readBlogsPurchaseLimit(new Request(), $context);
    }

    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(BlogPurchaseLimitRoute::class));
        $this->route->getDecorated();
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $response = new BlogPurchaseLimitRouteResponse(new BlogPurchaseLimitCollection());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('blog-purchase-limit-route.read-blogs-purchase-limit.pre', static function (BlogPurchaseLimitRouteExtension $extension) use ($request, $context, $response): void {
            static::assertSame(['request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new BlogPurchaseLimitRoute(
            static::createStub(ChannelRepository::class),
            static::createStub(AbstractBlogMaxPurchaseCalculator::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->readBlogsPurchaseLimit($request, $context));
    }

    /**
     * @param (ChannelRepository<ChannelBlogCollection>&MockObject)|null $blogRepository
     */
    private function createRoute(?ChannelRepository $blogRepository = null): BlogPurchaseLimitRoute
    {
        return new BlogPurchaseLimitRoute(
            $blogRepository ?? $this->blogRepository,
            $this->maxPurchaseCalculator,
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
