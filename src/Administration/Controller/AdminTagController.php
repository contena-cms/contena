<?php declare(strict_types=1);

namespace Contena\Administration\Controller;

use Contena\Administration\Framework\Routing\AdministrationRouteScope;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Tag\Service\FilterTagIdsService;
use Contena\Core\System\Tag\TagDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class AdminTagController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly FilterTagIdsService $filterTagIdsService)
    {
    }

    #[Route(
        path: '/api/_admin/tag-filter-ids',
        name: 'api.admin.tag-filter-ids',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['tag:read'], PlatformRequest::ATTRIBUTE_ENTITY => TagDefinition::ENTITY_NAME],
        methods: [Request::METHOD_POST]
    )]
    public function filterIds(Request $request, Criteria $criteria, Context $context): JsonResponse
    {
        $filteredTagIdsStruct = $this->filterTagIdsService->filterIds($request, $criteria, $context);

        return new JsonResponse([
            'total' => $filteredTagIdsStruct->getTotal(),
            'ids' => $filteredTagIdsStruct->getIds(),
        ]);
    }
}
