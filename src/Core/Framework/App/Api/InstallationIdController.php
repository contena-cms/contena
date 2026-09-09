<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Api;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\InstallationIdChangeSuggestedException;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\InstallationIdChangeResolver\Resolver;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class InstallationIdController extends AbstractController
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly Resolver $installationIdChangeResolver,
        private readonly InstallationIdProvider $installationIdProvider,
        private readonly EntityRepository $appRepository,
    ) {
    }

    #[Route(path: 'api/app-system/installation-id/change-strategies', name: 'api.app_system.installation_id.change_strategies', methods: ['GET'])]
    public function getAvailableStrategies(): JsonResponse
    {
        return new JsonResponse($this->installationIdChangeResolver->getAvailableStrategies());
    }

    #[Route(path: 'api/app-system/installation-id/change', name: 'api.app_system.installation_id.change', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system:app:change']], methods: ['POST'])]
    public function changeInstallationId(Request $request, Context $context): Response
    {
        $strategy = RequestParamHelper::get($request, 'strategy');

        if (!$strategy) {
            throw AppException::missingRequestParameter('strategy');
        }

        $this->installationIdChangeResolver->resolve($strategy, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(path: 'api/app-system/installation-id/check', name: 'api.app_system.installation_id.check', methods: ['POST'])]
    public function checkInstallationId(Context $context): Response
    {
        try {
            $this->installationIdProvider->getInstallationId();
        } catch (InstallationIdChangeSuggestedException $e) {
            return new JsonResponse([
                'apps' => $this->appsRegisteredAtAppServers($context),
                'fingerprints' => $e->comparisonResult,
            ]);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return list<string>
     */
    private function appsRegisteredAtAppServers(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotEqualsFilter('appSecret', null));

        $apps = $this->appRepository
            ->search($criteria, $context)
            ->getEntities()
            ->map(static function (AppEntity $app) {
                return $app->getTranslation('label');
            });

        return array_values($apps);
    }
}
