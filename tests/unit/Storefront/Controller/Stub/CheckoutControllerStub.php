<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Controller\Stub;

use Contena\Core\Framework\Script\Execution\Hook;
use Contena\Core\PlatformRequest;
use Contena\Storefront\Controller\CheckoutController;
use Contena\Storefront\Framework\Routing\StorefrontRouteScope;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class CheckoutControllerStub extends CheckoutController
{


    public function reset(): void
    {
        $this->recorder()->reset();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        return $this->recorder()->renderStorefront($view, $parameters);
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


    /**
     * @param array<string|int, mixed> $parameters
     */
    protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->recorder()->generateUrl($route);
    }
}
