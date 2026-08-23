<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Routing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Content\Seo\SeoResolver;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Routing\RequestTransformer as CoreRequestTransformer;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Routing\DomainLoader;
use Contena\Frontend\Framework\Routing\Exception\ChannelMappingException;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Routing\TenantDefaultDomainLoader;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Test\Framework\Routing\Helper\ExpectedRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @phpstan-type Channel array{id: string, name: string, active: bool, languages: array{id: string}[], domains: array{id: string, url: string, languageId: string, snippetSetId: string}[]}
 */
class RequestTransformerTest extends TestCase
{
    use IntegrationTestBehaviour;

    final public const LOCALE_ZH_CN_ISO = 'zh-CN';
    final public const LOCALE_EN_GB_ISO = 'en-GB';

    private RequestTransformer $requestTransformer;

    private string $zhLanguageId;

    private string $englishLanguageId;

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

        $this->zhLanguageId = Defaults::LANGUAGE_SYSTEM;
        $this->englishLanguageId = $this->getEnglishLanguageId();
    }

    /**
     * @param list<Channel> $channels
     * @param list<ExpectedRequest> $requests
     */
    #[DataProvider('domainProvider')]
    public function testDomainResolving(array $channels, array $requests): void
    {
        $this->createChannels($channels);

        $snippetSetEN = $this->getSnippetSetIdForLocale(self::LOCALE_EN_GB_ISO);
        $snippetSetZH = $this->getSnippetSetIdForLocale(self::LOCALE_ZH_CN_ISO);

        foreach ($requests as $expectedRequest) {
            if ($expectedRequest->exception) {
                $exception = $expectedRequest->exception;

                $this->expectException($exception);
            }

            $request = Request::create($expectedRequest->url);

            $resolved = $this->requestTransformer->transform($request);

            $expectedSnippetSetId = $expectedRequest->snippetLanguageCode === self::LOCALE_ZH_CN_ISO ? $snippetSetZH : $snippetSetEN;
            $expectedLanguageId = $expectedRequest->languageCode === self::LOCALE_ZH_CN_ISO ? $this->zhLanguageId : $this->englishLanguageId;

            static::assertSame($expectedRequest->channelId, $resolved->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID));

            static::assertSame($expectedRequest->domainId, $resolved->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_ID));
            static::assertSame($expectedRequest->isFrontendRequest, $resolved->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST));
            static::assertSame($expectedRequest->locale, $resolved->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_LOCALE));
            static::assertSame($expectedSnippetSetId, $resolved->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID));
            static::assertSame($expectedRequest->baseUrl, $resolved->attributes->get(RequestTransformer::CHANNEL_BASE_URL), $expectedRequest->url);
            static::assertSame($expectedRequest->resolvedUrl, $resolved->attributes->get(RequestTransformer::CHANNEL_RESOLVED_URI));
            static::assertSame($expectedLanguageId, $resolved->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        }
    }

    /**
     * @return iterable<string, array{0: list<Channel>, 1: list<ExpectedRequest>}>
     */
    public static function domainProvider(): iterable
    {
        $chineseId = Uuid::randomHex();
        $englishId = Uuid::randomHex();
        $zhUkId = Uuid::randomHex();
        $zhUkId2 = Uuid::randomHex();

        $zhDomainId = Uuid::randomHex();
        $ukDomainId = Uuid::randomHex();

        $zhDomainId2 = Uuid::randomHex();
        $ukDomainId2 = Uuid::randomHex();

        yield 'single' => [
            [self::getChineseChannel($chineseId, $zhDomainId, 'http://chinese.test')],
            [
                new ExpectedRequest('http://chinese.test', '', '/', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/', '', '/', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/foobar', '', '/foobar', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test//foobar', '', '/foobar', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
            ],
        ];
        yield 'two' => [
            [
                self::getChineseChannel($chineseId, $zhDomainId, 'http://chinese.test'),
                self::getEnglishChannel($englishId, $ukDomainId, 'http://english.test'),
            ],
            [
                new ExpectedRequest('http://chinese.test', '', '/', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/', '', '/', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/foobar', '', '/foobar', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://english.test', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/foobar', '', '/foobar', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://english.test/navigation/1', '', '/navigation/1', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://chinese.test/navigation/1', '', '/navigation/1', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
            ],
        ];
        yield 'single-with-ger-and-uk-domain' => [
            [
                self::getChannelWithGerAndUkDomain($zhUkId, $zhDomainId, 'http://chinese.test', $ukDomainId, 'http://english.test'),
            ],
            [
                new ExpectedRequest('http://chinese.test', '', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/', '', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://chinese.test/foobar', '', '/foobar', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://english.test', '', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/', '', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/foobar', '', '/foobar', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'single-with-ger-and-uk-domain-with-port' => [
            [
                self::getChannelWithGerAndUkDomain($zhUkId, $zhDomainId, 'http://base.test:1337', $ukDomainId, 'http://base.test:31337'),
            ],
            [
                new ExpectedRequest('http://base.test:1337', '', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://base.test:1337/', '', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://base.test:1337/foobar', '', '/foobar', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://base.test:31337', '', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:31337/', '', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:31337/foobar', '', '/foobar', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'single-with-ger-and-uk-domain-with-same-port-different-path' => [
            [
                self::getChannelWithGerAndUkDomain($zhUkId, $zhDomainId, 'http://base.test:1337/foo', $ukDomainId, 'http://base.test:1337/bar'),
            ],
            [
                new ExpectedRequest('http://base.test:1337/foo', '/foo', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://base.test:1337/foo/', '/foo', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://base.test:1337/foo/foobar', '/foo', '/foobar', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://base.test:1337/bar', '/bar', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:1337/bar/', '/bar', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:1337/bar/foobar', '/bar', '/foobar', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'two-domains-same-host-different-path' => [
            [
                self::getChannelWithGerAndUkDomain($zhUkId, $zhDomainId, 'http://saleschannel.test/de', $ukDomainId, 'http://saleschannel.test/en'),
            ],
            [
                new ExpectedRequest('http://saleschannel.test/de', '/de', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/de/', '/de', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/de/foobar', '/de', '/foobar', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://saleschannel.test/en', '/en', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/', '/en', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/foobar', '/en', '/foobar', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/de/navigation/1', '/de', '/navigation/1', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/en/navigation/1', '/en', '/navigation/1', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/de/de/navigation/1', '/de', '/de/navigation/1', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/en/en/navigation/1', '/en', '/en/navigation/1', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'two-scs-same-host-different-sub-path-unsorted' => [
            [
                self::getChannelWithGerAndUkDomain($zhUkId, $zhDomainId, 'http://saleschannel.test/de', $ukDomainId, 'http://saleschannel.test/en'),
                self::getChannelWithGerAndUkDomain($zhUkId2, $zhDomainId2, 'http://saleschannel.test/subdir/de', $ukDomainId2, 'http://saleschannel.test/subdir/en'),
            ],
            [
                new ExpectedRequest('http://saleschannel.test/de', '/de', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/de/', '/de', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/de/foobar', '/de', '/foobar', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://saleschannel.test/subdir/en', '/subdir/en', '/', $ukDomainId2, $zhUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/', '/subdir/en', '/', $ukDomainId2, $zhUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/foobar', '/subdir/en', '/foobar', $ukDomainId2, $zhUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/en', '/en', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/', '/en', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/foobar', '/en', '/foobar', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/de/navigation/1', '/de', '/navigation/1', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/navigation/1', '/subdir/en', '/navigation/1', $ukDomainId2, $zhUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/de/de/navigation/1', '/de', '/de/navigation/1', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/en/navigation/1', '/subdir/en', '/en/navigation/1', $ukDomainId2, $zhUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'two-domains-same-host-extended-path' => [
            [
                self::getChannelWithGerAndUkDomain($zhUkId, $zhDomainId, 'http://saleschannel.test/de', $ukDomainId, 'http://saleschannel.test'),
            ],
            [
                new ExpectedRequest('http://saleschannel.test/de', '/de', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/de/', '/de', '/', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://saleschannel.test/de/foobar', '/de', '/foobar', $zhDomainId, $zhUkId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),

                new ExpectedRequest('http://saleschannel.test', '', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/', '', '/', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/foobar', '', '/foobar', $ukDomainId, $zhUkId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'inactive' => [
            [
                self::getInactiveChannel($chineseId, $zhDomainId, 'http://inactive.test'),
            ],
            [
                new ExpectedRequest('http://inactive.test', null, null, null, null, null, null, null, null, ChannelMappingException::class),
                new ExpectedRequest('http://inactive.test/', null, null, null, null, null, null, null, null, ChannelMappingException::class),
                new ExpectedRequest('http://inactive.test/foobar', null, null, null, null, null, null, null, null, ChannelMappingException::class),
            ],
        ];
        yield 'punycode' => [
            [
                self::getChineseChannel($chineseId, $zhDomainId, 'http://würmer.test'),
                self::getEnglishChannel($englishId, $ukDomainId, 'http://xn--shpwre-eua5l.test'),
            ],
            [
                new ExpectedRequest('http://xn--wrmer-kva.test', '', '/', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://xn--wrmer-kva.test/', '', '/', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://xn--wrmer-kva.test/foobar', '', '/foobar', $zhDomainId, $chineseId, true, self::LOCALE_ZH_CN_ISO, 'zh-CN', self::LOCALE_ZH_CN_ISO),
                new ExpectedRequest('http://xn--shpwre-eua5l.test', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://xn--shpwre-eua5l.test/', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://xn--shpwre-eua5l.test/foobar', '', '/foobar', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
    }

    #[DataProvider('seoRedirectProvider')]
    public function testRedirectLinksUsesChannelPath(string $baseUrl, string $virtualUrl, string $resolvedUrl): void
    {
        $zhUkId = Uuid::randomHex();

        $zhDomainId = Uuid::randomHex();
        $ukDomainId = Uuid::randomHex();

        $channels = $this->getChannelWithGerAndUkDomain($zhUkId, $zhDomainId, 'http://base.test' . $virtualUrl, $ukDomainId, 'http://base.test/public/en');

        $this->createChannels([$channels]);

        $con = static::getContainer()->get(Connection::class);
        $con->insert(
            'seo_url',
            [
                'id' => Uuid::randomBytes(),
                'language_id' => Uuid::fromHexToBytes($this->zhLanguageId),
                'channel_id' => Uuid::fromHexToBytes($zhUkId),
                'foreign_key' => Uuid::randomBytes(),
                'route_name' => 'test',
                'path_info' => '/detail/87a78cf58f114d5587ae23c140825694',
                'seo_path_info' => 'Test',
                'is_canonical' => 1,
                'created_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $request = Request::create('http://base.test' . $virtualUrl . '/detail/87a78cf58f114d5587ae23c140825694');
        $ref = new \ReflectionClass($request);
        $ref->getProperty('baseUrl')->setValue($request, $baseUrl);

        $resolved = $this->requestTransformer->transform($request);

        static::assertSame('http://base.test' . $resolvedUrl, $resolved->attributes->get(ChannelRequest::ATTRIBUTE_CANONICAL_LINK));
    }

    public function testCanonicalSeoUrlWithQueryParameterDoesNotSetCanonicalLink(): void
    {
        $channelId = Uuid::randomHex();
        $domainId = Uuid::randomHex();

        $this->createChannels([
            self::getChineseChannel($channelId, $domainId, 'http://base.test'),
        ]);

        $con = static::getContainer()->get(Connection::class);
        $con->insert(
            'seo_url',
            [
                'id' => Uuid::randomBytes(),
                'language_id' => Uuid::fromHexToBytes($this->zhLanguageId),
                'channel_id' => Uuid::fromHexToBytes($channelId),
                'foreign_key' => Uuid::randomBytes(),
                'route_name' => BlogPageSeoUrlRoute::ROUTE_NAME,
                'path_info' => '/detail/87a78cf58f114d5587ae23c140825694',
                'seo_path_info' => 'Main-blog/CONTENA10001?test=123',
                'is_canonical' => 1,
                'created_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $request = Request::create('http://base.test/Main-blog/CONTENA10001?test=123');

        $resolved = $this->requestTransformer->transform($request);

        static::assertSame(
            '/detail/87a78cf58f114d5587ae23c140825694',
            $resolved->attributes->get(RequestTransformer::CHANNEL_RESOLVED_URI)
        );

        // Matching canonical SEO URL should not set a canonical link (no redirect loop)
        static::assertNull($resolved->attributes->get(ChannelRequest::ATTRIBUTE_CANONICAL_LINK));
    }

    public function testPlainCanonicalSeoUrlWithRequestQueryParameterDoesNotSetCanonicalLink(): void
    {
        $channelId = Uuid::randomHex();
        $domainId = Uuid::randomHex();

        $this->createChannels([
            self::getChineseChannel($channelId, $domainId, 'http://base.test'),
        ]);

        $con = static::getContainer()->get(Connection::class);
        $con->insert(
            'seo_url',
            [
                'id' => Uuid::randomBytes(),
                'language_id' => Uuid::fromHexToBytes($this->zhLanguageId),
                'channel_id' => Uuid::fromHexToBytes($channelId),
                'foreign_key' => Uuid::randomBytes(),
                'route_name' => BlogPageSeoUrlRoute::ROUTE_NAME,
                'path_info' => '/detail/87a78cf58f114d5587ae23c140825694',
                'seo_path_info' => 'Main-blog/CONTENA10001',
                'is_canonical' => 1,
                'created_at' => new \DateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $request = Request::create('http://base.test/Main-blog/CONTENA10001?utm=123');

        $resolved = $this->requestTransformer->transform($request);

        static::assertSame(
            '/detail/87a78cf58f114d5587ae23c140825694',
            $resolved->attributes->get(RequestTransformer::CHANNEL_RESOLVED_URI)
        );
        static::assertNull($resolved->attributes->get(ChannelRequest::ATTRIBUTE_CANONICAL_LINK));
    }

    /**
     * @return iterable<string, string[]>
     */
    public static function seoRedirectProvider(): iterable
    {
        yield 'Use with base url' => [
            '/public', // baseUrl
            '/public/de', // Virtual URL
            '/public/de/Test', // Resolved seo url
        ];

        yield 'Use with base url in subfolder' => [
            '/sw6/public', // baseUrl
            '/sw6/public/de', // Virtual URL
            '/sw6/public/de/Test', // Resolved seo url
        ];

        yield 'With Virtual url' => [
            '', // baseUrl
            '/de', // Virtual URL
            '/de/Test', // Resolved seo url
        ];

        yield 'Without virtual URL' => [
            '', // baseUrl
            '', // Virtual URL
            '/Test', // Resolved seo url
        ];
    }

    /**
     * @return Channel
     */
    private static function getEnglishChannel(string $channelId, string $domainId, string $url): array
    {
        return [
            'id' => $channelId,
            'name' => 'english',
            'active' => true,
            'languages' => [
                ['id' => self::LOCALE_EN_GB_ISO],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => self::LOCALE_EN_GB_ISO,
                    'snippetSetId' => self::LOCALE_EN_GB_ISO,
                ],
            ],
        ];
    }

    /**
     * @return Channel
     */
    private static function getChineseChannel(string $channelId, string $domainId, string $url): array
    {
        return [
            'id' => $channelId,
            'name' => 'chinese',
            'active' => true,
            'languages' => [
                ['id' => 'zh-CN'],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => 'zh-CN',
                    'snippetSetId' => self::LOCALE_ZH_CN_ISO,
                ],
            ],
        ];
    }

    /**
     * @return Channel
     */
    private static function getChannelWithGerAndUkDomain(
        string $channelId,
        string $zhDomainId,
        string $zhUrl,
        string $ukDomainId,
        string $ukUrl
    ): array {
        return [
            'id' => $channelId,
            'name' => 'english',
            'active' => true,
            'languages' => [
                ['id' => self::LOCALE_EN_GB_ISO],
                ['id' => self::LOCALE_ZH_CN_ISO],
            ],
            'domains' => [
                [
                    'id' => $zhDomainId,
                    'url' => $zhUrl,
                    'languageId' => self::LOCALE_ZH_CN_ISO,
                    'snippetSetId' => self::LOCALE_ZH_CN_ISO,
                ],
                [
                    'id' => $ukDomainId,
                    'url' => $ukUrl,
                    'languageId' => self::LOCALE_EN_GB_ISO,
                    'snippetSetId' => self::LOCALE_EN_GB_ISO,
                ],
            ],
        ];
    }

    /**
     * @return Channel
     */
    private static function getInactiveChannel(string $channelId, string $domainId, string $url): array
    {
        return [
            'id' => $channelId,
            'name' => 'inactive channel',
            'active' => false,
            'languages' => [
                ['id' => self::LOCALE_ZH_CN_ISO],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => self::LOCALE_ZH_CN_ISO,
                    'snippetSetId' => self::LOCALE_ZH_CN_ISO,
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $channels
     */
    private function createChannels(array $channels): EntityWrittenContainerEvent
    {
        $snippetSetEN = $this->getSnippetSetIdForLocale(self::LOCALE_EN_GB_ISO);
        $snippetSetZH = $this->getSnippetSetIdForLocale(self::LOCALE_ZH_CN_ISO);

        $channels = array_map(function ($channelData) use ($snippetSetZH, $snippetSetEN) {
            $defaults = [
                'typeId' => Defaults::CHANNEL_TYPE_WEB,
                'accessKey' => AccessKeyHelper::generateAccessKey('channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'navigationCategoryId' => $this->getValidCategoryId(),
                'countryId' => $this->getValidCountryId(),
                'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                'countries' => [['id' => $this->getValidCountryId()]],
                'memberGroupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            ];

            foreach ($channelData['languages'] as &$language) {
                if ($language['id'] === self::LOCALE_ZH_CN_ISO) {
                    $language['id'] = $this->zhLanguageId;
                }

                if ($language['id'] === self::LOCALE_EN_GB_ISO) {
                    $language['id'] = $this->englishLanguageId;
                }
            }

            foreach ($channelData['domains'] as &$domain) {
                if ($domain['languageId'] === self::LOCALE_ZH_CN_ISO) {
                    $domain['languageId'] = $this->zhLanguageId;
                }

                if ($domain['languageId'] === self::LOCALE_EN_GB_ISO) {
                    $domain['languageId'] = $this->englishLanguageId;
                }

                if ($domain['snippetSetId'] === self::LOCALE_EN_GB_ISO) {
                    $domain['snippetSetId'] = $snippetSetEN;
                }

                if ($domain['snippetSetId'] === self::LOCALE_ZH_CN_ISO) {
                    $domain['snippetSetId'] = $snippetSetZH;
                }
            }

            return array_merge_recursive($defaults, $channelData);
        }, $channels);

        return static::getContainer()->get('channel.repository')->create($channels, Context::createDefaultContext());
    }

    private function getEnglishLanguageId(): string
    {
        /** @var EntityRepository<LanguageCollection> $repository */
        $repository = static::getContainer()->get('language.repository');

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('translationCode.code', self::LOCALE_EN_GB_ISO));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($id);

        return $id;
    }
}
