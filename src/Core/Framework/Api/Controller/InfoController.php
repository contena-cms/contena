<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Controller;

use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Contena\Core\Content\Media\Upload\PresignedMediaUploadService;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Contena\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Contena\Core\Framework\Api\Route\RouteInfo;
use Contena\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Contena\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderSchemaGenerator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\MessageQueue\Stats\StatsService;
use Contena\Core\Framework\Migration\MigrationInfo;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Kernel;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpstan-import-type StyleOptionSchema from StyleOptionSpecification
 * @phpstan-import-type BindingSpecificationSchema from BindingSpecification
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class InfoController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionService $definitionService,
        private readonly ParameterBagInterface $params,
        private readonly MigrationInfo $migrationInfo,
        private readonly SystemConfigService $systemConfigService,
        private readonly ApiRouteInfoResolver $apiRouteInfoResolver,
        private readonly StatsService $messageStatsService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ContentSystemDataLoaderSchemaGenerator $dataLoaderSchemaGenerator,
        private readonly AbstractContentSystemElementTypeRegistry $elementTypeRegistry,
        private readonly AbstractContentSystemStyleOptionRegistry $styleOptionRegistry,
        private readonly RootSourceRegistry $rootSourceRegistry,
        private readonly AbstractContentSystemBindingSpecificationRegistry $bindingSpecificationRegistry,
        private readonly ?PresignedMediaUploadService $presignedMediaUploadService,
        private readonly MediaFileExtensionListProvider $mediaFileExtensionListProvider,
        private readonly BusinessEventCollector $businessEventCollector,
        private readonly FlowActionCollector $flowActionCollector,
    ) {
    }

    #[Route(
        path: '/api/_info/openapi3.json',
        name: 'api.info.openapi3',
        defaults: ['auth_required' => '%contena.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function info(Request $request): JsonResponse
    {
        $type = $request->query->getAlpha('type', DefinitionService::TYPE_JSON_API);

        $apiType = $this->definitionService->toApiType($type);
        if ($apiType === null) {
            throw ApiException::invalidApiType($type);
        }

        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT, DefinitionService::API, $apiType);

        return new JsonResponse($data);
    }

    #[Route(path: '/api/_info/message-stats.json', name: 'api.info.message-stats', defaults: [PlatformRequest::ATTRIBUTE_ACL => ['message_queue_stats:read']], methods: ['GET'])]
    public function messageStats(): JsonResponse
    {
        $response = new JsonResponse();
        $response->setEncodingOptions($response->getEncodingOptions() | \JSON_PRESERVE_ZERO_FRACTION);
        $response->setData($this->messageStatsService->getStats());

        return $response;
    }

    #[Route(
        path: '/api/_info/open-api-schema.json',
        name: 'api.info.open-api-schema',
        defaults: ['auth_required' => '%contena.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function openApiSchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT);

        return new JsonResponse($data);
    }

    #[Route(path: '/api/_info/entity-schema.json', name: 'api.info.entity-schema', methods: ['GET'])]
    public function entitySchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(EntitySchemaGenerator::FORMAT);

        return new JsonResponse($data);
    }

    #[Route(path: '/api/_info/content-system-data-loaders.json', name: 'api.info.content-system-data-loaders', methods: ['GET'])]
    public function contentSystemDataLoaders(): JsonResponse
    {
        return new JsonResponse($this->dataLoaderSchemaGenerator->getSchema());
    }

    #[Route(path: '/api/_info/content-system-entity-types.json', name: 'api.info.content-system-entity-types', methods: ['GET'])]
    public function contentSystemEntityTypes(): JsonResponse
    {
        return new JsonResponse(['entityTypes' => $this->rootSourceRegistry->entityRootSources()]);
    }

    #[Route(path: '/api/_info/events.json', name: 'api.info.business-events', methods: ['GET'])]
    public function businessEvents(Context $context): JsonResponse
    {
        return new JsonResponse($this->businessEventCollector->collect($context));
    }

    #[Route(path: '/api/_info/flow-actions.json', name: 'api.info.flow-actions', methods: ['GET'])]
    public function flowActions(Context $context): JsonResponse
    {
        return new JsonResponse($this->flowActionCollector->collect($context));
    }

    #[Route(
        path: '/api/_info/stoplightio.html',
        name: 'api.info.stoplightio',
        defaults: ['auth_required' => '%contena.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function stoplightIoInfoHtml(Request $request): Response
    {
        $nonce = $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);
        $apiType = $request->query->getAlpha('type', DefinitionService::TYPE_JSON);
        $response = $this->render(
            '@Framework/stoplightio.html.twig',
            [
                'schemaUrl' => 'api.info.openapi3',
                'cspNonce' => $nonce,
                'apiType' => $apiType,
            ]
        );

        $cspTemplate = trim($this->params->get('contena.security.csp_templates')['administration'] ?? '');
        if ($cspTemplate !== '') {
            $csp = str_replace(['%nonce%', "\n", "\r"], [$nonce, ' ', ' '], $cspTemplate);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    #[Route(path: '/api/_info/config', name: 'api.info.config', methods: ['GET'])]
    public function config(Context $context, Request $request): JsonResponse
    {
        $adminWorker = [
            'enableAdminWorker' => $this->params->get('contena.admin_worker.enable_admin_worker'),
            'enableNotificationWorker' => $this->params->get('contena.admin_worker.enable_notification_worker'),
            'transports' => $this->getAdminWorkerTransports(),
        ];

        $config = [
            'version' => $this->getContenaVersion(),
            'appUrl' => (string) EnvironmentHelper::getVariable('APP_URL'),
            'versionRevision' => $this->params->get('kernel.contena_version_revision'),
            'adminWorker' => $adminWorker,
            'bundles' => [],
            'settings' => [
                'enableUrlFeature' => $this->params->get('contena.media.enable_url_upload_feature'),
                'presignedUploadSupported' => $this->presignedMediaUploadService !== null
                    && $this->presignedMediaUploadService->isAvailable(),
                'firstMigrationDate' => $this->migrationInfo->getFirstMigrationDate(),
                'private_allowed_extensions' => $this->mediaFileExtensionListProvider->getAllowedExtensions(true, $context),
                'private_allowed_mime_types_by_extension' => $this->mediaFileExtensionListProvider->getMimeTypesByExtension(true, $context),
                'enableHtmlSanitizer' => $this->params->get('contena.html_sanitizer.enabled'),
                'disableExtensionManagement' => !$this->params->get('contena.deployment.runtime_extension_management'),
                'minSearchTermLength' => $this->systemConfigService->getInt('core.search.minSearchTermLength') ?: 2,
            ],
        ];

        $config = $this->eventDispatcher->dispatch(new AdminInfoConfigEvent($config))->getConfig();

        return new JsonResponse($config);
    }

    #[Route(path: '/api/_info/version', name: 'api.info.contena.version', methods: ['GET'])]
    #[Route(path: '/api/v1/_info/version', name: 'api.info.contena.version_old_version', methods: ['GET'])]
    public function infoContenaVersion(): JsonResponse
    {
        return new JsonResponse([
            'version' => $this->getContenaVersion(),
        ]);
    }

    #[Route(
        path: '/api/_info/routes',
        name: 'api.info.routes',
        defaults: ['auth_required' => '%contena.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function getRoutes(): JsonResponse
    {
        $endpoints = array_map(
            static fn (RouteInfo $endpoint) => ['path' => $endpoint->path, 'methods' => $endpoint->methods],
            $this->apiRouteInfoResolver->getApiRoutes(ApiRouteScope::ID)
        );

        return new JsonResponse(['endpoints' => $endpoints]);
    }

    #[Route(path: '/api/_info/content-system-element-types.json', name: 'api.info.content-system-element-types', methods: ['GET'])]
    public function getContentSystemElementTypes(): JsonResponse
    {
        $types = $this->elementTypeRegistry->all()
                |> array_values(...)
                |> (fn ($x) => array_map(fn (ContentSystemElementTypeSpecification $definition) => $this->elementTypeSchema($definition), $x));

        // styleOptions are universal (settable on every type), so they are folded in here as well as served standalone.
        // Cast to an object so an empty option set serializes as {} (the OpenAPI type: object), not [].
        return new JsonResponse(['types' => $types, 'styleOptions' => (object) $this->styleOptionSchemas()]);
    }

    #[Route(path: '/api/_info/content-system-style-options.json', name: 'api.info.content-system-style-options', methods: ['GET'])]
    public function getContentSystemStyleOptions(): JsonResponse
    {
        // Cast to an object so an empty option set serializes as {} (the OpenAPI type: object), not [].
        return new JsonResponse(['styleOptions' => (object) $this->styleOptionSchemas()]);
    }

    /**
     * bindingSpecifications are folded into each type entry (mirrors the styleOptions precedent), keyed by
     * source-qualified id. Cast to an object so a type with none serializes {} (the OpenAPI type: object), not [].
     *
     * @return array<string, mixed> the type's ElementTypeSchema plus the folded bindingSpecifications object
     */
    private function elementTypeSchema(ContentSystemElementTypeSpecification $def): array
    {
        $schema = $def->toSchema();
        $schema['bindingSpecifications'] = (object) $this->bindingSpecificationSchemasForType($def->name());

        return $schema;
    }

    /**
     * @return array<string, StyleOptionSchema> the registered style options keyed by their wire name
     */
    private function styleOptionSchemas(): array
    {
        return array_map(
            static fn (StyleOptionSpecification $specification) => $specification->toSchema(),
            $this->styleOptionRegistry->allResolved()
        );
    }

    /**
     * @return array<string, BindingSpecificationSchema> keyed by qualified id ("source:id"), filtered to the given type
     */
    private function bindingSpecificationSchemasForType(string $type): array
    {
        $schemas = [];

        foreach ($this->bindingSpecificationRegistry->byType($type) as $specification) {
            $schemas[$specification->qualifiedId()] = $specification->toSchema();
        }

        return $schemas;
    }

    /**
     * @return list<string>
     */
    private function getAdminWorkerTransports(): array
    {
        $transports = $this->params->get('contena.admin_worker.transports');
        if (!\is_array($transports)) {
            return [];
        }

        /** @var list<string> $transports */
        $transports = array_values($transports);

        return $transports;
    }

    private function getContenaVersion(): string
    {
        $contenaVersion = $this->params->get('kernel.contena_version');
        if ($contenaVersion === Kernel::CONTENA_FALLBACK_VERSION) {
            $contenaVersion = str_replace('.9999999-dev', '.9999999.9999999-dev', $contenaVersion);
        }

        return $contenaVersion;
    }
}
