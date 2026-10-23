<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use Contena\Core\Framework\Script\Execution\Hook;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records what a frontend controller under test asked its base class to do: the view it rendered, the
 * route it forwarded or redirected to, the hook it fired and the flashes it added. A controller stub
 * delegates the protected FrontendController seams here instead of mixing the recording state into the
 * controller through a trait.
 *
 * @internal
 */
final class FrontendControllerRecorder
{
    public string $renderFrontendView;

    /**
     * @var array<string, mixed>
     */
    public array $renderFrontendParameters;

    public Hook $calledHook;

    public string $forwardToRoute;

    /**
     * @var array<string, mixed>
     */
    public array $forwardToRouteAttributes;

    /**
     * @var array<string, mixed>
     */
    public array $forwardToRouteParameters;

    /**
     * @var array<string, array<int, array{parameters: array<string, mixed>, status: int}>>
     */
    public array $redirected = [];

    /**
     * @var array<string, array<int, mixed>>
     */
    public array $flashBag = [];

    public function reset(): void
    {
        $this->flashBag = [];
        $this->redirected = [];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function renderFrontend(string $view, array $parameters): Response
    {
        $this->renderFrontendView = $view;
        $this->renderFrontendParameters = $parameters;

        return new Response();
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $routeParameters
     */
    public function forwardToRoute(string $routeName, array $attributes, array $routeParameters): Response
    {
        $this->forwardToRoute = $routeName;
        $this->forwardToRouteAttributes = $attributes;
        $this->forwardToRouteParameters = $routeParameters;

        return new Response('forward to ' . $routeName);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function redirectToRoute(string $route, array $parameters, int $status): RedirectResponse
    {
        $this->redirected[$route][] = [
            'parameters' => $parameters,
            'status' => $status,
        ];

        return new RedirectResponse($route, $status);
    }

    public function hook(Hook $hook): void
    {
        $this->calledHook = $hook;
    }

    public function addFlash(string $type, mixed $message): void
    {
        $this->flashBag[$type][] = $message;
    }

    public function generateUrl(string $route): string
    {
        return 'url:' . $route;
    }
}
