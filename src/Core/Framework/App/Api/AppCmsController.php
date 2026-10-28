<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Api;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AppCmsController extends AbstractController
{
    /**
     * @param EntityRepository<AppCmsBlockCollection> $cmsBlockRepository
     */
    public function __construct(private readonly EntityRepository $cmsBlockRepository)
    {
    }

    #[Route(path: 'api/app-system/cms/blocks', name: 'api.app_system.cms.blocks', methods: ['GET'])]
    public function getBlocks(Context $context): Response
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('app.active', true))
            ->addSorting(new FieldSorting('name'));
        $blocks = $this->cmsBlockRepository->search($criteria, $context)->getEntities();

        return new JsonResponse(['blocks' => $this->formatBlocks($blocks)]);
    }

}
