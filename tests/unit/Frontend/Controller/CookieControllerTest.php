<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cookie\Channel\AbstractCookieRoute;
use Contena\Core\Content\Cookie\Channel\CookieRouteResponse;
use Contena\Core\Content\Cookie\Struct\CookieGroup;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\CookieController;
use Contena\Tests\Unit\Frontend\Controller\Stub\CookieControllerStub;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CookieController::class)]
class CookieControllerTest extends TestCase
{
    public function testOffcanvasCallsRouteAndRendersTemplate(): void
    {
        $request = new Request();
        $channelContext = Generator::generateChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->description = 'Test Group';

        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with($request, $channelContext)
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash', 'test-language-id'));

        $controller = new CookieControllerStub($cookieRoute);

        $response = $controller->offcanvas($request, $channelContext);

        static::assertSame('@Frontend/frontend/layout/cookie/cookie-configuration.html.twig', $controller->recorder()->renderFrontendView);
        static::assertArrayHasKey('cookieGroups', $controller->recorder()->renderFrontendParameters);
        static::assertNotCount(0, $controller->recorder()->renderFrontendParameters['cookieGroups']);
        static::assertSame('noindex,follow', $response->headers->get('x-robots-tag'));
    }

    public function testOffcanvasThrowsExceptionWhenCookieRouteFails(): void
    {
        $request = new Request();
        $channelContext = Generator::generateChannelContext();

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with($request, $channelContext)
            ->willThrowException(new \RuntimeException('Cookie route failed'));

        $controller = new CookieControllerStub($cookieRoute);

        $this->expectExceptionObject(new \RuntimeException('Cookie route failed'));

        $controller->offcanvas($request, $channelContext);
    }

    public function testPermissionCallsRouteAndRendersTemplate(): void
    {
        $request = new Request();
        $channelContext = Generator::generateChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->description = 'Test Group';

        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with($request, $channelContext)
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash', 'test-language-id'));

        $controller = new CookieControllerStub($cookieRoute);

        $response = $controller->permission($request, $channelContext);

        static::assertSame('@Frontend/frontend/layout/cookie/cookie-permission.html.twig', $controller->recorder()->renderFrontendView);
        static::assertArrayHasKey('cookieGroups', $controller->recorder()->renderFrontendParameters);
        static::assertNotCount(0, $controller->recorder()->renderFrontendParameters['cookieGroups']);
        static::assertSame('noindex,follow', $response->headers->get('x-robots-tag'));
    }

    public function testOffcanvasPassesCookieGroupsDirectlyToTemplate(): void
    {
        $request = new Request();
        $channelContext = Generator::generateChannelContext();

        // Create a cookie group to verify it gets passed through unchanged
        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->description = 'Test description';
        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $cookieRoute->method('getCookieGroups')
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash', 'test-language-id'));

        $controller = new CookieControllerStub($cookieRoute);

        $controller->offcanvas($request, $channelContext);

        // Verify the exact same collection is passed to the template (no transformation)
        $passedGroups = $controller->recorder()->renderFrontendParameters['cookieGroups'];
        static::assertSame($cookieGroups, $passedGroups);
        static::assertSame($cookieGroup, $passedGroups->first());
    }

    public function testCookieConsentOffcanvasWithDefaults(): void
    {
        $request = new Request();
        $channelContext = Generator::generateChannelContext();

        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $controller = new CookieControllerStub($cookieRoute);

        $controller->cookieConsentOffcanvas($request, $channelContext);

        static::assertSame('@Frontend/frontend/layout/cookie/cookie-consent-offcanvas.html.twig', $controller->recorder()->renderFrontendView);
        static::assertArrayHasKey('featureName', $controller->recorder()->renderFrontendParameters);
        static::assertArrayHasKey('cookieName', $controller->recorder()->renderFrontendParameters);
        static::assertSame('wishlist', $controller->recorder()->renderFrontendParameters['featureName']);
        static::assertSame('wishlist-enabled', $controller->recorder()->renderFrontendParameters['cookieName']);
    }

    public function testCookieConsentOffcanvasWithCustomParameters(): void
    {
        $request = new Request(['featureName' => 'customFeature', 'cookieName' => 'custom-cookie']);
        $channelContext = Generator::generateChannelContext();

        $cookieRoute = static::createStub(AbstractCookieRoute::class);
        $controller = new CookieControllerStub($cookieRoute);

        $controller->cookieConsentOffcanvas($request, $channelContext);

        static::assertSame('@Frontend/frontend/layout/cookie/cookie-consent-offcanvas.html.twig', $controller->recorder()->renderFrontendView);
        static::assertArrayHasKey('featureName', $controller->recorder()->renderFrontendParameters);
        static::assertArrayHasKey('cookieName', $controller->recorder()->renderFrontendParameters);
        static::assertSame('customFeature', $controller->recorder()->renderFrontendParameters['featureName']);
        static::assertSame('custom-cookie', $controller->recorder()->renderFrontendParameters['cookieName']);
    }

    public function testGroupsCallsCookieRouteAndReturnsData(): void
    {
        $request = new Request();
        $channelContext = Generator::generateChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->description = 'Test Group';
        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with($request, $channelContext)
            ->willReturn(new CookieRouteResponse($cookieGroups, 'test-hash', 'test-language-id'));

        $controller = new CookieControllerStub($cookieRoute);

        // Override the json method to capture the data being passed to it
        $jsonData = null;
        $controller->jsonCallback = static function ($data) use (&$jsonData) {
            $jsonData = $data;

            return new JsonResponse($data);
        };

        $controller->groups($request, $channelContext);

        static::assertNotNull($jsonData);
        static::assertArrayHasKey('elements', $jsonData);
        static::assertArrayHasKey('hash', $jsonData);
        static::assertSame('test-hash', $jsonData['hash']);
        static::assertSame($cookieGroups, $jsonData['elements']);
    }

    public function testGroupsThrowsExceptionWhenCookieRouteFails(): void
    {
        $request = new Request();
        $channelContext = Generator::generateChannelContext();

        $cookieRoute = $this->createMock(AbstractCookieRoute::class);
        $cookieRoute->expects($this->once())
            ->method('getCookieGroups')
            ->with($request, $channelContext)
            ->willThrowException(new \RuntimeException('Cookie route failed'));

        $controller = new CookieControllerStub($cookieRoute);

        $this->expectExceptionObject(new \RuntimeException('Cookie route failed'));

        $controller->groups($request, $channelContext);
    }
}
