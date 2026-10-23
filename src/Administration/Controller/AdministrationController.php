<?php declare(strict_types=1);

namespace Contena\Administration\Controller;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Contena\Administration\Events\PreResetExcludedSearchTermEvent;
use Contena\Administration\Framework\Routing\AdministrationRouteScope;
use Contena\Administration\Framework\Routing\KnownIps\KnownIpsCollectorInterface;
use Contena\Administration\Snippet\AdministrationSnippetService;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Store\Services\FirstRunWizardService;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Validation\UserEmailUniqueCheck;
use Contena\Core\System\User\Validation\UserEmailUniqueChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class AdministrationController extends AbstractController
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed together with the CacheControlListener and its BeforeCacheControlEvent.
     * This marker will be not read, as the listener that used it to detect administration will be removed.
     */
    public const CACHE_ID_HEADER = 'X-Contena-Cache-Id';

    /**
     * @deprecated tag:v6.8.0 - Will be removed together with the CacheControlListener and its BeforeCacheControlEvent.
     */
    public const CACHE_ID_ADMINISTRATION = 'administration';

    private readonly bool $esAdministrationEnabled;

    private readonly bool $esFrontendEnabled;

    private readonly bool $contentStreamIndexingEnabled;

    private readonly bool $hideUpdateModule;

    /**
     * @internal
     *
     * @param array<int, int> $supportedApiVersions
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly TemplateFinderInterface $finder,
        private readonly FirstRunWizardService $firstRunWizardService,
        private readonly AdministrationSnippetService $snippetService,
        private readonly array $supportedApiVersions,
        private readonly KnownIpsCollectorInterface $knownIpsCollector,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $contenaCoreDir,
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $currencyRepository,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        ParameterBagInterface $params,
        private readonly FilesystemOperator $fileSystem,
        private readonly string $serviceRegistryUrl,
        private readonly EntityRepository $languageRepository,
        private readonly string $analyticsGatewayUrl,
        private readonly UserEmailUniqueChecker $userEmailUniqueChecker,
        private readonly string $refreshTokenTtl = 'P1W',
    ) {
        // param is only available if the elasticsearch bundle is enabled
        $this->esAdministrationEnabled = $params->has('elasticsearch.administration.enabled')
            ? $params->get('elasticsearch.administration.enabled')
            : false;
        $this->esFrontendEnabled = $params->has('elasticsearch.enabled')
            ? $params->get('elasticsearch.enabled')
            : false;
        $this->contentStreamIndexingEnabled = $params->has('contena.content_stream.indexing')
            ? $params->get('contena.content_stream.indexing')
            : true;
        $this->hideUpdateModule = $params->has('contena.auto_update.hide_module')
            ? (bool) $params->get('contena.auto_update.hide_module')
            : false;
    }

    #[Route(
        path: '/%contena_administration.path_name%',
        name: 'administration.index',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function index(Request $request, Context $context): Response
    {
        $template = $this->finder->find('@Administration/administration/index.html.twig');

        $defaultCurrency = $this->currencyRepository->search(new Criteria([Defaults::CURRENCY]), $context)->getEntities()->first();

        $refreshTokenInterval = new \DateInterval($this->refreshTokenTtl);
        $refreshTokenTtl = $refreshTokenInterval->s + $refreshTokenInterval->i * 60 + $refreshTokenInterval->h * 3600 + $refreshTokenInterval->d * 86400;

        $response = $this->render($template, [
            'features' => Feature::getAll(),
            'systemLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'defaultLanguageIds' => [Defaults::LANGUAGE_SYSTEM],
            'systemCurrencyId' => Defaults::CURRENCY,
            'systemCurrencyISOCode' => $defaultCurrency?->getIsoCode(),
            'liveVersionId' => Defaults::LIVE_VERSION,
            'firstRunWizard' => $this->firstRunWizardService->frwShouldRun(),
            'apiVersion' => $this->getLatestApiVersion(),
            'cspNonce' => $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE),
            'adminEsEnable' => $this->esAdministrationEnabled,
            'frontendEsEnable' => $this->esFrontendEnabled,
            'refreshTokenTtl' => $refreshTokenTtl * 1000,
            'serviceRegistryUrl' => $this->serviceRegistryUrl,
            'contentStreamIndexingEnabled' => $this->contentStreamIndexingEnabled,
            'hideUpdateModule' => $this->hideUpdateModule,
            'analyticsGatewayUrl' => $this->analyticsGatewayUrl,
        ]);

        $response->setPublic();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(0);

        if (!$this->firstRunWizardService->frwShouldRun()) {
            $response->headers->addCacheControlDirective('stale-while-revalidate', '86400');
        }

        if (!Feature::isActive('v6.8.0.0')) {
            $response->headers->set(self::CACHE_ID_HEADER, self::CACHE_ID_ADMINISTRATION);
        }

        return $response;
    }

    #[Route(
        path: '/api/_admin/snippets',
        name: 'api.admin.snippets',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function snippets(Request $request): Response
    {
        $snippets = $this->snippetService->getSnippets($request);

        $response = new JsonResponse($snippets);
        $response->setEtag(Hasher::hash($snippets));
        $response->isNotModified($request);

        return $response;
    }

    #[Route(
        path: '/api/_admin/locales',
        name: 'api.admin.locales',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function getLocales(Request $request, Context $context): Response
    {
        $criteria = (new Criteria())->addAssociation('locale');

        $languages = $this->languageRepository->search($criteria, $context);
        $installedLocales = $languages->getEntities()->reduce(static function (array $accumulator, LanguageEntity $language) {
            $locale = $language->getLocale();
            if ($locale !== null) {
                $accumulator[$language->getId()] = $locale->getCode();
            }

            return $accumulator;
        }, []);

        return new JsonResponse($installedLocales);
    }

    #[Route(
        path: '/api/_admin/known-ips',
        name: 'api.admin.known-ips',
        methods: [Request::METHOD_GET]
    )]
    public function knownIps(Request $request): Response
    {
        $ips = [];

        foreach ($this->knownIpsCollector->collectIps($request) as $ip => $name) {
            $ips[] = [
                'name' => $name,
                'value' => $ip,
            ];
        }

        return new JsonResponse(['ips' => $ips]);
    }

    #[Route(
        path: '/%contena_administration.path_name%/{pluginName}/index.html',
        name: 'administration.plugin.index',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function pluginIndex(string $pluginName): Response
    {
        try {
            $publicAssetBaseUrl = $this->fileSystem->publicUrl('/');
            $viteIndexHtml = $this->fileSystem->read('bundles/' . $pluginName . '/meteor-app/index.html');
        } catch (FilesystemException) {
            return new Response('Plugin index.html not found', Response::HTTP_NOT_FOUND);
        }

        $indexHtml = str_replace('__$ASSET_BASE_PATH$__', \sprintf('%sbundles/%s/meteor-app/', $publicAssetBaseUrl, $pluginName), $viteIndexHtml);

        $response = new Response($indexHtml, Response::HTTP_OK, [
            'Content-Type' => 'text/html',
            'Content-Security-Policy' => 'script-src * \'unsafe-eval\' \'unsafe-inline\'',
            PlatformRequest::HEADER_FRAME_OPTIONS => 'sameorigin',
        ]);
        $response->setPublic();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(0);
        $response->headers->addCacheControlDirective('stale-while-revalidate', '86400');

        if (!Feature::isActive('v6.8.0.0')) {
            $response->headers->set(self::CACHE_ID_HEADER, self::CACHE_ID_ADMINISTRATION);
        }

        return $response;
    }

    #[Route(
        path: '/api/_admin/reset-excluded-search-term',
        name: 'api.admin.reset-excluded-search-term',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['system_config:update', 'system_config:create', 'system_config:delete']],
        methods: [Request::METHOD_POST]
    )]
    public function resetExcludedSearchTerm(Context $context): JsonResponse
    {
        $searchConfigId = $this->connection->fetchOne('SELECT id FROM blog_search_config WHERE language_id = :language_id', ['language_id' => Uuid::fromHexToBytes($context->getLanguageId())]);

        if ($searchConfigId === false) {
            throw RoutingException::languageNotFound($context->getLanguageId());
        }

        $languageIds = $this->fetchLanguageIdsByIsoCodes(['de-DE', 'en-GB'], $this->connection);
        $deLanguageId = $languageIds['de-DE'] ?? null;
        $enLanguageId = $languageIds['en-GB'] ?? null;

        switch ($context->getLanguageId()) {
            case $deLanguageId:
                $defaultExcludedTerm = require $this->contenaCoreDir . '/Migration/Fixtures/stopwords/de.php';

                break;
            case $enLanguageId:
                $defaultExcludedTerm = require $this->contenaCoreDir . '/Migration/Fixtures/stopwords/en.php';

                break;
            default:
                $preResetExcludedSearchTermEvent = $this->eventDispatcher->dispatch(new PreResetExcludedSearchTermEvent($searchConfigId, [], $context));
                $defaultExcludedTerm = $preResetExcludedSearchTermEvent->getExcludedTerms();
        }

        $this->connection->executeStatement(
            'UPDATE `blog_search_config` SET `excluded_terms` = :excludedTerms WHERE `id` = :id',
            [
                'excludedTerms' => json_encode($defaultExcludedTerm, \JSON_THROW_ON_ERROR),
                'id' => $searchConfigId,
            ]
        );

        return new JsonResponse([
            'success' => true,
        ]);
    }

    #[Route(
        path: '/api/_admin/check-user-email-valid',
        name: 'api.admin.check-user-email-valid',
        methods: [Request::METHOD_POST]
    )]
    public function checkUserEmailValid(Request $request, Context $context): JsonResponse
    {
        $params = [];
        if (!$request->request->has('email')) {
            throw RoutingException::missingRequestParameter('email');
        }

        $email = (string) $request->request->get('email');
        $boundChannelId = $request->request->get('boundChannelId');
        if ($boundChannelId !== null && !\is_string($boundChannelId)) {
            throw RoutingException::invalidRequestParameter('boundChannelId');
        }

        $userId = $request->request->get('id');
        $conflictingUserId = $this->userEmailUniqueChecker->findConflictingUserId(new UserEmailUniqueCheck(
            email: $email,
            userId: $userId !== null ? (string) $userId : null,
            boundChannelId: $boundChannelId,
        ));
        $user = $conflictingUserId !== null ? $this->getUserById($conflictingUserId, $context) : null;
        if ($user === null) {
            return new JsonResponse(
                ['isValid' => true]
            );
        }

        $message = 'The email address {{ email }} is already in use';
        $params['{{ email }}'] = $email;

        if ($user->getBoundChannel() !== null) {
            $message .= ' in the Sales Channel {{ channel }}';
            $params['{{ channel }}'] = (string) $user->getBoundChannel()->getName();
        }

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            str_replace(array_keys($params), array_values($params), $message),
            $message,
            $params,
            null,
            null,
            $email,
            null,
            '79d30fe0-febf-421e-ac9b-1bfd5c9007f7'
        ));

        throw new ConstraintViolationException($violations, $request->request->all());
    }

    #[Route(
        path: '/api/_admin/sanitize-html',
        name: 'api.admin.sanitize-html',
        methods: [Request::METHOD_POST]
    )]
    public function sanitizeHtml(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has('html')) {
            throw RoutingException::missingRequestParameter('html');
        }

        $html = (string) $request->request->get('html');
        $field = (string) $request->request->get('field');

        if ($field === '') {
            return new JsonResponse(
                ['preview' => $this->htmlSanitizer->sanitize($html)]
            );
        }

        [$entityName, $propertyName] = explode('.', $field);
        $property = $this->definitionInstanceRegistry->getByEntityName($entityName)->getField($propertyName);

        if ($property === null) {
            throw RoutingException::invalidRequestParameter($field);
        }

        $flag = $property->getFlag(AllowHtml::class);

        if ($flag === null) {
            return new JsonResponse(
                ['preview' => $this->htmlSanitizer->stripTags($html)]
            );
        }

        if (!$flag->isSanitized()) {
            return new JsonResponse(
                ['preview' => $html]
            );
        }

        return new JsonResponse(
            ['preview' => $this->htmlSanitizer->sanitize($html, [], false, $field)]
        );
    }

    /**
     * @param list<string> $isoCodes
     *
     * @return array<string, string> language id (hex) indexed by iso code
     */
    private function fetchLanguageIdsByIsoCodes(array $isoCodes, Connection $connection): array
    {
        /** @var array<string, string> $rows */
        $rows = $connection->fetchAllKeyValue(
            '
            SELECT `locale`.`code`, `language`.id FROM `language`
            INNER JOIN locale ON language.translation_code_id = locale.id
            WHERE `code` IN (:codes)',
            ['codes' => $isoCodes],
            ['codes' => ArrayParameterType::STRING]
        );

        return array_map(Uuid::fromBytesToHex(...), $rows);
    }

    private function getLatestApiVersion(): ?int
    {
        $sortedSupportedApiVersions = array_values($this->supportedApiVersions);

        usort($sortedSupportedApiVersions, static fn (int $version1, int $version2) => \version_compare((string) $version1, (string) $version2));

        return array_pop($sortedSupportedApiVersions);
    }

    private function getUserById(string $userId, Context $context): ?UserEntity
    {
        $criteria = new Criteria([$userId]);
        $criteria->addAssociation('boundChannel');

        return $this->userRepository->search($criteria, $context)->getEntities()->first();
    }
}
