<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Contena\Core\Content\Category\Service\CategoryUrlGenerator;
use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\NavigationController;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Navigation\NavigationPage;
use Contena\Frontend\Page\Navigation\NavigationPageLoaderInterface;
use Contena\Frontend\Pagelet\Footer\FooterPagelet;
use Contena\Frontend\Pagelet\Footer\FooterPageletLoaderInterface;
use Contena\Frontend\Pagelet\Header\HeaderPagelet;
use Contena\Frontend\Pagelet\Header\HeaderPageletLoaderInterface;
use Contena\Frontend\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoaderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NavigationController::class)]
class NavigationControllerTest extends TestCase
{
    private NavigationPageLoaderInterface&Stub $pageLoader;

    private MenuOffcanvasPageletLoaderInterface&Stub $offCanvasLoader;

    private NavigationControllerTestClass $controller;

    private HeaderPageletLoaderInterface&Stub $headerLoader;

    private FooterPageletLoaderInterface&Stub $footerLoader;

    private AbstractCategoryUrlGenerator $categoryUrlGenerator;

    private SeoUrlPlaceholderHandlerInterface&Stub $seoUrlReplacer;

    private AbstractContentRoute&Stub $contentRoute;

    private AbstractContentRoute&Stub $headerContentRoute;

    private AbstractContentRoute&Stub $footerContentRoute;

    protected function setUp(): void
    {
        $this->pageLoader = static::createStub(NavigationPageLoaderInterface::class);
        $this->offCanvasLoader = static::createStub(MenuOffcanvasPageletLoaderInterface::class);
        $this->headerLoader = static::createStub(HeaderPageletLoaderInterface::class);
        $this->footerLoader = static::createStub(FooterPageletLoaderInterface::class);

        $this->seoUrlReplacer = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $this->seoUrlReplacer->method('replace')
            ->willReturnCallback(static fn (string $url) => $url);

        $entityRouteResolver = static::createStub(EntityRouteResolver::class);
        $entityRouteResolver->method('generateSeoUrlPlaceholder')
            ->willReturnCallback(static function (string $entityName, string $primaryKey) {
                return match ($entityName) {
                    BlogDefinition::ENTITY_NAME => '/blog/' . $primaryKey,
                    CategoryDefinition::ENTITY_NAME => '/navigation/' . $primaryKey,
                    'landing_page' => '/landingPage/' . $primaryKey,
                    default => '/' . $entityName,
                };
            });
        $this->categoryUrlGenerator = new CategoryUrlGenerator($entityRouteResolver);
        $this->contentRoute = static::createStub(AbstractContentRoute::class);
        $this->headerContentRoute = static::createStub(AbstractContentRoute::class);
        $this->footerContentRoute = static::createStub(AbstractContentRoute::class);
        $this->headerContentRoute->method('load')->willThrowException(new \RuntimeException('No header layout assigned'));
        $this->footerContentRoute->method('load')->willThrowException(new \RuntimeException('No footer layout assigned'));

        $this->controller = new NavigationControllerTestClass(
            $this->pageLoader,
            $this->offCanvasLoader,
            $this->headerLoader,
            $this->footerLoader,
            $this->categoryUrlGenerator,
            $this->seoUrlReplacer,
            $this->contentRoute,
            $this->headerContentRoute,
            $this->footerContentRoute,
        );
    }

    public function testHomeRendersFrontend(): void
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_PAGE);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')
            ->willReturn($navigationPage);

        $request = new Request();
        $context = Generator::generateChannelContext();
        $contentPage = new ContentPage('layout-id', [], 'home-layout', null);
        $this->contentRoute->method('load')->willReturn(new ContentRouteResponse($contentPage));

        $this->controller->home($request, $context);
        static::assertSame('@Frontend/frontend/page/content/page.html.twig', $this->controller->renderFrontendView);
        static::assertSame($contentPage, $this->controller->renderFrontendParameters['contentPage']);
    }

    public function testIndexRendersFrontend(): void
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_PAGE);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')
            ->willReturn($navigationPage);

        $request = new Request([
            'navigationId' => Uuid::randomHex(),
        ]);
        $context = Generator::generateChannelContext();
        $contentPage = new ContentPage('layout-id', [], 'category-layout', null);
        $this->contentRoute->method('load')->willReturn(new ContentRouteResponse($contentPage));

        $this->controller->index($context, $request);
        static::assertSame('@Frontend/frontend/page/content/page.html.twig', $this->controller->renderFrontendView);
        static::assertSame($contentPage, $this->controller->renderFrontendParameters['contentPage']);
    }

    public function testIndexRendersAssignedContentLayout(): void
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_PAGE);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')->willReturn($navigationPage);

        $request = new Request(['navigationId' => $category->getId()]);
        $context = Generator::generateChannelContext();
        $contentPage = new ContentPage('layout-id', [], 'category-layout', null);
        $this->contentRoute->method('load')->willReturnCallback(
            static function (string $path, Request $routeRequest, ChannelContext $routeContext) use ($category, $request, $context, $contentPage): ContentRouteResponse {
                static::assertSame('/category/' . $category->getId(), $path);
                static::assertSame($request, $routeRequest);
                static::assertSame($context, $routeContext);

                return new ContentRouteResponse($contentPage);
            },
        );

        $this->controller->index($context, $request);

        static::assertSame('@Frontend/frontend/page/content/page.html.twig', $this->controller->renderFrontendView);
        static::assertSame($navigationPage, $this->controller->renderFrontendParameters['page']);
        static::assertSame($contentPage, $this->controller->renderFrontendParameters['contentPage']);
        static::assertTrue($this->controller->renderFrontendParameters['isNewContentStructure']);
    }

    public function testContentRendersRawContentLayout(): void
    {
        $navigationPage = new NavigationPage();
        $this->pageLoader->method('load')->willReturn($navigationPage);

        $request = new Request();
        $context = Generator::generateChannelContext();
        $contentPage = new ContentPage('layout-id', [], 'raw-layout', null);
        $this->contentRoute->method('load')->willReturn(new ContentRouteResponse($contentPage));

        $this->controller->content('standalone', $request, $context);

        static::assertSame('@Frontend/frontend/page/content/raw.html.twig', $this->controller->renderFrontendView);
        static::assertSame($navigationPage, $this->controller->renderFrontendParameters['page']);
        static::assertSame($contentPage, $this->controller->renderFrontendParameters['contentPage']);
        static::assertTrue($this->controller->renderFrontendParameters['isNewContentStructure']);
    }

    public static function redirectOnLinkTypeDataProvider(): \Generator
    {
        $blogId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        yield 'blog link type' => [
            'data' => [
                'linkType' => CategoryDefinition::LINK_TYPE_BLOG,
                'internalLink' => $blogId,
                'externalLink' => 'This should not be used',
            ],
            'expectedUrl' => '/blog/' . $blogId,
        ];

        yield 'category link type' => [
            'data' => [
                'linkType' => CategoryDefinition::LINK_TYPE_CATEGORY,
                'internalLink' => $categoryId,
                'externalLink' => 'This should not be used',
            ],
            'expectedUrl' => '/navigation/' . $categoryId,
        ];

        yield 'external link type' => [
            'data' => [
                'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL,
                'internalLink' => 'This should not be used',
                'externalLink' => 'https://example.com',
            ],
            'expectedUrl' => 'https://example.com',
        ];
    }

    /**
     * @param array{linkType: string, internalLink: string, externalLink: string} $data
     */
    #[DataProvider('redirectOnLinkTypeDataProvider')]
    public function testIndexRedirectsOnLinkType(array $data, string $expectedUrl): void
    {
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType($data['linkType']);
        $category->setInternalLink($data['internalLink']);
        $category->setExternalLink($data['externalLink']);

        $category->setTranslated([
            'linkType' => $data['linkType'],
            'internalLink' => $data['internalLink'],
            'externalLink' => $data['externalLink'],
        ]);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')
            ->willReturn($navigationPage);

        $request = new Request(
            ['navigationId' => Uuid::randomHex()],
            [],
            [RequestTransformer::FRONTEND_URL => 'https://example.com'],
        );

        $context = Generator::generateChannelContext();

        $response = $this->controller->index($context, $request);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame($expectedUrl, $response->getTargetUrl());
    }

    public function testIndexDoesNotRedirectOnLinkTypeWithoutUrl(): void
    {
        $categoryId = Uuid::randomHex();
        $category = new CategoryEntity();
        $category->setId($categoryId);
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setLinkType(CategoryDefinition::LINK_TYPE_BLOG);
        $category->setInternalLink(null);

        $category->setTranslated([
            'linkType' => CategoryDefinition::LINK_TYPE_BLOG,
            'internalLink' => null,
        ]);

        $navigationPage = new NavigationPage();
        $navigationPage->setCategory($category);

        $this->pageLoader->method('load')
            ->willReturn($navigationPage);

        $request = new Request(
            ['navigationId' => Uuid::randomHex()],
            [],
            [RequestTransformer::FRONTEND_URL => 'https://example.com'],
        );

        $context = Generator::generateChannelContext();

        $this->expectExceptionObject(new CategoryNotFoundException($categoryId));

        $this->controller->index($context, $request);
    }

    public function testOffcanvasRendersFrontend(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();

        $response = $this->controller->offcanvas($request, $context);
        static::assertSame('noindex', $response->headers->get('x-robots-tag'));
        static::assertSame('@Frontend/frontend/layout/navigation/offcanvas/navigation-pagelet.html.twig', $this->controller->renderFrontendView);
    }

    public function testHeaderRendersFrontend(): void
    {
        $request = new Request(['headerParameters' => ['foo' => 'bar']]);
        $context = Generator::generateChannelContext();
        $headerPagelet = new HeaderPagelet(new Tree(null, []), new LanguageCollection());

        $headerLoader = $this->createMock(HeaderPageletLoaderInterface::class);
        $headerLoader->expects($this->once())->method('load')->with($request, $context)->willReturn($headerPagelet);

        $this->controller = $this->buildController(headerLoader: $headerLoader);
        $this->controller->header($request, $context);
        static::assertSame('@Frontend/frontend/layout/header.html.twig', $this->controller->renderFrontendView);
        static::assertSame(['foo' => 'bar'], $this->controller->renderFrontendParameters['headerParameters']);
    }

    public function testHeaderRendersNewContentStructure(): void
    {
        $request = new Request(['headerParameters' => ['isNewContentStructure' => true]]);
        $context = Generator::generateChannelContext();

        $this->controller->header($request, $context);

        static::assertSame('@Frontend/frontend/page/content/header.html.twig', $this->controller->renderFrontendView);
        static::assertNull($this->controller->renderFrontendParameters['contentPage']);
        static::assertTrue($this->controller->renderFrontendParameters['isNewContentStructure']);
    }

    public function testFooterRendersFrontend(): void
    {
        $request = new Request(['footerParameters' => ['foo' => 'bar']]);
        $context = Generator::generateChannelContext();
        $footerPagelet = new FooterPagelet(null, new CategoryCollection());

        $footerLoader = $this->createMock(FooterPageletLoaderInterface::class);
        $footerLoader->expects($this->once())->method('load')->with($request, $context)->willReturn($footerPagelet);

        $this->controller = $this->buildController(footerLoader: $footerLoader);
        $this->controller->footer($request, $context);
        static::assertSame('@Frontend/frontend/layout/footer.html.twig', $this->controller->renderFrontendView);
        static::assertSame(['foo' => 'bar'], $this->controller->renderFrontendParameters['footerParameters']);
    }

    public function testHeaderRendersAssignedContentLayout(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $contentPage = new ContentPage('header-layout', [], 'Header', null);
        $headerContentRoute = static::createStub(AbstractContentRoute::class);
        $headerContentRoute->method('load')->willReturn(new ContentRouteResponse($contentPage));

        $this->controller = $this->buildController(headerContentRoute: $headerContentRoute);
        $this->controller->header($request, $context);

        static::assertSame('@Frontend/frontend/page/content/header.html.twig', $this->controller->renderFrontendView);
        static::assertSame($contentPage, $this->controller->renderFrontendParameters['contentPage']);
    }

    public function testFooterRendersAssignedContentLayout(): void
    {
        $request = new Request();
        $context = Generator::generateChannelContext();
        $contentPage = new ContentPage('footer-layout', [], 'Footer', null);
        $footerContentRoute = static::createStub(AbstractContentRoute::class);
        $footerContentRoute->method('load')->willReturn(new ContentRouteResponse($contentPage));

        $this->controller = $this->buildController(footerContentRoute: $footerContentRoute);
        $this->controller->footer($request, $context);

        static::assertSame('@Frontend/frontend/layout/footer.html.twig', $this->controller->renderFrontendView);
        static::assertSame($contentPage, $this->controller->renderFrontendParameters['contentPage']);
    }

    private function buildController(
        ?HeaderPageletLoaderInterface $headerLoader = null,
        ?FooterPageletLoaderInterface $footerLoader = null,
        ?AbstractContentRoute $headerContentRoute = null,
        ?AbstractContentRoute $footerContentRoute = null,
    ): NavigationControllerTestClass {
        return new NavigationControllerTestClass(
            $this->pageLoader,
            $this->offCanvasLoader,
            $headerLoader ?? $this->headerLoader,
            $footerLoader ?? $this->footerLoader,
            $this->categoryUrlGenerator,
            $this->seoUrlReplacer,
            $this->contentRoute,
            $headerContentRoute ?? $this->headerContentRoute,
            $footerContentRoute ?? $this->footerContentRoute,
        );
    }
}

/**
 * @internal
 */
class NavigationControllerTestClass extends NavigationController
{
    use FrontendControllerMockTrait;
}
