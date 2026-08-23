<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Contena\Core\Framework\Adapter\Cache\Http\CacheHeadersService;
use Contena\Core\Framework\Adapter\Cache\Http\CacheRelevantRulesResolver;
use Contena\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * @internal
 */
#[CoversClass(CacheHeadersService::class)]
class CacheHeadersServiceTest extends TestCase
{
    use EventDispatcherBehaviour;

    private CacheHeadersService $cacheHeadersService;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $extensionDispatcher = new ExtensionDispatcher($this->eventDispatcher);

        $this->cacheHeadersService = new CacheHeadersService(
            $extensionDispatcher,
            new CacheRelevantRulesResolver($extensionDispatcher),
            [],
            $this->eventDispatcher,
        );
    }

    #[DataProvider('cacheHashProvider')]
    public function testGenerateCacheHash(?MemberEntity $member, bool $expectsCookie): void
    {
        $channelContext = $this->createCacheHashContext('language-a', $member);
        $request = new Request();

        if (!$expectsCookie) {
            $request->cookies->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'foo');
        }

        $response = new Response();
        $this->cacheHeadersService->applyCacheHash($request, $channelContext, $response);

        if ($expectsCookie) {
            $cookies = array_filter(
                $response->headers->getCookies(),
                static fn (Cookie $cookie): bool => $cookie->getName() === HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE
            );

            static::assertCount(1, $cookies);
            $cookie = array_shift($cookies);
            static::assertNotNull($cookie->getValue());
            static::assertSame($cookie->getValue(), $response->headers->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE));

            return;
        }

        $cookies = $response->headers->getCookies();
        static::assertNotEmpty($cookies, 'the client cookie should be cleared');

        foreach ($cookies as $cookie) {
            static::assertSame(1, $cookie->getExpiresTime(), 'cookie should expire');
        }

        static::assertNull($response->headers->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE));
    }

    /**
     * @return iterable<string, array{member: MemberEntity|null, expectsCookie: bool}>
     */
    public static function cacheHashProvider(): iterable
    {
        yield 'anonymous member' => ['member' => null, 'expectsCookie' => false];
        yield 'logged-in member' => ['member' => new MemberEntity(), 'expectsCookie' => true];
    }

    public function testFrontendCacheHashDoesNotContainLanguageId(): void
    {
        $event = $this->cacheHeadersService->applyCacheHash(
            new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]]),
            $this->createCacheHashContext('language-a', new MemberEntity()),
            new Response()
        );

        static::assertInstanceOf(HttpCacheCookieEvent::class, $event);
        static::assertNull($event->get(HttpCacheCookieEvent::LANGUAGE_ID));
    }

    public function testChannelApiCacheHashContainsLanguageId(): void
    {
        $event = $this->cacheHeadersService->applyCacheHash(
            new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]]),
            $this->createCacheHashContext('language-a', new MemberEntity()),
            new Response()
        );

        static::assertInstanceOf(HttpCacheCookieEvent::class, $event);
        static::assertSame('language-a', $event->get(HttpCacheCookieEvent::LANGUAGE_ID));
    }

    public function testCacheCookieStaysTheSameIfEventPartsAreSortedDifferently(): void
    {
        $context = $this->createCacheHashContext('language-a', new MemberEntity());
        $request = new Request();

        $firstResponse = new Response();
        $this->cacheHeadersService->applyCacheHash($request, $context, $firstResponse);

        $firstCacheCookie = $firstResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $firstCacheCookie);

        $this->addEventListener($this->eventDispatcher, HttpCacheCookieEvent::class, static function (HttpCacheCookieEvent $event): void {
            $ruleIds = $event->get(HttpCacheCookieEvent::RULE_IDS);
            self::assertIsArray($ruleIds);
            $event->remove(HttpCacheCookieEvent::RULE_IDS);
            $event->add(HttpCacheCookieEvent::RULE_IDS, $ruleIds);
        });

        $secondResponse = new Response();
        $this->cacheHeadersService->applyCacheHash($request, $context, $secondResponse);

        $secondCacheCookie = $secondResponse->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $secondCacheCookie);

        static::assertSame($firstCacheCookie->getValue(), $secondCacheCookie->getValue());
    }

    public function testCacheCookieHasNoCacheValueIfSetInEvent(): void
    {
        $context = $this->createCacheHashContext('language-a', new MemberEntity());
        $request = new Request();

        $this->addEventListener($this->eventDispatcher, HttpCacheCookieEvent::class, static function (HttpCacheCookieEvent $event): void {
            $event->isCacheable = false;
        });

        $response = new Response();
        $result = $this->cacheHeadersService->applyCacheHash($request, $context, $response);

        static::assertInstanceOf(HttpCacheCookieEvent::class, $result);
        $cacheCookie = $response->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY)['']['/'][HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE];
        static::assertInstanceOf(Cookie::class, $cacheCookie);
        static::assertSame(HttpCacheCookieEvent::NOT_CACHEABLE, $cacheCookie->getValue());
        static::assertSame(HttpCacheCookieEvent::NOT_CACHEABLE, $result->getHash());
    }

    public function testSetLanguageHeaders(): void
    {
        $response = new Response();

        $context = $this->createCacheHashContext('language-id');

        $this->cacheHeadersService->applyCacheHeaders($context, $response);

        static::assertSame('language-id', $response->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));

        $vary = $response->headers->all('vary');
        static::assertCount(3, $vary);
        static::assertContains(PlatformRequest::HEADER_ACCESS_KEY, $vary);
        static::assertContains(PlatformRequest::HEADER_LANGUAGE_ID, $vary);
        static::assertContains(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $vary);
    }

    public function testCustomCacheRelevantCookiesInfluenceTheStateCookie(): void
    {
        $extensionDispatcher = new ExtensionDispatcher($this->eventDispatcher);
        $cacheHeadersService = new CacheHeadersService(
            $extensionDispatcher,
            new CacheRelevantRulesResolver($extensionDispatcher),
            ['my-custom-cookie'],
            $this->eventDispatcher,
        );

        $request = new Request();
        $context = $this->createCacheHashContext('language-a');
        $response = new Response();

        $cacheHeadersService->applyCacheHash($request, $context, $response);
        static::assertEmpty($response->headers->getCookies());

        $request->cookies->set('my-custom-cookie', 'foo');
        $response = new Response();
        $cacheHeadersService->applyCacheHash($request, $context, $response);

        $cookies = $response->headers->getCookies();
        static::assertNotEmpty($cookies);
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        $firstHash = $cookies[0]->getValue();

        $response = new Response();
        $request->cookies->set('my-custom-cookie', 'bar');
        $cacheHeadersService->applyCacheHash($request, $context, $response);

        $cookies = $response->headers->getCookies();
        static::assertNotEmpty($cookies);
        static::assertSame(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $cookies[0]->getName());
        static::assertNotSame($firstHash, $cookies[0]->getValue());
    }

    private function createCacheHashContext(string $languageId, ?MemberEntity $member = null): ChannelContext
    {
        return Generator::generateChannelContext(
            baseContext: new Context(new SystemSource(), [$languageId]),
            member: $member,
        );
    }
}
