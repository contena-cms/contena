<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cookie\Channel\AbstractCookieConsentLogRoute;
use Contena\Core\Content\Cookie\Channel\AbstractCookieRoute;
use Contena\Core\Content\Cookie\Channel\CookieRouteResponse;
use Contena\Core\Content\Cookie\Struct\CookieGroup;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\CookieController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

        $controller = new CookieControllerTestClass($cookieRoute, static::createStub(AbstractCookieConsentLogRoute::class));

        $response = $controller->offcanvas($request, $channelContext);

        static::assertSame('@Frontend/frontend/layout/cookie/cookie-configuration.html.twig', $controller->renderFrontendView);
        static::assertArrayHasKey('cookieGroups', $controller->renderFrontendParameters);
        static::assertNotEmpty($controller->renderFrontendParameters['cookieGroups']);
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

        $controller = new CookieControllerTestClass($cookieRoute, static::createStub(AbstractCookieConsentLogRoute::class));

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

        $controller = new CookieControllerTestClass($cookieRoute, static::createStub(AbstractCookieConsentLogRoute::class));

        $response = $controller->permission($request, $channelContext);

        static::assertSame('@Frontend/frontend/layout/cookie/cookie-permission.html.twig', $controller->renderFrontendView);
        static::assertArrayHasKey('cookieGroups', $controller->renderFrontendParameters);
        static::assertNotEmpty($controller->renderFrontendParameters['cookieGroups']);
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

        $controller = new CookieControllerTestClass($cookieRoute, static::createStub(AbstractCookieConsentLogRoute::class));

        $controller->offcanvas($request, $channelContext);

        // Verify the exact same collection is passed to the template (no transformation)
        $passedGroups = $controller->renderFrontendParameters['cookieGroups'];
        static::assertSame($cookieGroups, $passedGroups);
        static::assertSame($cookieGroup, $passedGroups->first());
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

        $controller = new CookieControllerTestClass($cookieRoute, static::createStub(AbstractCookieConsentLogRoute::class));

        // Override the json method to capture the data being passed to it
        $jsonData = null;
        $controller->jsonCallback = static function ($data) use (&$jsonData) {
            $jsonData = $data;

            return new JsonResponse($data);
        };

        $response = $controller->groups($request, $channelContext);

        static::assertNotNull($jsonData);
        static::assertArrayHasKey('elements', $jsonData);
        static::assertArrayHasKey('hash', $jsonData);
        static::assertSame('test-hash', $jsonData['hash']);
        static::assertSame($cookieGroups, $jsonData['elements']);
    }

    public function testLogConsentDelegatesToConsentLogRoute(): void
    {
        $request = new Request();
        $channelContext = Generator::generateChannelContext();

        $consentLogRoute = $this->createMock(AbstractCookieConsentLogRoute::class);
        $consentLogRoute->expects($this->once())
            ->method('log')
            ->with($request, $channelContext)
            ->willReturn(new NoContentResponse());

        $controller = new CookieControllerTestClass(static::createStub(AbstractCookieRoute::class), $consentLogRoute);

        $response = $controller->logConsent($request, $channelContext);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
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

        $controller = new CookieControllerTestClass($cookieRoute, static::createStub(AbstractCookieConsentLogRoute::class));

        $this->expectExceptionObject(new \RuntimeException('Cookie route failed'));

        $controller->groups($request, $channelContext);
    }
}

/**
 * @internal
 */
class CookieControllerTestClass extends CookieController
{
    use FrontendControllerMockTrait;

    /**
     * @var callable|null
     */
    public $jsonCallback;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $context
     */
    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        if ($this->jsonCallback !== null) {
            if (\is_object($data) && method_exists($data, 'all')) {
                $data = $data->all();
            }

            return ($this->jsonCallback)($data);
        }

        return new JsonResponse($data, $status, $headers);
    }
}
