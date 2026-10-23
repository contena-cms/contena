<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Extension\BlogSearchRouteExtension;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRouteResponse;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilderInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogSearchRoute::class)]
class BlogSearchRouteTest extends TestCase
{
    /**
     * @var BlogListingLoader&MockObject
     */
    private BlogListingLoader $listingLoader;

    /**
     * @var BlogSearchBuilderInterface&MockObject
     */
    private BlogSearchBuilderInterface $searchBuilder;

    protected function setUp(): void
    {
        $this->searchBuilder = $this->createMock(BlogSearchBuilderInterface::class);
        $this->listingLoader = $this->createMock(BlogListingLoader::class);
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        $this->searchBuilder->expects($this->never())->method('build');
        $this->listingLoader->expects($this->never())->method('load');

        static::expectException(DecorationPatternException::class);

        $this->getBlogSearchRoute()->getDecorated();
    }

    public function testLoadWithSearchTerm(): void
    {
        $request = new Request();
        $request->query->set('search', 'test');

        $criteria = new Criteria();

        $this->searchBuilder->expects($this->once())
            ->method('build')
            ->with(
                $request,
                $criteria,
                static::isInstanceOf(ChannelContext::class)
            );

        $this->listingLoader->expects($this->once())
            ->method('load')
            ->willReturn(new BlogListingResult(
                BlogDefinition::ENTITY_NAME,
                1,
                new BlogCollection([]),
                null,
                $criteria,
                Context::createDefaultContext()
            ));

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $this->getBlogSearchRoute()->load(
            $request,
            $channelContext,
            $criteria
        );

        static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
    }

    public function testLoadWithoutSearchTerm(): void
    {
        $request = new Request();
        $criteria = new Criteria();

        $this->searchBuilder->expects($this->never())
            ->method('build');

        $this->listingLoader->expects($this->once())
            ->method('load')
            ->willReturn(new BlogListingResult(
                BlogDefinition::ENTITY_NAME,
                1,
                new BlogCollection([]),
                null,
                $criteria,
                Context::createDefaultContext()
            ));

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $this->getBlogSearchRoute()->load(
            $request,
            $channelContext,
            $criteria
        );

        static::assertTrue($criteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(BlogSearchRouteResponse::class);

        $this->searchBuilder->expects($this->never())->method('build');
        $this->listingLoader->expects($this->never())->method('load');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('blog-search-route.load.pre', static function (BlogSearchRouteExtension $extension) use ($request, $context, $criteria, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new BlogSearchRoute(
            $this->searchBuilder,
            $this->listingLoader,
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria));
    }

    private function getBlogSearchRoute(): BlogSearchRoute
    {
        return new BlogSearchRoute(
            $this->searchBuilder,
            $this->listingLoader,
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
