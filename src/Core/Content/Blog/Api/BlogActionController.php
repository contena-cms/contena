<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Api;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class BlogActionController extends AbstractController
{

    #[Route(path: '/api/_action/blog/{blogId}/combinations', name: 'api.action.blog.combinations', methods: ['GET'])]
    public function getCombinations(string $blogId, Context $context): JsonResponse
    {
        return new JsonResponse(
            $this->combinationLoader->load($blogId, $context)
        );
    }

    #[Route(path: '/api/_action/blog/types', name: 'api.action.blog.types', methods: ['GET'])]
    public function getBlogTypes(): JsonResponse
    {
        return new JsonResponse($this->blogTypeRegistry->getTypes());
    }
}
