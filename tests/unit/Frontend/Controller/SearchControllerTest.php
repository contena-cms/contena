<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Search\AbstractBlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRouteResponse;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\SearchController;
use Contena\Frontend\Page\Search\SearchPage;
use Contena\Frontend\Page\Search\SearchPageLoader;
use Contena\Frontend\Page\Suggest\SuggestPage;
use Contena\Frontend\Page\Suggest\SuggestPageLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SearchController::class)]
class SearchControllerTest extends TestCase
{
    private SearchPageLoader&Stub $searchPageLoader;

    private SuggestPageLoader&Stub $suggestPageLoader;

    private AbstractBlogSearchRoute&Stub $blogSearchRoute;

    private SearchControllerTestClass $controller;

    protected function setUp(): void
    {
        $this->searchPageLoader = static::createStub(SearchPageLoader::class);
        $this->suggestPageLoader = static::createStub(SuggestPageLoader::class);
        $this->blogSearchRoute = static::createStub(AbstractBlogSearchRoute::class);
        $this->controller = new SearchControllerTestClass(
            $this->searchPageLoader,
            $this->suggestPageLoader,
            $this->blogSearchRoute,
        );
    }

    public function testSearchRendersPage(): void
    {
        $context = Generator::generateChannelContext();
        $page = new SearchPage();
        $this->searchPageLoader->method('load')->willReturn($page);

        $response = $this->controller->search($context, new Request(['search' => 'test']));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('@Frontend/frontend/page/search/index.html.twig', $this->controller->renderFrontendView);
        static::assertSame($page, $this->controller->renderFrontendParameters['page']);
    }

    public function testSearchWithoutSearchParameterForwardsToHomePage(): void
    {
        $context = Generator::generateChannelContext();
        $this->searchPageLoader->method('load')->willThrowException(RoutingException::missingRequestParameter('search'));

        $response = $this->controller->search($context, new Request());

        static::assertSame('frontend.home.page', $this->controller->forwardToRoute);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSearchRethrowsUnexpectedRoutingException(): void
    {
        $context = Generator::generateChannelContext();
        $exception = RoutingException::invalidRequestParameter('search');
        $this->searchPageLoader->method('load')->willThrowException($exception);

        static::expectExceptionObject($exception);
        $this->controller->search($context, new Request(['search' => 'test']));
    }

    public function testSuggestDisablesAggregationsAndRendersPagelet(): void
    {
        $context = Generator::generateChannelContext();
        $page = new SuggestPage();
        $suggestPageLoader = $this->createMock(SuggestPageLoader::class);
        $suggestPageLoader->expects($this->once())->method('load')->willReturnCallback(
            static function (Request $request, ChannelContext $channelContext) use ($page, $context): SuggestPage {
                static::assertSame($context, $channelContext);
                static::assertTrue($request->request->getBoolean('no-aggregations'));

                return $page;
            },
        );
        $this->controller = new SearchControllerTestClass($this->searchPageLoader, $suggestPageLoader, $this->blogSearchRoute);

        $request = new Request(['search' => 'test']);
        $response = $this->controller->suggest($context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('@Frontend/frontend/layout/header/search-suggest.html.twig', $this->controller->renderFrontendView);
        static::assertSame($page, $this->controller->renderFrontendParameters['page']);
    }

    public function testAjaxDisablesAggregationsAndSetsNoIndexHeader(): void
    {
        $context = Generator::generateChannelContext();
        $page = new SearchPage();
        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $searchPageLoader->expects($this->once())->method('load')->willReturnCallback(
            static function (Request $request, ChannelContext $channelContext) use ($page, $context): SearchPage {
                static::assertSame($context, $channelContext);
                static::assertTrue($request->request->getBoolean('no-aggregations'));

                return $page;
            },
        );
        $this->controller = new SearchControllerTestClass($searchPageLoader, $this->suggestPageLoader, $this->blogSearchRoute);

        $response = $this->controller->ajax(new Request(['search' => 'test']), $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('noindex', $response->headers->get('x-robots-tag'));
        static::assertSame('@Frontend/frontend/page/search/search-pagelet.html.twig', $this->controller->renderFrontendView);
    }

    public function testFilterReturnsAggregationsAsJsonAndSetsNoIndexHeader(): void
    {
        $context = Generator::generateChannelContext();
        $aggregations = new AggregationResultCollection();
        $aggregations->add(new CountResult('blog_count', 3));
        $listing = BlogListingResult::fromSearchResult(new EntitySearchResult(
            0,
            new BlogCollection(),
            $aggregations,
            new Criteria(),
            Context::createDefaultContext(),
        ));
        $blogSearchRoute = $this->createMock(AbstractBlogSearchRoute::class);
        $blogSearchRoute->expects($this->once())->method('load')->willReturnCallback(
            static function (Request $request, ChannelContext $channelContext, Criteria $criteria) use ($listing, $context): BlogSearchRouteResponse {
                static::assertSame($context, $channelContext);
                static::assertTrue($request->request->getBoolean('only-aggregations'));
                static::assertTrue($request->request->getBoolean('reduce-aggregations'));
                static::assertSame('search-page', $criteria->getTitle());

                return new BlogSearchRouteResponse($listing);
            },
        );
        $this->controller = new SearchControllerTestClass($this->searchPageLoader, $this->suggestPageLoader, $blogSearchRoute);

        $response = $this->controller->filter(new Request(['search' => 'test']), $context);

        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame('noindex', $response->headers->get('x-robots-tag'));
        static::assertSame(
            ['blog_count' => ['extensions' => [], 'name' => 'blog_count', 'count' => 3]],
            json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    public function testFilterRequiresSearchParameter(): void
    {
        $context = Generator::generateChannelContext();

        static::expectExceptionObject(RoutingException::missingRequestParameter('search'));
        $this->controller->filter(new Request(), $context);
    }
}

/**
 * @internal
 */
class SearchControllerTestClass extends SearchController
{
    use FrontendControllerMockTrait;
}
