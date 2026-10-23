<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller\Stub;

use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Controller\ErrorController;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Tests\Unit\Frontend\Controller\FrontendControllerRecorder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class ErrorControllerStub extends ErrorController implements ResetInterface
{
    private ?FrontendControllerRecorder $recorder = null;

    public function recorder(): FrontendControllerRecorder
    {
        return $this->recorder ??= new FrontendControllerRecorder();
    }

    public function reset(): void
    {
        $this->recorder()->reset();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderFrontend(string $view, array $parameters = []): Response
    {
        return $this->recorder()->renderFrontend($view, $parameters);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $routeParameters
     */
    protected function forwardToRoute(string $routeName, array $attributes = [], array $routeParameters = []): Response
    {
        return $this->recorder()->forwardToRoute($routeName, $attributes, $routeParameters);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_FOUND): RedirectResponse
    {
        return $this->recorder()->redirectToRoute($route, $parameters, $status);
    }

    protected function hook(Hook $hook): void
    {
        $this->recorder()->hook($hook);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $snippet, array $parameters = []): string
    {
        return $snippet;
    }

    protected function addFlash(string $type, mixed $message): void
    {
        $this->recorder()->addFlash($type, $message);
    }

    protected function addCartErrors(Cart $cart, ?\Closure $filter = null): void
    {
    }

    /**
     * @param array<string|int, mixed> $parameters
     */
    protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->recorder()->generateUrl($route);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderView(string $view, array $parameters = []): string
    {
        return '<div>' . $view . '</div>';
    }
}
