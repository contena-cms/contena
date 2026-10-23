<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Search\AbstractBlogSearchRoute;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Controller\SearchController;
use Contena\Frontend\Event\FrontendRedirectEvent;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Page\Search\SearchPage;
use Contena\Frontend\Page\Search\SearchPageLoadedHook;
use Contena\Frontend\Page\Search\SearchPageLoader;
use Contena\Frontend\Page\Suggest\SuggestPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(SearchController::class)]
class SearchControllerTest extends TestCase
{
    private SearchPageLoader&Stub $searchPageLoader;

    private SuggestPageLoader&Stub $suggestPageLoader;

    private AbstractBlogSearchRoute&Stub $blogSearchRoute;

    private SearchController $searchController;

    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->searchPageLoader = static::createStub(SearchPageLoader::class);
        $this->suggestPageLoader = static::createStub(SuggestPageLoader::class);
        $this->blogSearchRoute = static::createStub(AbstractBlogSearchRoute::class);

        $this->container = new ContainerBuilder();
        $this->container->set(SystemConfigService::class, static::createStub(SystemConfigService::class));
        $this->container->set(SeoUrlPlaceholderHandlerInterface::class, static::createStub(SeoUrlPlaceholderHandlerInterface::class));
        $this->container->set(MediaUrlPlaceholderHandlerInterface::class, static::createStub(MediaUrlPlaceholderHandlerInterface::class));
        $this->container->set('event_dispatcher', new EventDispatcher());
        $this->container->set(RequestTransformerInterface::class, static::createStub(RequestTransformerInterface::class));
        $this->container->set('http_kernel', static::createStub(HttpKernelInterface::class));
        $this->container->set('router', static::createStub(RouterInterface::class));
        $this->container->set('twig', static::createStub(Environment::class));

        $this->searchController = $this->createSearchController();
    }

    public function testSearchWithManyBlogsFound(): void
    {
        $context = static::createStub(ChannelContext::class);

        $blog1 = new BlogEntity();
        $blog1->setBlogNumber('test_1');
        $blog1->setId('1');

        $blog2 = new BlogEntity();
        $blog2->setBlogNumber('test_2');
        $blog2->setId('2');

        $searchPage = new SearchPage();

        $searchPage->setListing(new BlogListingResult(
            BlogDefinition::ENTITY_NAME,
            1,
            new BlogCollection([$blog1, $blog2]),
            null,
            new Criteria(),
            $context->getContext(),
        ));

        $hook = new SearchPageLoadedHook($searchPage, $context);

        $executor = static::createMock(ScriptExecutor::class);
        $executor
            ->expects($this->once())
            ->method('execute')
            ->with($hook);

        $request = new Request(
            query: ['search' => 'test'],
            attributes: [
                PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $context,
                RequestTransformer::FRONTEND_URL => 'http://localhost/search?search=test',
            ],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->container->set('request_stack', $requestStack);
        $this->container->set(ScriptExecutor::class, $executor);
        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder
            ->expects($this->once())
            ->method('find')
            ->with('@Frontend/frontend/page/search/index.html.twig')
            ->willReturn('@Frontend/frontend/page/search/index.html.twig');

        $this->container->set(TemplateFinder::class, $templateFinder);

        $parameters = [
            'page' => $searchPage,
            'context' => $context,
            'headerParameters' => [],
            'footerParameters' => [],
        ];

        $twig = static::createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Frontend/frontend/page/search/index.html.twig', $parameters)
            ->willReturn('foo');

        $this->container->set('twig', $twig);

        $this->container->set('router', static::createStub(RouterInterface::class));

        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $searchPageLoader->expects($this->once())->method('load')->willReturn($searchPage);
        $searchController = $this->createSearchController($searchPageLoader);

        $response = $searchController->search($context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSearchWithNoBlogsFound(): void
    {
        $context = static::createStub(ChannelContext::class);

        $searchPage = new SearchPage();
        $searchPage->setListing(new BlogListingResult(
            BlogDefinition::ENTITY_NAME,
            1,
            new BlogCollection([]),
            null,
            new Criteria(),
            $context->getContext(),
        ));

        $hook = new SearchPageLoadedHook($searchPage, $context);

        $executor = static::createMock(ScriptExecutor::class);
        $executor
            ->expects($this->once())
            ->method('execute')
            ->with($hook);

        $request = new Request(
            query: ['search' => 'test'],
            attributes: [
                PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $context,
                RequestTransformer::FRONTEND_URL => 'http://localhost/search?search=test',
            ],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->container->set('request_stack', $requestStack);
        $this->container->set(ScriptExecutor::class, $executor);
        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder
            ->expects($this->once())
            ->method('find')
            ->with('@Frontend/frontend/page/search/index.html.twig')
            ->willReturn('@Frontend/frontend/page/search/index.html.twig');

        $this->container->set(TemplateFinder::class, $templateFinder);

        $parameters = [
            'page' => $searchPage,
            'context' => $context,
            'headerParameters' => [],
            'footerParameters' => [],
        ];

        $twig = static::createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@Frontend/frontend/page/search/index.html.twig', $parameters)
            ->willReturn('foo');

        $this->container->set('twig', $twig);

        $this->container->set('router', static::createStub(RouterInterface::class));

        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $searchPageLoader->expects($this->once())->method('load')->willReturn($searchPage);
        $searchController = $this->createSearchController($searchPageLoader);

        $response = $searchController->search($context, $request);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testSearchWithoutSearchParameterShouldRedirectToHomePage(): void
    {
        $context = static::createStub(ChannelContext::class);

        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $searchPageLoader->expects($this->once())
            ->method('load')
            ->willThrowException(RoutingException::missingRequestParameter('search'));
        $searchController = $this->createSearchController($searchPageLoader);

        $request = new Request(
            query: ['search' => 'test'],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->container->set('request_stack', $requestStack);

        $twig = static::createStub(Environment::class);
        $this->container->set('twig', $twig);

        $router = static::createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('frontend.home.page', [], 10)
            ->willReturn('http://localhost/');

        $router->expects($this->once())
            ->method('match')
            ->willReturn(['_controller' => SearchController::class]);

        $requestContext = new RequestContext();
        $router->method('getContext')
            ->willReturn($requestContext);

        $this->container->set('router', $router);

        $searchController->search($context, $request);
    }

    public function testSearchError(): void
    {
        $exception = RoutingException::invalidRequestParameter('test');

        $context = static::createStub(ChannelContext::class);

        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $searchPageLoader->expects($this->once())
            ->method('load')
            ->willThrowException($exception);
        $searchController = $this->createSearchController($searchPageLoader);

        static::expectExceptionObject($exception);
        $searchController->search($context, new Request());
    }

    /**
     * @return iterable<string, array{0: callable(BlogEntity): void, 1: string}>
     */
    public static function firstHitIdentifierProvider(): iterable
    {
        yield 'blogNumber' => [
            static fn (BlogEntity $p) => $p->setBlogNumber('SW-100'),
            'sw-100',
        ];

        yield 'blogNumber matches search term in original casing' => [
            static fn (BlogEntity $p) => $p->setBlogNumber('SW-100'),
            'SW-100',
        ];

        yield 'blogNumber matches search term with surrounding whitespace' => [
            static fn (BlogEntity $p) => $p->setBlogNumber('SW-100'),
            '  SW-100  ',
        ];

        yield 'ean' => [
            static fn (BlogEntity $p) => $p->setEan('4006381333931'),
            '4006381333931',
        ];

        yield 'ean matches search term with alphabetic characters' => [
            static fn (BlogEntity $p) => $p->setEan('BC1010'),
            'bc1010',
        ];

        yield 'manufacturerNumber' => [
            static fn (BlogEntity $p) => $p->setManufacturerNumber('MPN-XYZ'),
            'mpn-xyz',
        ];

        yield 'manufacturerNumber matches search term in original casing' => [
            static fn (BlogEntity $p) => $p->setManufacturerNumber('MPN-XYZ'),
            'MPN-XYZ',
        ];
    }

    /**
     * @param callable(BlogEntity): void $configureBlog
     */
    #[DataProvider('firstHitIdentifierProvider')]
    public function testSearchHandleFirstHit(callable $configureBlog, string $searchTerm): void
    {
        $request = new Request();
        $request->query->set('search', $searchTerm);

        $context = static::createStub(ChannelContext::class);

        $blog = new BlogEntity();
        $blog->setBlogNumber('different-number');
        $blog->setId('123');
        $configureBlog($blog);

        $searchPage = new SearchPage();
        $searchPage->setListing(new BlogListingResult(
            BlogDefinition::ENTITY_NAME,
            1,
            new BlogCollection([$blog]),
            null,
            new Criteria(),
            $context->getContext(),
        ));

        $dispatcher = new EventDispatcher();

        $redirectEvent = null;
        $dispatcher->addListener(FrontendRedirectEvent::class, static function (FrontendRedirectEvent $event) use (&$redirectEvent): void {
            $redirectEvent = $event;
        });

        $router = static::createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with(BlogPageSeoUrlRoute::ROUTE_NAME, ['blogId' => '123'])
            ->willReturn('http://localhost/blog/123');

        $requestContext = new RequestContext();
        $router->method('getContext')
            ->willReturn($requestContext);

        $this->container->set('router', $router);
        $this->container->set('event_dispatcher', $dispatcher);

        $this->searchPageLoader->method('load')->willReturn($searchPage);

        $response = $this->searchController->search($context, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(FrontendRedirectEvent::class, $redirectEvent);
        static::assertSame(Response::HTTP_FOUND, $redirectEvent->getStatus());
        static::assertSame(BlogPageSeoUrlRoute::ROUTE_NAME, $redirectEvent->getRoute());
        static::assertSame([
            'blogId' => '123',
        ], $redirectEvent->getParameters());
    }

    public function testSearchDoesNotRedirectWhenFieldExcludedByConfig(): void
    {
        $searchPageLoader = $this->createMock(SearchPageLoader::class);
        $controller = $this->createSearchController($searchPageLoader, ['blogNumber']);

        $context = static::createStub(ChannelContext::class);

        $blog = new BlogEntity();
        $blog->setBlogNumber('different-number');
        $blog->setEan('4006381333931');
        $blog->setId('123');

        $searchPage = new SearchPage();
        $searchPage->setListing(new BlogListingResult(
            BlogDefinition::ENTITY_NAME,
            1,
            new BlogCollection([$blog]),
            null,
            new Criteria(),
            $context->getContext(),
        ));

        $request = new Request(
            query: ['search' => '4006381333931'],
            attributes: [
                PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $context,
                RequestTransformer::FRONTEND_URL => 'http://localhost/search?search=4006381333931',
            ],
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->container->set('request_stack', $requestStack);
        $this->container->set(ScriptExecutor::class, static::createStub(ScriptExecutor::class));

        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder
            ->expects($this->once())
            ->method('find')
            ->with('@Frontend/frontend/page/search/index.html.twig')
            ->willReturn('@Frontend/frontend/page/search/index.html.twig');
        $this->container->set(TemplateFinder::class, $templateFinder);

        $twig = static::createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->willReturn('rendered');
        $this->container->set('twig', $twig);

        $router = static::createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');
        $this->container->set('router', $router);

        $searchPageLoader->expects($this->once())->method('load')->willReturn($searchPage);

        $response = $controller->search($context, $request);

        static::assertNotInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @param list<string>|null $redirectOnSingleHitFields
     */
    private function createSearchController(?SearchPageLoader $searchPageLoader = null, ?array $redirectOnSingleHitFields = null): SearchController
    {
        $controller = $redirectOnSingleHitFields === null
            ? new SearchController(
                $searchPageLoader ?? $this->searchPageLoader,
                $this->suggestPageLoader,
                $this->blogSearchRoute,
            )
            : new SearchController(
                $searchPageLoader ?? $this->searchPageLoader,
                $this->suggestPageLoader,
                $this->blogSearchRoute,
                $redirectOnSingleHitFields,
            );
        $controller->setContainer($this->container);

        return $controller;
    }
}
