<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\Api;

use Contena\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Contena\Core\Content\Seo\Exception\NoEntitiesForPreviewException;
use Contena\Core\Content\Seo\SeoException;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Contena\Core\Content\Seo\SeoUrlGenerator;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Seo\Validation\SeoUrlDataValidationFactoryInterface;
use Contena\Core\Content\Seo\Validation\SeoUrlValidationFactory;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class SeoActionController extends AbstractController
{
    /**
     * @internal
     *
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly SeoUrlGenerator $seoUrlGenerator,
        private readonly SeoUrlPersister $seoUrlPersister,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry,
        private readonly SeoUrlDataValidationFactoryInterface $seoUrlValidator,
        private readonly DataValidator $validator,
        private readonly EntityRepository $channelRepository,
        private readonly RequestCriteriaBuilder $requestCriteriaBuilder,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly EntityRouteResolver $entityRouteResolver,
    ) {
    }

    #[Route(
        path: '/api/_action/seo-url-template/validate',
        name: 'api.seo-url-template.validate',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url_template:update']],
        methods: [Request::METHOD_POST]
    )]
    public function validate(Request $request, Context $context): JsonResponse
    {
        $context->setConsiderInheritance(true);

        $this->validateSeoUrlTemplate($request);
        $seoUrlTemplate = $request->request->all();

        // just call it to validate the template
        $this->getPreview($seoUrlTemplate, $context);

        return new JsonResponse();
    }

    #[Route(
        path: '/api/_action/seo-url-template/preview',
        name: 'api.seo-url-template.preview',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url_template:update']],
        methods: [Request::METHOD_POST]
    )]
    public function preview(Request $request, Context $context): Response
    {
        $this->validateSeoUrlTemplate($request);
        $seoUrlTemplate = $request->request->all();

        $previewCriteria = new Criteria();
        if (\array_key_exists('criteria', $seoUrlTemplate) && \is_string($seoUrlTemplate['entityName']) && \is_array($seoUrlTemplate['criteria'])) {
            $definition = $this->definitionInstanceRegistry->getByEntityName($seoUrlTemplate['entityName']);

            $previewCriteria = $this->requestCriteriaBuilder->handleRequest(
                Request::create('', Request::METHOD_POST, $seoUrlTemplate['criteria']),
                $previewCriteria,
                $definition,
                $context
            );
            unset($seoUrlTemplate['criteria']);
        }

        try {
            $preview = $this->getPreview($seoUrlTemplate, $context, $previewCriteria);
        } catch (NoEntitiesForPreviewException) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse($preview);
    }

    #[Route(
        path: '/api/_action/seo-url-template/context',
        name: 'api.seo-url-template.context',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url_template:read']],
        methods: [Request::METHOD_POST]
    )]
    public function getSeoUrlContext(RequestDataBag $data, Context $context): JsonResponse
    {
        $routeName = $data->get('routeName');
        $fk = $data->get('foreignKey');
        $seoUrlRoute = $this->getEntitySeoUrlRoute($routeName);

        if ($seoUrlRoute === null) {
            throw SeoException::seoUrlRouteNotFound((string) $routeName);
        }

        $route = new ConfiguredEntitySeoUrlRoute($seoUrlRoute);
        $entity = $this->loadPreviewEntity($route->getConfig(), $fk, $context);

        if ($entity === null) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $mapping = $route->getMapping($entity, null);

        return new JsonResponse($mapping->getSeoPathInfoContext());
    }

    #[Route(
        path: '/api/_action/seo-url/canonical',
        name: 'api.seo-url.canonical',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url:update']],
        methods: [Request::METHOD_PATCH]
    )]
    public function updateCanonicalUrl(RequestDataBag $seoUrl, Context $context): Response
    {
        if (!$seoUrl->has('routeName')) {
            throw SeoException::routeNameParameterIsMissing();
        }

        $routeName = $seoUrl->get('routeName') ?? '';
        $seoUrlRoute = $this->getEntitySeoUrlRoute($routeName);
        if (!$seoUrlRoute) {
            throw SeoException::seoUrlRouteNotFound($seoUrl->get('routeName'));
        }

        $validation = $this->seoUrlValidator->buildValidation($context, $seoUrlRoute->getConfig());

        $seoUrlData = $seoUrl->all();
        $this->validator->validate($seoUrlData, $validation);
        $seoUrlData['isModified'] ??= true;

        $channelId = $seoUrlData['channelId'] ?? null;

        if ($channelId === null) {
            throw SeoException::channelIdParameterIsMissing();
        }

        $channel = $this->channelRepository->search(new Criteria([$channelId]), $context)->getEntities()->first();

        if ($channel === null) {
            throw SeoException::channelNotFound($channelId);
        }

        $seoUrlData = [
            ...$seoUrlData,
            ...$this->entityRouteResolver->getSeoUrlRouteNameAndPathInfo(
                $seoUrlRoute->getConfig()->getDefinition()->getEntityName(),
                $seoUrlData['routeName'],
                $seoUrlData['foreignKey'],
                $channel->getTypeId()
            ),
        ];

        $this->seoUrlPersister->forceUpdateSeoUrls(
            $context,
            $seoUrlData['routeName'],
            [$seoUrlData['foreignKey']],
            [$seoUrlData],
            $channel,
        );

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/seo-url/create-custom-url',
        name: 'api.seo-url.create',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url:create']],
        methods: [Request::METHOD_POST]
    )]
    public function createCustomSeoUrls(RequestDataBag $dataBag, Context $context): Response
    {
        /** @var ParameterBag $dataBag */
        $dataBag = $dataBag->get('urls');
        $urls = $dataBag->all();

        /** @var SeoUrlValidationFactory $validatorBuilder */
        $validatorBuilder = $this->seoUrlValidator;

        $validation = $validatorBuilder->buildValidation($context, null);
        $channels = new ChannelCollection();

        $channelIds = array_column($urls, 'channelId');

        if ($channelIds !== []) {
            $channels = $this->channelRepository->search(new Criteria($channelIds), $context)->getEntities();
        }

        $writeData = [];

        foreach ($urls as $seoUrlData) {
            $id = $seoUrlData['channelId'] ?? null;

            $this->validator->validate($seoUrlData, $validation);
            $seoUrlData['isModified'] ??= true;

            $writeData[$id][] = $seoUrlData;
        }

        foreach ($writeData as $channelId => $writeRows) {
            if ($channelId === '') {
                throw SeoException::channelIdParameterIsMissing();
            }

            $channelEntity = $channels->get($channelId);

            if ($channelEntity === null) {
                throw SeoException::channelNotFound((string) $channelId);
            }

            $this->seoUrlPersister->forceUpdateSeoUrls(
                $context,
                $writeRows[0]['routeName'],
                array_column($writeRows, 'foreignKey'),
                $writeRows,
                $channelEntity,
            );
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/seo-url-template/default/{routeName}',
        name: 'api.seo-url-template.default',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['seo_url_template:read']],
        methods: [Request::METHOD_GET]
    )]
    public function getDefaultSeoTemplate(string $routeName, Context $context): JsonResponse
    {
        $seoUrlRoute = $this->getEntitySeoUrlRoute($routeName);

        if (!$seoUrlRoute) {
            throw SeoException::seoUrlRouteNotFound($routeName);
        }

        return new JsonResponse(['defaultTemplate' => $seoUrlRoute->getConfig()->getTemplate()]);
    }

    private function getEntitySeoUrlRoute(string $routeName): ?EntitySeoUrlRouteInterface
    {
        return $this->seoUrlRouteRegistry->findByRouteName($routeName)
            ?? $this->entityRouteResolver->findEntitySeoUrlRoute($routeName);
    }

    private function validateSeoUrlTemplate(Request $request): void
    {
        if (!$request->request->has('template')) {
            throw SeoException::templateParameterIsMissing();
        }

        if (!$request->request->has('channelId')) {
            throw SeoException::channelIdParameterIsMissing();
        }

        if (!$request->request->has('routeName')) {
            throw SeoException::routeNameParameterIsMissing();
        }

        if (!$request->request->has('entityName')) {
            throw SeoException::entityNameParameterIsMissing();
        }
    }

    /**
     * @param array<string, mixed> $seoUrlTemplate
     *
     * @return array<SeoUrlEntity>
     */
    private function getPreview(array $seoUrlTemplate, Context $context, ?Criteria $previewCriteria = null): array
    {
        $routeName = $seoUrlTemplate['routeName'];
        $seoUrlRoute = $this->getEntitySeoUrlRoute($routeName);

        if ($seoUrlRoute === null) {
            throw SeoException::seoUrlRouteNotFound($routeName);
        }

        $route = new ConfiguredEntitySeoUrlRoute($seoUrlRoute);
        $route->getConfig()->setSkipInvalid(false);
        $repository = $this->getRepository($route->getConfig());

        $channel = $this->resolveChannel($seoUrlTemplate, $context);
        if ($channel === null) {
            throw SeoException::channelIdParameterIsMissing();
        }

        $template = $seoUrlTemplate['template'] ?? '';

        $criteria = $previewCriteria ?? new Criteria();
        $criteria->setLimit(10);
        $route->prepareCriteria($criteria, $channel);

        $ids = $repository->searchIds($criteria, $context)->getIds();
        if ($ids === []) {
            throw SeoException::noEntitiesForPreview($repository->getDefinition()->getEntityName(), $routeName);
        }

        $result = $this->seoUrlGenerator->generate($ids, $template, $route, $context, $channel);
        $result = \is_array($result) ? $result : iterator_to_array($result);

        if ($channel->getTypeId() !== Defaults::CHANNEL_TYPE_API) {
            return $result;
        }

        $externalFrontendDomain = $this->getExternalFrontendDomain($channel, $context);
        if ($externalFrontendDomain === null) {
            return $result;
        }

        foreach ($result as $seoUrl) {
            $seoUrl->setSeoPathInfo(rtrim($externalFrontendDomain, '/') . '/' . ltrim($seoUrl->getSeoPathInfo(), '/'));
        }

        return $result;
    }

    private function getExternalFrontendDomain(ChannelEntity $channel, Context $context): ?string
    {
        return $channel->getDomains()
            ?->firstWhere(static fn (ChannelDomainEntity $domain): bool => $domain->getIsExternalFrontend()
                && $domain->getLanguageId() === $context->getLanguageId())
            ?->getUrl();
    }

    private function loadPreviewEntity(SeoUrlRouteConfig $config, ?string $foreignKey, Context $context): ?Entity
    {
        $criteria = $foreignKey !== null && $foreignKey !== '' ? new Criteria([$foreignKey]) : new Criteria();
        $criteria->setLimit(1);

        return $this->getRepository($config)
            ->search($criteria, $context)
            ->getEntities()
            ->first();
    }

    /**
     * @param array<string, mixed> $seoUrlTemplate
     */
    private function resolveChannel(array $seoUrlTemplate, Context $context): ?ChannelEntity
    {
        if (isset($seoUrlTemplate['channelId']) && \is_string($seoUrlTemplate['channelId'])) {
            $criteria = new Criteria([$seoUrlTemplate['channelId']])->setLimit(1);
        } else {
            $criteria = new Criteria()
                ->addFilter(new OrFilter([
                    new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_WEB),
                    new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_API),
                ]))
                ->setLimit(1);
        }

        $criteria->addAssociation('domains');
        $criteria->addSorting(new FieldSorting('typeId'));

        return $this->channelRepository
            ->search($criteria, $context)
            ->getEntities()
            ->first();
    }

    /**
     * @return EntityRepository<covariant EntityCollection<covariant Entity>>
     */
    private function getRepository(SeoUrlRouteConfig $config): EntityRepository
    {
        return $this->definitionRegistry->getRepository($config->getDefinition()->getEntityName());
    }
}
