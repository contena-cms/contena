<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Api;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\System\SystemConfig\SystemConfigException;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class SystemConfigController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly SystemConfigService $systemConfig,
        private readonly SystemConfigValidator $systemConfigValidator
    ) {
    }

    #[Route(
        path: '/api/_action/system-config/check',
        name: 'api.action.core.system-config.check',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system_config:read']],
        methods: [Request::METHOD_GET]
    )]
    public function checkConfiguration(Request $request, Context $context): JsonResponse
    {
        $domain = (string) $request->query->get('domain');

        if ($domain === '') {
            return new JsonResponse(false);
        }

        return new JsonResponse($this->configurationService->checkConfiguration($domain, $context));
    }

    #[Route(
        path: '/api/_action/system-config/schema',
        name: 'api.action.core.system-config',
        methods: [Request::METHOD_GET]
    )]
    public function getConfiguration(Request $request, Context $context): JsonResponse
    {
        $domain = (string) $request->query->get('domain');

        if ($domain === '') {
            throw SystemConfigException::missingRequestParameter('domain');
        }

        return new JsonResponse($this->configurationService->getConfiguration($domain, $context));
    }

    #[Route(
        path: '/api/_action/system-config',
        name: 'api.action.core.system-config.value',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system_config:read']],
        methods: [Request::METHOD_GET]
    )]
    public function getConfigurationValues(Request $request, Context $context): JsonResponse
    {
        $domain = (string) $request->query->get('domain');
        if ($domain === '') {
            throw SystemConfigException::missingRequestParameter('domain');
        }

        $channelId = $request->query->get('channelId');
        if (!\is_string($channelId)) {
            $channelId = null;
        }

        $values = $this->systemConfig->getDomain($domain, $channelId, $request->query->getBoolean('inherit'), $context);
        if ($values === []) {
            $json = '{}';
        } else {
            $json = json_encode($values, \JSON_PRESERVE_ZERO_FRACTION);
        }

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route(
        path: '/api/_action/system-config',
        name: 'api.action.core.save.system-config',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system_config:update', 'system_config:create', 'system_config:delete']],
        methods: [Request::METHOD_POST]
    )]
    public function saveConfiguration(Request $request, Context $context): JsonResponse
    {
        $channelId = $request->query->get('channelId');
        if (!\is_string($channelId)) {
            $channelId = null;
        }

        $kvs = $request->request->all();

        $this->systemConfig->setMultiple($kvs, $channelId, $request->query->getBoolean('silent', true), $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/system-config/batch',
        name: 'api.action.core.save.system-config.batch',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system_config:update', 'system_config:create', 'system_config:delete']],
        methods: [Request::METHOD_POST]
    )]
    public function batchSaveConfiguration(Request $request, Context $context): JsonResponse
    {
        $this->systemConfigValidator->validate($request->request->all(), $context);

        /**
         * @var string $channelId
         * @var array<string, mixed> $kvs
         */
        foreach ($request->request->all() as $channelId => $kvs) {
            if ($channelId === 'null') {
                $channelId = null;
            }

            $this->systemConfig->setMultiple($kvs, $channelId, $request->query->getBoolean('silent', true), $context);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
