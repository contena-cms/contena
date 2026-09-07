<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Framework\ContentSystem\LayoutReference;
use Contena\Core\Framework\ContentSystem\Output\RenderResult;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\LandingPageController;
use Contena\Frontend\Page\LandingPage\LandingPage;
use Contena\Frontend\Page\LandingPage\LandingPageLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(LandingPageController::class)]
class LandingPageControllerTest extends TestCase
{
    public function testIndexRendersAssignedContentLayout(): void
    {
        $landingPageId = Uuid::randomHex();
        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);

        $page = new LandingPage();
        $page->setLandingPage($landingPage);

        $pageLoader = static::createStub(LandingPageLoader::class);
        $pageLoader->method('load')->willReturn($page);

        $request = new Request();
        $context = Generator::generateChannelContext();
        $renderResult = new RenderResult([], LayoutReference::create('layout-id', 'landing-page-layout', null), null);
        $contentPage = ContentPage::fromRenderResult($renderResult);
        $controller = new LandingPageControllerTestClass($pageLoader);
        $controller->contentPage = $contentPage;
        $controller->index($context, $request);

        static::assertSame('/landing-page/' . $landingPageId, $controller->loadedContentPath);
        static::assertSame('@Frontend/frontend/page/landing-page/index.html.twig', $controller->renderFrontendView);
        static::assertSame($page, $controller->renderFrontendParameters['page']);
        static::assertSame($contentPage, $controller->renderFrontendParameters['contentPage']);
        static::assertTrue($controller->renderFrontendParameters['isNewContentStructure']);
    }
}

/**
 * @internal
 */
class LandingPageControllerTestClass extends LandingPageController
{
    use FrontendControllerMockTrait;
}
