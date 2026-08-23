<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ContextSource;
use Contena\Core\Framework\Api\Controller\ApiController;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\Exception\InvalidRouteScopeException;
use Contena\Core\Framework\Routing\RouteScopeListener;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\WebProfilerBundle\Controller\ProfilerController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
class RouteScopeListenerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRouteScopeListenerFailsHardWithoutMainRequest(): void
    {
        $listener = static::getContainer()->get(RouteScopeListener::class);

        $request = $this->createRequest('/api', ApiRouteScope::ID, new AdminApiSource(null, null));

        $event = $this->createEvent($request);

        $this->expectExceptionObject(RoutingException::missingMainRequest());
        $listener->checkScope($event);
    }

    public function testRouteScopeListenerIgnoresSymfonyControllers(): void
    {
        $listener = static::getContainer()->get(RouteScopeListener::class);

        $request = $this->createRequest('/api', ApiRouteScope::ID, new AdminApiSource(null, null));

        $event = $this->createEvent($request);
        /** @var ProfilerController $profilerController */
        $profilerController = static::getContainer()->get('web_profiler.controller.profiler');
        $event->setController($profilerController->panelAction(...));

        $error = null;
        $message = '';

        try {
            $listener->checkScope($event);
        } catch (\Throwable $e) {
            $error = $e;
            $message = \sprintf('No error expected, got "%s" with: %s', $error->getMessage(), $error->getTraceAsString());
        }
        static::assertNull($error, $message);
    }

    public function testRouteScopeListenerFailsHardWithoutAnnotation(): void
    {
        $listener = static::getContainer()->get(RouteScopeListener::class);

        $request = $this->createRequest('/api', ApiRouteScope::ID, new AdminApiSource(null, null));
        $request->attributes->remove(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE);

        $event = $this->createEvent($request);

        $this->expectException(InvalidRouteScopeException::class);
        $listener->checkScope($event);
    }

    public function testRouteScopeListenerHandlesValidAdminRequests(): void
    {
        $stack = static::getContainer()->get(RequestStack::class);
        $listener = static::getContainer()->get(RouteScopeListener::class);

        $request = $this->createRequest('/api', ApiRouteScope::ID, new AdminApiSource(null, null));

        $stack->push($request);
        $event = $this->createEvent($request);
        $error = null;
        $message = '';

        try {
            $listener->checkScope($event);
        } catch (\Throwable $e) {
            $error = $e;
            $message = \sprintf('No error expected, got "%s" with: %s', $error->getMessage(), $error->getTraceAsString());
        }
        static::assertNull($error, $message);
    }

    private function createEvent(Request $request): ControllerEvent
    {
        $controller = static::getContainer()->get(ApiController::class);

        return new ControllerEvent(
            static::getContainer()->get('kernel'),
            $controller->clone(...),
            $request,
            HttpKernelInterface::SUB_REQUEST
        );
    }

    private function createRequest(string $route, string $scopeName, ContextSource $source): Request
    {
        $request = Request::create($route);

        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [$scopeName]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, Context::createDefaultContext($source));
        $request->attributes->set('_route', 'test.it');

        return $request;
    }
}
