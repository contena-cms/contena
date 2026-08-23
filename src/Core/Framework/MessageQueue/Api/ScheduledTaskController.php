<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\Api;

use Contena\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TaskScheduler;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ScheduledTaskController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly TaskScheduler $taskScheduler)
    {
    }

    #[Route(path: '/api/_action/scheduled-task/run', name: 'api.action.scheduled-task.run', methods: ['POST'])]
    public function runScheduledTasks(): JsonResponse
    {
        $this->taskScheduler->queueScheduledTasks();

        return new JsonResponse(['message' => 'Success']);
    }

    #[Route(path: '/api/_action/scheduled-task/min-run-interval', name: 'api.action.scheduled-task.min-run-interval', methods: ['GET'])]
    public function getMinRunInterval(): JsonResponse
    {
        return new JsonResponse(['minRunInterval' => $this->taskScheduler->getMinRunInterval()]);
    }
}
