<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoResolver;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Routing\RequestTransformer as CoreRequestTransformer;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Routing\DomainLoader;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Routing\Router;
use Contena\Frontend\Framework\Routing\TenantDefaultDomainLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;

/**
 * @internal
 */
class FrontendRoutingTest extends TestCase
{
    use IntegrationTestBehaviour;

    private RequestTransformerInterface $requestTransformer;

    private Router $router;

    private RequestStack $requestStack;

    private RequestContext $oldContext;

    private SeoUrlPlaceholderHandlerInterface $seoUrlReplacer;

    protected function setUp(): void
    {
        /** @var list<string> $registeredApiPrefixes */
        $registeredApiPrefixes = static::getContainer()->getParameter('contena.routing.registered_api_prefixes');

        $this->requestTransformer = new RequestTransformer(
            new CoreRequestTransformer(),
            static::getContainer()->get(SeoResolver::class),
            $registeredApiPrefixes,
            static::getContainer()->get(DomainLoader::class),
            static::getContainer()->get(TenantDefaultDomainLoader::class)
        );

        $this->seoUrlReplacer = static::getContainer()->get(SeoUrlPlaceholderHandlerInterface::class);

        $this->requestStack = static::getContainer()->get('request_stack');
        while ($this->requestStack->pop()) {
        }
        $this->router = static::getContainer()->get('router');
        $this->oldContext = $this->router->getContext();
    }

    protected function tearDown(): void
    {
        while ($this->requestStack->pop()) {
        }
        $this->router->setContext($this->oldContext);
    }

    #[DataProvider('getRequestTestCaseProvider')]
    public function testInvariants(RequestTestCase $case): void
    {
        $channelContext = $this->registerDomain($case);

        $request = $case->createRequest();
        $transformedRequest = $this->requestTransformer->transform($request);

        $this->requestStack->push($transformedRequest);

        $context = $this->getContext($transformedRequest);
        $this->router->setContext($context);

        $absolutePath = $this->router->generate($case->route);
        $absoluteUrl = $this->router->generate($case->route, [], Router::ABSOLUTE_URL);
        $networkPath = $this->router->generate($case->route, [], Router::NETWORK_PATH);
        $pathInfo = $this->router->generate($case->route, [], Router::PATH_INFO);

        static::assertSame($case->getAbsolutePath(), $absolutePath, var_export($case, true));
        static::assertSame($case->getAbsoluteUrl(), $absoluteUrl, var_export($case, true));
        static::assertSame($case->getNetworkPath(), $networkPath, var_export($case, true));
        static::assertSame($case->getPathInfo(), $pathInfo, var_export($case, true));

        $matches = $this->router->matchRequest($transformedRequest);
        static::assertSame($case->route, $matches['_route']);

        $matches = $this->router->match($transformedRequest->getPathInfo());
        static::assertSame($case->route, $matches['_route']);

        // test seo url generation
        $host = $transformedRequest->attributes->get(RequestTransformer::CHANNEL_ABSOLUTE_BASE_URL)
            . $transformedRequest->attributes->get(RequestTransformer::CHANNEL_BASE_URL);

        $absoluteSeoUrl = $this->seoUrlReplacer->replace(
            $this->seoUrlReplacer->generate(
                $case->route
            ),
            $host,
            $channelContext
        );

        static::assertSame($case->getAbsoluteUrl(), $absoluteSeoUrl);
    }

    /**
     * @return iterable<string, array<int, RequestTestCase>>
     */
    public static function getRequestTestCaseProvider(): iterable
    {
        $config = [
            'https' => [false, true],
            'host' => ['router.test', 'router.test:8000'],
            'subDir' => ['', '/public', '/sw/public'],
            'channel' => ['', '/de', '/de/premium', '/public'],
        ];
        $cases = self::generateCases(array_keys($config), $config);

        foreach ($cases as $params) {
            yield \sprintf(
                '%s host %s subdir %s channel %s',
                $params['https'] ? 'https' : 'http',
                $params['host'],
                $params['subDir'] === '' ? 'root' : trim($params['subDir'], '/'),
                $params['channel'] === '' ? 'root' : trim($params['channel'], '/')
            ) => [self::createCase($params['https'], $params['host'], $params['subDir'], $params['channel'])];
        }
    }

    private function getContext(Request $request): RequestContext
    {
        $httpPort = (!$request->isSecure() && $request->getPort() !== 80) ? $request->getPort() : 80;
        $httpsPort = ($request->isSecure() && $request->getPort() !== 443) ? $request->getPort() : 443;

        return new RequestContext(
            $request->getBaseUrl(),
            $request->getMethod(),
            $request->getHost(),
            $request->getScheme(),
            (int) $httpPort,
            (int) $httpsPort,
            $request->getPathInfo(),
            ''
        );
    }

    private static function createCase(bool $https, string $host, string $subDir, string $channel): RequestTestCase
    {
        return new RequestTestCase(
            'POST',
            'frontend.account.register.save',
            '/app' . $subDir . '/index.php',
            $subDir . '/index.php',
            $host,
            $subDir . $channel . '/account/register',
            '/account/register',
            $channel,
            $https
        );
    }

    /**
     * @param array<int, string> $keys
     * @param array<string, array<int, bool|string>> $config
     *
     * @return array<mixed>
     */
    private static function generateCases(array $keys, array $config): array
    {
        if ($keys === []) {
            return [];
        }

        $results = [];
        $key = array_pop($keys);
        foreach ($config[$key] as $value) {
            $childResults = self::generateCases($keys, $config);
            $base = [$key => $value];
            foreach ($childResults as $childResult) {
                $base = array_merge($base, $childResult);
                $results[] = $base;
            }
            if ($childResults === []) {
                $results[] = $base;
            }
        }

        return $results;
    }

    private function registerDomain(RequestTestCase $case): ChannelContext
    {
        $request = $case->createRequest();
        $channel = [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'id' => Uuid::randomHex(),
                    'url' => ($case->https ? 'https://' : 'http://') . $case->host . $request->getBaseUrl() . $case->channelPrefix,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ],
            ],
        ];

        return $this->createChannels([$channel]);
    }

    /**
     * @param array<int, array<string, array<int, array<string, string|null>>|string>> $channels
     */
    private function createChannels(array $channels): ChannelContext
    {
        $channels = array_map(function ($channelData) {
            $countryId = $this->getValidCountryId();
            $defaults = [
                'typeId' => Defaults::CHANNEL_TYPE_WEB,
                'accessKey' => AccessKeyHelper::generateAccessKey('channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'navigationCategoryId' => $this->getValidCategoryId(),
                'countryId' => $countryId,
                'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                'countries' => [['id' => $countryId]],
                'memberGroupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            ];

            return array_merge_recursive($defaults, $channelData);
        }, $channels);

        /** @var EntityRepository<ChannelCollection> $channelRepository */
        $channelRepository = static::getContainer()->get('channel.repository');

        $event = $channelRepository->create($channels, Context::createDefaultContext());
        $entities = $event->getEventByEntityName($channelRepository->getDefinition()->getEntityName());

        static::assertNotNull($entities);
        $id = $entities->getIds()[0];

        return static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), $id);
    }
}
