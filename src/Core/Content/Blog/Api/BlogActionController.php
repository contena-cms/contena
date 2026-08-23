<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Api;

use Contena\Core\Content\Blog\BlogTypeRegistry;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class BlogActionController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly BlogTypeRegistry $blogTypeRegistry)
    {
    }

    #[Route(path: '/api/_action/blog/types', name: 'api.action.blog.types', methods: ['GET'])]
    public function getBlogTypes(): JsonResponse
    {
        return new JsonResponse($this->blogTypeRegistry->getTypes());
    }
}
