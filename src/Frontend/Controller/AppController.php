<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\App\Api\AppJWTGenerateRoute;
use Contena\Core\Framework\App\AppException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
final readonly class AppController
{
    public function __construct(private AppJWTGenerateRoute $appJWTGenerateRoute)
    {
    }

    #[Route(
        path: '/app-system/{name}/generate-token',
        name: 'frontend.app-system.generate-token',
        defaults: [PlatformRequest::ATTRIBUTE_NO_STORE => true],
        methods: [Request::METHOD_POST]
    )]
    public function generateToken(string $name, ChannelContext $context): Response
    {
        try {
            return $this->appJWTGenerateRoute->generate($name, $context);
        } catch (AppException $e) {
            return new JsonResponse(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }
}
