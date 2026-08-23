<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Search\AbstractBlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRouteResponse;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\Search\SearchPageLoadedEvent;
use Contena\Frontend\Page\Search\SearchPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SearchPageLoader::class)]
class SearchPageLoaderTest extends TestCase
{
    public function testItLoad(): void
    {
        $request = new Request(['search' => 'test']);
        $channelContext = Generator::generateChannelContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use ($channelContext, $request) {
                static::assertInstanceOf(SearchPageLoadedEvent::class, $event);
                static::assertSame($channelContext, $event->getChannelContext());
                static::assertSame($request, $event->getRequest());

                return $event;
            });

        $searchPageLoader = new SearchPageLoader(
            static::createStub(GenericPageLoaderInterface::class),
            $this->getBlogSearchRoute($channelContext),
            $eventDispatcher,
            static::createStub(AbstractTranslator::class),
        );

        $page = $searchPageLoader->load($request, $channelContext);

        static::assertSame('test', $page->getSearchTerm());
    }

    public function testItLoadWithoutSearchTerm(): void
    {
        $request = new Request();
        $channelContext = Generator::generateChannelContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use ($channelContext, $request) {
                static::assertInstanceOf(SearchPageLoadedEvent::class, $event);
                static::assertSame($channelContext, $event->getChannelContext());
                static::assertSame($request, $event->getRequest());

                return $event;
            });

        $searchPageLoader = new SearchPageLoader(
            static::createStub(GenericPageLoaderInterface::class),
            $this->getBlogSearchRoute($channelContext),
            $eventDispatcher,
            static::createStub(AbstractTranslator::class),
        );

        $page = $searchPageLoader->load($request, $channelContext);

        static::assertSame('', $page->getSearchTerm());
    }

    private function getBlogSearchRoute(ChannelContext $channelContext): AbstractBlogSearchRoute
    {
        $result = new EntitySearchResult(
            0,
            new BlogCollection(),
            new AggregationResultCollection(),
            new Criteria(),
            Context::createDefaultContext(),
        );

        $route = static::createStub(AbstractBlogSearchRoute::class);
        $route->method('load')->willReturn(new BlogSearchRouteResponse(BlogListingResult::fromSearchResult($result)));

        return $route;
    }
}
