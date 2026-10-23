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
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Routing\RequestTransformer as CoreRequestTransformer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Framework\Routing\DomainLoader;
use Contena\Frontend\Framework\Routing\Exception\ChannelMappingException;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Test\Framework\Routing\Helper\ExpectedRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @phpstan-type Channel array{id: string, name: string, active: bool, languages: array{id: string}[], domains: array{id: string, url: string, languageId: string, currencyId: string, snippetSetId: string}[]}
 */
class RequestTransformerTest extends TestCase
{
    use IntegrationTestBehaviour;

    final public const LOCALE_DE_DE_ISO = 'de-DE';
    final public const LOCALE_EN_GB_ISO = 'en-GB';

    private RequestTransformer $requestTransformer;

    private string $deLanguageId;

    protected function setUp(): void
    {
        /** @var list<string> $registeredApiPrefixes */
        $registeredApiPrefixes = static::getContainer()->getParameter('contena.routing.registered_api_prefixes');
        /** @var list<string> $apiContextRoutePrefixes */
        $apiContextRoutePrefixes = static::getContainer()->getParameter('contena.routing.api_context_route_prefixes');

        $this->requestTransformer = new RequestTransformer(
            new CoreRequestTransformer(),
            static::getContainer()->get(SeoResolver::class),
            $registeredApiPrefixes,
            static::getContainer()->get(DomainLoader::class),
            $apiContextRoutePrefixes,
        );

        $this->deLanguageId = $this->getDeDeLanguageId();
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
        $snippetSetDE = $this->getSnippetSetIdForLocale(self::LOCALE_DE_DE_ISO);

        foreach ($requests as $expectedRequest) {
            if ($expectedRequest->exception) {
                $exception = $expectedRequest->exception;

                $this->expectException($exception);
            }

            $request = Request::create($expectedRequest->url);

            $resolved = $this->requestTransformer->transform($request);

            $expectedSnippetSetId = $expectedRequest->snippetLanguageCode === 'de-DE' ? $snippetSetDE : $snippetSetEN;
            $expectedLanguageId = $expectedRequest->languageCode === 'de-DE' ? $this->deLanguageId : Defaults::LANGUAGE_SYSTEM;

            static::assertSame($expectedRequest->channelId, $resolved->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID));

            static::assertSame($expectedRequest->domainId, $resolved->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_ID));
            static::assertSame($expectedRequest->isFrontendRequest, $resolved->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST));
            static::assertSame($expectedRequest->locale, $resolved->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_LOCALE));
            static::assertSame($expectedRequest->currency, $resolved->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
            static::assertSame($expectedSnippetSetId, $resolved->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID));
            static::assertSame($expectedRequest->baseUrl, $resolved->attributes->get(RequestTransformer::CHANNEL_BASE_URL), $expectedRequest->url);
            static::assertSame($expectedRequest->resolvedUrl, $resolved->attributes->get(RequestTransformer::CHANNEL_RESOLVED_URI));
            static::assertSame($expectedLanguageId, $resolved->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
        }
    }

    public function testAdministrationBelowVirtualDomainPathIsNotTransformed(): void
    {
        $this->createChannels([
            self::getGermanChannel(Uuid::randomHex(), Uuid::randomHex(), 'http://german.test/de'),
        ]);

        $resolved = $this->requestTransformer->transform(Request::create('http://german.test/de/admin'));

        static::assertFalse($resolved->attributes->has(PlatformRequest::ATTRIBUTE_CHANNEL_ID));
        static::assertFalse($resolved->attributes->has(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST));
    }

    /**
     * @return iterable<string, array{0: list<Channel>, 1: list<ExpectedRequest>}>
     */
    public static function domainProvider(): iterable
    {
        $germanId = Uuid::randomHex();
        $englishId = Uuid::randomHex();
        $gerUkId = Uuid::randomHex();
        $gerUkId2 = Uuid::randomHex();

        $gerDomainId = Uuid::randomHex();
        $ukDomainId = Uuid::randomHex();

        $gerDomainId2 = Uuid::randomHex();
        $ukDomainId2 = Uuid::randomHex();

        yield 'single' => [
            [self::getGermanChannel($germanId, $gerDomainId, 'http://german.test')],
            [
                new ExpectedRequest('http://german.test', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://german.test/', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://german.test/foobar', '', '/foobar', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://german.test//foobar', '', '/foobar', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
            ],
        ];
        yield 'two' => [
            [
                self::getGermanChannel($germanId, $gerDomainId, 'http://german.test'),
                self::getEnglishChannel($englishId, $ukDomainId, 'http://english.test'),
            ],
            [
                new ExpectedRequest('http://german.test', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://german.test/', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://german.test/foobar', '', '/foobar', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),

                new ExpectedRequest('http://english.test', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/foobar', '', '/foobar', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://english.test/navigation/1', '', '/navigation/1', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://german.test/navigation/1', '', '/navigation/1', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
            ],
        ];
        yield 'single-with-ger-and-uk-domain' => [
            [
                self::getChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://german.test', $ukDomainId, 'http://english.test'),
            ],
            [
                new ExpectedRequest('http://german.test', '', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://german.test/', '', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://german.test/foobar', '', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),

                new ExpectedRequest('http://english.test', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://english.test/foobar', '', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'single-with-ger-and-uk-domain-with-port' => [
            [
                self::getChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://base.test:1337', $ukDomainId, 'http://base.test:31337'),
            ],
            [
                new ExpectedRequest('http://base.test:1337', '', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://base.test:1337/', '', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://base.test:1337/foobar', '', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),

                new ExpectedRequest('http://base.test:31337', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:31337/', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:31337/foobar', '', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'single-with-ger-and-uk-domain-with-same-port-different-path' => [
            [
                self::getChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://base.test:1337/foo', $ukDomainId, 'http://base.test:1337/bar'),
            ],
            [
                new ExpectedRequest('http://base.test:1337/foo', '/foo', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://base.test:1337/foo/', '/foo', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://base.test:1337/foo/foobar', '/foo', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),

                new ExpectedRequest('http://base.test:1337/bar', '/bar', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:1337/bar/', '/bar', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://base.test:1337/bar/foobar', '/bar', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'two-domains-same-host-different-path' => [
            [
                self::getChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://saleschannel.test/de', $ukDomainId, 'http://saleschannel.test/en'),
            ],
            [
                new ExpectedRequest('http://saleschannel.test/de', '/de', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://saleschannel.test/de/', '/de', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://saleschannel.test/de/foobar', '/de', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),

                new ExpectedRequest('http://saleschannel.test/en', '/en', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/', '/en', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/foobar', '/en', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/de/navigation/1', '/de', '/navigation/1', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://saleschannel.test/en/navigation/1', '/en', '/navigation/1', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/de/de/navigation/1', '/de', '/de/navigation/1', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://saleschannel.test/en/en/navigation/1', '/en', '/en/navigation/1', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'two-scs-same-host-different-sub-path-unsorted' => [
            [
                self::getChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://saleschannel.test/de', $ukDomainId, 'http://saleschannel.test/en'),
                self::getChannelWithGerAndUkDomain($gerUkId2, $gerDomainId2, 'http://saleschannel.test/subdir/de', $ukDomainId2, 'http://saleschannel.test/subdir/en'),
            ],
            [
                new ExpectedRequest('http://saleschannel.test/de', '/de', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://saleschannel.test/de/', '/de', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://saleschannel.test/de/foobar', '/de', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),

                new ExpectedRequest('http://saleschannel.test/subdir/en', '/subdir/en', '/', $ukDomainId2, $gerUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/', '/subdir/en', '/', $ukDomainId2, $gerUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/foobar', '/subdir/en', '/foobar', $ukDomainId2, $gerUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/en', '/en', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/', '/en', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/en/foobar', '/en', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/de/navigation/1', '/de', '/navigation/1', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/navigation/1', '/subdir/en', '/navigation/1', $ukDomainId2, $gerUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),

                new ExpectedRequest('http://saleschannel.test/de/de/navigation/1', '/de', '/de/navigation/1', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://saleschannel.test/subdir/en/en/navigation/1', '/subdir/en', '/en/navigation/1', $ukDomainId2, $gerUkId2, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'two-domains-same-host-extended-path' => [
            [
                self::getChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://saleschannel.test/de', $ukDomainId, 'http://saleschannel.test'),
            ],
            [
                new ExpectedRequest('http://saleschannel.test/de', '/de', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://saleschannel.test/de/', '/de', '/', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://saleschannel.test/de/foobar', '/de', '/foobar', $gerDomainId, $gerUkId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),

                new ExpectedRequest('http://saleschannel.test', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/', '', '/', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://saleschannel.test/foobar', '', '/foobar', $ukDomainId, $gerUkId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
        yield 'inactive' => [
            [
                self::getInactiveChannel($germanId, $gerDomainId, 'http://inactive.test'),
            ],
            [
                new ExpectedRequest('http://inactive.test', null, null, null, null, null, null, null, null, null, ChannelMappingException::class),
                new ExpectedRequest('http://inactive.test/', null, null, null, null, null, null, null, null, null, ChannelMappingException::class),
                new ExpectedRequest('http://inactive.test/foobar', null, null, null, null, null, null, null, null, null, ChannelMappingException::class),
            ],
        ];
        yield 'punycode' => [
            [
                self::getGermanChannel($germanId, $gerDomainId, 'http://würmer.test'),
                self::getEnglishChannel($englishId, $ukDomainId, 'http://xn--shpwre-eua5l.test'),
            ],
            [
                new ExpectedRequest('http://xn--wrmer-kva.test', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://xn--wrmer-kva.test/', '', '/', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://xn--wrmer-kva.test/foobar', '', '/foobar', $gerDomainId, $germanId, true, self::LOCALE_DE_DE_ISO, Defaults::CURRENCY, 'de-DE', self::LOCALE_DE_DE_ISO),
                new ExpectedRequest('http://xn--shpwre-eua5l.test', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://xn--shpwre-eua5l.test/', '', '/', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
                new ExpectedRequest('http://xn--shpwre-eua5l.test/foobar', '', '/foobar', $ukDomainId, $englishId, true, self::LOCALE_EN_GB_ISO, Defaults::CURRENCY, Defaults::LANGUAGE_SYSTEM, self::LOCALE_EN_GB_ISO),
            ],
        ];
    }

    #[DataProvider('seoRedirectProvider')]
    public function testRedirectLinksUsesChannelPath(string $baseUrl, string $virtualUrl, string $resolvedUrl): void
    {
        $gerUkId = Uuid::randomHex();

        $gerDomainId = Uuid::randomHex();
        $ukDomainId = Uuid::randomHex();

        $channels = $this->getChannelWithGerAndUkDomain($gerUkId, $gerDomainId, 'http://base.test' . $virtualUrl, $ukDomainId, 'http://base.test/public/en');

        $this->createChannels([$channels]);

        $con = static::getContainer()->get(Connection::class);
        $con->insert(
            'seo_url',
            [
                'id' => Uuid::randomBytes(),
                'language_id' => Uuid::fromHexToBytes($this->deLanguageId),
                'channel_id' => Uuid::fromHexToBytes($gerUkId),
                'foreign_key' => Uuid::randomBytes(),
                'route_name' => 'test',
                'path_info' => '/detail/87a78cf58f114d5587ae23c140825694',
                'seo_path_info' => 'Test',
                'is_canonical' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
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
            self::getGermanChannel($channelId, $domainId, 'http://base.test'),
        ]);

        $con = static::getContainer()->get(Connection::class);
        $con->insert(
            'seo_url',
            [
                'id' => Uuid::randomBytes(),
                'language_id' => Uuid::fromHexToBytes($this->deLanguageId),
                'channel_id' => Uuid::fromHexToBytes($channelId),
                'foreign_key' => Uuid::randomBytes(),
                'route_name' => BlogPageSeoUrlRoute::ROUTE_NAME,
                'path_info' => '/detail/87a78cf58f114d5587ae23c140825694',
                'seo_path_info' => 'Main-blog/SWDEMO10001?test=123',
                'is_canonical' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $request = Request::create('http://base.test/Main-blog/SWDEMO10001?test=123');

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
            self::getGermanChannel($channelId, $domainId, 'http://base.test'),
        ]);

        $con = static::getContainer()->get(Connection::class);
        $con->insert(
            'seo_url',
            [
                'id' => Uuid::randomBytes(),
                'language_id' => Uuid::fromHexToBytes($this->deLanguageId),
                'channel_id' => Uuid::fromHexToBytes($channelId),
                'foreign_key' => Uuid::randomBytes(),
                'route_name' => BlogPageSeoUrlRoute::ROUTE_NAME,
                'path_info' => '/detail/87a78cf58f114d5587ae23c140825694',
                'seo_path_info' => 'Main-blog/SWDEMO10001',
                'is_canonical' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $request = Request::create('http://base.test/Main-blog/SWDEMO10001?utm=123');

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
                ['id' => Defaults::LANGUAGE_SYSTEM],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => self::LOCALE_EN_GB_ISO,
                ],
            ],
        ];
    }

    /**
     * @return Channel
     */
    private static function getGermanChannel(string $channelId, string $domainId, string $url): array
    {
        return [
            'id' => $channelId,
            'name' => 'german',
            'active' => true,
            'languages' => [
                ['id' => 'de-DE'],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => 'de-DE',
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => self::LOCALE_DE_DE_ISO,
                ],
            ],
        ];
    }

    /**
     * @return Channel
     */
    private static function getChannelWithGerAndUkDomain(
        string $channelId,
        string $gerDomainId,
        string $gerUrl,
        string $ukDomainId,
        string $ukUrl
    ): array {
        return [
            'id' => $channelId,
            'name' => 'english',
            'active' => true,
            'languages' => [
                ['id' => Defaults::LANGUAGE_SYSTEM],
                ['id' => self::LOCALE_DE_DE_ISO],
            ],
            'domains' => [
                [
                    'id' => $gerDomainId,
                    'url' => $gerUrl,
                    'languageId' => self::LOCALE_DE_DE_ISO,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => self::LOCALE_DE_DE_ISO,
                ],
                [
                    'id' => $ukDomainId,
                    'url' => $ukUrl,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
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
            'name' => 'inactive sales channel',
            'active' => false,
            'languages' => [
                ['id' => self::LOCALE_DE_DE_ISO],
            ],
            'domains' => [
                [
                    'id' => $domainId,
                    'url' => $url,
                    'languageId' => self::LOCALE_DE_DE_ISO,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => self::LOCALE_DE_DE_ISO,
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
        $snippetSetDE = $this->getSnippetSetIdForLocale(self::LOCALE_DE_DE_ISO);

        $channels = array_map(function ($channelData) use ($snippetSetDE, $snippetSetEN) {
            $defaults = [
                'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
                'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $snippetSetEN,
                'currencyId' => Defaults::CURRENCY,
                'currencyVersionId' => Defaults::LIVE_VERSION,
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'paymentMethodVersionId' => Defaults::LIVE_VERSION,
                'shippingMethodId' => $this->getValidShippingMethodId(),
                'shippingMethodVersionId' => Defaults::LIVE_VERSION,
                'navigationCategoryId' => $this->getValidCategoryId(),
                'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
                'countryId' => $this->getValidCountryId(),
                'countryVersionId' => Defaults::LIVE_VERSION,
                'currencies' => [['id' => Defaults::CURRENCY]],
                'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
                'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
                'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
                'countries' => [['id' => $this->getValidCountryId()]],
                'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            ];

            foreach ($channelData['languages'] as &$language) {
                if ($language['id'] === self::LOCALE_DE_DE_ISO) {
                    $language['id'] = $this->deLanguageId;
                }

                if ($language['id'] === self::LOCALE_EN_GB_ISO) {
                    $language['id'] = Defaults::LANGUAGE_SYSTEM;
                }
            }

            foreach ($channelData['domains'] as &$domain) {
                if ($domain['languageId'] === self::LOCALE_DE_DE_ISO) {
                    $domain['languageId'] = $this->deLanguageId;
                }

                if ($domain['languageId'] === self::LOCALE_EN_GB_ISO) {
                    $domain['languageId'] = Defaults::LANGUAGE_SYSTEM;
                }

                if ($domain['snippetSetId'] === self::LOCALE_EN_GB_ISO) {
                    $domain['snippetSetId'] = $snippetSetEN;
                }

                if ($domain['snippetSetId'] === self::LOCALE_DE_DE_ISO) {
                    $domain['snippetSetId'] = $snippetSetDE;
                }
            }

            return array_merge_recursive($defaults, $channelData);
        }, $channels);

        return static::getContainer()->get('channel.repository')->create($channels, Context::createDefaultContext());
    }
}
