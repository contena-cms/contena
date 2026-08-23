<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Robots\RobotsPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * we use both API and Frontend route scope here, so that the robots.txt can be accessed
 * via all channel domains (+ path routing) + all top level domains without any channel domain
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Frontend\Controller\RobotsControllerTest
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID, FrontendRouteScope::ID], 'auth_required' => false])]
class RobotsController extends FrontendController
{
    public function __construct(private readonly RobotsPageLoader $robotsPageLoader)
    {
    }

    #[Route(
        path: '/robots.txt',
        name: 'frontend.robots.txt',
        defaults: [
            '_format' => 'txt',
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function robotsTxt(Request $request, Context $context): Response
    {
        $page = $this->robotsPageLoader->load($request, $context);

        $response = $this->render('@Frontend/frontend/page/robots/robots.txt.twig', ['page' => $page]);
        $response->headers->set('content-type', 'text/plain; charset=utf-8');

        return $response;
    }
}
