<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Controller;

use Contena\Core\Content\Flow\FlowException;
use Contena\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventCollection;
use Contena\Core\Framework\App\Event\CustomAppEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class TriggerFlowController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<AppFlowEventCollection> $appFlowEventRepository
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly EntityRepository $appFlowEventRepository,
    ) {
    }

    #[Route(path: '/api/_action/trigger-event/{eventName}', name: 'api.action.trigger_event', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['flow:dispatch']], methods: ['POST'])]
    public function trigger(string $eventName, Request $request, Context $context): JsonResponse
    {
        $data = $request->request->all();

        $this->checkAppEventIsExist($eventName, $context);

        $this->eventDispatcher->dispatch(new CustomAppEvent($eventName, $data, $context), $eventName);

        return new JsonResponse([
            'message' => \sprintf('The trigger `%s`successfully dispatched!', $eventName),
        ], Response::HTTP_OK);
    }

    private function checkAppEventIsExist(string $eventName, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('name', $eventName));
        $criteria->addFilter(new EqualsFilter('app.active', 1));

        $this->appFlowEventRepository->search($criteria, $context)->getEntities()->first() ?? throw FlowException::customTriggerByNameNotFound($eventName);
    }
}
