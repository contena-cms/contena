<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller\fixtures;

use Contena\Frontend\Controller\FrontendController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class TestFrontendController extends FrontendController
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function testRenderFrontend(string $view, array $parameters = []): Response
    {
        return $this->renderFrontend($view, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function testTrans(string $snippet, array $parameters = []): string
    {
        return $this->trans($snippet, $parameters);
    }

    public function testCreateActionResponse(Request $request): Response
    {
        return $this->createActionResponse($request);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $routeParameters
     */
    public function testForwardToRoute(string $routeName, array $attributes = [], array $routeParameters = []): Response
    {
        return $this->forwardToRoute($routeName, $attributes, $routeParameters);
    }

    /**
     * @return array<string, mixed>
     */
    public function testDecodeParam(Request $request, string $param): array
    {
        return $this->decodeParam($request, $param);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function testRedirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_FOUND): RedirectResponse
    {
        return $this->redirectToRoute($route, $parameters, $status);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function testRenderView(string $view, array $parameters = []): string
    {
        return $this->renderView($view, $parameters);
    }
}
