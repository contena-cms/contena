<?php declare(strict_types=1);

namespace Contena\Administration\Controller;

use Contena\Administration\Dashboard\OrderAmountService;
use Contena\Administration\Framework\Routing\AdministrationRouteScope;
use Contena\Core\Framework\Context;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class DashboardController extends AbstractController
{
    public function __construct(private readonly OrderAmountService $orderAmountService)
    {
    }

    #[Route(path: '/api/_admin/dashboard/order-amount/{since}', name: 'api.admin.dashboard.order-amount', methods: ['GET'])]
    public function orderAmount(string $since, Request $request, Context $context): JsonResponse
    {
        $paid = $request->query->getBoolean('paid', true);

        $timezone = $request->query->get('timezone', 'UTC');

        $amount = $this->orderAmountService->load($context, $since, $paid, $timezone);

        return new JsonResponse(['statistic' => $amount]);
    }
}
