<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\LandingPageController;
use Contena\Frontend\Page\LandingPage\LandingPage;
use Contena\Frontend\Page\LandingPage\LandingPageLoader;
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
        $contentPage = new ContentPage('layout-id', [], 'landing-page-layout', null);

        $contentRoute = $this->createMock(AbstractContentRoute::class);
        $contentRoute->expects($this->once())
            ->method('load')
            ->with('/landing-page/' . $landingPageId, $request, $context)
            ->willReturn(new ContentRouteResponse($contentPage));

        $controller = new LandingPageControllerTestClass($pageLoader, $contentRoute);
        $controller->index($context, $request);

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
