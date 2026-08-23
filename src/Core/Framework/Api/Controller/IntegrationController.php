<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Controller;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Controller\Exception\PermissionDeniedException;
use Contena\Core\Framework\Api\Response\ResponseFactoryInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Integration\IntegrationCollection;
use Contena\Core\System\Integration\IntegrationDefinition;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class IntegrationController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<IntegrationCollection> $integrationRepository
     */
    public function __construct(private readonly EntityRepository $integrationRepository)
    {
    }

    #[Route(
        path: '/api/integration',
        name: 'api.integration.create',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['integration:create']],
        methods: [Request::METHOD_POST]
    )]
    public function upsertIntegration(
        ?string $integrationId,
        Request $request,
        Context $context,
        ResponseFactoryInterface $factory
    ): Response {
        $source = $context->getSource();

        $data = $request->request->all();

        // only an admin is allowed to set the admin field
        if ((!$source instanceof AdminApiSource)
            || (!$source->isAdmin()
            && isset($data['admin']))
        ) {
            throw new PermissionDeniedException();
        }

        if (!isset($data['id'])) {
            $data['id'] = null;
        }
        $data['id'] = $integrationId ?: $data['id'];

        $events = $context->scope(Context::SYSTEM_SCOPE, fn (Context $context): EntityWrittenContainerEvent => $this->integrationRepository->upsert([$data], $context));

        $eventIds = $events->getEventByEntityName(IntegrationDefinition::ENTITY_NAME)?->getIds() ?? [];
        $entityId = array_last($eventIds);
        \assert($entityId !== null);

        return $factory->createRedirectResponse($this->integrationRepository->getDefinition(), $entityId, $request, $context);
    }

    #[Route(
        path: '/api/integration/{integrationId}',
        name: 'api.integration.update',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['integration:update']],
        methods: [Request::METHOD_PATCH]
    )]
    public function updateIntegration(
        ?string $integrationId,
        Request $request,
        Context $context,
        ResponseFactoryInterface $factory
    ): Response {
        return $this->upsertIntegration($integrationId, $request, $context, $factory);
    }
}
