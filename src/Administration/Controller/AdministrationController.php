<?php declare(strict_types=1);

namespace Contena\Administration\Controller;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\OAuth2\Server\Exception\OAuthServerException;
use Contena\Administration\Events\PreResetExcludedSearchTermEvent;
use Contena\Administration\Framework\Routing\AdministrationRouteScope;
use Contena\Administration\Framework\Routing\KnownIps\KnownIpsCollectorInterface;
use Contena\Administration\Snippet\SnippetFinderInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Contena\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [AdministrationRouteScope::ID]])]
class AdministrationController extends AbstractController
{
    private const array UNAUTHENTICATED_SNIPPET_NAMESPACES = [
        'ct-login',
        'global',
    ];

    /**
     * @internal
     *
     * @param array<int, int> $supportedApiVersions
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly TemplateFinderInterface $finder,
        private readonly SnippetFinderInterface $snippetFinder,
        private readonly array $supportedApiVersions,
        private readonly KnownIpsCollectorInterface $knownIpsCollector,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
        private readonly FilesystemOperator $fileSystem,
        private readonly EntityRepository $languageRepository,
        private readonly SymfonyBearerTokenValidator $tokenValidator,
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string $contenaCoreDir,
        private readonly string $refreshTokenTtl = 'P1W',
    ) {
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

        $refreshTokenInterval = new \DateInterval($this->refreshTokenTtl);
        $refreshTokenTtl = $refreshTokenInterval->s + $refreshTokenInterval->i * 60 + $refreshTokenInterval->h * 3600 + $refreshTokenInterval->d * 86400;

        $response = $this->render($template, [
            'features' => Feature::getAll(),
            'systemLanguageId' => Defaults::LANGUAGE_SYSTEM,
            'defaultLanguageIds' => [Defaults::LANGUAGE_SYSTEM],
            'systemCurrencyId' => null,
            'systemCurrencyISOCode' => null,
            'liveVersionId' => Defaults::LIVE_VERSION,
            'firstRunWizard' => false,
            'apiVersion' => $this->getLatestApiVersion(),
            'cspNonce' => $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE),
            'adminEsEnable' => false,
            'storefrontEsEnable' => false,
            'refreshTokenTtl' => $refreshTokenTtl * 1000,
            'serviceRegistryUrl' => '',
            'productStreamIndexingEnabled' => false,
            'analyticsGatewayUrl' => '',
        ]);

        $response->setPublic();
        $response->setMaxAge(0);
        $response->setSharedMaxAge(0);

        $response->headers->addCacheControlDirective('stale-while-revalidate', '86400');

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
        $snippets = [];
        $locale = (string) $request->query->get('locale', Defaults::DEFAULT_LOCALE);
        $snippets[$locale] = $this->snippetFinder->findSnippets($locale);

        if ($locale !== 'en-GB') {
            $snippets['en-GB'] = $this->snippetFinder->findSnippets('en-GB');
            $snippets = $this->filterByAuthentication($request, $snippets, 'en-GB');
        }

        $snippets = $this->filterByAuthentication($request, $snippets, $locale);

        return new JsonResponse($snippets);
    }

    #[Route(
        path: '/api/_admin/locales',
        name: 'api.admin.locales',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET]
    )]
    public function getLocales(Request $request, Context $context): Response
    {
        $criteria = new Criteria()->addAssociation('locale');

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
        $tenantId = $context->getTenantId();
        $tenantCondition = 'tenant_id IS NULL';
        $tenantParameters = [];
        if ($tenantId !== null) {
            $tenantCondition = 'tenant_id = :tenant_id';
            $tenantParameters['tenant_id'] = Uuid::fromHexToBytes($tenantId);
        }

        $searchConfigId = $this->connection->fetchOne(
            'SELECT id FROM blog_search_config WHERE language_id = :language_id AND ' . $tenantCondition,
            [
                'language_id' => Uuid::fromHexToBytes($context->getLanguageId()),
                ...$tenantParameters,
            ]
        );

        if ($searchConfigId === false) {
            throw RoutingException::languageNotFound($context->getLanguageId());
        }

        $englishLanguageId = $this->fetchLanguageIdByName('en-GB');

        if ($context->getLanguageId() === $englishLanguageId) {
            $defaultExcludedTerms = require $this->contenaCoreDir . '/Migration/Fixtures/stopwords/en.php';
        } else {
            $event = $this->eventDispatcher->dispatch(new PreResetExcludedSearchTermEvent($searchConfigId, [], $context));
            $defaultExcludedTerms = $event->getExcludedTerms();
        }

        $this->connection->executeStatement(
            'UPDATE `blog_search_config` SET `excluded_terms` = :excludedTerms WHERE `id` = :id AND ' . $tenantCondition,
            [
                'excludedTerms' => json_encode($defaultExcludedTerms, \JSON_THROW_ON_ERROR),
                'id' => $searchConfigId,
                ...$tenantParameters,
            ]
        );

        return new JsonResponse(['success' => true]);
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
                ['preview' => strip_tags($html)]
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

    private function fetchLanguageIdByName(string $isoCode): ?string
    {
        $languageId = $this->connection->fetchOne(
            'SELECT `language`.id FROM `language`
             INNER JOIN locale ON language.translation_code_id = locale.id
             WHERE `code` = :code',
            ['code' => $isoCode]
        );

        return $languageId === false ? null : Uuid::fromBytesToHex($languageId);
    }

    private function getLatestApiVersion(): ?int
    {
        $sortedSupportedApiVersions = array_values($this->supportedApiVersions);

        usort($sortedSupportedApiVersions, static fn (int $version1, int $version2) => \version_compare((string) $version1, (string) $version2));

        return array_pop($sortedSupportedApiVersions);
    }

    /**
     * @description Filters snippets based on authentication status. If the request is unauthenticated, only the bare minimum of translations is available.
     *
     * @param array<string, mixed> $snippets
     *
     * @return array<string, mixed>
     */
    private function filterByAuthentication(Request $request, array $snippets, string $locale): array
    {
        try {
            $this->tokenValidator->validateAuthorization($request);
        } catch (OAuthServerException) {
            $snippets[$locale] = \array_filter(
                $snippets[$locale],
                static fn (string $key) => \in_array($key, self::UNAUTHENTICATED_SNIPPET_NAMESPACES, true),
                \ARRAY_FILTER_USE_KEY
            );
        }

        return $snippets;
    }
}
