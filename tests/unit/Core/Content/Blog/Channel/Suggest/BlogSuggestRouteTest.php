<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Suggest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Blog\Channel\Suggest\AbstractBlogSuggestRoute;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRoute;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRouteResponse;
use Contena\Core\Content\Blog\Channel\Suggest\ResolvedCriteriaBlogSuggestRoute;
use Contena\Core\Content\Blog\Extension\BlogSuggestRouteExtension;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilderInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogSuggestRoute::class)]
class BlogSuggestRouteTest extends TestCase
{
    /**
     * @var BlogListingLoader&MockObject
     */
    private BlogListingLoader $listingLoader;

    protected function setUp(): void
    {
        $this->listingLoader = $this->createMock(BlogListingLoader::class);
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        $this->listingLoader->expects($this->never())->method('load');

        $this->expectExceptionObject(new DecorationPatternException(BlogSuggestRoute::class));

        $this->getBlogSuggestRoute()->getDecorated();
    }

    public function testLoadThrowsExceptionForMissingSearchParameter(): void
    {
        $this->listingLoader->expects($this->never())->method('load');

        $this->expectExceptionObject(BlogException::missingRequestParameter('search'));

        $route = new ResolvedCriteriaBlogSuggestRoute(
            static::createStub(BlogSearchBuilderInterface::class),
            new EventDispatcher(),
            static::createStub(AbstractBlogSuggestRoute::class),
            new CompositeListingProcessor([])
        );

        $route->load(
            new Request(),
            static::createStub(ChannelContext::class),
            new Criteria()
        );
    }

    public function testLoadSuccessfully(): void
    {
        $request = new Request();
        $request->query->set('search', 'test');

        $criteria = new Criteria();

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

        $this->getBlogSuggestRoute()->load(
            $request,
            $channelContext,
            $criteria
        );
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(BlogSuggestRouteResponse::class);

        $this->listingLoader->expects($this->never())->method('load');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('blog-suggest-route.load.pre', static function (BlogSuggestRouteExtension $extension) use ($request, $context, $criteria, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new BlogSuggestRoute(
            $this->listingLoader,
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria));
    }

    private function getBlogSuggestRoute(): BlogSuggestRoute
    {
        return new BlogSuggestRoute(
            $this->listingLoader,
            new ExtensionDispatcher(new EventDispatcher())
        );
    }
}
